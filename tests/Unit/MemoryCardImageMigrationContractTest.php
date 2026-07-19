<?php

declare(strict_types=1);

namespace Tests\Unit;

use app\models\MemoryCardImage;
use PHPUnit\Framework\TestCase;

final class MemoryCardImageMigrationContractTest extends TestCase
{
    public function test_forward_migration_defines_owned_image_metadata(): void
    {
        $path = dirname(__DIR__, 2) . '/database/migrations/0006_create_memory_card_images.sql';
        self::assertFileExists($path);
        $sql = file_get_contents($path);

        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `memory_card_images`', $sql);
        foreach (['original_key', 'large_key', 'small_key', 'original_sha256', 'large_sha256', 'small_sha256'] as $column) {
            self::assertStringContainsString("`{$column}`", $sql);
        }
        self::assertMatchesRegularExpression('/UNIQUE KEY\s+`[^`]+`\s*\(`memory_card_id`\)/i', $sql);
        self::assertSame(2, substr_count($sql, 'ON DELETE CASCADE'));
    }

    public function test_model_exposes_fillable_metadata_and_owned_lookup(): void
    {
        $model = new MemoryCardImage();

        self::assertContains('large_sha256', $model->getFillable());
        self::assertContains('original_bytes', $model->getFillable());
        self::assertTrue(method_exists($model, 'forOwnedCard'));
    }
}
