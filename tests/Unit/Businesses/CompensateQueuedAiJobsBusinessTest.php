<?php

declare(strict_types=1);

namespace Tests\Unit\Businesses;

use app\businesses\CompensateQueuedAiJobsBusiness;
use app\entities\QueueMessageEntity;
use app\services\contracts\AiGenerationQueue;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use support\Db;

final class CompensateQueuedAiJobsBusinessTest extends TestCase
{
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = (int) Db::table('users')->insertGetId([
            'email' => 'codex-compensation@example.com',
            'password_hash' => password_hash('SecurePass123!', PASSWORD_DEFAULT),
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    protected function tearDown(): void
    {
        Db::table('ai_generation_jobs')->where('user_id', $this->userId)->delete();
        Db::table('memory_cards')->where('user_id', $this->userId)->delete();
        Db::table('users')->where('id', $this->userId)->delete();
        parent::tearDown();
    }

    public function test_it_only_republishes_old_queued_dispatch_gaps(): void
    {
        $oldNull = $this->createJob('old-null', 'queued', '-10 minutes', null);
        $oldStale = $this->createJob('old-stale', 'queued', '-10 minutes', '-10 minutes');
        $recent = $this->createJob('recent', 'queued', '-10 seconds', null);
        $failed = $this->createJob('failed', 'failed', '-10 minutes', null);
        $active = $this->createJob('active', 'generating_text', '-10 minutes', null);
        $completed = $this->createJob('completed', 'completed', '-10 minutes', null);
        $queue = new CompensationQueue();

        $count = (new CompensateQueuedAiJobsBusiness($queue, 120))->dispatch(100);

        self::assertSame(2, $count);
        self::assertSame([$oldNull, $oldStale], $queue->publishedJobIds);
        self::assertNotNull(Db::table('ai_generation_jobs')->where('id', $oldNull)->value('dispatched_at'));
        self::assertNotNull(Db::table('ai_generation_jobs')->where('id', $oldStale)->value('dispatched_at'));
        foreach ([$recent, $failed, $active, $completed] as $jobId) {
            self::assertNull(Db::table('ai_generation_jobs')->where('id', $jobId)->value('dispatched_at'));
        }
    }

    public function test_queue_errors_remain_eligible_and_limit_is_bounded(): void
    {
        $first = $this->createJob('first', 'queued', '-10 minutes', null);
        $second = $this->createJob('second', 'queued', '-10 minutes', null);
        $queue = new CompensationQueue([$first]);
        $business = new CompensateQueuedAiJobsBusiness($queue, 120);

        self::assertSame(0, $business->dispatch(1));
        self::assertSame([$first], $queue->publishedJobIds);
        self::assertNull(Db::table('ai_generation_jobs')->where('id', $first)->value('dispatched_at'));
        self::assertNull(Db::table('ai_generation_jobs')->where('id', $second)->value('dispatched_at'));
    }

    private function createJob(string $key, string $status, string $created, ?string $dispatched): int
    {
        $createdAt = date('Y-m-d H:i:s', strtotime($created));
        $cardId = (int) Db::table('memory_cards')->insertGetId([
            'user_id' => $this->userId,
            'source_text' => $key,
            'normalized_text' => $key,
            'content_type' => 'word',
            'memory_style' => 'auto',
            'card_payload' => '{}',
            'review_stage' => 0,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
        $request = ['text' => $key, 'content_type' => 'word', 'memory_style' => 'auto'];

        return (int) Db::table('ai_generation_jobs')->insertGetId([
            'user_id' => $this->userId,
            'memory_card_id' => $cardId,
            'idempotency_key' => $key,
            'request_hash' => hash('sha256', json_encode($request, JSON_THROW_ON_ERROR)),
            'request_payload' => json_encode($request, JSON_THROW_ON_ERROR),
            'status' => $status,
            'attempts' => 0,
            'dispatched_at' => $dispatched === null ? null : date('Y-m-d H:i:s', strtotime($dispatched)),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}

final class CompensationQueue implements AiGenerationQueue
{
    public array $publishedJobIds = [];

    public function __construct(private array $failIds = []) {}

    public function publish(int $jobId): void
    {
        $this->publishedJobIds[] = $jobId;
        if (in_array($jobId, $this->failIds, true)) {
            throw new RuntimeException('queue down');
        }
    }

    public function consume(string $consumer, int $blockMs = 5000): ?QueueMessageEntity { return null; }
    public function ack(QueueMessageEntity $message): void {}
    public function claimStale(string $consumer, int $idleMs): ?QueueMessageEntity { return null; }
}
