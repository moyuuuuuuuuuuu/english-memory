<?php

declare(strict_types=1);

namespace app\businesses;

use app\common\enums\AiGenerationStatus;
use app\common\enums\BusinessCode;
use app\entities\AsyncMemoryCardResultEntity;
use app\models\AiGenerationJob;
use app\models\MemoryCard;
use app\services\contracts\AiGenerationQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use support\Db;
use Throwable;

final class RegenerateMemoryCardBusiness
{
    public function __construct(private readonly AiGenerationQueue $queue) {}

    public function regenerate(int $userId, int $cardId, string $idempotencyKey): AsyncMemoryCardResultEntity
    {
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') {
            return AsyncMemoryCardResultEntity::failure(422, BusinessCode::IdempotencyKeyRequired, '请提供新的 Idempotency-Key。');
        }
        if (strlen($idempotencyKey) > 128) {
            return AsyncMemoryCardResultEntity::failure(422, BusinessCode::InvalidInput, 'Idempotency-Key 过长。');
        }

        $hash = hash('sha256', json_encode([
            'operation' => 'regenerate',
            'card_id' => $cardId,
        ], JSON_THROW_ON_ERROR));
        $existing = $this->findByKey($userId, $idempotencyKey);
        if ($existing !== null) {
            return $this->replayOrConflict($existing, $cardId, $hash);
        }

        try {
            $result = Db::transaction(function () use ($userId, $cardId, $idempotencyKey, $hash): AiGenerationJob|AsyncMemoryCardResultEntity {
                /** @var MemoryCard|null $card */
                $card = MemoryCard::query()
                    ->where('user_id', $userId)
                    ->where('id', $cardId)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();
                if ($card === null) {
                    return AsyncMemoryCardResultEntity::failure(404, BusinessCode::CardNotFound, '记忆卡不存在。');
                }

                /** @var AiGenerationJob|null $latest */
                $latest = AiGenerationJob::query()
                    ->where('user_id', $userId)
                    ->where('memory_card_id', $cardId)
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();
                if ($latest === null || !in_array((string) $latest->status, [
                    AiGenerationStatus::Completed->value,
                    AiGenerationStatus::Failed->value,
                ], true)) {
                    return AsyncMemoryCardResultEntity::failure(409, BusinessCode::JobStateConflict, '当前记忆卡仍在生成中。');
                }

                return AiGenerationJob::query()->create([
                    'user_id' => $userId,
                    'memory_card_id' => $cardId,
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $hash,
                    'operation' => 'regenerate',
                    'request_payload' => [
                        'text' => (string) $card->source_text,
                        'content_type' => (string) $card->content_type,
                        'memory_style' => (string) $card->memory_style,
                    ],
                    'status' => AiGenerationStatus::Queued->value,
                    'parent_job_id' => (int) $latest->id,
                    'attempts' => 0,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            $winner = $this->findByKey($userId, $idempotencyKey);
            if ($winner === null) {
                throw new \RuntimeException('Idempotency winner could not be loaded.');
            }
            return $this->replayOrConflict($winner, $cardId, $hash);
        }

        if ($result instanceof AsyncMemoryCardResultEntity) {
            return $result;
        }

        $dispatchPending = false;
        try {
            $this->queue->publish((int) $result->id);
            $result->forceFill(['dispatched_at' => date('Y-m-d H:i:s')])->save();
        } catch (Throwable) {
            $dispatchPending = true;
        }

        return AsyncMemoryCardResultEntity::accepted($cardId, (int) $result->id, (string) $result->status, false, $dispatchPending);
    }

    private function findByKey(int $userId, string $key): ?AiGenerationJob
    {
        return AiGenerationJob::query()->where('user_id', $userId)->where('idempotency_key', $key)->first();
    }

    private function replayOrConflict(AiGenerationJob $job, int $cardId, string $hash): AsyncMemoryCardResultEntity
    {
        if ((int) $job->memory_card_id !== $cardId
            || (string) $job->operation !== 'regenerate'
            || !hash_equals((string) $job->request_hash, $hash)) {
            return AsyncMemoryCardResultEntity::failure(409, BusinessCode::IdempotencyConflict, '该 Idempotency-Key 已用于不同请求。');
        }

        return AsyncMemoryCardResultEntity::accepted($cardId, (int) $job->id, (string) $job->status, true, $job->dispatched_at === null);
    }
}
