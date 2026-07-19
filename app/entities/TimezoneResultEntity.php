<?php

declare(strict_types=1);

namespace app\entities;

use app\common\enums\BusinessCode;

final readonly class TimezoneResultEntity
{
    private function __construct(private int $status, private array $response) {}
    public static function success(AuthenticatedUserEntity $user): self { return new self(200, ['success' => true, 'data' => ['user' => $user->toArray()]]); }
    public static function failure(BusinessCode $code, string $message): self { return new self(422, ['success' => false, 'error' => ['code' => $code->value, 'message' => $message]]); }
    public function httpStatus(): int { return $this->status; }
    public function toResponseArray(): array { return $this->response; }
}
