<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DatabaseMigrationContractTest extends TestCase
{
    public static function tableProvider(): array
    {
        return [
            ['users'],
            ['refresh_tokens'],
            ['memory_cards'],
            ['ai_generation_jobs'],
            ['review_events'],
            ['daily_learning_stats'],
            ['password_reset_tokens'],
            ['memory_card_images'],
            ['tags'],
            ['memory_card_tags'],
        ];
    }

    #[DataProvider('tableProvider')]
    public function test_initial_migrations_define_required_table(string $table): void
    {
        $files = glob(dirname(__DIR__, 2) . '/database/migrations/*.sql') ?: [];
        $sql = implode("\n", array_map(static fn (string $file): string => file_get_contents($file), $files));

        self::assertStringContainsString("CREATE TABLE IF NOT EXISTS `{$table}`", $sql);
    }

    public function test_migration_runner_exists(): void
    {
        self::assertFileExists(dirname(__DIR__, 2) . '/scripts/migrate.php');
    }

    public function test_async_generation_changes_are_in_a_new_forward_migration(): void
    {
        self::assertFileExists(
            dirname(__DIR__, 2) . '/database/migrations/0005_extend_ai_generation_jobs_for_async.sql',
        );
    }

    public function test_card_library_changes_are_in_a_new_forward_migration(): void
    {
        self::assertFileExists(
            dirname(__DIR__, 2) . '/database/migrations/0007_add_card_library_and_sync.sql',
        );
    }

    public function test_review_scheduling_changes_are_in_a_new_forward_migration(): void
    {
        self::assertFileExists(
            dirname(__DIR__, 2) . '/database/migrations/0008_add_review_scheduling_and_game_data.sql',
        );
    }
}
