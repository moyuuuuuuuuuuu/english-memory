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

    public function test_refresh_and_logout_routes_are_explicitly_declared(): void
    {
        $routeConfig = file_get_contents(dirname(__DIR__, 2) . '/config/route.php');

        self::assertStringContainsString("Route::post('/api/auth/refresh'", $routeConfig);
        self::assertStringContainsString("Route::post('/api/auth/logout'", $routeConfig);
        self::assertGreaterThanOrEqual(2, substr_count($routeConfig, '->middleware([Authenticate::class])'));
    }

    public function test_password_reset_routes_and_mail_boundary_are_declared(): void
    {
        $routeConfig = file_get_contents(dirname(__DIR__, 2) . '/config/route.php');
        $dependencies = file_get_contents(dirname(__DIR__, 2) . '/config/dependence.php');

        self::assertStringContainsString("Route::post('/api/auth/forgot-password'", $routeConfig);
        self::assertStringContainsString("Route::post('/api/auth/reset-password'", $routeConfig);
        self::assertStringContainsString('PasswordResetMail::class', $dependencies);
    }

    public function test_async_memory_card_creation_route_is_authenticated(): void
    {
        $routeConfig = file_get_contents(dirname(__DIR__, 2) . '/config/route.php');

        self::assertStringContainsString("Route::post('/api/memory-cards'", $routeConfig);
        self::assertStringContainsString('CreateMemoryCardController', $routeConfig);
        self::assertGreaterThanOrEqual(3, substr_count($routeConfig, '->middleware([Authenticate::class])'));
    }
}
