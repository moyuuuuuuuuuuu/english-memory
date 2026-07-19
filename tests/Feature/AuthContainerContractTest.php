<?php

declare(strict_types=1);

namespace Tests\Feature;

use app\middleware\Authenticate;
use app\services\TokenService;
use PHPUnit\Framework\TestCase;
use support\Container;

final class AuthContainerContractTest extends TestCase
{
    public function test_auth_dependencies_are_resolvable_from_the_runtime_container(): void
    {
        self::assertInstanceOf(TokenService::class, Container::get(TokenService::class));
        self::assertInstanceOf(Authenticate::class, Container::get(Authenticate::class));
    }
}
