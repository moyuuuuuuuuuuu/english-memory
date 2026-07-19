<?php

declare(strict_types=1);

namespace app\entities;

final class AuthResultEntity
{
    private function __construct(
        private readonly int $httpStatus,
        private readonly array $response,
    ) {
    }

    public static function registered(int $id, ?string $email, ?string $username): self
    {
        return new self(201, [
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $id,
                    'email' => $email,
                    'username' => $username,
                ],
            ],
        ]);
    }

    public static function failure(int $httpStatus, string $code, string $message): self
    {
        return new self($httpStatus, [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ]);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public function toResponseArray(): array
    {
        return $this->response;
    }
}
