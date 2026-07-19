<?php

declare(strict_types=1);

namespace Tests\Unit;

use app\businesses\ProcessAiGenerationJobBusiness;
use app\entities\AiGenerationResultEntity;
use app\entities\QueueMessageEntity;
use app\processes\AiGenerationWorker;
use app\services\contracts\AiGenerationQueue;
use app\services\contracts\MemoryCardGenerator;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use support\Db;

final class AiGenerationWorkerTest extends TestCase
{
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = (int) Db::table('users')->insertGetId([
            'email' => 'codex-worker-loop@example.com',
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

    public function test_it_acks_after_completed_and_expected_failed_processing(): void
    {
        $completedJob = $this->createJob('worker-complete');
        $failedJob = $this->createJob('worker-failed');
        $successQueue = new WorkerQueue(null, new QueueMessageEntity('1-0', $completedJob));
        $failureQueue = new WorkerQueue(null, new QueueMessageEntity('2-0', $failedJob));

        $success = new AiGenerationWorker(
            $successQueue,
            new ProcessAiGenerationJobBusiness(new LoopGenerator($this->validResult())),
            0,
            10,
        );
        $failure = new AiGenerationWorker(
            $failureQueue,
            new ProcessAiGenerationJobBusiness(new LoopGenerator(new RuntimeException('provider down'))),
            0,
            10,
        );

        self::assertTrue($success->runOnce('consumer-success'));
        self::assertTrue($failure->runOnce('consumer-failure'));
        self::assertSame(['1-0'], $successQueue->ackedIds);
        self::assertSame(['2-0'], $failureQueue->ackedIds);
        self::assertSame('completed', Db::table('ai_generation_jobs')->where('id', $completedJob)->value('status'));
        self::assertSame('failed', Db::table('ai_generation_jobs')->where('id', $failedJob)->value('status'));
    }

    public function test_it_does_not_ack_when_unexpected_persistence_exception_escapes(): void
    {
        $jobId = $this->createJob('worker-unexpected');
        $queue = new WorkerQueue(null, new QueueMessageEntity('3-0', $jobId));
        $invalid = $this->validResult();
        $card = $invalid->card();
        $card['not_json_encodable'] = NAN;
        $worker = new AiGenerationWorker(
            $queue,
            new ProcessAiGenerationJobBusiness(new LoopGenerator(AiGenerationResultEntity::success(
                $card,
                $invalid->imageUrl(),
                $invalid->executeId(),
            ))),
            0,
            10,
        );

        try {
            $worker->runOnce('consumer-error');
            self::fail('Expected persistence exception was not thrown.');
        } catch (\Throwable) {
            self::assertSame([], $queue->ackedIds);
        }
    }

    public function test_it_claims_stale_before_reading_new_messages(): void
    {
        $queue = new WorkerQueue(new QueueMessageEntity('4-0', 999999999), null);
        $worker = new AiGenerationWorker(
            $queue,
            new ProcessAiGenerationJobBusiness(new LoopGenerator($this->validResult())),
            1000,
            10,
        );

        self::assertTrue($worker->runOnce('replacement'));
        self::assertSame(1, $queue->claimCalls);
        self::assertSame(0, $queue->consumeCalls);
        self::assertSame(['4-0'], $queue->ackedIds);
    }

    private function createJob(string $key): int
    {
        $now = date('Y-m-d H:i:s');
        $cardId = (int) Db::table('memory_cards')->insertGetId([
            'user_id' => $this->userId,
            'source_text' => 'resilient',
            'normalized_text' => 'resilient',
            'content_type' => 'word',
            'memory_style' => 'auto',
            'card_payload' => '{}',
            'review_stage' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $request = ['text' => 'resilient', 'content_type' => 'word', 'memory_style' => 'auto'];

        return (int) Db::table('ai_generation_jobs')->insertGetId([
            'user_id' => $this->userId,
            'memory_card_id' => $cardId,
            'idempotency_key' => $key,
            'request_hash' => hash('sha256', json_encode($request, JSON_THROW_ON_ERROR)),
            'request_payload' => json_encode($request, JSON_THROW_ON_ERROR),
            'status' => 'queued',
            'attempts' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function validResult(): AiGenerationResultEntity
    {
        return AiGenerationResultEntity::success([
            'success' => true,
            'word' => 'resilient',
            'normalized_text' => 'resilient',
            'meanings' => [['zh' => '坚韧的', 'en' => 'able to recover quickly']],
            'mnemonic_sentence' => '遇挫快速回弹',
            'example' => ['en' => 'She is resilient.', 'zh' => '她很坚韧。'],
        ], 'https://example.test/card.png', 'execute-worker');
    }
}

final class WorkerQueue implements AiGenerationQueue
{
    public array $ackedIds = [];
    public int $claimCalls = 0;
    public int $consumeCalls = 0;

    public function __construct(
        private ?QueueMessageEntity $claimed,
        private ?QueueMessageEntity $consumed,
    ) {
    }

    public function publish(int $jobId): void {}

    public function consume(string $consumer, int $blockMs = 5000): ?QueueMessageEntity
    {
        ++$this->consumeCalls;
        return $this->consumed;
    }

    public function ack(QueueMessageEntity $message): void
    {
        $this->ackedIds[] = $message->messageId();
    }

    public function claimStale(string $consumer, int $idleMs): ?QueueMessageEntity
    {
        ++$this->claimCalls;
        return $this->claimed;
    }
}

final class LoopGenerator implements MemoryCardGenerator
{
    public function __construct(private AiGenerationResultEntity|RuntimeException $result) {}

    public function generate(string $text, string $contentType, string $nativeLanguage, string $memoryStyle): AiGenerationResultEntity
    {
        if ($this->result instanceof RuntimeException) {
            throw $this->result;
        }
        return $this->result;
    }
}
