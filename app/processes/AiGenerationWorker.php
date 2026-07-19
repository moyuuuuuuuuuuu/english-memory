<?php

declare(strict_types=1);

namespace app\processes;

use app\businesses\ProcessAiGenerationJobBusiness;
use app\services\contracts\AiGenerationQueue;

final class AiGenerationWorker
{
    public function __construct(
        private readonly AiGenerationQueue $queue,
        private readonly ProcessAiGenerationJobBusiness $processor,
        private readonly int $claimIdleMs = 60000,
        private readonly int $blockMs = 5000,
    ) {
    }

    public function runOnce(string $consumer): bool
    {
        $message = $this->queue->claimStale($consumer, $this->claimIdleMs)
            ?? $this->queue->consume($consumer, $this->blockMs);

        if ($message === null) {
            return false;
        }

        $this->processor->process($message->jobId());
        $this->queue->ack($message);

        return true;
    }
}
