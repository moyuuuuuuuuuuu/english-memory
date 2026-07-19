<?php

declare(strict_types=1);

namespace app\businesses;

use app\common\enums\BusinessCode;
use app\common\enums\ReviewDifficulty;
use app\common\enums\ReviewMode;
use app\common\enums\ReviewRating;
use app\entities\ReviewAnswerResultEntity;
use app\models\AiGenerationJob;
use app\models\DailyLearningStat;
use app\models\MemoryCard;
use app\models\ReviewEvent;
use app\models\User;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\UniqueConstraintViolationException;
use support\Db;

final class SubmitReviewAnswerBusiness
{
    public function __construct(
        private readonly ReviewAnswerNormalizerBusiness $normalizer = new ReviewAnswerNormalizerBusiness(),
        private readonly ReviewSchedulerBusiness $scheduler = new ReviewSchedulerBusiness(),
        private readonly ReviewModeBusiness $modes = new ReviewModeBusiness(),
        private readonly SyncVersionBusiness $syncVersions = new SyncVersionBusiness(),
    ) {}

    public function submit(int $userId, int $cardId, string $key, string $mode, string $answer, string $difficulty, int $responseMs, ?DateTimeImmutable $nowUtc = null): ReviewAnswerResultEntity
    {
        $key = trim($key);
        if ($key === '') return ReviewAnswerResultEntity::failure(422, BusinessCode::IdempotencyKeyRequired, '请提供 Idempotency-Key。');
        $modeEnum = ReviewMode::tryFrom($mode);
        $difficultyEnum = ReviewDifficulty::tryFrom($difficulty);
        $normalized = $this->normalizer->normalize($answer);
        if (strlen($key) > 128 || $normalized === '' || mb_strlen($answer) > 2000 || $difficultyEnum === null || $responseMs < 0 || $responseMs > 3600000) {
            return ReviewAnswerResultEntity::failure(422, BusinessCode::InvalidInput, '请求参数不正确。');
        }
        if ($modeEnum === null) return ReviewAnswerResultEntity::failure(422, BusinessCode::InvalidReviewMode, '复习模式无效。');
        $hash = hash('sha256', json_encode([$cardId, $mode, $normalized, $difficulty, $responseMs], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $existing = ReviewEvent::forUser($userId)->where('idempotency_key', $key)->first();
        if ($existing !== null) return $this->replay($existing, $hash);
        $nowUtc ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));

        try {
            return Db::transaction(function () use ($userId, $cardId, $key, $mode, $answer, $normalized, $difficultyEnum, $responseMs, $hash, $nowUtc): ReviewAnswerResultEntity {
            $user = User::query()->whereKey($userId)->lockForUpdate()->first();
            $card = MemoryCard::query()->where('user_id', $userId)->whereKey($cardId)->whereNull('deleted_at')->lockForUpdate()->first();
            if ($user === null || $card === null) return ReviewAnswerResultEntity::failure(404, BusinessCode::CardNotFound, '记忆卡不存在。');
            $latest = AiGenerationJob::latestForCard($userId, $cardId);
            if ($latest === null || $latest->status !== 'completed') return ReviewAnswerResultEntity::failure(409, BusinessCode::ReviewNotAvailable, '记忆卡暂不可复习。');
            $projection = $this->modes->project($card);
            if (!in_array($mode, $projection['available_modes'], true)) return ReviewAnswerResultEntity::failure(422, BusinessCode::InvalidReviewMode, '该记忆卡不支持此复习模式。');
            $expected = $this->normalizer->normalize((string) $card->normalized_text ?: (string) (($card->card_payload ?? [])['word'] ?? ''));
            $correct = hash_equals($expected, $normalized);
            $rating = $correct ? ReviewRating::from($difficultyEnum->value) : ReviewRating::Again;
            $isNew = $card->first_reviewed_at === null;
            $previousStage = (int) $card->review_stage;
            $previousDueAt = $card->next_review_at?->format('Y-m-d H:i:s');
            $schedule = $this->scheduler->schedule($isNew, (int) $card->review_stage, $rating, $nowUtc);
            $xp = $correct ? match ($difficultyEnum) { ReviewDifficulty::Hard => 8, ReviewDifficulty::Good => 10, ReviewDifficulty::Easy => 12 } : 2;
            $version = $this->syncVersions->nextLocked($user);
            $now = $nowUtc->format('Y-m-d H:i:s');
            $card->forceFill(['sync_version'=>$version,'first_reviewed_at'=>$card->first_reviewed_at ?? $now,'last_reviewed_at'=>$now,'review_count'=>(int)$card->review_count + 1,'review_stage'=>$schedule->nextStage(),'next_review_at'=>$schedule->nextDueAt()->format('Y-m-d H:i:s')])->save();
            $localDate = $nowUtc->setTimezone(new DateTimeZone((string) $user->timezone))->format('Y-m-d');
            $stat = DailyLearningStat::forUser($userId)->where('stat_date', $localDate)->lockForUpdate()->first();
            if ($stat === null) $stat = DailyLearningStat::query()->create(['user_id'=>$userId,'stat_date'=>$localDate]);
            $current = $correct ? (int) $stat->current_correct_streak + 1 : 0;
            $stat->forceFill(['reviews_completed'=>(int)$stat->reviews_completed + 1,'correct_reviews'=>(int)$stat->correct_reviews + ($correct ? 1 : 0),'xp_earned'=>(int)$stat->xp_earned + $xp,'current_correct_streak'=>$current,'best_correct_streak'=>max((int)$stat->best_correct_streak, $current)])->save();
            $data = ['card_id'=>$cardId,'is_correct'=>$correct,'expected_answer'=>$expected,'effective_rating'=>$rating->value,'previous_stage'=>$previousStage,'next_stage'=>$schedule->nextStage(),'next_review_at'=>$schedule->nextDueAt()->format('Y-m-d H:i:s'),'xp_awarded'=>$xp,'daily_reviews'=>(int)$stat->reviews_completed,'current_correct_streak'=>$current,'sync_version'=>$version,'content_version'=>(int)$card->content_version];
            ReviewEvent::query()->create(['user_id'=>$userId,'memory_card_id'=>$cardId,'idempotency_key'=>$key,'request_hash'=>$hash,'mode'=>$mode,'answer_text'=>$answer,'normalized_answer'=>$normalized,'is_correct'=>$correct,'difficulty'=>$difficultyEnum->value,'rating'=>$rating->value,'effective_rating'=>$rating->value,'previous_stage'=>$previousStage,'next_stage'=>$schedule->nextStage(),'previous_due_at'=>$previousDueAt,'next_due_at'=>$schedule->nextDueAt(),'response_ms'=>$responseMs,'xp_awarded'=>$xp,'response_payload'=>$data,'reviewed_at'=>$now]);
                return ReviewAnswerResultEntity::success($this->stableResponse($data));
            });
        } catch (UniqueConstraintViolationException $exception) {
            $existing = ReviewEvent::forUser($userId)->where('idempotency_key', $key)->first();
            if ($existing !== null) {
                return $this->replay($existing, $hash);
            }

            throw $exception;
        }
    }

    private function replay(ReviewEvent $event, string $hash): ReviewAnswerResultEntity
    {
        if (!hash_equals((string) $event->request_hash, $hash)) return ReviewAnswerResultEntity::failure(409, BusinessCode::IdempotencyConflict, '该 Idempotency-Key 已用于不同请求。');
        return ReviewAnswerResultEntity::success($this->stableResponse((array) $event->response_payload));
    }

    private function stableResponse(array $data): array
    {
        return [
            'card_id' => (int) $data['card_id'],
            'is_correct' => (bool) $data['is_correct'],
            'expected_answer' => (string) $data['expected_answer'],
            'effective_rating' => (string) $data['effective_rating'],
            'previous_stage' => (int) $data['previous_stage'],
            'next_stage' => (int) $data['next_stage'],
            'next_review_at' => (string) $data['next_review_at'],
            'xp_awarded' => (int) $data['xp_awarded'],
            'daily_reviews' => (int) $data['daily_reviews'],
            'current_correct_streak' => (int) $data['current_correct_streak'],
            'sync_version' => (int) $data['sync_version'],
            'content_version' => (int) $data['content_version'],
        ];
    }
}
