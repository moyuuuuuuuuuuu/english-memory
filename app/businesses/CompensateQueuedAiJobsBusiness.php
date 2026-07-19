<?php

declare(strict_types=1);

namespace app\businesses;

use app\common\enums\AiGenerationStatus;
use app\models\AiGenerationJob;
use app\services\contracts\AiGenerationQueue;
use Throwable;

final class CompensateQueuedAiJobsBusiness
{
    public function __construct(
        private readonly AiGenerationQueue $queue,
        private readonly int $ageSeconds = 120,
    ) {
    }

    public function dispatch(int $limit = 100): int
    {
        $limit = max(1, min($limit, 1000));
        $cutoff = date('Y-m-d H:i:s', time() - max(1, $this->ageSeconds));
        $jobs = AiGenerationJob::query()
            ->where('status', AiGenerationStatus::Queued->value)
            ->where('created_at', '<=', $cutoff)
            ->where(static function ($query) use ($cutoff): void {
                $query->whereNull('dispatched_at')
                    ->orWhere('dispatched_at', '<=', $cutoff);
            })
            ->orderBy('id')
            ->limit($limit)
            ->get(['id']);

        $published = 0;
        foreach ($jobs as $job) {
            try {
                $this->queue->publish((int) $job->id);
            } catch (Throwable) {
                continue;
            }

            AiGenerationJob::query()
                ->where('id', (int) $job->id)
                ->where('status', AiGenerationStatus::Queued->value)
                ->update(['dispatched_at' => date('Y-m-d H:i:s')]);
            ++$published;
        }

        return $published;
    }
}
