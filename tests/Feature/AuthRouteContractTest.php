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

    public function test_login_and_current_user_routes_are_explicitly_declared(): void
    {
        $routeConfig = file_get_contents(dirname(__DIR__, 2) . '/config/route.php');
        $dependencies = file_get_contents(dirname(__DIR__, 2) . '/config/dependence.php');

        self::assertStringContainsString("Route::post('/api/auth/login'", $routeConfig);
        self::assertStringContainsString("Route::get('/api/auth/me'", $routeConfig);
        self::assertStringContainsString('->middleware([Authenticate::class])', $routeConfig);
        self::assertStringContainsString('TokenService::class', $dependencies);
        self::assertStringContainsString('Authenticate::class', $dependencies);
    }
}
