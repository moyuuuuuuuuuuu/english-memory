#!/usr/bin/env php
<?php

declare(strict_types=1);

use Dotenv\Dotenv;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

if (is_file($root . '/.env')) {
    Dotenv::createUnsafeImmutable($root)->load();
}

$database = getenv('DB_DATABASE') ?: 'english_memory';
if (!preg_match('/^[a-zA-Z0-9_]+$/', $database)) {
    throw new RuntimeException('DB_DATABASE contains unsupported characters.');
}

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    getenv('DB_HOST') ?: 'mysql',
    (int) (getenv('DB_PORT') ?: 3306),
    $database,
);

$pdo = new PDO($dsn, getenv('DB_USERNAME') ?: 'root', getenv('DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `migrations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `migration` VARCHAR(255) NOT NULL,
    `checksum` CHAR(64) NOT NULL,
    `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `migrations_name_unique` (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

$executed = $pdo->query('SELECT `migration`, `checksum` FROM `migrations`')
    ->fetchAll(PDO::FETCH_KEY_PAIR);
$files = glob($root . '/database/migrations/*.sql') ?: [];
sort($files, SORT_STRING);

foreach ($files as $file) {
    $name = basename($file);
    $sql = file_get_contents($file);
    $checksum = hash('sha256', $sql);

    if (isset($executed[$name])) {
        if (!hash_equals($executed[$name], $checksum)) {
            throw new RuntimeException("Applied migration was modified: {$name}");
        }
        echo "skip {$name}\n";
        continue;
    }

    $pdo->exec($sql);
    $statement = $pdo->prepare('INSERT INTO `migrations` (`migration`, `checksum`) VALUES (?, ?)');
    $statement->execute([$name, $checksum]);
    echo "applied {$name}\n";
}

echo "migrations complete\n";
