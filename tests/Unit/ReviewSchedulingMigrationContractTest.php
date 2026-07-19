<?php

declare(strict_types=1);

namespace Tests\Unit;

use app\common\enums\ReviewDifficulty;
use app\common\enums\ReviewMode;
use app\common\enums\ReviewRating;
use app\models\DailyLearningStat;
use app\models\MemoryCard;
use app\models\ReviewEvent;
use app\models\User;
use PHPUnit\Framework\TestCase;

final class ReviewSchedulingMigrationContractTest extends TestCase
{
    public function test_forward_migration_defines_review_and_game_schema(): void
    {
        $path = dirname(__DIR__, 2) . '/database/migrations/0008_add_review_scheduling_and_game_data.sql';
        self::assertFileExists($path);
        $sql = file_get_contents($path);
        foreach ([
            'timezone', 'first_reviewed_at', 'last_reviewed_at', 'review_count',
            'idempotency_key', 'request_hash', 'mode', 'answer_text', 'normalized_answer',
            'is_correct', 'difficulty', 'effective_rating', 'previous_due_at', 'next_due_at',
            'response_ms', 'xp_awarded', 'response_payload', 'xp_earned',
            'current_correct_streak', 'best_correct_streak',
        ] as $column) {
            self::assertStringContainsString("`{$column}`", $sql);
        }
        self::assertStringContainsString("DEFAULT 'Asia/Shanghai'", $sql);
        self::assertMatchesRegularExpression('/UNIQUE KEY[^\n]+`user_id`[^\n]+`idempotency_key`/', $sql);
        self::assertStringContainsString("CONCAT('legacy-', `id`)", $sql);
    }

    public function test_enums_and_models_expose_review_contract(): void
    {
        self::assertSame(['image_recall', 'listening_spelling', 'zh_to_en'], array_column(ReviewMode::cases(), 'value'));
        self::assertSame(['hard', 'good', 'easy'], array_column(ReviewDifficulty::cases(), 'value'));
        self::assertSame(['again', 'hard', 'good', 'easy'], array_column(ReviewRating::cases(), 'value'));
        self::assertContains('timezone', (new User())->getFillable());
        self::assertContains('review_count', (new MemoryCard())->getFillable());
        self::assertSame('integer', (new MemoryCard())->getCasts()['review_count']);
        self::assertSame('review_events', (new ReviewEvent())->getTable());
        self::assertSame('array', (new ReviewEvent())->getCasts()['response_payload']);
        self::assertSame('daily_learning_stats', (new DailyLearningStat())->getTable());
    }
}
