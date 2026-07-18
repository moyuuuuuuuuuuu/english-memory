<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use support\Db;
use support\Redis;

final class InfrastructureConnectionTest extends TestCase
{
    public function test_mysql_connection_is_available(): void
    {
        $row = Db::selectOne('SELECT 1 AS connection_ok');

        self::assertSame(1, (int) $row->connection_ok);
    }

    public function test_redis_connection_is_available(): void
    {
        self::assertTrue(Redis::ping());
    }
}
