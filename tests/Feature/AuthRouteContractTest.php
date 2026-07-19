<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class AuthRouteContractTest extends TestCase
{
    public function test_registration_route_is_explicitly_declared(): void
    {
        $routeConfig = file_get_contents(dirname(__DIR__, 2) . '/config/route.php');

        self::assertStringContainsString("Route::post('/api/auth/register'", $routeConfig);
        self::assertStringContainsString('new RegisterController(new RegisterBusiness())', $routeConfig);
    }
}
