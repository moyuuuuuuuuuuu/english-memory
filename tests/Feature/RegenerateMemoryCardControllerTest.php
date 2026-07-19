<?php

declare(strict_types=1);

namespace Tests\Feature;

use app\businesses\RegenerateMemoryCardBusiness;
use app\controllers\RegenerateMemoryCardController;
use app\entities\QueueMessageEntity;
use app\services\contracts\AiGenerationQueue;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

final class RegenerateMemoryCardControllerTest extends TestCase
{
    private int $userId;
    private int $otherUserId;
    private int $cardId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = $this->user('regenerate@example.com');
        $this->otherUserId = $this->user('regenerate-other@example.com');
        $this->cardId = $this->card($this->userId);
        $this->job($this->userId, $this->cardId, 'original-completed', 'completed');
    }

    protected function tearDown(): void
    {
        $ids = [$this->userId, $this->otherUserId];
        Db::table('ai_generation_jobs')->whereIn('user_id', $ids)->delete();
        Db::table('memory_cards')->whereIn('user_id', $ids)->delete();
        Db::table('users')->whereIn('id', $ids)->delete();
        parent::tearDown();
    }

    public function test_terminal_card_queues_regeneration_without_changing_visible_content(): void
    {
        $queue = new RegenerationQueue();
        $before = (array) Db::table('memory_cards')->where('id', $this->cardId)->first();

        $response = $this->regenerate($this->userId, $this->cardId, 'regen-1', $queue);

        self::assertSame(202, $response->getStatusCode());
        $data = $this->payload($response)['data'];
        $job = (array) Db::table('ai_generation_jobs')->where('id', $data['job_id'])->first();
        self::assertSame('regenerate', $job['operation']);
        self::assertNotNull($job['parent_job_id']);
        self::assertSame('queued', $job['status']);
        self::assertSame([$data['job_id']], $queue->published);
        $after = (array) Db::table('memory_cards')->where('id', $this->cardId)->first();
        self::assertSame($before['card_payload'], $after['card_payload']);
        self::assertSame($before['image_url'], $after['image_url']);
        self::assertSame($before['content_version'], $after['content_version']);
    }

    public function test_idempotency_replay_is_stable_and_cross_card_reuse_conflicts(): void
    {
        $queue = new RegenerationQueue();
        $first = $this->regenerate($this->userId, $this->cardId, 'regen-replay', $queue);
        $second = $this->regenerate($this->userId, $this->cardId, 'regen-replay', $queue);
        self::assertSame($this->payload($first)['data']['job_id'], $this->payload($second)['data']['job_id']);
        self::assertTrue($this->payload($second)['data']['replayed']);
        self::assertCount(1, $queue->published);

        $otherCard = $this->card($this->userId);
        $this->job($this->userId, $otherCard, 'other-completed', 'completed');
        $conflict = $this->regenerate($this->userId, $otherCard, 'regen-replay', $queue);
        self::assertSame(409, $conflict->getStatusCode());
        self::assertSame('IDEMPOTENCY_CONFLICT', $this->payload($conflict)['error']['code']);
    }

    public function test_it_rejects_missing_key_nonterminal_deleted_and_foreign_cards(): void
    {
        $queue = new RegenerationQueue();
        $missing = $this->regenerate($this->userId, $this->cardId, '', $queue);
        self::assertSame(422, $missing->getStatusCode());
        self::assertSame('IDEMPOTENCY_KEY_REQUIRED', $this->payload($missing)['error']['code']);

        $this->job($this->userId, $this->cardId, 'active-job', 'generating_image');
        $active = $this->regenerate($this->userId, $this->cardId, 'regen-active', $queue);
        self::assertSame(409, $active->getStatusCode());
        self::assertSame('JOB_STATE_CONFLICT', $this->payload($active)['error']['code']);

        Db::table('ai_generation_jobs')->where('idempotency_key', 'active-job')->delete();
        Db::table('memory_cards')->where('id', $this->cardId)->update(['deleted_at' => date('Y-m-d H:i:s')]);
        $deleted = $this->regenerate($this->userId, $this->cardId, 'regen-deleted', $queue);
        self::assertSame(404, $deleted->getStatusCode());

        $foreign = $this->card($this->otherUserId);
        $this->job($this->otherUserId, $foreign, 'foreign-completed', 'completed');
        $foreignResponse = $this->regenerate($this->userId, $foreign, 'regen-foreign', $queue);
        self::assertSame(404, $foreignResponse->getStatusCode());
    }

    private function regenerate(int $userId, int $cardId, string $key, RegenerationQueue $queue): \Webman\Http\Response
    {
        $request = new Request("POST /api/memory-cards/{$cardId}/regenerate HTTP/1.1\r\nHost: localhost\r\nIdempotency-Key: {$key}\r\n\r\n");
        $request->setAuthenticatedUserId($userId);
        return (new RegenerateMemoryCardController(new RegenerateMemoryCardBusiness($queue)))($request, $cardId);
    }

    private function user(string $email): int
    {
        return (int) Db::table('users')->insertGetId([
            'email' => $email, 'password_hash' => password_hash('SecurePass123!', PASSWORD_DEFAULT),
            'status' => 'active', 'sync_version' => 1,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function card(int $userId): int
    {
        $now = date('Y-m-d H:i:s');
        return (int) Db::table('memory_cards')->insertGetId([
            'user_id' => $userId, 'sync_version' => 1, 'content_version' => 1,
            'source_text' => 'ambition', 'normalized_text' => 'ambition',
            'content_type' => 'word', 'memory_style' => 'auto', 'is_favorite' => 1,
            'card_payload' => json_encode(['word' => 'ambition', 'story' => 'old'], JSON_THROW_ON_ERROR),
            'image_url' => 'http://e.test/storage/old.webp', 'image_storage_key' => 'old.webp',
            'review_stage' => 2, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function job(int $userId, int $cardId, string $key, string $status): int
    {
        $request = ['text' => 'ambition', 'content_type' => 'word', 'memory_style' => 'auto'];
        return (int) Db::table('ai_generation_jobs')->insertGetId([
            'user_id' => $userId, 'memory_card_id' => $cardId, 'idempotency_key' => $key,
            'request_hash' => hash('sha256', json_encode($request, JSON_THROW_ON_ERROR)),
            'operation' => 'create', 'request_payload' => json_encode($request, JSON_THROW_ON_ERROR),
            'status' => $status, 'attempts' => 1,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function payload(\Webman\Http\Response $response): array
    {
        return json_decode($response->rawBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}

final class RegenerationQueue implements AiGenerationQueue
{
    public array $published = [];
    public function publish(int $jobId): void { $this->published[] = $jobId; }
    public function consume(string $consumer, int $blockMs = 5000): ?QueueMessageEntity { return null; }
    public function ack(QueueMessageEntity $message): void {}
    public function claimStale(string $consumer, int $idleMs): ?QueueMessageEntity { return null; }
}
