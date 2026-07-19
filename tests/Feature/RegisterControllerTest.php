<?php

declare(strict_types=1);

namespace Tests\Feature;

use app\businesses\RegisterBusiness;
use app\controllers\RegisterController;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

final class RegisterControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Db::table('users')->where('email', 'like', 'codex-register-%')->delete();
        Db::table('users')->where('username', 'like', 'codex_register_%')->delete();
    }

    public function test_it_registers_with_email_and_hashes_the_password(): void
    {
        $email = 'codex-register-email@example.com';
        $response = (new RegisterController(new RegisterBusiness()))($this->jsonRequest([
            'email' => $email,
            'password' => 'SecurePass123!',
        ]));

        self::assertSame(201, $response->getStatusCode());
        $payload = json_decode($response->rawBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['success']);
        self::assertSame($email, $payload['data']['user']['email']);
        self::assertNull($payload['data']['user']['username']);
        self::assertArrayNotHasKey('password_hash', $payload['data']['user']);

        $user = (array) Db::table('users')->where('email', $email)->first();
        self::assertNotSame('SecurePass123!', $user['password_hash']);
        self::assertTrue(password_verify('SecurePass123!', $user['password_hash']));
    }

    public function test_it_registers_with_username(): void
    {
        $response = $this->register([
            'username' => 'codex_register_user',
            'password' => 'SecurePass123!',
        ]);

        self::assertSame(201, $response->getStatusCode());
        $payload = json_decode($response->rawBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('codex_register_user', $payload['data']['user']['username']);
        self::assertNull($payload['data']['user']['email']);
    }

    public function test_it_rejects_registration_without_email_or_username(): void
    {
        $this->assertError(
            $this->register(['password' => 'SecurePass123!']),
            422,
            'INVALID_INPUT',
        );
    }

    public function test_it_rejects_invalid_email(): void
    {
        $this->assertError(
            $this->register(['email' => 'not-an-email', 'password' => 'SecurePass123!']),
            422,
            'INVALID_INPUT',
        );
    }

    public function test_it_rejects_short_password(): void
    {
        $this->assertError(
            $this->register(['email' => 'codex-register-short@example.com', 'password' => 'short']),
            422,
            'INVALID_INPUT',
        );
    }

    public function test_it_rejects_duplicate_email(): void
    {
        $payload = ['email' => 'codex-register-duplicate@example.com', 'password' => 'SecurePass123!'];
        self::assertSame(201, $this->register($payload)->getStatusCode());

        $this->assertError($this->register($payload), 409, 'IDENTITY_TAKEN');
    }

    public function test_it_rejects_duplicate_username(): void
    {
        $payload = ['username' => 'codex_register_duplicate', 'password' => 'SecurePass123!'];
        self::assertSame(201, $this->register($payload)->getStatusCode());

        $this->assertError($this->register($payload), 409, 'IDENTITY_TAKEN');
    }

    private function register(array $payload): \Webman\Http\Response
    {
        return (new RegisterController(new RegisterBusiness()))($this->jsonRequest($payload));
    }

    private function assertError(
        \Webman\Http\Response $response,
        int $status,
        string $code,
    ): void {
        self::assertSame($status, $response->getStatusCode());
        $payload = json_decode($response->rawBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($payload['success']);
        self::assertSame($code, $payload['error']['code']);
        self::assertNotSame('', $payload['error']['message']);
    }

    private function jsonRequest(array $payload): Request
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return new Request(
            "POST /api/auth/register HTTP/1.1\r\n"
            . "Host: localhost\r\n"
            . "Content-Type: application/json\r\n"
            . 'Content-Length: ' . strlen($body) . "\r\n\r\n"
            . $body,
        );
    }
}
