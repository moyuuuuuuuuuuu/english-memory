<?php

declare(strict_types=1);

namespace app\processes;

use support\Log;
use support\Container;
use Throwable;
use Workerman\Timer;

final class AiGenerationConsumerProcess
{
    public function onWorkerStart(): void
    {
        $worker = Container::get(AiGenerationWorker::class);
        $consumerPrefix = (string) config('ai_generation.consumer_prefix', 'worker');
        $host = gethostname() ?: 'host';
        $consumer = sprintf('%s-%s-%d', $consumerPrefix, $host, getmypid());

        Timer::add(0.01, static function () use ($consumer, $worker): void {
            try {
                $worker->runOnce($consumer);
            } catch (Throwable $throwable) {
                Log::error('AI generation consumer iteration failed.', [
                    'exception' => $throwable,
                ]);
            }
        });
    }
}
