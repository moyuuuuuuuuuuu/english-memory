<?php

declare(strict_types=1);

namespace app\entities;

use app\common\enums\BusinessCode;

final readonly class TagResultEntity
{
    private function __construct(private int $httpStatus, private array $response) {}

    public static function success(array $data): self
    {
        return new self(200, ['success' => true, 'data' => $data]);
    }

    public static function failure(int $status, BusinessCode $code, string $message): self
    {
        return new self($status, ['success' => false, 'error' => ['code' => $code->value, 'message' => $message]]);
    }

    public function httpStatus(): int { return $this->httpStatus; }
    public function toResponseArray(): array { return $this->response; }
}
