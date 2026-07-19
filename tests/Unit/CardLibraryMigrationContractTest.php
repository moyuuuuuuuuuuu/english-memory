<?php

declare(strict_types=1);

namespace Tests\Unit;

use app\businesses\SyncVersionBusiness;
use app\models\AiGenerationJob;
use app\models\MemoryCard;
use app\models\MemoryCardTag;
use app\models\RefreshToken;
use app\models\Tag;
use app\models\User;
use PHPUnit\Framework\TestCase;
use support\Db;

final class CardLibraryMigrationContractTest extends TestCase
{
    public function test_forward_migration_defines_card_library_and_sync_schema(): void
    {
        $path = dirname(__DIR__, 2) . '/database/migrations/0007_add_card_library_and_sync.sql';

        self::assertFileExists($path);
        $sql = file_get_contents($path);
        foreach ([
            'sync_version',
            'session_version',
            'content_version',
            'is_favorite',
            'deleted_at',
            'pending_card_payload',
            'operation',
        ] as $column) {
            self::assertStringContainsString("`{$column}`", $sql);
        }
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `tags`', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `memory_card_tags`', $sql);
        self::assertStringContainsString('ROW_NUMBER() OVER', $sql);
        self::assertMatchesRegularExpression('/UNIQUE KEY[^\n]+`user_id`[^\n]+`normalized_name`/', $sql);
        self::assertMatchesRegularExpression('/UNIQUE KEY[^\n]+`memory_card_id`[^\n]+`tag_id`/', $sql);
    }

    public function test_models_expose_the_new_persistence_contract(): void
    {
        self::assertContains('sync_version', (new User())->getFillable());
        self::assertContains('session_version', (new RefreshToken())->getFillable());
        self::assertContains('content_version', (new MemoryCard())->getFillable());
        self::assertSame('boolean', (new MemoryCard())->getCasts()['is_favorite']);
        self::assertSame('datetime', (new MemoryCard())->getCasts()['deleted_at']);
        self::assertContains('pending_card_payload', (new AiGenerationJob())->getFillable());
        self::assertSame('array', (new AiGenerationJob())->getCasts()['pending_card_payload']);
        self::assertSame('tags', (new Tag())->getTable());
        self::assertSame('memory_card_tags', (new MemoryCardTag())->getTable());
    }

    public function test_locked_sync_allocator_increments_and_persists_the_user_version(): void
    {
        $userId = (int) Db::table('users')->insertGetId([
            'email' => 'sync-allocator@example.com',
            'password_hash' => password_hash('SecurePass123!', PASSWORD_DEFAULT),
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        try {
            $version = Db::transaction(static function () use ($userId): int {
                $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
                return (new SyncVersionBusiness())->nextLocked($user);
            });

            self::assertSame(1, $version);
            self::assertSame(1, (int) User::query()->findOrFail($userId)->sync_version);
        } finally {
            Db::table('users')->where('id', $userId)->delete();
        }
    }
}
