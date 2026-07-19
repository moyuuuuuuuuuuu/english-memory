<?php

declare(strict_types=1);

namespace app\processes;

use app\businesses\CompensateQueuedAiJobsBusiness;
use support\Container;
use support\Log;
use Throwable;
use Workerman\Timer;

final class AiGenerationCompensationProcess
{
    public function onWorkerStart(): void
    {
        $business = Container::get(CompensateQueuedAiJobsBusiness::class);
        $intervalSeconds = (int) config('ai_generation.compensation_interval_seconds', 30);

        Timer::add(max(1, $intervalSeconds), static function () use ($business): void {
            try {
                $business->dispatch();
            } catch (Throwable $throwable) {
                Log::error('AI generation compensation iteration failed.', [
                    'exception' => $throwable,
                ]);
            }
        });
    }
}
