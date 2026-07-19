<?php

declare(strict_types=1);

namespace app\entities;

final readonly class QueueMessageEntity
{
    public function __construct(
        private string $messageId,
        private int $jobId,
    ) {
    }

    public function messageId(): string
    {
        return $this->messageId;
    }

    public function jobId(): int
    {
        return $this->jobId;
    }
}
