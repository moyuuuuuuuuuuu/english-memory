<?php

declare(strict_types=1);

namespace Tests\Unit\Businesses;

use app\businesses\MemoryCardViewBusiness;
use app\models\MemoryCard;
use PHPUnit\Framework\TestCase;
use support\Db;

final class MemoryCardViewBusinessTest extends TestCase
{
    private int $userId;
    private int $cardId;

    protected function setUp(): void
    {
        parent::setUp();
        $now = date('Y-m-d H:i:s');
        $this->userId = (int) Db::table('users')->insertGetId([
            'email' => 'card-view@example.com',
            'password_hash' => password_hash('SecurePass123!', PASSWORD_DEFAULT),
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->cardId = (int) Db::table('memory_cards')->insertGetId([
            'user_id' => $this->userId,
            'sync_version' => 4,
            'content_version' => 2,
            'source_text' => 'ambition',
            'normalized_text' => 'ambition',
            'content_type' => 'word',
            'memory_style' => 'auto',
            'is_favorite' => 1,
            'card_payload' => json_encode(['word' => 'ambition'], JSON_THROW_ON_ERROR),
            'first_reviewed_at' => '2026-07-18 01:02:03',
            'last_reviewed_at' => '2026-07-19 04:05:06',
            'review_count' => 3,
            'next_review_at' => '2026-07-20 04:05:06',
            'review_stage' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        foreach ([['Career', 'career'], ['Dream', 'dream']] as [$name, $normalized]) {
            $tagId = (int) Db::table('tags')->insertGetId([
                'user_id' => $this->userId,
                'name' => $name,
                'normalized_name' => $normalized,
                'sync_version' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            Db::table('memory_card_tags')->insert([
                'user_id' => $this->userId,
                'memory_card_id' => $this->cardId,
                'tag_id' => $tagId,
                'sync_version' => 4,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        Db::table('ai_generation_jobs')->insert([
            'user_id' => $this->userId,
            'memory_card_id' => $this->cardId,
            'idempotency_key' => 'view-job',
            'request_hash' => hash('sha256', '{}'),
            'request_payload' => '{}',
            'provider_payload' => json_encode(['secret' => 'hidden'], JSON_THROW_ON_ERROR),
            'pending_card_payload' => json_encode(['secret' => 'hidden'], JSON_THROW_ON_ERROR),
            'status' => 'completed',
            'attempts' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected function tearDown(): void
    {
        Db::table('ai_generation_jobs')->where('user_id', $this->userId)->delete();
        Db::table('memory_card_tags')->where('user_id', $this->userId)->delete();
        Db::table('tags')->where('user_id', $this->userId)->delete();
        Db::table('memory_cards')->where('user_id', $this->userId)->delete();
        Db::table('users')->where('id', $this->userId)->delete();
        parent::tearDown();
    }

    public function test_it_builds_the_shared_public_card_view(): void
    {
        $card = MemoryCard::query()->findOrFail($this->cardId);

        $view = (new MemoryCardViewBusiness())->build($this->userId, $card)->toArray();

        self::assertTrue($view['card']['is_favorite']);
        self::assertSame(2, $view['card']['content_version']);
        self::assertSame(4, $view['card']['sync_version']);
        self::assertSame('2026-07-18 01:02:03', $view['card']['first_reviewed_at']);
        self::assertSame('2026-07-19 04:05:06', $view['card']['last_reviewed_at']);
        self::assertSame(3, $view['card']['review_count']);
        self::assertSame(2, $view['card']['review_stage']);
        self::assertSame('2026-07-20 04:05:06', $view['card']['next_review_at']);
        self::assertSame(['Career', 'Dream'], array_column($view['card']['tags'], 'name'));
        self::assertSame('completed', $view['job']['status']);
        self::assertStringNotContainsString('secret', json_encode($view, JSON_THROW_ON_ERROR));
        self::assertArrayNotHasKey('image_storage_key', $view['card']);
    }
}
