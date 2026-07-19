<?php

declare(strict_types=1);

namespace app\services\contracts;

use app\entities\QueueMessageEntity;

interface AiGenerationQueue
{
    public function publish(int $jobId): void;

    public function consume(string $consumer, int $blockMs = 5000): ?QueueMessageEntity;

    public function ack(QueueMessageEntity $message): void;

    public function claimStale(string $consumer, int $idleMs): ?QueueMessageEntity;
}
