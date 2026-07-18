<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Webman\Config;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);

if (is_file($root . '/.env')) {
    Dotenv::createUnsafeImmutable($root)->load();
}

Config::load($root . '/config', ['route']);
