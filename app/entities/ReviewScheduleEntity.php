<?php

declare(strict_types=1);

namespace app\entities;

use DateTimeImmutable;

final readonly class ReviewScheduleEntity
{
    public function __construct(private int $previousStage, private int $nextStage, private DateTimeImmutable $nextDueAt) {}
    public function previousStage(): int { return $this->previousStage; }
    public function nextStage(): int { return $this->nextStage; }
    public function nextDueAt(): DateTimeImmutable { return $this->nextDueAt; }
}
