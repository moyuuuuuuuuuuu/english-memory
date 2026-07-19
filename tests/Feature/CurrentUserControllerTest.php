<?php

declare(strict_types=1);

namespace Tests\Feature;

use app\businesses\CurrentUserBusiness;
use app\controllers\CurrentUserController;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

final class CurrentUserControllerTest extends TestCase
{
    public function test_it_returns_the_authenticated_user_without_sensitive_fields(): void
    {
        Db::table('users')->where('email', 'codex-current-user@example.com')->delete();
        $userId = (int) Db::table('users')->insertGetId([
            'email' => 'codex-current-user@example.com',
            'username' => 'codex_current_user',
            'password_hash' => password_hash('SecurePass123!', PASSWORD_DEFAULT),
            'status' => 'active',
        ]);

        try {
            $request = new Request("GET /api/auth/me HTTP/1.1\r\nHost: localhost\r\n\r\n");
            $request->setAuthenticatedUserId($userId);
            $response = (new CurrentUserController(new CurrentUserBusiness()))($request);

            self::assertSame(200, $response->getStatusCode());
            $payload = json_decode($response->rawBody(), true, 512, JSON_THROW_ON_ERROR);
            self::assertTrue($payload['success']);
            self::assertSame($userId, $payload['data']['user']['id']);
            self::assertSame('codex-current-user@example.com', $payload['data']['user']['email']);
            self::assertSame('Asia/Shanghai', $payload['data']['user']['timezone']);
            self::assertArrayNotHasKey('password_hash', $payload['data']['user']);
        } finally {
            Db::table('users')->where('id', $userId)->delete();
        }
    }
}
