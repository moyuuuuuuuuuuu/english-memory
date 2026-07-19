<?php

declare(strict_types=1);

namespace Tests\Feature;

use app\businesses\ListMemoryCardsBusiness;
use app\controllers\ListMemoryCardsController;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

final class ListMemoryCardsControllerTest extends TestCase
{
    private int $userId;
    private int $otherUserId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = $this->user('library-owner@example.com');
        $this->otherUserId = $this->user('library-other@example.com');
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

    public function test_it_lists_only_active_owned_cards_in_stable_descending_order(): void
    {
        $old = $this->card($this->userId, 'old', '2026-07-19 10:00:00');
        $new = $this->card($this->userId, 'new', '2026-07-19 11:00:00');
        $this->card($this->userId, 'deleted', '2026-07-19 12:00:00', deleted: true);
        $this->card($this->otherUserId, 'foreign', '2026-07-19 13:00:00');

        $data = $this->success($this->request());

        self::assertSame([$new, $old], array_column($data['items'], 'id'));
        self::assertFalse($data['has_more']);
        self::assertNull($data['next_cursor']);
    }

    public function test_keyset_cursor_returns_each_card_once(): void
    {
        $ids = [];
        for ($index = 0; $index < 5; ++$index) {
            $ids[] = $this->card($this->userId, 'card-' . $index, '2026-07-19 10:00:00');
        }

        $first = $this->success($this->request(['limit' => '2']));
        $second = $this->success($this->request(['limit' => '2', 'cursor' => $first['next_cursor']]));
        $third = $this->success($this->request(['limit' => '2', 'cursor' => $second['next_cursor']]));
        $returned = array_merge(
            array_column($first['items'], 'id'),
            array_column($second['items'], 'id'),
            array_column($third['items'], 'id'),
        );

        rsort($ids);
        self::assertSame($ids, $returned);
        self::assertTrue($first['has_more']);
        self::assertTrue($second['has_more']);
        self::assertFalse($third['has_more']);
    }

    public function test_it_filters_search_favorite_tag_content_type_and_latest_status(): void
    {
        $match = $this->card($this->userId, '100% resilient', '2026-07-19 10:00:00', favorite: true, contentType: 'sentence', status: 'failed');
        $this->card($this->userId, '100x resilient', '2026-07-19 11:00:00', favorite: true, contentType: 'sentence', status: 'failed');
        $tagId = (int) Db::table('tags')->insertGetId([
            'user_id' => $this->userId,
            'name' => 'Important',
            'normalized_name' => 'important',
            'sync_version' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        Db::table('memory_card_tags')->insert([
            'user_id' => $this->userId,
            'memory_card_id' => $match,
            'tag_id' => $tagId,
            'sync_version' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $data = $this->success($this->request([
            'q' => '%',
            'is_favorite' => 'true',
            'content_type' => 'sentence',
            'tag_id' => (string) $tagId,
            'status' => 'failed',
        ]));

        self::assertSame([$match], array_column($data['items'], 'id'));
    }

    public function test_it_rejects_invalid_cursor_limit_and_filters(): void
    {
        $invalidCursor = $this->request(['cursor' => 'not-base64']);
        self::assertSame(422, $invalidCursor->getStatusCode());
        self::assertSame('INVALID_CURSOR', json_decode($invalidCursor->rawBody(), true)['error']['code']);

        foreach ([
            ['limit' => '0'],
            ['limit' => '101'],
            ['content_type' => 'paragraph'],
            ['is_favorite' => 'sometimes'],
            ['tag_id' => '-1'],
            ['status' => 'unknown'],
        ] as $query) {
            $response = $this->request($query);
            self::assertSame(422, $response->getStatusCode());
            self::assertSame('INVALID_INPUT', json_decode($response->rawBody(), true)['error']['code']);
        }
    }

    public function test_route_is_registered_before_the_dynamic_detail_route(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/config/route.php');
        $list = strpos($routes, "Route::get('/api/memory-cards'");
        $detail = strpos($routes, "Route::get('/api/memory-cards/{id}'");

        self::assertIsInt($list);
        self::assertIsInt($detail);
        self::assertLessThan($detail, $list);
    }

    private function request(array $query = []): \Webman\Http\Response
    {
        $path = '/api/memory-cards' . ($query === [] ? '' : '?' . http_build_query($query));
        $request = new Request("GET {$path} HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->setAuthenticatedUserId($this->userId);

        return (new ListMemoryCardsController(new ListMemoryCardsBusiness()))($request);
    }

    private function success(\Webman\Http\Response $response): array
    {
        self::assertSame(200, $response->getStatusCode());
        return json_decode($response->rawBody(), true, 512, JSON_THROW_ON_ERROR)['data'];
    }

    private function card(
        int $userId,
        string $text,
        string $createdAt,
        bool $deleted = false,
        bool $favorite = false,
        string $contentType = 'word',
        string $status = 'completed',
    ): int {
        $cardId = (int) Db::table('memory_cards')->insertGetId([
            'user_id' => $userId,
            'sync_version' => 1,
            'content_version' => 1,
            'source_text' => $text,
            'normalized_text' => $text,
            'content_type' => $contentType,
            'memory_style' => 'auto',
            'is_favorite' => $favorite,
            'card_payload' => '{}',
            'review_stage' => 0,
            'deleted_at' => $deleted ? $createdAt : null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
        Db::table('ai_generation_jobs')->insert([
            'user_id' => $userId,
            'memory_card_id' => $cardId,
            'idempotency_key' => 'library-' . $cardId,
            'request_hash' => hash('sha256', '{}'),
            'request_payload' => '{}',
            'status' => $status,
            'attempts' => 1,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
        return $cardId;
    }

    private function user(string $email): int
    {
        return (int) Db::table('users')->insertGetId([
            'email' => $email,
            'password_hash' => password_hash('SecurePass123!', PASSWORD_DEFAULT),
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
