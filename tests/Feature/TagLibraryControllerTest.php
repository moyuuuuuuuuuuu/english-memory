<?php

declare(strict_types=1);

namespace Tests\Feature;

use app\businesses\DeleteTagBusiness;
use app\businesses\ListTagsBusiness;
use app\businesses\RenameTagBusiness;
use app\controllers\DeleteTagController;
use app\controllers\ListTagsController;
use app\controllers\RenameTagController;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

final class TagLibraryControllerTest extends TestCase
{
    private int $userId;
    private int $otherUserId;
    private int $cardId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = $this->user('tags@example.com');
        $this->otherUserId = $this->user('tags-other@example.com');
        $this->cardId = $this->card($this->userId);
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

    public function test_list_returns_active_owned_tags_with_active_card_counts(): void
    {
        $tag = $this->tag($this->userId, 'Focus', 'focus', 1);
        $this->relation($this->userId, $this->cardId, $tag, 1);
        $this->tag($this->userId, 'Deleted', 'deleted', 2, true);
        $this->tag($this->otherUserId, 'Foreign', 'foreign', 1);

        $response = (new ListTagsController(new ListTagsBusiness()))($this->request('GET', '/api/tags', $this->userId));
        $tags = json_decode($response->rawBody(), true, 512, JSON_THROW_ON_ERROR)['data']['tags'];

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([['id' => $tag, 'name' => 'Focus', 'sync_version' => 1, 'card_count' => 1]], $tags);
    }

    public function test_rename_normalizes_and_allocates_a_new_version(): void
    {
        $tag = $this->tag($this->userId, 'Focus', 'focus', 1);

        $response = $this->rename($this->userId, $tag, ['sync_version' => 1, 'name' => '  Deep   Work  ']);
        $data = json_decode($response->rawBody(), true, 512, JSON_THROW_ON_ERROR)['data']['tag'];

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Deep Work', $data['name']);
        self::assertSame('deep work', Db::table('tags')->where('id', $tag)->value('normalized_name'));
        self::assertSame((int) Db::table('users')->where('id', $this->userId)->value('sync_version'), $data['sync_version']);
    }

    public function test_rename_rejects_stale_version_collision_and_foreign_tag(): void
    {
        $focus = $this->tag($this->userId, 'Focus', 'focus', 2);
        $this->tag($this->userId, 'Work', 'work', 3);
        $foreign = $this->tag($this->otherUserId, 'Foreign', 'foreign', 1);

        $this->assertError($this->rename($this->userId, $focus, ['sync_version' => 1, 'name' => 'New']), 409, 'TAG_VERSION_CONFLICT');
        $this->assertError($this->rename($this->userId, $focus, ['sync_version' => 2, 'name' => 'work']), 409, 'TAG_NAME_CONFLICT');
        $this->assertError($this->rename($this->userId, $foreign, ['sync_version' => 1, 'name' => 'New']), 404, 'TAG_NOT_FOUND');
    }

    public function test_delete_soft_deletes_tag_and_relations_and_is_idempotent(): void
    {
        $tag = $this->tag($this->userId, 'Focus', 'focus', 1);
        $relation = $this->relation($this->userId, $this->cardId, $tag, 1);

        $first = $this->delete($this->userId, $tag, 1);
        $firstData = json_decode($first->rawBody(), true, 512, JSON_THROW_ON_ERROR)['data'];
        self::assertSame(200, $first->getStatusCode());
        self::assertTrue($firstData['deleted']);
        self::assertNotNull(Db::table('tags')->where('id', $tag)->value('deleted_at'));
        self::assertNotNull(Db::table('memory_card_tags')->where('id', $relation)->value('deleted_at'));
        self::assertSame(
            (int) Db::table('tags')->where('id', $tag)->value('sync_version'),
            (int) Db::table('memory_card_tags')->where('id', $relation)->value('sync_version'),
        );

        $second = $this->delete($this->userId, $tag, (int) $firstData['sync_version']);
        self::assertSame(200, $second->getStatusCode());
        self::assertSame($firstData['sync_version'], json_decode($second->rawBody(), true)['data']['sync_version']);
    }

    private function rename(int $userId, int $tagId, array $payload): \Webman\Http\Response
    {
        $request = $this->request('PATCH', "/api/tags/{$tagId}", $userId, $payload);
        return (new RenameTagController(new RenameTagBusiness()))($request, $tagId);
    }

    private function delete(int $userId, int $tagId, int $syncVersion): \Webman\Http\Response
    {
        $request = $this->request('DELETE', "/api/tags/{$tagId}", $userId, ['sync_version' => $syncVersion]);
        return (new DeleteTagController(new DeleteTagBusiness()))($request, $tagId);
    }

    private function request(string $method, string $path, int $userId, array $payload = []): Request
    {
        $body = $payload === [] ? '' : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $request = new Request(
            "{$method} {$path} HTTP/1.1\r\nHost: localhost\r\nContent-Type: application/json\r\n"
            . 'Content-Length: ' . strlen($body) . "\r\n\r\n{$body}",
        );
        $request->setAuthenticatedUserId($userId);
        return $request;
    }

    private function assertError(\Webman\Http\Response $response, int $status, string $code): void
    {
        self::assertSame($status, $response->getStatusCode());
        self::assertSame($code, json_decode($response->rawBody(), true)['error']['code']);
    }

    private function tag(int $userId, string $name, string $normalized, int $version, bool $deleted = false): int
    {
        return (int) Db::table('tags')->insertGetId([
            'user_id' => $userId,
            'name' => $name,
            'normalized_name' => $normalized,
            'sync_version' => $version,
            'deleted_at' => $deleted ? date('Y-m-d H:i:s') : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function relation(int $userId, int $cardId, int $tagId, int $version): int
    {
        return (int) Db::table('memory_card_tags')->insertGetId([
            'user_id' => $userId,
            'memory_card_id' => $cardId,
            'tag_id' => $tagId,
            'sync_version' => $version,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function card(int $userId): int
    {
        return (int) Db::table('memory_cards')->insertGetId([
            'user_id' => $userId,
            'sync_version' => 1,
            'source_text' => 'ambition',
            'normalized_text' => 'ambition',
            'content_type' => 'word',
            'memory_style' => 'auto',
            'card_payload' => '{}',
            'review_stage' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function user(string $email): int
    {
        return (int) Db::table('users')->insertGetId([
            'email' => $email,
            'password_hash' => password_hash('SecurePass123!', PASSWORD_DEFAULT),
            'status' => 'active',
            'sync_version' => 3,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
