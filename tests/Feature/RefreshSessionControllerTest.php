<?php

declare(strict_types=1);

namespace Tests\Feature;

use app\businesses\RefreshSessionBusiness;
use app\controllers\RefreshSessionController;
use app\services\contracts\AccessTokenStore;
use app\services\TokenService;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

final class RefreshSessionControllerTest extends TestCase
{
    private int $userId;

    protected function setUp(): void
    {
        Db::table('users')->where('email', 'codex-refresh@example.com')->delete();
        $this->userId = (int) Db::table('users')->insertGetId([
            'email' => 'codex-refresh@example.com',
            'password_hash' => password_hash('SecurePass123!', PASSWORD_DEFAULT),
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        Db::table('users')->where('id', $this->userId)->delete();
    }

    public function test_it_rotates_a_valid_refresh_token_and_rejects_reuse(): void
    {
        $plainToken = 'valid-refresh-token';
        $oldId = $this->insertRefreshToken($plainToken, date('Y-m-d H:i:s', time() + 3600));
        $controller = $this->controller();

        $response = $controller($this->request($plainToken));

        self::assertSame(200, $response->getStatusCode());
        $payload = json_decode($response->rawBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertNotSame('', $payload['data']['access_token']);
        self::assertNotSame($plainToken, $payload['data']['refresh_token']);
        self::assertNotNull(Db::table('refresh_tokens')->where('id', $oldId)->value('revoked_at'));
        self::assertTrue(Db::table('refresh_tokens')
            ->where('token_hash', hash('sha256', $payload['data']['refresh_token']))
            ->exists());
        self::assertSame(0, (int) Db::table('refresh_tokens')
            ->where('token_hash', hash('sha256', $payload['data']['refresh_token']))
            ->value('session_version'));

        $reuse = $controller($this->request($plainToken));
        self::assertSame(401, $reuse->getStatusCode());
        self::assertSame('INVALID_REFRESH_TOKEN', json_decode($reuse->rawBody(), true)['error']['code']);
    }

    public function test_it_rejects_expired_refresh_token(): void
    {
        $plainToken = 'expired-refresh-token';
        $this->insertRefreshToken($plainToken, date('Y-m-d H:i:s', time() - 1));

        $response = $this->controller()($this->request($plainToken));

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('INVALID_REFRESH_TOKEN', json_decode($response->rawBody(), true)['error']['code']);
    }

    public function test_it_rejects_a_refresh_token_from_a_replaced_session(): void
    {
        $plainToken = 'replaced-refresh-token';
        $this->insertRefreshToken($plainToken, date('Y-m-d H:i:s', time() + 3600));
        Db::table('users')->where('id', $this->userId)->update(['session_version' => 1]);

        $response = $this->controller()($this->request($plainToken));

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('INVALID_REFRESH_TOKEN', json_decode($response->rawBody(), true)['error']['code']);
    }

    private function insertRefreshToken(string $plainToken, string $expiresAt): int
    {
        return (int) Db::table('refresh_tokens')->insertGetId([
            'user_id' => $this->userId,
            'token_hash' => hash('sha256', $plainToken),
            'device_name' => 'rotation-device',
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function controller(): RefreshSessionController
    {
        return new RefreshSessionController(new RefreshSessionBusiness(
            new TokenService(new RefreshMemoryTokenStore(), 900),
            2592000,
        ));
    }

    private function request(string $refreshToken): Request
    {
        $body = json_encode(['refresh_token' => $refreshToken], JSON_THROW_ON_ERROR);

        return new Request(
            "POST /api/auth/refresh HTTP/1.1\r\nHost: localhost\r\nContent-Type: application/json\r\n"
            . 'Content-Length: ' . strlen($body) . "\r\n\r\n{$body}",
        );
    }
}

final class RefreshMemoryTokenStore implements AccessTokenStore
{
    private array $values = [];

    public function put(string $key, string $value, int $ttlSeconds): void
    {
        $this->values[$key] = $value;
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
