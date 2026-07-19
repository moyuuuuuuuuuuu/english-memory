<?php

declare(strict_types=1);

namespace Tests\Unit;

use app\services\contracts\AccessTokenStore;
use app\services\TokenService;
use PHPUnit\Framework\TestCase;

final class TokenServiceTest extends TestCase
{
    public function test_it_issues_and_resolves_an_opaque_access_token(): void
    {
        $store = new InMemoryAccessTokenStore();
        $service = new TokenService($store, 900);

        $issued = $service->issueAccessToken(42, 7);

        self::assertSame(900, $issued['expires_in']);
        self::assertGreaterThanOrEqual(43, strlen($issued['token']));
        $identity = $service->resolveAccessToken($issued['token']);
        self::assertSame(42, $identity?->userId());
        self::assertSame(7, $identity?->sessionVersion());
        self::assertArrayHasKey('auth:access:' . hash('sha256', $issued['token']), $store->values);
        self::assertArrayNotHasKey('auth:access:' . $issued['token'], $store->values);
        self::assertSame(900, $store->ttl);
    }

    public function test_it_rejects_blank_or_unknown_tokens(): void
    {
        $service = new TokenService(new InMemoryAccessTokenStore(), 900);

        self::assertNull($service->resolveAccessToken(''));
        self::assertNull($service->resolveAccessToken('unknown'));
    }

    public function test_it_revokes_an_access_token(): void
    {
        $store = new InMemoryAccessTokenStore();
        $service = new TokenService($store, 900);
        $issued = $service->issueAccessToken(42, 7);

        $service->revokeAccessToken($issued['token']);

        self::assertNull($service->resolveAccessToken($issued['token']));
    }

    public function test_it_rejects_malformed_stored_identity_values(): void
    {
        $store = new InMemoryAccessTokenStore();
        $store->values['auth:access:' . hash('sha256', 'bad')] = '42';

        self::assertNull((new TokenService($store, 900))->resolveAccessToken('bad'));
    }
}

final class InMemoryAccessTokenStore implements AccessTokenStore
{
    public array $values = [];
    public int $ttl = 0;

    public function put(string $key, string $value, int $ttlSeconds): void
    {
        $this->values[$key] = $value;
        $this->ttl = $ttlSeconds;
    }

    public function get(string $key): ?string
    {
        return $this->values[$key] ?? null;
    }

    public function delete(string $key): void
    {
        unset($this->values[$key]);
    }
}
