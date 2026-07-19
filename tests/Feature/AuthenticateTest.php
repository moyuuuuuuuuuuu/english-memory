<?php

declare(strict_types=1);

namespace Tests\Feature;

use app\middleware\Authenticate;
use app\services\contracts\AccessTokenStore;
use app\services\TokenService;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;
use Webman\Http\Response;

final class AuthenticateTest extends TestCase
{
    private ?int $userId = null;

    protected function setUp(): void
    {
        Db::table('users')->where('email', 'codex-authenticate@example.com')->delete();
        $this->userId = (int) Db::table('users')->insertGetId([
            'email' => 'codex-authenticate@example.com',
            'password_hash' => password_hash('SecurePass123!', PASSWORD_DEFAULT),
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        Db::table('users')->where('email', 'codex-authenticate@example.com')->delete();
    }

    public function test_it_rejects_missing_bearer_token(): void
    {
        $called = false;
        $response = $this->middleware(new AuthMemoryTokenStore())->process(
            $this->request(),
            static function () use (&$called): Response {
                $called = true;
                return json(['success' => true]);
            },
        );

        self::assertFalse($called);
        $this->assertUnauthenticated($response);
    }

    public function test_it_rejects_invalid_bearer_token(): void
    {
        $response = $this->middleware(new AuthMemoryTokenStore())->process(
            $this->request('invalid-token'),
            static fn (): Response => json(['success' => true]),
        );

        $this->assertUnauthenticated($response);
    }

    public function test_it_attaches_authenticated_user_id_for_valid_token(): void
    {
        $store = new AuthMemoryTokenStore();
        $tokens = new TokenService($store, 900);
        $issued = $tokens->issueAccessToken($this->userId);

        $response = (new Authenticate($tokens))->process(
            $this->request($issued['token']),
            function (Request $request): Response {
                self::assertSame($this->userId, $request->authenticatedUserId());
                return json(['success' => true]);
            },
        );

        self::assertSame(200, $response->getStatusCode());
    }

    private function middleware(AccessTokenStore $store): Authenticate
    {
        return new Authenticate(new TokenService($store, 900));
    }

    private function request(?string $token = null): Request
    {
        $authorization = $token === null ? '' : "Authorization: Bearer {$token}\r\n";

        return new Request(
            "GET /api/auth/me HTTP/1.1\r\n"
            . "Host: localhost\r\n"
            . $authorization
            . "\r\n",
        );
    }

    private function assertUnauthenticated(Response $response): void
    {
        self::assertSame(401, $response->getStatusCode());
        $payload = json_decode($response->rawBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($payload['success']);
        self::assertSame('UNAUTHENTICATED', $payload['error']['code']);
    }
}

final class AuthMemoryTokenStore implements AccessTokenStore
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
