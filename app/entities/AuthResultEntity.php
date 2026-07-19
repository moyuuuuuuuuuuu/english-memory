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

    public static function authenticated(AuthenticatedUserEntity $user, array $accessToken): self
    {
        return new self(200, [
            'success' => true,
            'data' => [
                'user' => $user->toArray(),
                'token_type' => 'Bearer',
                'access_token' => $accessToken['token'],
                'expires_in' => $accessToken['expires_in'],
            ],
        ]);
    }

    public static function currentUser(AuthenticatedUserEntity $user): self
    {
        return new self(200, [
            'success' => true,
            'data' => ['user' => $user->toArray()],
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
