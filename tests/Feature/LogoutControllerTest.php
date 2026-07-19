<?php

declare(strict_types=1);

namespace Tests\Feature;

use app\businesses\LogoutBusiness;
use app\controllers\LogoutController;
use app\services\contracts\AccessTokenStore;
use app\services\TokenService;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

final class LogoutControllerTest extends TestCase
{
    public function test_it_revokes_current_access_and_refresh_tokens(): void
    {
        Db::table('users')->where('email', 'codex-logout@example.com')->delete();
        $userId = (int) Db::table('users')->insertGetId([
            'email' => 'codex-logout@example.com',
            'password_hash' => password_hash('SecurePass123!', PASSWORD_DEFAULT),
            'status' => 'active',
        ]);
        $plainRefreshToken = 'logout-refresh-token';
        $refreshId = (int) Db::table('refresh_tokens')->insertGetId([
            'user_id' => $userId,
            'token_hash' => hash('sha256', $plainRefreshToken),
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $otherRefreshId = (int) Db::table('refresh_tokens')->insertGetId([
            'user_id' => $userId,
            'token_hash' => hash('sha256', 'other-refresh-token'),
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        try {
            $store = new LogoutMemoryTokenStore();
            $tokens = new TokenService($store, 900);
            $access = $tokens->issueAccessToken($userId, 0)['token'];
            $request = $this->request($userId, $access, $plainRefreshToken);

            $response = (new LogoutController(new LogoutBusiness($tokens)))($request);

            self::assertSame(200, $response->getStatusCode());
            self::assertTrue(json_decode($response->rawBody(), true)['success']);
            self::assertNull($tokens->resolveAccessToken($access));
            self::assertNotNull(Db::table('refresh_tokens')->where('id', $refreshId)->value('revoked_at'));
            self::assertNotNull(Db::table('refresh_tokens')->where('id', $otherRefreshId)->value('revoked_at'));
            self::assertSame(1, (int) Db::table('users')->where('id', $userId)->value('session_version'));
        } finally {
            Db::table('users')->where('id', $userId)->delete();
        }
    }

    private function request(int $userId, string $accessToken, string $refreshToken): Request
    {
        $body = json_encode(['refresh_token' => $refreshToken], JSON_THROW_ON_ERROR);
        $request = new Request(
            "POST /api/auth/logout HTTP/1.1\r\nHost: localhost\r\nAuthorization: Bearer {$accessToken}\r\n"
            . "Content-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n{$body}",
        );
        $request->setAuthenticatedUserId($userId);

        return $request;
    }
}

final class LogoutMemoryTokenStore implements AccessTokenStore
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
