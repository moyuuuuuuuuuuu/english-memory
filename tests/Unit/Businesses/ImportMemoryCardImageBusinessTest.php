<?php

declare(strict_types=1);

namespace Tests\Unit\Businesses;

use app\businesses\ImportMemoryCardImageBusiness;
use app\entities\DownloadedImageEntity;
use app\entities\ImageArtifactEntity;
use app\entities\ProcessedImageSetEntity;
use app\entities\StoredImageSetEntity;
use app\services\contracts\ImageProcessor;
use app\services\contracts\ImageStorage;
use app\services\contracts\RemoteImageDownloader;
use PHPUnit\Framework\TestCase;
use support\Db;

final class ImportMemoryCardImageBusinessTest extends TestCase
{
    private int $userId;
    private int $cardId;
    private int $jobId;
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->temporaryDirectory = sys_get_temp_dir() . '/english-memory-import-' . bin2hex(random_bytes(6));
        mkdir($this->temporaryDirectory, 0700, true);
        $now = date('Y-m-d H:i:s');
        $this->userId = (int) Db::table('users')->insertGetId([
            'email' => 'codex-image-import@example.com',
            'password_hash' => password_hash('SecurePass123!', PASSWORD_DEFAULT),
            'status' => 'active',
            'sync_version' => 3,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->cardId = (int) Db::table('memory_cards')->insertGetId([
            'user_id' => $this->userId,
            'sync_version' => 3,
            'content_version' => 1,
            'source_text' => 'ambition',
            'normalized_text' => 'ambition',
            'content_type' => 'word',
            'memory_style' => 'auto',
            'card_payload' => '{"word":"ambition"}',
            'review_stage' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $request = ['text' => 'ambition', 'content_type' => 'word', 'memory_style' => 'auto'];
        $this->jobId = (int) Db::table('ai_generation_jobs')->insertGetId([
            'user_id' => $this->userId,
            'memory_card_id' => $this->cardId,
            'idempotency_key' => 'image-import-job',
            'request_hash' => hash('sha256', json_encode($request, JSON_THROW_ON_ERROR)),
            'request_payload' => json_encode($request, JSON_THROW_ON_ERROR),
            'status' => 'generating_image',
            'attempts' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected function tearDown(): void
    {
        Db::table('ai_generation_jobs')->where('user_id', $this->userId)->delete();
        Db::table('memory_card_images')->where('user_id', $this->userId)->delete();
        Db::table('memory_cards')->where('user_id', $this->userId)->delete();
        Db::table('users')->where('id', $this->userId)->delete();
        foreach (glob($this->temporaryDirectory . '/*') ?: [] as $file) { unlink($file); }
        if (is_dir($this->temporaryDirectory)) { rmdir($this->temporaryDirectory); }
        parent::tearDown();
    }

    public function test_it_persists_owned_metadata_and_completes_the_job(): void
    {
        [$downloader, $processor, $storage, $stored] = $this->boundaries();
        $business = new ImportMemoryCardImageBusiness($downloader, $processor, $storage);

        $business->import($this->userId, $this->cardId, $this->jobId, 'https://s.coze.cn/t/source');

        $card = (array) Db::table('memory_cards')->where('id', $this->cardId)->first();
        $job = (array) Db::table('ai_generation_jobs')->where('id', $this->jobId)->first();
        $image = (array) Db::table('memory_card_images')->where('memory_card_id', $this->cardId)->first();
        self::assertSame('completed', $job['status']);
        self::assertNotNull($job['completed_at']);
        self::assertSame($stored->largeUrl(), $card['image_url']);
        self::assertSame($stored->largeKey(), $card['image_storage_key']);
        self::assertSame($stored->smallSha256(), $image['small_sha256']);
        self::assertStringNotContainsString('s.coze.cn', json_encode([$card, $job, $image], JSON_THROW_ON_ERROR));
        self::assertSame([], $storage->deletedBatches);
        self::assertSame(4, (int) $card['sync_version']);
        self::assertSame(4, (int) Db::table('users')->where('id', $this->userId)->value('sync_version'));
    }

    public function test_replacement_deletes_old_keys_only_after_new_metadata_commits(): void
    {
        Db::table('memory_card_images')->insert([
            'user_id' => $this->userId,
            'memory_card_id' => $this->cardId,
            'storage_driver' => 'local',
            'original_key' => 'memory-cards/1/1/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa-original.png',
            'large_key' => 'memory-cards/1/1/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa-large.webp',
            'small_key' => 'memory-cards/1/1/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa-small.webp',
            'original_sha256' => str_repeat('1', 64),
            'large_sha256' => str_repeat('2', 64),
            'small_sha256' => str_repeat('3', 64),
            'original_mime' => 'image/png',
            'original_width' => 640,
            'original_height' => 480,
            'original_bytes' => 100,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        [$downloader, $processor, $storage] = $this->boundaries();

        (new ImportMemoryCardImageBusiness($downloader, $processor, $storage))
            ->import($this->userId, $this->cardId, $this->jobId, 'https://s.coze.cn/t/source');

        self::assertSame([[
            'memory-cards/1/1/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa-original.png',
            'memory-cards/1/1/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa-large.webp',
            'memory-cards/1/1/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa-small.webp',
        ]], $storage->deletedBatches);
    }

    public function test_database_failure_deletes_new_stored_keys_and_keeps_job_uncompleted(): void
    {
        [$downloader, $processor, $storage, $stored] = $this->boundaries(invalidDatabaseKey: true);

        try {
            (new ImportMemoryCardImageBusiness($downloader, $processor, $storage))
                ->import($this->userId, $this->cardId, $this->jobId, 'https://s.coze.cn/t/source');
            self::fail('Expected database persistence failure.');
        } catch (\Throwable) {
            self::assertSame([$stored->keys()], $storage->deletedBatches);
        }

        self::assertSame('generating_image', Db::table('ai_generation_jobs')->where('id', $this->jobId)->value('status'));
        self::assertSame(0, Db::table('memory_card_images')->where('memory_card_id', $this->cardId)->count());
    }

    public function test_regeneration_atomically_swaps_card_image_and_versions_without_touching_learning_state(): void
    {
        Db::table('memory_cards')->where('id', $this->cardId)->update([
            'is_favorite' => 1,
            'review_stage' => 4,
            'next_review_at' => '2030-01-02 03:04:05',
            'image_url' => 'http://e.test/storage/old-large.webp',
            'image_storage_key' => 'old-large.webp',
        ]);
        Db::table('ai_generation_jobs')->where('id', $this->jobId)->update([
            'operation' => 'regenerate',
            'pending_card_payload' => json_encode(['word' => 'resilient'], JSON_THROW_ON_ERROR),
        ]);
        Db::table('memory_card_images')->insert([
            'user_id' => $this->userId,
            'memory_card_id' => $this->cardId,
            'storage_driver' => 'local',
            'original_key' => 'old-original.png',
            'large_key' => 'old-large.webp',
            'small_key' => 'old-small.webp',
            'original_sha256' => str_repeat('1', 64),
            'large_sha256' => str_repeat('2', 64),
            'small_sha256' => str_repeat('3', 64),
            'original_mime' => 'image/png',
            'original_width' => 640,
            'original_height' => 480,
            'original_bytes' => 100,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        [$downloader, $processor, $storage, $stored] = $this->boundaries();
        $replacement = [
            'success' => true,
            'word' => 'resilient',
            'normalized_text' => 'resilient',
            'meanings' => [['en' => 'able to recover', 'zh' => '坚韧的']],
            'story' => 'new story',
            'example' => ['en' => 'She is resilient.', 'zh' => '她很坚韧。'],
        ];

        (new ImportMemoryCardImageBusiness($downloader, $processor, $storage))
            ->import($this->userId, $this->cardId, $this->jobId, 'https://s.coze.cn/t/source', $replacement);

        $card = (array) Db::table('memory_cards')->where('id', $this->cardId)->first();
        $job = (array) Db::table('ai_generation_jobs')->where('id', $this->jobId)->first();
        self::assertSame('resilient', $card['normalized_text']);
        self::assertSame('resilient', json_decode($card['card_payload'], true, 512, JSON_THROW_ON_ERROR)['word']);
        self::assertSame($stored->largeUrl(), $card['image_url']);
        self::assertSame(2, (int) $card['content_version']);
        self::assertSame(4, (int) $card['sync_version']);
        self::assertSame(4, (int) Db::table('users')->where('id', $this->userId)->value('sync_version'));
        self::assertSame(1, (int) $card['is_favorite']);
        self::assertSame(4, (int) $card['review_stage']);
        self::assertSame('2030-01-02 03:04:05', $card['next_review_at']);
        self::assertSame('completed', $job['status']);
        self::assertNull($job['pending_card_payload']);
        self::assertSame([['old-original.png', 'old-large.webp', 'old-small.webp']], $storage->deletedBatches);
    }

    private function boundaries(bool $invalidDatabaseKey = false): array
    {
        $downloadPath = $this->file('download', 'download');
        $original = $this->artifact('original', 'png', 'image/png');
        $large = $this->artifact('large', 'webp', 'image/webp');
        $small = $this->artifact('small', 'webp', 'image/webp');
        $downloaded = new DownloadedImageEntity($downloadPath, 'image/png', filesize($downloadPath), 640, 480);
        $processed = new ProcessedImageSetEntity($original, $large, $small);
        $prefix = $invalidDatabaseKey ? str_repeat('x', 520) : 'memory-cards/1/1/bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $stored = new StoredImageSetEntity(
            'local',
            $prefix . '-original.png',
            $prefix . '-large.webp',
            $prefix . '-small.webp',
            $original->sha256(),
            $large->sha256(),
            $small->sha256(),
            'image/png',
            640,
            480,
            100,
            'http://e.test/storage/' . $prefix . '-large.webp',
            'http://e.test/storage/' . $prefix . '-small.webp',
        );
        $downloader = new ImportDownloader($downloaded);
        $processor = new ImportProcessor($processed);
        $storage = new ImportStorage($stored);

        return [$downloader, $processor, $storage, $stored];
    }

    private function artifact(string $role, string $extension, string $mime): ImageArtifactEntity
    {
        $path = $this->file($role, $role . '-bytes');
        return new ImageArtifactEntity($role, $path, $extension, $mime, 640, 480, filesize($path), hash_file('sha256', $path));
    }

    private function file(string $name, string $bytes): string
    {
        $path = $this->temporaryDirectory . '/' . $name . '-' . bin2hex(random_bytes(4));
        file_put_contents($path, $bytes);
        return $path;
    }
}

final class ImportDownloader implements RemoteImageDownloader
{
    public function __construct(private readonly DownloadedImageEntity $image) {}
    public function download(string $url): DownloadedImageEntity { return $this->image; }
}

final class ImportProcessor implements ImageProcessor
{
    public function __construct(private readonly ProcessedImageSetEntity $images) {}
    public function process(DownloadedImageEntity $source): ProcessedImageSetEntity { return $this->images; }
}

final class ImportStorage implements ImageStorage
{
    public array $deletedBatches = [];
    public function __construct(private readonly StoredImageSetEntity $stored) {}
    public function store(int $userId, int $cardId, ProcessedImageSetEntity $images): StoredImageSetEntity { return $this->stored; }
    public function deleteKeys(array $keys): void { $this->deletedBatches[] = $keys; }
}
