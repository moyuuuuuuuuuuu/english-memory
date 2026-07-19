<?php

declare(strict_types=1);

namespace app\services\contracts;

interface AccessTokenStore
{
    public function put(string $key, string $value, int $ttlSeconds): void;

    public function get(string $key): ?string;

    public function delete(string $key): void;
}
