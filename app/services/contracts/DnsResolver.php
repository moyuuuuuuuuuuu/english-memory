<?php

declare(strict_types=1);

namespace app\services\contracts;

interface DnsResolver
{
    /** @return list<string> */
    public function resolve(string $host): array;
}
