<?php

declare(strict_types=1);

namespace Tests\Feature;

use app\common\base\SyncGenerationRoutePolicy;
use PHPUnit\Framework\TestCase;

final class SyncGenerationRouteFlagTest extends TestCase
{
    public function test_local_environment_requires_explicit_enable_flag(): void
    {
        self::assertTrue(SyncGenerationRoutePolicy::enabled('local', 'true'));
        self::assertFalse(SyncGenerationRoutePolicy::enabled('local', 'false'));
        self::assertFalse(SyncGenerationRoutePolicy::enabled('local', ''));
    }

    public function test_production_is_forced_disabled_even_when_flag_is_true(): void
    {
        self::assertFalse(SyncGenerationRoutePolicy::enabled('production', 'true'));
    }

    public function test_route_and_environment_template_use_the_policy(): void
    {
        $root = dirname(__DIR__, 2);
        $route = file_get_contents($root . '/config/route.php');
        $environment = file_get_contents($root . '/.env.example');

        self::assertStringContainsString('SyncGenerationRoutePolicy::enabled', $route);
        self::assertStringContainsString('ENABLE_SYNC_GENERATION=false', $environment);
    }
}
