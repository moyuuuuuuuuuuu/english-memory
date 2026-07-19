<?php

declare(strict_types=1);

namespace app\services;

use app\entities\AccessTokenIdentityEntity;
use app\services\contracts\AccessTokenStore;
use JsonException;

final class TokenService
{
    public function __construct(
        private readonly AccessTokenStore $store,
        private readonly int $ttlSeconds = 900,
    ) {
    }

    public function issueAccessToken(int $userId, int $sessionVersion): array
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->store->put($this->key($token), json_encode([
            'user_id' => $userId,
            'session_version' => $sessionVersion,
        ], JSON_THROW_ON_ERROR), $this->ttlSeconds);

        return [
            'token' => $token,
            'expires_in' => $this->ttlSeconds,
        ];
    }

    public function resolveAccessToken(string $token): ?AccessTokenIdentityEntity
    {
        if ($token === '') {
            return null;
        }

        $stored = $this->store->get($this->key($token));
        if ($stored === null) {
            return null;
        }

        try {
            $identity = json_decode($stored, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        if (!is_array($identity)
            || !is_int($identity['user_id'] ?? null)
            || !is_int($identity['session_version'] ?? null)
            || $identity['user_id'] <= 0
            || $identity['session_version'] < 0) {
            return null;
        }

        return new AccessTokenIdentityEntity($identity['user_id'], $identity['session_version']);
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
