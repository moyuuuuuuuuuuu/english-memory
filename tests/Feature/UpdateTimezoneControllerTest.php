<?php

declare(strict_types=1);

namespace Tests\Feature;

use app\businesses\UpdateTimezoneBusiness;
use app\controllers\UpdateTimezoneController;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

final class UpdateTimezoneControllerTest extends TestCase
{
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = (int) Db::table('users')->insertGetId([
            'email' => 'timezone@example.com',
            'password_hash' => password_hash('SecurePass123!', PASSWORD_DEFAULT),
            'status' => 'active',
            'session_version' => 7,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    protected function tearDown(): void
    {
        Db::table('users')->where('id', $this->userId)->delete();
        parent::tearDown();
    }

    public function test_it_updates_a_canonical_iana_timezone_without_replacing_session(): void
    {
        $response = $this->update('America/New_York');
        self::assertSame(200, $response->getStatusCode());
        $user = json_decode($response->rawBody(), true, 512, JSON_THROW_ON_ERROR)['data']['user'];
        self::assertSame('America/New_York', $user['timezone']);
        self::assertSame('America/New_York', Db::table('users')->where('id', $this->userId)->value('timezone'));
        self::assertSame(7, (int) Db::table('users')->where('id', $this->userId)->value('session_version'));
    }

    public function test_it_rejects_invalid_or_noncanonical_timezone(): void
    {
        foreach (['GMT+8', 'Asia/Not_A_Place', ''] as $timezone) {
            $response = $this->update($timezone);
            self::assertSame(422, $response->getStatusCode());
            self::assertSame('INVALID_TIMEZONE', json_decode($response->rawBody(), true)['error']['code']);
        }
        self::assertSame('Asia/Shanghai', Db::table('users')->where('id', $this->userId)->value('timezone'));
    }

    private function update(string $timezone): \Webman\Http\Response
    {
        $body = json_encode(['timezone' => $timezone], JSON_THROW_ON_ERROR);
        $request = new Request("PATCH /api/auth/me/timezone HTTP/1.1\r\nHost: localhost\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n{$body}");
        $request->setAuthenticatedUserId($this->userId);
        return (new UpdateTimezoneController(new UpdateTimezoneBusiness()))($request);
    }
}
