<?php

declare(strict_types=1);

namespace Tests\Feature;

use app\businesses\GetSyncChangesBusiness;
use app\controllers\GetSyncChangesController;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

final class GetSyncChangesControllerTest extends TestCase
{
    private int $userId;
    private int $otherUserId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = $this->user('sync@example.com', 4);
        $this->otherUserId = $this->user('sync-other@example.com', 2);
    }

    protected function tearDown(): void
    {
        $ids = [$this->userId, $this->otherUserId];
        Db::table('memory_card_tags')->whereIn('user_id', $ids)->delete();
        Db::table('tags')->whereIn('user_id', $ids)->delete();
        Db::table('memory_cards')->whereIn('user_id', $ids)->delete();
        Db::table('users')->whereIn('id', $ids)->delete();
        parent::tearDown();
    }

    public function test_cursor_zero_returns_owned_active_resources_and_minimal_tombstones(): void
    {
        $activeCard = $this->card($this->userId, 1);
        $deletedCard = $this->card($this->userId, 2, true);
        $tag = $this->tag($this->userId, 3);
        $relation = $this->relation($this->userId, $activeCard, $tag, 4, true);
        $this->card($this->otherUserId, 2);

        $response = $this->sync($this->userId, '0', '200');

        self::assertSame(200, $response->getStatusCode());
        $data = $this->payload($response)['data'];
        self::assertSame(4, $data['next_cursor']);
        self::assertFalse($data['has_more']);
        self::assertSame($activeCard, $data['changes']['cards'][0]['card']['id']);
        self::assertSame([
            'id' => $deletedCard,
            'content_version' => 2,
            'sync_version' => 2,
            'deleted_at' => $data['changes']['cards'][1]['deleted_at'],
        ], $data['changes']['cards'][1]);
        self::assertSame($tag, $data['changes']['tags'][0]['id']);
        self::assertSame(['id', 'sync_version', 'deleted_at'], array_keys($data['changes']['card_tags'][0]));
        self::assertSame($relation, $data['changes']['card_tags'][0]['id']);
    }

    public function test_limit_never_splits_a_shared_version_and_retry_is_stable(): void
    {
        $card = $this->card($this->userId, 2);
        $tag = $this->tag($this->userId, 2);
        $this->relation($this->userId, $card, $tag, 2);
        $this->card($this->userId, 4);

        $first = $this->payload($this->sync($this->userId, '0', '2'))['data'];
        self::assertSame(2, $first['next_cursor']);
        self::assertTrue($first['has_more']);
        self::assertSame(3, count($first['changes']['cards']) + count($first['changes']['tags']) + count($first['changes']['card_tags']));
        self::assertSame($first, $this->payload($this->sync($this->userId, '0', '2'))['data']);

        $second = $this->payload($this->sync($this->userId, '2', '2'))['data'];
        self::assertSame(4, $second['next_cursor']);
        self::assertFalse($second['has_more']);
    }

    public function test_empty_and_invalid_cursors_have_explicit_results(): void
    {
        $empty = $this->payload($this->sync($this->userId, '4', '200'))['data'];
        self::assertSame(['cards' => [], 'tags' => [], 'card_tags' => []], $empty['changes']);
        self::assertSame(4, $empty['next_cursor']);

        foreach (['-1', '5', 'abc', '1.5'] as $cursor) {
            $response = $this->sync($this->userId, $cursor, '200');
            self::assertSame(422, $response->getStatusCode());
            self::assertSame('INVALID_CURSOR', $this->payload($response)['error']['code']);
        }
    }

    private function sync(int $userId, string $cursor, string $limit): \Webman\Http\Response
    {
        $request = new Request("GET /api/sync/changes?cursor={$cursor}&limit={$limit} HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->setAuthenticatedUserId($userId);
        return (new GetSyncChangesController(new GetSyncChangesBusiness()))($request);
    }

    private function user(string $email, int $version): int
    {
        return (int) Db::table('users')->insertGetId([
            'email' => $email, 'password_hash' => password_hash('SecurePass123!', PASSWORD_DEFAULT),
            'status' => 'active', 'sync_version' => $version,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function card(int $userId, int $version, bool $deleted = false): int
    {
        $now = date('Y-m-d H:i:s');
        return (int) Db::table('memory_cards')->insertGetId([
            'user_id' => $userId, 'sync_version' => $version, 'content_version' => $deleted ? 2 : 1,
            'source_text' => $deleted ? '' : 'word-' . $version,
            'normalized_text' => $deleted ? '' : 'word-' . $version,
            'content_type' => 'word', 'memory_style' => 'auto',
            'card_payload' => json_encode($deleted ? [] : ['word' => 'word-' . $version], JSON_THROW_ON_ERROR),
            'review_stage' => 0, 'deleted_at' => $deleted ? $now : null,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function tag(int $userId, int $version): int
    {
        return (int) Db::table('tags')->insertGetId([
            'user_id' => $userId, 'name' => 'Tag ' . $version, 'normalized_name' => 'tag ' . $version,
            'sync_version' => $version, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function relation(int $userId, int $cardId, int $tagId, int $version, bool $deleted = false): int
    {
        return (int) Db::table('memory_card_tags')->insertGetId([
            'user_id' => $userId, 'memory_card_id' => $cardId, 'tag_id' => $tagId,
            'sync_version' => $version, 'deleted_at' => $deleted ? date('Y-m-d H:i:s') : null,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function payload(\Webman\Http\Response $response): array
    {
        return json_decode($response->rawBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
