<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Webman\Config;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($root): void {
    $prefixes = [
        'app\\' => 'app/',
        'support\\' => 'support/',
    ];

    foreach ($prefixes as $prefix => $directory) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $file = $root . '/' . $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
        return;
    }
}, true, true);

if (is_file($root . '/.env')) {
    Dotenv::createUnsafeImmutable($root)->load();
}

Config::load($root . '/config', ['route']);
