<?php

declare(strict_types=1);

namespace Tests\Unit;

use app\common\enums\AiGenerationStatus;
use app\models\AiGenerationJob;
use app\models\MemoryCard;
use PHPUnit\Framework\TestCase;

final class AsyncGenerationMigrationContractTest extends TestCase
{
    public function test_async_generation_statuses_are_stable(): void
    {
        self::assertSame([
            'queued',
            'generating_text',
            'generating_image',
            'completed',
            'failed',
        ], array_column(AiGenerationStatus::cases(), 'value'));
    }

    public function test_forward_migration_defines_async_job_contract(): void
    {
        $path = dirname(__DIR__, 2) . '/database/migrations/0005_extend_ai_generation_jobs_for_async.sql';

        self::assertFileExists($path);
        $sql = file_get_contents($path);

        foreach (['idempotency_key', 'request_hash', 'failure_type', 'parent_job_id', 'dispatched_at'] as $column) {
            self::assertStringContainsString("`{$column}`", $sql);
        }

        self::assertMatchesRegularExpression(
            '/UNIQUE KEY\s+`[^`]+`\s*\(`user_id`,\s*`idempotency_key`\)/i',
            $sql,
        );
        self::assertMatchesRegularExpression(
            '/FOREIGN KEY\s*\(`parent_job_id`\)\s+REFERENCES\s+`ai_generation_jobs`\s*\(`id`\)\s+ON DELETE SET NULL/i',
            $sql,
        );
        self::assertStringContainsString("CONCAT('legacy-', `id`)", $sql);
        self::assertStringContainsString('SHA2', $sql);
    }

    public function test_models_expose_only_expected_persistence_contracts(): void
    {
        $card = new MemoryCard();
        $job = new AiGenerationJob();

        self::assertContains('card_payload', $card->getFillable());
        self::assertSame('array', $card->getCasts()['card_payload']);
        self::assertContains('idempotency_key', $job->getFillable());
        self::assertContains('parent_job_id', $job->getFillable());
        self::assertSame('array', $job->getCasts()['request_payload']);
        self::assertSame('array', $job->getCasts()['provider_payload']);
        self::assertContains('provider_payload', $job->getHidden());
        self::assertContains('error_message', $job->getHidden());
        self::assertTrue(method_exists($job, 'latestForCard'));
    }
}
