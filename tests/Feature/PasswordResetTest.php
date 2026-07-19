<?php

declare(strict_types=1);

namespace Tests\Feature;

use app\businesses\ForgotPasswordBusiness;
use app\businesses\ResetPasswordBusiness;
use app\controllers\ForgotPasswordController;
use app\controllers\ResetPasswordController;
use app\services\contracts\PasswordResetMail;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

final class PasswordResetTest extends TestCase
{
    private int $userId;

    protected function setUp(): void
    {
        Db::table('users')->where('email', 'codex-reset@example.com')->delete();
        $this->userId = (int) Db::table('users')->insertGetId([
            'email' => 'codex-reset@example.com',
            'password_hash' => password_hash('OldSecurePass123!', PASSWORD_DEFAULT),
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        Db::table('users')->where('id', $this->userId)->delete();
    }

    public function test_forgot_password_is_neutral_and_persists_only_a_hash(): void
    {
        $mail = new FakePasswordResetMail();
        $controller = new ForgotPasswordController(new ForgotPasswordBusiness($mail, 1800));

        $existing = $controller($this->request('/api/auth/forgot-password', ['email' => 'codex-reset@example.com']));
        $missing = $controller($this->request('/api/auth/forgot-password', ['email' => 'missing@example.com']));

        self::assertSame(200, $existing->getStatusCode());
        self::assertSame($existing->rawBody(), $missing->rawBody());
        self::assertCount(1, $mail->messages);
        $plainToken = $mail->messages[0]['token'];
        $storedHash = Db::table('password_reset_tokens')->where('user_id', $this->userId)->value('token_hash');
        self::assertSame(hash('sha256', $plainToken), $storedHash);
        self::assertNotSame($plainToken, $storedHash);
    }

    public function test_it_resets_password_once_with_a_valid_token(): void
    {
        $plainToken = 'valid-password-reset-token';
        $resetId = $this->insertResetToken($plainToken, date('Y-m-d H:i:s', time() + 1800));
        $controller = new ResetPasswordController(new ResetPasswordBusiness());

        $response = $controller($this->request('/api/auth/reset-password', [
            'token' => $plainToken,
            'password' => 'NewSecurePass123!',
        ]));

        self::assertSame(200, $response->getStatusCode());
        $hash = Db::table('users')->where('id', $this->userId)->value('password_hash');
        self::assertTrue(password_verify('NewSecurePass123!', $hash));
        self::assertNotNull(Db::table('password_reset_tokens')->where('id', $resetId)->value('used_at'));

        $reuse = $controller($this->request('/api/auth/reset-password', [
            'token' => $plainToken,
            'password' => 'AnotherPass123!',
        ]));
        self::assertSame(422, $reuse->getStatusCode());
        self::assertSame('INVALID_RESET_TOKEN', json_decode($reuse->rawBody(), true)['error']['code']);
    }

    public function test_it_rejects_expired_reset_token(): void
    {
        $plainToken = 'expired-password-reset-token';
        $this->insertResetToken($plainToken, date('Y-m-d H:i:s', time() - 1));

        $response = (new ResetPasswordController(new ResetPasswordBusiness()))(
            $this->request('/api/auth/reset-password', [
                'token' => $plainToken,
                'password' => 'NewSecurePass123!',
            ]),
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('INVALID_RESET_TOKEN', json_decode($response->rawBody(), true)['error']['code']);
    }

    private function insertResetToken(string $plainToken, string $expiresAt): int
    {
        return (int) Db::table('password_reset_tokens')->insertGetId([
            'user_id' => $this->userId,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function request(string $path, array $payload): Request
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return new Request(
            "POST {$path} HTTP/1.1\r\nHost: localhost\r\nContent-Type: application/json\r\n"
            . 'Content-Length: ' . strlen($body) . "\r\n\r\n{$body}",
        );
    }
}

final class FakePasswordResetMail implements PasswordResetMail
{
    public array $messages = [];

    public function send(string $email, string $plainToken): void
    {
        $this->messages[] = ['email' => $email, 'token' => $plainToken];
    }
}
