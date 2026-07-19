<?php

declare(strict_types=1);

namespace app\entities;

final readonly class StatsOverviewResultEntity
{
    public function __construct(private array $data) {}
    public function httpStatus(): int { return 200; }
    public function toResponseArray(): array { return ['success' => true, 'data' => $this->data]; }
}
