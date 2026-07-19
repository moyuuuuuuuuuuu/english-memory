<?php

declare(strict_types=1);

namespace app\services;

use app\services\contracts\AccessTokenStore;
use support\Redis;

final class RedisAccessTokenStore implements AccessTokenStore
{
    public function put(string $key, string $value, int $ttlSeconds): void
    {
        Redis::setex($key, $ttlSeconds, $value);
    }

    public function get(string $key): ?string
    {
        $value = Redis::get($key);

        return $value === null || $value === false ? null : (string) $value;
    }

    public function delete(string $key): void
    {
        Redis::del($key);
    }
}
