<?php

declare(strict_types=1);

namespace app\common\base;

final class SyncGenerationRoutePolicy
{
    public static function enabled(string $environment, string|bool|null $flag): bool
    {
        if (strtolower(trim($environment)) === 'production') {
            return false;
        }

        return filter_var($flag, FILTER_VALIDATE_BOOL);
    }
}
