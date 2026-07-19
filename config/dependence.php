<?php

use app\middleware\Authenticate;
use app\services\RedisAccessTokenStore;
use app\services\TokenService;
use Psr\Container\ContainerInterface;
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

return [
    TokenService::class => static fn (): TokenService => new TokenService(
        new RedisAccessTokenStore(),
        (int) config('auth.access_token_ttl', 900),
    ),
    Authenticate::class => static fn (ContainerInterface $container): Authenticate => new Authenticate(
        $container->get(TokenService::class),
    ),
];
