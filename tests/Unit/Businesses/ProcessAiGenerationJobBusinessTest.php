<?php

declare(strict_types=1);

namespace Tests\Unit\Businesses;

use app\businesses\ImportMemoryCardImageBusiness;
use app\businesses\ProcessAiGenerationJobBusiness;
use app\common\enums\BusinessCode;
use app\common\exceptions\ImageImportException;
use app\entities\AiGenerationResultEntity;
use app\entities\DownloadedImageEntity;
use app\entities\ImageArtifactEntity;
use app\entities\ProcessedImageSetEntity;
use app\entities\StoredImageSetEntity;
use app\services\contracts\ImageProcessor;
use app\services\contracts\ImageStorage;
use app\services\contracts\MemoryCardGenerator;
use app\services\contracts\RemoteImageDownloader;
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
        Db::table('memory_card_images')->where('user_id', $this->userId)->delete();
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

        (new ProcessAiGenerationJobBusiness($generator, $this->imageImporter()))->process($jobId);

        $job = $this->job($jobId);
        $card = (array) Db::table('memory_cards')->where('id', $cardId)->first();
        self::assertSame('completed', $job['status']);
        self::assertSame(1, (int) $job['attempts']);
        self::assertNotNull($job['started_at']);
        self::assertNotNull($job['completed_at']);
        self::assertSame('resilient', $card['normalized_text']);
        self::assertSame('http://e.test/storage/memory-cards/test-large.webp', $card['image_url']);
        self::assertSame('memory-cards/test-large.webp', $card['image_storage_key']);
        self::assertSame('resilient', json_decode($card['card_payload'], true, 512, JSON_THROW_ON_ERROR)['word']);
        self::assertStringNotContainsString('example.test', json_encode($card, JSON_THROW_ON_ERROR));
    }

    public function test_provider_declared_failure_becomes_terminal_without_raw_error(): void
    {
        [, $jobId] = $this->createJob();
        $generator = new WorkerGenerator(AiGenerationResultEntity::failure([], 'provider secret detail'));

        (new ProcessAiGenerationJobBusiness($generator, $this->imageImporter()))->process($jobId);

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

        (new ProcessAiGenerationJobBusiness($generator, $this->imageImporter()))->process($jobId);

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
        ), $this->imageImporter()))->process($invalidCardJob);
        (new ProcessAiGenerationJobBusiness(new WorkerGenerator(
            AiGenerationResultEntity::success($this->validCard(), '', 'execute-b'),
        ), $this->imageImporter()))->process($missingImageJob);

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
        $business = new ProcessAiGenerationJobBusiness($generator, $this->imageImporter());

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

        (new ProcessAiGenerationJobBusiness($generator, $this->imageImporter()))->process(999999999);

        self::assertSame(0, $generator->calls);
    }

    public function test_image_import_failure_preserves_generated_text_and_records_safe_error(): void
    {
        [$cardId, $jobId] = $this->createJob('image-failure');
        $generator = new WorkerGenerator(AiGenerationResultEntity::success(
            $this->validCard(),
            'https://example.test/temporary-card.png',
            'execute-image-failure',
        ));
        $failure = new ImageImportException(
            BusinessCode::ImageDownloadFailed,
            'image_download',
            '图片下载失败，请手动重试。',
        );

        (new ProcessAiGenerationJobBusiness($generator, $this->imageImporter($failure)))->process($jobId);

        $job = $this->job($jobId);
        $card = (array) Db::table('memory_cards')->where('id', $cardId)->first();
        self::assertSame('failed', $job['status']);
        self::assertSame('IMAGE_DOWNLOAD_FAILED', $job['error_code']);
        self::assertSame('image_download', $job['failure_type']);
        self::assertSame('图片下载失败，请手动重试。', $job['error_message']);
        self::assertSame('resilient', $card['normalized_text']);
        self::assertSame('resilient', json_decode($card['card_payload'], true, 512, JSON_THROW_ON_ERROR)['word']);
        self::assertNull($card['image_url']);
        self::assertStringNotContainsString('example.test', (string) $job['provider_payload']);
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

    private function imageImporter(?ImageImportException $failure = null): ImportMemoryCardImageBusiness
    {
        return new ImportMemoryCardImageBusiness(
            new BusinessTestImageDownloader($failure),
            new BusinessTestImageProcessor(),
            new BusinessTestImageStorage(),
        );
    }
}

final class BusinessTestImageDownloader implements RemoteImageDownloader
{
    public function __construct(private readonly ?ImageImportException $failure) {}

    public function download(string $url): DownloadedImageEntity
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        $path = tempnam(sys_get_temp_dir(), 'business-image-');
        file_put_contents($path, 'original');
        return new DownloadedImageEntity($path, 'image/png', 8, 1024, 1024);
    }
}

final class BusinessTestImageProcessor implements ImageProcessor
{
    public function process(DownloadedImageEntity $source): ProcessedImageSetEntity
    {
        return new ProcessedImageSetEntity(
            $this->artifact('original', 'png', 'image/png', 1024),
            $this->artifact('large', 'webp', 'image/webp', 1024),
            $this->artifact('small', 'webp', 'image/webp', 512),
        );
    }

    private function artifact(string $role, string $extension, string $mime, int $size): ImageArtifactEntity
    {
        $path = tempnam(sys_get_temp_dir(), 'business-artifact-');
        file_put_contents($path, $role);
        return new ImageArtifactEntity($role, $path, $extension, $mime, $size, $size, strlen($role), hash('sha256', $role));
    }
}

final class BusinessTestImageStorage implements ImageStorage
{
    public function store(int $userId, int $cardId, ProcessedImageSetEntity $images): StoredImageSetEntity
    {
        return new StoredImageSetEntity(
            'local',
            'memory-cards/test-original.png',
            'memory-cards/test-large.webp',
            'memory-cards/test-small.webp',
            $images->original()->sha256(),
            $images->large()->sha256(),
            $images->small()->sha256(),
            'image/png',
            1024,
            1024,
            8,
            'http://e.test/storage/memory-cards/test-large.webp',
            'http://e.test/storage/memory-cards/test-small.webp',
        );
    }

    public function deleteKeys(array $keys): void {}
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
