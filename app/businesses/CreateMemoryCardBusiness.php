<?php

declare(strict_types=1);

namespace app\businesses;

use app\common\enums\AiGenerationStatus;
use app\common\enums\BusinessCode;
use app\entities\AsyncMemoryCardResultEntity;
use app\models\AiGenerationJob;
use app\models\MemoryCard;
use app\models\User;
use app\services\contracts\AiGenerationQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use stdClass;
use support\Db;
use Throwable;

final class CreateMemoryCardBusiness
{
    private const CONTENT_TYPES = ['word', 'sentence'];
    private const MEMORY_STYLES = ['auto', 'phonetic_story', 'semantic_scene'];

    private readonly SyncVersionBusiness $syncVersions;

    public function __construct(
        private readonly AiGenerationQueue $queue,
        ?SyncVersionBusiness $syncVersions = null,
    ) {
        $this->syncVersions = $syncVersions ?? new SyncVersionBusiness();
    }

    public function create(
        int $userId,
        string $idempotencyKey,
        string $text,
        string $contentType = 'word',
        string $memoryStyle = 'auto',
    ): AsyncMemoryCardResultEntity {
        $idempotencyKey = trim($idempotencyKey);
        $text = trim($text);

        if ($idempotencyKey === '') {
            return AsyncMemoryCardResultEntity::failure(
                422,
                BusinessCode::IdempotencyKeyRequired,
                '请提供 Idempotency-Key。',
            );
        }
        if (strlen($idempotencyKey) > 128 || $text === '') {
            return AsyncMemoryCardResultEntity::failure(422, BusinessCode::InvalidInput, '请求参数不正确。');
        }
        if (!in_array($contentType, self::CONTENT_TYPES, true)
            || !in_array($memoryStyle, self::MEMORY_STYLES, true)) {
            return AsyncMemoryCardResultEntity::failure(422, BusinessCode::InvalidInput, '参数值不在允许范围内。');
        }

        $requestPayload = [
            'text' => $text,
            'content_type' => $contentType,
            'memory_style' => $memoryStyle,
        ];
        $requestHash = hash('sha256', json_encode($requestPayload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $existing = $this->findByIdempotencyKey($userId, $idempotencyKey);
        if ($existing !== null) {
            return $this->replayOrConflict($existing, $requestHash);
        }

        try {
            /** @var array{0: MemoryCard, 1: AiGenerationJob} $created */
            $created = Db::transaction(function () use (
                $userId,
                $idempotencyKey,
                $requestHash,
                $requestPayload,
                $text,
                $contentType,
                $memoryStyle,
            ): array {
                $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
                $syncVersion = $this->syncVersions->nextLocked($user);
                $card = MemoryCard::query()->create([
                    'user_id' => $userId,
                    'sync_version' => $syncVersion,
                    'source_text' => $text,
                    'normalized_text' => $text,
                    'content_type' => $contentType,
                    'memory_style' => $memoryStyle,
                    'card_payload' => new stdClass(),
                    'review_stage' => 0,
                ]);
                $job = AiGenerationJob::query()->create([
                    'user_id' => $userId,
                    'memory_card_id' => (int) $card->id,
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $requestHash,
                    'request_payload' => $requestPayload,
                    'status' => AiGenerationStatus::Queued->value,
                    'attempts' => 0,
                ]);

                return [$card, $job];
            });
        } catch (UniqueConstraintViolationException) {
            $winner = $this->findByIdempotencyKey($userId, $idempotencyKey);
            if ($winner === null) {
                throw new \RuntimeException('Idempotency winner could not be loaded.');
            }

            return $this->replayOrConflict($winner, $requestHash);
        }

        [$card, $job] = $created;
        $dispatchPending = false;
        try {
            $this->queue->publish((int) $job->id);
            $job->forceFill(['dispatched_at' => date('Y-m-d H:i:s')])->save();
        } catch (Throwable) {
            $dispatchPending = true;
        }

        return AsyncMemoryCardResultEntity::accepted(
            (int) $card->id,
            (int) $job->id,
            AiGenerationStatus::Queued->value,
            false,
            $dispatchPending,
        );
    }

    private function findByIdempotencyKey(int $userId, string $key): ?AiGenerationJob
    {
        /** @var AiGenerationJob|null $job */
        $job = AiGenerationJob::query()
            ->where('user_id', $userId)
            ->where('idempotency_key', $key)
            ->first();

        return $job;
    }

    private function replayOrConflict(AiGenerationJob $job, string $requestHash): AsyncMemoryCardResultEntity
    {
        if (!hash_equals((string) $job->request_hash, $requestHash)) {
            return AsyncMemoryCardResultEntity::failure(
                409,
                BusinessCode::IdempotencyConflict,
                '该 Idempotency-Key 已用于不同请求。',
            );
        }

        return AsyncMemoryCardResultEntity::accepted(
            (int) $job->memory_card_id,
            (int) $job->id,
            (string) $job->status,
            true,
            $job->dispatched_at === null,
        );
    }
}
