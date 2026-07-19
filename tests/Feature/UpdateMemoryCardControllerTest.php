<?php

declare(strict_types=1);

namespace Tests\Feature;

use app\businesses\UpdateMemoryCardBusiness;
use app\controllers\UpdateMemoryCardController;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

final class UpdateMemoryCardControllerTest extends TestCase
{
    private int $userId;
    private int $otherUserId;
    private int $cardId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = $this->user('update-card@example.com');
        $this->otherUserId = $this->user('update-card-other@example.com');
        $this->cardId = $this->card($this->userId);
    }

    protected function tearDown(): void
    {
        $ids = [$this->userId, $this->otherUserId];
        Db::table('ai_generation_jobs')->whereIn('user_id', $ids)->delete();
        Db::table('memory_card_tags')->whereIn('user_id', $ids)->delete();
        Db::table('tags')->whereIn('user_id', $ids)->delete();
        Db::table('memory_cards')->whereIn('user_id', $ids)->delete();
        Db::table('users')->whereIn('id', $ids)->delete();
        parent::tearDown();
    }

    public function test_it_updates_approved_fields_favorite_and_normalized_tags_with_one_version(): void
    {
        $response = $this->update($this->userId, $this->cardId, [
            'content_version' => 1,
            'meanings' => [['en' => 'a strong desire to succeed', 'zh' => '雄心，抱负']],
            'mnemonic_sentence' => '俺必胜',
            'story' => '年轻人为了远大目标不断努力。',
            'example' => ['en' => 'Her ambition is clear.', 'zh' => '她的抱负很明确。'],
            'is_favorite' => true,
            'tags' => [' Career  Goal ', 'career goal', '重点'],
        ]);

        self::assertSame(200, $response->getStatusCode());
        $card = json_decode($response->rawBody(), true, 512, JSON_THROW_ON_ERROR)['data']['card'];
        self::assertTrue($card['is_favorite']);
        self::assertSame(2, $card['content_version']);
        self::assertSame(['Career Goal', '重点'], array_column($card['tags'], 'name'));
        $stored = (array) Db::table('memory_cards')->where('id', $this->cardId)->first();
        self::assertSame((int) $stored['sync_version'], (int) Db::table('users')->where('id', $this->userId)->value('sync_version'));
        self::assertSame(2, Db::table('memory_card_tags')->where('memory_card_id', $this->cardId)->whereNull('deleted_at')->count());
    }

    public function test_it_soft_removes_and_restores_the_same_tag_relation(): void
    {
        $first = $this->data($this->update($this->userId, $this->cardId, [
            'content_version' => 1,
            'tags' => ['Focus'],
        ]));
        $tagId = $first['card']['tags'][0]['id'];
        $relationId = (int) Db::table('memory_card_tags')->where('memory_card_id', $this->cardId)->value('id');

        $second = $this->data($this->update($this->userId, $this->cardId, [
            'content_version' => 2,
            'tags' => [],
        ]));
        self::assertSame([], $second['card']['tags']);
        self::assertNotNull(Db::table('memory_card_tags')->where('id', $relationId)->value('deleted_at'));

        $third = $this->data($this->update($this->userId, $this->cardId, [
            'content_version' => 3,
            'tags' => ['focus'],
        ]));
        self::assertSame($tagId, $third['card']['tags'][0]['id']);
        self::assertSame($relationId, (int) Db::table('memory_card_tags')->where('memory_card_id', $this->cardId)->value('id'));
        self::assertNull(Db::table('memory_card_tags')->where('id', $relationId)->value('deleted_at'));
    }

    public function test_it_rejects_unknown_and_invalid_edit_fields(): void
    {
        foreach ([
            ['content_version' => 1, 'word' => 'changed'],
            ['content_version' => 1, 'meanings' => []],
            ['content_version' => 1, 'example' => ['en' => '', 'zh' => '中文']],
            ['content_version' => 1, 'tags' => ['']],
            ['content_version' => 1],
        ] as $payload) {
            $response = $this->update($this->userId, $this->cardId, $payload);
            self::assertSame(422, $response->getStatusCode());
            self::assertSame('INVALID_INPUT', json_decode($response->rawBody(), true)['error']['code']);
        }
    }

    public function test_stale_version_returns_latest_server_card_without_mutation(): void
    {
        Db::table('memory_cards')->where('id', $this->cardId)->update([
            'content_version' => 3,
            'is_favorite' => 1,
        ]);

        $response = $this->update($this->userId, $this->cardId, [
            'content_version' => 1,
            'is_favorite' => false,
        ]);

        self::assertSame(409, $response->getStatusCode());
        $payload = json_decode($response->rawBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('CARD_VERSION_CONFLICT', $payload['error']['code']);
        self::assertSame(3, $payload['error']['server_card']['content_version']);
        self::assertTrue($payload['error']['server_card']['is_favorite']);
    }

    public function test_foreign_and_deleted_cards_are_hidden(): void
    {
        $foreign = $this->card($this->otherUserId);
        $this->assertNotFound($this->update($this->userId, $foreign, ['content_version' => 1, 'is_favorite' => true]));
        Db::table('memory_cards')->where('id', $this->cardId)->update(['deleted_at' => date('Y-m-d H:i:s')]);
        $this->assertNotFound($this->update($this->userId, $this->cardId, ['content_version' => 1, 'is_favorite' => true]));
    }

    private function update(int $userId, int $cardId, array $payload): \Webman\Http\Response
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $request = new Request(
            "PATCH /api/memory-cards/{$cardId} HTTP/1.1\r\nHost: localhost\r\nContent-Type: application/json\r\n"
            . 'Content-Length: ' . strlen($body) . "\r\n\r\n{$body}",
        );
        $request->setAuthenticatedUserId($userId);

        return (new UpdateMemoryCardController(new UpdateMemoryCardBusiness()))($request, $cardId);
    }

    private function data(\Webman\Http\Response $response): array
    {
        self::assertSame(200, $response->getStatusCode());
        return json_decode($response->rawBody(), true, 512, JSON_THROW_ON_ERROR)['data'];
    }

    private function assertNotFound(\Webman\Http\Response $response): void
    {
        self::assertSame(404, $response->getStatusCode());
        self::assertSame('CARD_NOT_FOUND', json_decode($response->rawBody(), true)['error']['code']);
    }

    private function card(int $userId): int
    {
        $now = date('Y-m-d H:i:s');
        return (int) Db::table('memory_cards')->insertGetId([
            'user_id' => $userId,
            'sync_version' => 1,
            'content_version' => 1,
            'source_text' => 'ambition',
            'normalized_text' => 'ambition',
            'content_type' => 'word',
            'memory_style' => 'auto',
            'card_payload' => json_encode([
                'word' => 'ambition',
                'phonetic' => '/æmˈbɪʃn/',
                'meanings' => [['en' => 'a strong desire', 'zh' => '抱负']],
                'mnemonic_sentence' => '俺必胜',
                'story' => '年轻人满怀抱负。',
                'example' => ['en' => 'She has ambition.', 'zh' => '她有抱负。'],
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'review_stage' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function user(string $email): int
    {
        return (int) Db::table('users')->insertGetId([
            'email' => $email,
            'password_hash' => password_hash('SecurePass123!', PASSWORD_DEFAULT),
            'status' => 'active',
            'sync_version' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
