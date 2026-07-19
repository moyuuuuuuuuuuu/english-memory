<?php

declare(strict_types=1);

namespace app\services;

use app\services\contracts\AccessTokenStore;

final class TokenService
{
    public function __construct(
        private readonly AccessTokenStore $store,
        private readonly int $ttlSeconds = 900,
    ) {
    }

    public function issueAccessToken(int $userId): array
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->store->put($this->key($token), (string) $userId, $this->ttlSeconds);

        return [
            'token' => $token,
            'expires_in' => $this->ttlSeconds,
        ];
    }

    public function resolveAccessToken(string $token): ?int
    {
        if ($token === '') {
            return null;
        }

        $userId = $this->store->get($this->key($token));

        return $userId !== null && ctype_digit($userId) ? (int) $userId : null;
    }

    public function revokeAccessToken(string $token): void
    {
        if ($token !== '') {
            $this->store->delete($this->key($token));
        }
    }

    private function key(string $token): string
    {
        return 'auth:access:' . hash('sha256', $token);
    }
}
