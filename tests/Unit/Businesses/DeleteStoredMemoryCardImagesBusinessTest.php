<?php

declare(strict_types=1);

namespace Tests\Unit\Businesses;

use app\businesses\DeleteStoredMemoryCardImagesBusiness;
use app\common\enums\BusinessCode;
use app\common\exceptions\ImageImportException;
use app\entities\ProcessedImageSetEntity;
use app\entities\StoredImageSetEntity;
use app\services\contracts\ImageStorage;
use PHPUnit\Framework\TestCase;
use support\Db;

final class DeleteStoredMemoryCardImagesBusinessTest extends TestCase
{
    private int $userId;
    private int $otherUserId;
    private array $cardIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = $this->user('codex-image-cleanup@example.com');
        $this->otherUserId = $this->user('codex-image-cleanup-other@example.com');
    }

    protected function tearDown(): void
    {
        Db::table('memory_card_images')->whereIn('user_id', [$this->userId, $this->otherUserId])->delete();
        Db::table('memory_cards')->whereIn('user_id', [$this->userId, $this->otherUserId])->delete();
        Db::table('users')->whereIn('id', [$this->userId, $this->otherUserId])->delete();
        parent::tearDown();
    }

    public function test_delete_card_is_owned_and_removes_all_keys_before_metadata(): void
    {
        $cardId = $this->image($this->userId, 'a');
        $storage = new CleanupStorage();
        $business = new DeleteStoredMemoryCardImagesBusiness($storage);

        $business->deleteCard($this->otherUserId, $cardId);
        self::assertSame([], $storage->deletedBatches);
        self::assertSame(1, Db::table('memory_card_images')->where('memory_card_id', $cardId)->count());

        $business->deleteCard($this->userId, $cardId);
        self::assertCount(1, $storage->deletedBatches);
        self::assertCount(3, $storage->deletedBatches[0]);
        self::assertSame(0, Db::table('memory_card_images')->where('memory_card_id', $cardId)->count());
    }

    public function test_delete_user_processes_all_owned_cards(): void
    {
        $this->image($this->userId, 'a');
        $this->image($this->userId, 'b');
        $this->image($this->otherUserId, 'c');
        $storage = new CleanupStorage();

        (new DeleteStoredMemoryCardImagesBusiness($storage))->deleteUser($this->userId);

        self::assertCount(2, $storage->deletedBatches);
        self::assertSame(0, Db::table('memory_card_images')->where('user_id', $this->userId)->count());
        self::assertSame(1, Db::table('memory_card_images')->where('user_id', $this->otherUserId)->count());
    }

    public function test_storage_failure_keeps_metadata_for_retry(): void
    {
        $cardId = $this->image($this->userId, 'a');
        $storage = new CleanupStorage(true);

        try {
            (new DeleteStoredMemoryCardImagesBusiness($storage))->deleteCard($this->userId, $cardId);
            self::fail('Expected cleanup failure.');
        } catch (ImageImportException $exception) {
            self::assertSame(BusinessCode::ImageStorageFailed, $exception->businessCode());
        }

        self::assertSame(1, Db::table('memory_card_images')->where('memory_card_id', $cardId)->count());
    }

    private function image(int $userId, string $suffix): int
    {
        $now = date('Y-m-d H:i:s');
        $cardId = (int) Db::table('memory_cards')->insertGetId([
            'user_id' => $userId,
            'source_text' => 'word-' . $suffix,
            'normalized_text' => 'word-' . $suffix,
            'content_type' => 'word',
            'memory_style' => 'auto',
            'card_payload' => '{}',
            'review_stage' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $prefix = "memory-cards/{$userId}/{$cardId}/" . str_repeat($suffix, 32);
        Db::table('memory_card_images')->insert([
            'user_id' => $userId,
            'memory_card_id' => $cardId,
            'storage_driver' => 'local',
            'original_key' => $prefix . '-original.png',
            'large_key' => $prefix . '-large.webp',
            'small_key' => $prefix . '-small.webp',
            'original_sha256' => str_repeat('1', 64),
            'large_sha256' => str_repeat('2', 64),
            'small_sha256' => str_repeat('3', 64),
            'original_mime' => 'image/png',
            'original_width' => 640,
            'original_height' => 480,
            'original_bytes' => 100,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $cardId;
    }

    private function user(string $email): int
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

final class CleanupStorage implements ImageStorage
{
    public array $deletedBatches = [];
    public function __construct(private readonly bool $fail = false) {}
    public function store(int $userId, int $cardId, ProcessedImageSetEntity $images): StoredImageSetEntity { throw new \LogicException(); }
    public function deleteKeys(array $keys): void
    {
        if ($this->fail) {
            throw new ImageImportException(BusinessCode::ImageStorageFailed, 'image_storage', '图片删除失败。');
        }
        $this->deletedBatches[] = $keys;
    }
}
