<?php

declare(strict_types=1);

namespace Tests\Feature;

use app\businesses\GetMemoryCardBusiness;
use app\businesses\RetryMemoryCardBusiness;
use app\controllers\GetMemoryCardController;
use app\controllers\RetryMemoryCardController;
use app\entities\QueueMessageEntity;
use app\services\contracts\AiGenerationQueue;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use support\Db;
use support\Request;

final class MemoryCardAsyncLifecycleTest extends TestCase
{
    private int $userId;
    private int $otherUserId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = $this->createUser('codex-lifecycle@example.com');
        $this->otherUserId = $this->createUser('codex-lifecycle-other@example.com');
    }

    protected function tearDown(): void
    {
        Db::table('ai_generation_jobs')->whereIn('user_id', [$this->userId, $this->otherUserId])->delete();
        Db::table('memory_cards')->whereIn('user_id', [$this->userId, $this->otherUserId])->delete();
        Db::table('users')->whereIn('id', [$this->userId, $this->otherUserId])->delete();
        parent::tearDown();
    }

    public function test_owned_detail_returns_public_queued_completed_and_failed_states(): void
    {
        [$queuedCard] = $this->createCardAndJob($this->userId, 'queued', 'queued-detail');
        [$completedCard] = $this->createCardAndJob($this->userId, 'completed', 'completed-detail', [
            'card_payload' => ['word' => 'ambition'],
            'image_url' => 'https://example.test/image.png',
        ]);
        [$failedCard] = $this->createCardAndJob($this->userId, 'failed', 'failed-detail', [], [
            'error_code' => 'AI_PROVIDER_ERROR',
            'error_message' => 'raw provider exception secret',
            'failure_type' => 'provider',
            'provider_payload' => ['reasoning_content' => 'must not leak'],
        ]);

        $queued = $this->detail($this->userId, $queuedCard);
        $completed = $this->detail($this->userId, $completedCard);
        $failed = $this->detail($this->userId, $failedCard);

        self::assertSame('queued', $queued['job']['status']);
        self::assertSame(['word' => 'ambition'], $completed['card']['card_payload']);
        self::assertSame('AI_PROVIDER_ERROR', $failed['job']['error_code']);
        self::assertArrayNotHasKey('provider_payload', $failed['job']);
        self::assertArrayNotHasKey('error_message', $failed['job']);
        self::assertStringNotContainsString('secret', json_encode($failed, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('reasoning_content', json_encode($failed, JSON_THROW_ON_ERROR));
    }

    public function test_missing_and_foreign_cards_are_hidden(): void
    {
        [$foreignCard] = $this->createCardAndJob($this->otherUserId, 'queued', 'foreign');

        $this->assertError($this->detailResponse($this->userId, $foreignCard), 404, 'CARD_NOT_FOUND');
        $this->assertError($this->detailResponse($this->userId, 999999999), 404, 'CARD_NOT_FOUND');
    }

    public function test_failed_latest_job_can_be_retried_as_a_linked_child(): void
    {
        [$cardId, $failedJobId] = $this->createCardAndJob($this->userId, 'failed', 'original-failed');
        $queue = new LifecycleQueue();

        $response = $this->retry($queue, $this->userId, $cardId, 'retry-one');

        self::assertSame(202, $response->getStatusCode());
        $data = $this->data($response);
        self::assertSame('queued', $data['status']);
        self::assertFalse($data['replayed']);
        self::assertSame([$data['job_id']], $queue->publishedJobIds);
        $child = (array) Db::table('ai_generation_jobs')->where('id', $data['job_id'])->first();
        self::assertSame($failedJobId, (int) $child['parent_job_id']);
        self::assertSame('retry-one', $child['idempotency_key']);
    }

    public function test_retry_requires_a_fresh_bounded_key_and_replays_it(): void
    {
        [$cardId] = $this->createCardAndJob($this->userId, 'failed', 'original-key');
        $queue = new LifecycleQueue();

        $this->assertError($this->retry($queue, $this->userId, $cardId, ''), 422, 'IDEMPOTENCY_KEY_REQUIRED');
        $this->assertError($this->retry($queue, $this->userId, $cardId, 'original-key'), 409, 'IDEMPOTENCY_CONFLICT');

        $first = $this->data($this->retry($queue, $this->userId, $cardId, 'retry-replay'));
        $second = $this->data($this->retry($queue, $this->userId, $cardId, 'retry-replay'));
        self::assertSame($first['job_id'], $second['job_id']);
        self::assertTrue($second['replayed']);
        self::assertCount(1, $queue->publishedJobIds);
    }

    public function test_active_and_completed_latest_jobs_cannot_be_retried(): void
    {
        [$queuedCard] = $this->createCardAndJob($this->userId, 'queued', 'queued-conflict');
        [$completedCard] = $this->createCardAndJob($this->userId, 'completed', 'completed-conflict');
        $queue = new LifecycleQueue();

        $this->assertError($this->retry($queue, $this->userId, $queuedCard, 'retry-queued'), 409, 'JOB_STATE_CONFLICT');
        $this->assertError($this->retry($queue, $this->userId, $completedCard, 'retry-completed'), 409, 'JOB_STATE_CONFLICT');
    }

    public function test_foreign_card_cannot_be_retried_and_queue_failure_is_preserved(): void
    {
        [$foreignCard] = $this->createCardAndJob($this->otherUserId, 'failed', 'foreign-failed');
        [$ownedCard] = $this->createCardAndJob($this->userId, 'failed', 'owned-failed');

        $this->assertError($this->retry(new LifecycleQueue(), $this->userId, $foreignCard, 'foreign-retry'), 404, 'CARD_NOT_FOUND');

        $response = $this->retry(new LifecycleQueue(true), $this->userId, $ownedCard, 'queue-failure');
        $data = $this->data($response);
        self::assertTrue($data['dispatch_pending']);
        $job = (array) Db::table('ai_generation_jobs')->where('id', $data['job_id'])->first();
        self::assertSame('queued', $job['status']);
        self::assertNull($job['dispatched_at']);
    }

    private function detail(int $userId, int $cardId): array
    {
        return $this->data($this->detailResponse($userId, $cardId));
    }

    private function detailResponse(int $userId, int $cardId): \Webman\Http\Response
    {
        $request = new Request("GET /api/memory-cards/{$cardId} HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->setAuthenticatedUserId($userId);

        return (new GetMemoryCardController(new GetMemoryCardBusiness()))($request, $cardId);
    }

    private function retry(LifecycleQueue $queue, int $userId, int $cardId, string $key): \Webman\Http\Response
    {
        $request = new Request(
            "POST /api/memory-cards/{$cardId}/retry HTTP/1.1\r\n"
            . "Host: localhost\r\nIdempotency-Key: {$key}\r\nContent-Length: 0\r\n\r\n",
        );
        $request->setAuthenticatedUserId($userId);

        return (new RetryMemoryCardController(new RetryMemoryCardBusiness($queue)))($request, $cardId);
    }

    private function data(\Webman\Http\Response $response): array
    {
        self::assertContains($response->getStatusCode(), [200, 202]);
        return json_decode($response->rawBody(), true, 512, JSON_THROW_ON_ERROR)['data'];
    }

    private function assertError(\Webman\Http\Response $response, int $status, string $code): void
    {
        self::assertSame($status, $response->getStatusCode());
        $payload = json_decode($response->rawBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($code, $payload['error']['code']);
    }

    private function createCardAndJob(
        int $userId,
        string $status,
        string $key,
        array $cardOverrides = [],
        array $jobOverrides = [],
    ): array {
        $now = date('Y-m-d H:i:s');
        $cardValues = array_merge([
            'user_id' => $userId,
            'source_text' => 'ambition',
            'normalized_text' => 'ambition',
            'content_type' => 'word',
            'memory_style' => 'auto',
            'card_payload' => '{}',
            'review_stage' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], $cardOverrides);
        if (is_array($cardValues['card_payload'])) {
            $cardValues['card_payload'] = json_encode($cardValues['card_payload'], JSON_THROW_ON_ERROR);
        }
        $cardId = (int) Db::table('memory_cards')->insertGetId($cardValues);
        $request = ['text' => 'ambition', 'content_type' => 'word', 'memory_style' => 'auto'];
        $jobValues = array_merge([
            'user_id' => $userId,
            'memory_card_id' => $cardId,
            'idempotency_key' => $key,
            'request_hash' => hash('sha256', json_encode($request, JSON_THROW_ON_ERROR)),
            'request_payload' => json_encode($request, JSON_THROW_ON_ERROR),
            'status' => $status,
            'attempts' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], $jobOverrides);
        if (isset($jobValues['provider_payload']) && is_array($jobValues['provider_payload'])) {
            $jobValues['provider_payload'] = json_encode($jobValues['provider_payload'], JSON_THROW_ON_ERROR);
        }
        $jobId = (int) Db::table('ai_generation_jobs')->insertGetId($jobValues);

        return [$cardId, $jobId];
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

final class LifecycleQueue implements AiGenerationQueue
{
    public array $publishedJobIds = [];

    public function __construct(private readonly bool $fail = false)
    {
    }

    public function publish(int $jobId): void
    {
        if ($this->fail) {
            throw new RuntimeException('queue down');
        }
        $this->publishedJobIds[] = $jobId;
    }

    public function consume(string $consumer, int $blockMs = 5000): ?QueueMessageEntity { return null; }
    public function ack(QueueMessageEntity $message): void {}
    public function claimStale(string $consumer, int $idleMs): ?QueueMessageEntity { return null; }
}
