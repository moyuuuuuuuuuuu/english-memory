<?php

declare(strict_types=1);

namespace Tests\Feature;

use app\businesses\CreateMemoryCardBusiness;
use app\controllers\CreateMemoryCardController;
use app\entities\QueueMessageEntity;
use app\services\contracts\AiGenerationQueue;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use support\Db;
use support\Request;

final class CreateMemoryCardControllerTest extends TestCase
{
    private int $userId;
    private int $otherUserId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = $this->createUser('codex-async-create@example.com');
        $this->otherUserId = $this->createUser('codex-async-other@example.com');
    }

    protected function tearDown(): void
    {
        Db::table('ai_generation_jobs')->whereIn('user_id', [$this->userId, $this->otherUserId])->delete();
        Db::table('memory_cards')->whereIn('user_id', [$this->userId, $this->otherUserId])->delete();
        Db::table('users')->whereIn('id', [$this->userId, $this->otherUserId])->delete();
        parent::tearDown();
    }

    public function test_it_requires_a_bounded_idempotency_key(): void
    {
        $queue = new RecordingGenerationQueue();

        $missing = $this->create($queue, $this->userId, '', ['text' => 'ambition']);
        $oversized = $this->create($queue, $this->userId, str_repeat('x', 129), ['text' => 'ambition']);

        $this->assertError($missing, 422, 'IDEMPOTENCY_KEY_REQUIRED');
        $this->assertError($oversized, 422, 'INVALID_INPUT');
        self::assertSame([], $queue->publishedJobIds);
    }

    public function test_it_rejects_blank_text_and_invalid_options(): void
    {
        $queue = new RecordingGenerationQueue();

        $this->assertError($this->create($queue, $this->userId, 'blank', ['text' => '  ']), 422, 'INVALID_INPUT');
        $this->assertError($this->create($queue, $this->userId, 'type', [
            'text' => 'ambition',
            'content_type' => 'paragraph',
        ]), 422, 'INVALID_INPUT');
        $this->assertError($this->create($queue, $this->userId, 'style', [
            'text' => 'ambition',
            'memory_style' => 'random',
        ]), 422, 'INVALID_INPUT');
    }

    public function test_it_durably_creates_and_dispatches_a_card_job(): void
    {
        $queue = new RecordingGenerationQueue();
        $response = $this->create($queue, $this->userId, 'create-one', [
            'text' => ' ambition ',
            'content_type' => 'word',
            'memory_style' => 'auto',
        ]);

        self::assertSame(202, $response->getStatusCode());
        $data = $this->data($response);
        self::assertSame('queued', $data['status']);
        self::assertFalse($data['replayed']);
        self::assertFalse($data['dispatch_pending']);
        self::assertSame([$data['job_id']], $queue->publishedJobIds);

        $card = (array) Db::table('memory_cards')->where('id', $data['card_id'])->first();
        $job = (array) Db::table('ai_generation_jobs')->where('id', $data['job_id'])->first();
        self::assertSame('ambition', $card['source_text']);
        self::assertSame($this->userId, (int) $card['user_id']);
        self::assertSame('create-one', $job['idempotency_key']);
        self::assertNotNull($job['dispatched_at']);
    }

    public function test_same_key_and_payload_replays_without_republishing(): void
    {
        $queue = new RecordingGenerationQueue();
        $first = $this->data($this->create($queue, $this->userId, 'replay-key', ['text' => 'resilient']));
        $second = $this->data($this->create($queue, $this->userId, 'replay-key', ['text' => 'resilient']));

        self::assertSame($first['card_id'], $second['card_id']);
        self::assertSame($first['job_id'], $second['job_id']);
        self::assertTrue($second['replayed']);
        self::assertCount(1, $queue->publishedJobIds);
    }

    public function test_same_key_with_different_payload_conflicts(): void
    {
        $queue = new RecordingGenerationQueue();
        $this->create($queue, $this->userId, 'conflict-key', ['text' => 'resilient']);

        $response = $this->create($queue, $this->userId, 'conflict-key', ['text' => 'ambition']);

        $this->assertError($response, 409, 'IDEMPOTENCY_CONFLICT');
        self::assertCount(1, $queue->publishedJobIds);
    }

    public function test_queue_failure_preserves_durable_queued_records(): void
    {
        $queue = new RecordingGenerationQueue(true);
        $response = $this->create($queue, $this->userId, 'queue-down', ['text' => 'resilient']);

        self::assertSame(202, $response->getStatusCode());
        $data = $this->data($response);
        self::assertTrue($data['dispatch_pending']);
        self::assertSame(1, Db::table('memory_cards')->where('id', $data['card_id'])->count());
        $job = (array) Db::table('ai_generation_jobs')->where('id', $data['job_id'])->first();
        self::assertSame('queued', $job['status']);
        self::assertNull($job['dispatched_at']);
    }

    public function test_different_users_can_reuse_the_same_key(): void
    {
        $queue = new RecordingGenerationQueue();
        $first = $this->data($this->create($queue, $this->userId, 'shared-key', ['text' => 'ambition']));
        $second = $this->data($this->create($queue, $this->otherUserId, 'shared-key', ['text' => 'ambition']));

        self::assertNotSame($first['job_id'], $second['job_id']);
        self::assertCount(2, $queue->publishedJobIds);
    }

    private function create(
        RecordingGenerationQueue $queue,
        int $userId,
        string $key,
        array $payload,
    ): \Webman\Http\Response {
        $request = $this->jsonRequest($payload, $key);
        $request->setAuthenticatedUserId($userId);

        return (new CreateMemoryCardController(new CreateMemoryCardBusiness($queue)))($request);
    }

    private function data(\Webman\Http\Response $response): array
    {
        return json_decode($response->rawBody(), true, 512, JSON_THROW_ON_ERROR)['data'];
    }

    private function assertError(\Webman\Http\Response $response, int $status, string $code): void
    {
        self::assertSame($status, $response->getStatusCode());
        $payload = json_decode($response->rawBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($payload['success']);
        self::assertSame($code, $payload['error']['code']);
    }

    private function jsonRequest(array $payload, string $key): Request
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return new Request(
            "POST /api/memory-cards HTTP/1.1\r\n"
            . "Host: localhost\r\n"
            . "Content-Type: application/json\r\n"
            . "Idempotency-Key: {$key}\r\n"
            . 'Content-Length: ' . strlen($body) . "\r\n\r\n"
            . $body,
        );
    }

    private function createUser(string $email): int
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

final class RecordingGenerationQueue implements AiGenerationQueue
{
    /** @var list<int> */
    public array $publishedJobIds = [];

    public function __construct(private readonly bool $failPublish = false)
    {
    }

    public function publish(int $jobId): void
    {
        if ($this->failPublish) {
            throw new RuntimeException('Redis unavailable.');
        }
        $this->publishedJobIds[] = $jobId;
    }

    public function consume(string $consumer, int $blockMs = 5000): ?QueueMessageEntity
    {
        return null;
    }

    public function ack(QueueMessageEntity $message): void
    {
    }

    public function claimStale(string $consumer, int $idleMs): ?QueueMessageEntity
    {
        return null;
    }
}
