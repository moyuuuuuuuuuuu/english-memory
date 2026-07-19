<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Webman\Config;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($root): void {
    if (!str_starts_with($class, 'app\\')) {
        return;
    }

    $file = $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
}, true, true);

if (is_file($root . '/.env')) {
    Dotenv::createUnsafeImmutable($root)->load();
}

Config::load($root . '/config', ['route']);
