<?php

declare(strict_types=1);

namespace Tests\Unit\Businesses;

use app\businesses\ProcessAiGenerationJobBusiness;
use app\entities\AiGenerationResultEntity;
use app\services\contracts\MemoryCardGenerator;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use support\Db;

final class ProcessAiGenerationJobBusinessTest extends TestCase
{
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = (int) Db::table('users')->insertGetId([
            'email' => 'codex-worker@example.com',
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

    public function test_successfully_processes_a_queued_job_to_completion(): void
    {
        [$cardId, $jobId] = $this->createJob();
        $generator = new WorkerGenerator(AiGenerationResultEntity::success(
            $this->validCard(),
            'https://example.test/card.png',
            'execute-safe-123',
        ));

        (new ProcessAiGenerationJobBusiness($generator))->process($jobId);

        $job = $this->job($jobId);
        $card = (array) Db::table('memory_cards')->where('id', $cardId)->first();
        self::assertSame('completed', $job['status']);
        self::assertSame(1, (int) $job['attempts']);
        self::assertNotNull($job['started_at']);
        self::assertNotNull($job['completed_at']);
        self::assertSame('resilient', $card['normalized_text']);
        self::assertSame('https://example.test/card.png', $card['image_url']);
        self::assertSame('resilient', json_decode($card['card_payload'], true, 512, JSON_THROW_ON_ERROR)['word']);
    }

    public function test_provider_declared_failure_becomes_terminal_without_raw_error(): void
    {
        [, $jobId] = $this->createJob();
        $generator = new WorkerGenerator(AiGenerationResultEntity::failure([], 'provider secret detail'));

        (new ProcessAiGenerationJobBusiness($generator))->process($jobId);

        $job = $this->job($jobId);
        self::assertSame('failed', $job['status']);
        self::assertSame('AI_PROVIDER_ERROR', $job['error_code']);
        self::assertSame('provider', $job['failure_type']);
        self::assertStringNotContainsString('secret', (string) $job['error_message']);
    }

    public function test_thrown_provider_exception_becomes_safe_terminal_failure(): void
    {
        [, $jobId] = $this->createJob();
        $generator = new WorkerGenerator(new RuntimeException('token and raw provider secret'));

        (new ProcessAiGenerationJobBusiness($generator))->process($jobId);

        $job = $this->job($jobId);
        self::assertSame('failed', $job['status']);
        self::assertSame('AI_PROVIDER_ERROR', $job['error_code']);
        self::assertStringNotContainsString('token', (string) $job['error_message']);
        self::assertNull($job['provider_payload']);
    }

    public function test_invalid_card_shape_and_missing_image_are_rejected(): void
    {
        [, $invalidCardJob] = $this->createJob('invalid-card');
        [, $missingImageJob] = $this->createJob('missing-image');

        (new ProcessAiGenerationJobBusiness(new WorkerGenerator(
            AiGenerationResultEntity::success(['word' => 'resilient'], 'https://example.test/a.png', 'execute-a'),
        )))->process($invalidCardJob);
        (new ProcessAiGenerationJobBusiness(new WorkerGenerator(
            AiGenerationResultEntity::success($this->validCard(), '', 'execute-b'),
        )))->process($missingImageJob);

        foreach ([$invalidCardJob, $missingImageJob] as $jobId) {
            $job = $this->job($jobId);
            self::assertSame('failed', $job['status']);
            self::assertSame('INVALID_AI_RESULT', $job['error_code']);
            self::assertSame('validation', $job['failure_type']);
        }
    }

    public function test_terminal_or_already_claimed_jobs_are_no_ops(): void
    {
        [, $completedJob] = $this->createJob('completed', 'completed');
        [, $activeJob] = $this->createJob('active', 'generating_text');
        $generator = new WorkerGenerator(AiGenerationResultEntity::success(
            $this->validCard(),
            'https://example.test/card.png',
            'execute-safe',
        ));
        $business = new ProcessAiGenerationJobBusiness($generator);

        $business->process($completedJob);
        $business->process($activeJob);

        self::assertSame(0, $generator->calls);
        self::assertSame('completed', $this->job($completedJob)['status']);
        self::assertSame('generating_text', $this->job($activeJob)['status']);
    }

    public function test_unknown_job_is_a_no_op(): void
    {
        $generator = new WorkerGenerator(AiGenerationResultEntity::success(
            $this->validCard(),
            'https://example.test/card.png',
            'execute-safe',
        ));

        (new ProcessAiGenerationJobBusiness($generator))->process(999999999);

        self::assertSame(0, $generator->calls);
    }

    private function createJob(string $key = 'worker-job', string $status = 'queued'): array
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
        $jobId = (int) Db::table('ai_generation_jobs')->insertGetId([
            'user_id' => $this->userId,
            'memory_card_id' => $cardId,
            'idempotency_key' => $key,
            'request_hash' => hash('sha256', json_encode($request, JSON_THROW_ON_ERROR)),
            'request_payload' => json_encode($request, JSON_THROW_ON_ERROR),
            'status' => $status,
            'attempts' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$cardId, $jobId];
    }

    private function validCard(): array
    {
        return [
            'success' => true,
            'word' => 'resilient',
            'normalized_text' => 'resilient',
            'meanings' => [['zh' => '坚韧的', 'en' => 'able to recover quickly']],
            'mnemonic_sentence' => '遇挫快速回弹',
            'story' => '竹子被风压弯后重新挺直。',
            'example' => ['en' => 'She is resilient.', 'zh' => '她很坚韧。'],
        ];
    }

    private function job(int $jobId): array
    {
        return (array) Db::table('ai_generation_jobs')->where('id', $jobId)->first();
    }
}

final class WorkerGenerator implements MemoryCardGenerator
{
    public int $calls = 0;

    public function __construct(private readonly AiGenerationResultEntity|RuntimeException $outcome)
    {
    }

    public function generate(
        string $text,
        string $contentType,
        string $nativeLanguage,
        string $memoryStyle,
    ): AiGenerationResultEntity {
        ++$this->calls;
        if ($this->outcome instanceof RuntimeException) {
            throw $this->outcome;
        }

        return $this->outcome;
    }
}
