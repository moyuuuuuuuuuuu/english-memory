<?php

declare(strict_types=1);

namespace app\entities;

use app\common\enums\BusinessCode;

final readonly class SyncChangesResultEntity
{
    private function __construct(private int $httpStatus, private array $response) {}

    public static function success(array $changes, int $nextCursor, bool $hasMore): self
    {
        return new self(200, ['success' => true, 'data' => [
            'changes' => $changes,
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
        ]]);
    }

    public static function failure(BusinessCode $code, string $message): self
    {
        return new self(422, ['success' => false, 'error' => ['code' => $code->value, 'message' => $message]]);
    }

    public function httpStatus(): int { return $this->httpStatus; }
    public function toResponseArray(): array { return $this->response; }
}
