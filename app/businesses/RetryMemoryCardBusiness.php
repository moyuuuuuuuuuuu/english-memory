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

final class RetryMemoryCardBusiness
{
    public function __construct(private readonly AiGenerationQueue $queue)
    {
    }

    public function retry(int $userId, int $cardId, string $idempotencyKey): AsyncMemoryCardResultEntity
    {
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') {
            return AsyncMemoryCardResultEntity::failure(
                422,
                BusinessCode::IdempotencyKeyRequired,
                '请提供新的 Idempotency-Key。',
            );
        }
        if (strlen($idempotencyKey) > 128) {
            return AsyncMemoryCardResultEntity::failure(422, BusinessCode::InvalidInput, 'Idempotency-Key 过长。');
        }

        $cardExists = MemoryCard::query()
            ->where('user_id', $userId)
            ->where('id', $cardId)
            ->exists();
        if (!$cardExists) {
            return AsyncMemoryCardResultEntity::failure(404, BusinessCode::CardNotFound, '记忆卡不存在。');
        }

        $existing = $this->findByKey($userId, $idempotencyKey);
        if ($existing !== null) {
            return $this->replayOrConflict($existing, $cardId);
        }

        try {
            /** @var AiGenerationJob|AsyncMemoryCardResultEntity $result */
            $result = Db::transaction(function () use ($userId, $cardId, $idempotencyKey) {
                /** @var AiGenerationJob|null $latest */
                $latest = AiGenerationJob::query()
                    ->where('user_id', $userId)
                    ->where('memory_card_id', $cardId)
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                if ($latest === null || $latest->status !== AiGenerationStatus::Failed->value) {
                    return AsyncMemoryCardResultEntity::failure(
                        409,
                        BusinessCode::JobStateConflict,
                        '只有生成失败的最新任务可以重试。',
                    );
                }

                return AiGenerationJob::query()->create([
                    'user_id' => $userId,
                    'memory_card_id' => $cardId,
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => (string) $latest->request_hash,
                    'request_payload' => $latest->request_payload,
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

            return $this->replayOrConflict($winner, $cardId);
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

        return AsyncMemoryCardResultEntity::accepted(
            $cardId,
            (int) $result->id,
            AiGenerationStatus::Queued->value,
            false,
            $dispatchPending,
        );
    }

    private function findByKey(int $userId, string $key): ?AiGenerationJob
    {
        /** @var AiGenerationJob|null $job */
        $job = AiGenerationJob::query()
            ->where('user_id', $userId)
            ->where('idempotency_key', $key)
            ->first();

        return $job;
    }

    private function replayOrConflict(AiGenerationJob $job, int $cardId): AsyncMemoryCardResultEntity
    {
        if ((int) $job->memory_card_id !== $cardId || $job->parent_job_id === null) {
            return AsyncMemoryCardResultEntity::failure(
                409,
                BusinessCode::IdempotencyConflict,
                '该 Idempotency-Key 不能用于本次重试。',
            );
        }

        return AsyncMemoryCardResultEntity::accepted(
            $cardId,
            (int) $job->id,
            (string) $job->status,
            true,
            $job->dispatched_at === null,
        );
    }
}
