<?php

declare(strict_types=1);

namespace app\entities;

final readonly class MemoryCardViewEntity
{
    public function __construct(
        private array $card,
        private ?array $job,
    ) {
    }

    public function card(): array
    {
        return $this->card;
    }

    public function job(): ?array
    {
        return $this->job;
    }

    public function toArray(): array
    {
        return [
            'card' => $this->card,
            'job' => $this->job,
        ];
    }
}
