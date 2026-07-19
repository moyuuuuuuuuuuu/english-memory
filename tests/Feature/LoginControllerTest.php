<?php

declare(strict_types=1);

namespace Tests\Feature;

use app\businesses\LoginBusiness;
use app\controllers\LoginController;
use app\services\contracts\AccessTokenStore;
use app\services\TokenService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

final class LoginControllerTest extends TestCase
{
    private const TEST_EMAILS = [
        'codex-login@example.com',
        'codex-login-disabled@example.com',
    ];

    protected function setUp(): void
    {
        Db::table('users')->whereIn('email', self::TEST_EMAILS)->delete();
        Db::table('users')->insert([
            'email' => 'codex-login@example.com',
            'username' => 'codex_login_user',
            'password_hash' => password_hash('SecurePass123!', PASSWORD_DEFAULT),
            'status' => 'active',
        ]);
        Db::table('users')->insert([
            'email' => 'codex-login-disabled@example.com',
            'username' => 'codex_login_disabled',
            'password_hash' => password_hash('SecurePass123!', PASSWORD_DEFAULT),
            'status' => 'disabled',
        ]);
    }

    protected function tearDown(): void
    {
        Db::table('users')->whereIn('email', self::TEST_EMAILS)->delete();
    }

    public static function identityProvider(): array
    {
        return [
            ['codex-login@example.com'],
            ['codex_login_user'],
        ];
    }

    #[DataProvider('identityProvider')]
    public function test_it_logs_in_with_email_or_username(string $identity): void
    {
        $response = $this->login($identity, 'SecurePass123!');

        self::assertSame(200, $response->getStatusCode());
        $payload = json_decode($response->rawBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['success']);
        self::assertSame('Bearer', $payload['data']['token_type']);
        self::assertSame(900, $payload['data']['expires_in']);
        self::assertNotSame('', $payload['data']['access_token']);
        self::assertSame(2592000, $payload['data']['refresh_expires_in']);
        self::assertNotSame('', $payload['data']['refresh_token']);
        self::assertSame('codex-login@example.com', $payload['data']['user']['email']);
        self::assertArrayNotHasKey('password_hash', $payload['data']['user']);

        $stored = (array) Db::table('refresh_tokens')
            ->where('user_id', $payload['data']['user']['id'])
            ->first();
        self::assertSame(hash('sha256', $payload['data']['refresh_token']), $stored['token_hash']);
        self::assertNotSame($payload['data']['refresh_token'], $stored['token_hash']);
        self::assertSame('phpunit-device', $stored['device_name']);
    }

    public function test_it_rejects_wrong_password(): void
    {
        $this->assertAuthFailure($this->login('codex-login@example.com', 'wrong-password'), 401, 'INVALID_CREDENTIALS');
    }

    public function test_it_rejects_unknown_identity(): void
    {
        $this->assertAuthFailure($this->login('missing@example.com', 'SecurePass123!'), 401, 'INVALID_CREDENTIALS');
    }

    public function test_it_rejects_disabled_user(): void
    {
        $this->assertAuthFailure($this->login('codex-login-disabled@example.com', 'SecurePass123!'), 403, 'ACCOUNT_DISABLED');
    }

    private function login(string $identity, string $password): \Webman\Http\Response
    {
        $controller = new LoginController(new LoginBusiness(
            new TokenService(new LoginMemoryTokenStore(), 900),
        ));

        return $controller($this->jsonRequest([
            'identity' => $identity,
            'password' => $password,
            'device_name' => 'phpunit-device',
        ]));
    }

    private function assertAuthFailure(\Webman\Http\Response $response, int $status, string $code): void
    {
        self::assertSame($status, $response->getStatusCode());
        $payload = json_decode($response->rawBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($payload['success']);
        self::assertSame($code, $payload['error']['code']);
    }

    private function jsonRequest(array $payload): Request
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return new Request(
            "POST /api/auth/login HTTP/1.1\r\n"
            . "Host: localhost\r\n"
            . "Content-Type: application/json\r\n"
            . 'Content-Length: ' . strlen($body) . "\r\n\r\n"
            . $body,
        );
    }
}

final class LoginMemoryTokenStore implements AccessTokenStore
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
