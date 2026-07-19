<?php

declare(strict_types=1);

namespace Tests\Feature;

use app\businesses\DeleteMemoryCardBusiness;
use app\businesses\DeleteStoredMemoryCardImagesBusiness;
use app\businesses\GetMemoryCardBusiness;
use app\businesses\RetryMemoryCardBusiness;
use app\common\enums\BusinessCode;
use app\common\exceptions\ImageImportException;
use app\controllers\DeleteMemoryCardController;
use app\entities\ProcessedImageSetEntity;
use app\entities\QueueMessageEntity;
use app\entities\StoredImageSetEntity;
use app\services\contracts\AiGenerationQueue;
use app\services\contracts\ImageStorage;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

final class DeleteMemoryCardControllerTest extends TestCase
{
    private int $userId;
    private int $otherUserId;
    private int $cardId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = $this->user('delete-card@example.com');
        $this->otherUserId = $this->user('delete-card-other@example.com');
        $this->cardId = $this->card($this->userId);
    }

    protected function tearDown(): void
    {
        $userIds = [$this->userId, $this->otherUserId];
        Db::table('review_events')->whereIn('user_id', $userIds)->delete();
        Db::table('memory_card_images')->whereIn('user_id', $userIds)->delete();
        Db::table('ai_generation_jobs')->whereIn('user_id', $userIds)->delete();
        Db::table('memory_card_tags')->whereIn('user_id', $userIds)->delete();
        Db::table('tags')->whereIn('user_id', $userIds)->delete();
        Db::table('memory_cards')->whereIn('user_id', $userIds)->delete();
        Db::table('users')->whereIn('id', $userIds)->delete();
        parent::tearDown();
    }

    public function test_it_rejects_stale_foreign_and_missing_cards_without_mutation(): void
    {
        $foreignCardId = $this->card($this->otherUserId);

        $stale = $this->delete($this->userId, $this->cardId, 9, new DeleteCardStorage());
        self::assertSame(409, $stale->getStatusCode());
        self::assertSame('CARD_VERSION_CONFLICT', $this->payload($stale)['error']['code']);
        self::assertNull(Db::table('memory_cards')->where('id', $this->cardId)->value('deleted_at'));

        foreach ([$foreignCardId, 999999999] as $cardId) {
            $response = $this->delete($this->userId, $cardId, 1, new DeleteCardStorage());
            self::assertSame(404, $response->getStatusCode());
            self::assertSame('CARD_NOT_FOUND', $this->payload($response)['error']['code']);
        }
    }

    public function test_it_commits_one_sanitized_tombstone_and_cleans_related_private_data(): void
    {
        $this->relatedPrivateData($this->cardId);
        $storage = new DeleteCardStorage();

        $response = $this->delete($this->userId, $this->cardId, 1, $storage);

        self::assertSame(200, $response->getStatusCode());
        $data = $this->payload($response)['data'];
        self::assertTrue($data['deleted']);
        self::assertSame($this->cardId, $data['card_id']);
        self::assertSame(2, $data['content_version']);
        $card = (array) Db::table('memory_cards')->where('id', $this->cardId)->first();
        self::assertSame('', $card['source_text']);
        self::assertSame('', $card['normalized_text']);
        self::assertSame([], json_decode($card['card_payload'], true, 512, JSON_THROW_ON_ERROR));
        self::assertNull($card['image_url']);
        self::assertNull($card['image_storage_key']);
        self::assertNull($card['next_review_at']);
        self::assertSame(0, (int) $card['review_stage']);
        self::assertSame(0, (int) $card['is_favorite']);
        self::assertNotNull($card['deleted_at']);
        self::assertSame(2, (int) $card['content_version']);
        self::assertSame((int) $card['sync_version'], (int) Db::table('users')->where('id', $this->userId)->value('sync_version'));

        $job = (array) Db::table('ai_generation_jobs')->where('memory_card_id', $this->cardId)->first();
        self::assertSame([], json_decode($job['request_payload'], true, 512, JSON_THROW_ON_ERROR));
        self::assertNull($job['provider_payload']);
        self::assertNull($job['pending_card_payload']);
        self::assertNull($job['error_code']);
        self::assertNull($job['error_message']);
        self::assertNull($job['failure_type']);
        self::assertSame(0, Db::table('review_events')->where('memory_card_id', $this->cardId)->count());
        self::assertSame($card['sync_version'], Db::table('memory_card_tags')->where('memory_card_id', $this->cardId)->value('sync_version'));
        self::assertNotNull(Db::table('memory_card_tags')->where('memory_card_id', $this->cardId)->value('deleted_at'));
        self::assertSame(0, Db::table('memory_card_images')->where('memory_card_id', $this->cardId)->count());
        self::assertCount(1, $storage->deletedBatches);
    }

    public function test_storage_failure_keeps_deleted_card_and_image_manifest_then_retry_resumes_without_new_version(): void
    {
        $this->relatedPrivateData($this->cardId);
        $failure = $this->delete($this->userId, $this->cardId, 1, new DeleteCardStorage(true));

        self::assertSame(503, $failure->getStatusCode());
        self::assertSame('IMAGE_STORAGE_FAILED', $this->payload($failure)['error']['code']);
        $deleted = (array) Db::table('memory_cards')->where('id', $this->cardId)->first();
        self::assertNotNull($deleted['deleted_at']);
        self::assertSame(1, Db::table('memory_card_images')->where('memory_card_id', $this->cardId)->count());
        $version = (int) $deleted['sync_version'];

        $storage = new DeleteCardStorage();
        $retry = $this->delete($this->userId, $this->cardId, 1, $storage);

        self::assertSame(200, $retry->getStatusCode());
        self::assertSame($version, $this->payload($retry)['data']['sync_version']);
        self::assertSame($version, (int) Db::table('users')->where('id', $this->userId)->value('sync_version'));
        self::assertSame(0, Db::table('memory_card_images')->where('memory_card_id', $this->cardId)->count());
        self::assertCount(1, $storage->deletedBatches);
    }

    public function test_deleted_card_is_hidden_from_detail_and_retry(): void
    {
        $this->delete($this->userId, $this->cardId, 1, new DeleteCardStorage());

        $detail = (new GetMemoryCardBusiness())->get($this->userId, $this->cardId);
        self::assertSame(404, $detail->httpStatus());
        self::assertSame('CARD_NOT_FOUND', $detail->toResponseArray()['error']['code']);
        $retry = (new RetryMemoryCardBusiness(new DeleteCardQueue()))->retry($this->userId, $this->cardId, 'delete-retry-key');
        self::assertSame(404, $retry->httpStatus());
        self::assertSame('CARD_NOT_FOUND', $retry->toResponseArray()['error']['code']);
    }

    private function delete(int $userId, int $cardId, int $contentVersion, DeleteCardStorage $storage): \Webman\Http\Response
    {
        $body = json_encode(['content_version' => $contentVersion], JSON_THROW_ON_ERROR);
        $request = new Request(
            "DELETE /api/memory-cards/{$cardId} HTTP/1.1\r\nHost: localhost\r\nContent-Type: application/json\r\n"
            . 'Content-Length: ' . strlen($body) . "\r\n\r\n{$body}",
        );
        $request->setAuthenticatedUserId($userId);
        $business = new DeleteMemoryCardBusiness(new DeleteStoredMemoryCardImagesBusiness($storage));

        return (new DeleteMemoryCardController($business))($request, $cardId);
    }

    private function relatedPrivateData(int $cardId): void
    {
        $now = date('Y-m-d H:i:s');
        $tagId = (int) Db::table('tags')->insertGetId([
            'user_id' => $this->userId,
            'name' => 'Private',
            'normalized_name' => 'private',
            'sync_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        Db::table('memory_card_tags')->insert([
            'user_id' => $this->userId,
            'memory_card_id' => $cardId,
            'tag_id' => $tagId,
            'sync_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        Db::table('review_events')->insert([
            'user_id' => $this->userId,
            'memory_card_id' => $cardId,
            'idempotency_key' => 'delete-review-' . $cardId,
            'request_hash' => str_repeat('b', 64),
            'mode' => 'zh_to_en',
            'answer_text' => 'ambition',
            'normalized_answer' => 'ambition',
            'is_correct' => 1,
            'difficulty' => 'good',
            'rating' => 'good',
            'effective_rating' => 'good',
            'previous_stage' => 0,
            'next_stage' => 1,
            'next_due_at' => $now,
            'response_ms' => 1000,
            'xp_awarded' => 10,
            'response_payload' => json_encode(['success' => true], JSON_THROW_ON_ERROR),
            'reviewed_at' => $now,
            'created_at' => $now,
        ]);
        Db::table('ai_generation_jobs')->insert([
            'user_id' => $this->userId,
            'memory_card_id' => $cardId,
            'idempotency_key' => 'delete-job-' . $cardId,
            'request_hash' => str_repeat('a', 64),
            'operation' => 'create',
            'request_payload' => json_encode(['text' => 'ambition'], JSON_THROW_ON_ERROR),
            'provider_payload' => json_encode(['temporary_url' => 'https://temporary.invalid/image'], JSON_THROW_ON_ERROR),
            'pending_card_payload' => json_encode(['story' => 'private'], JSON_THROW_ON_ERROR),
            'status' => 'failed',
            'error_code' => 'PRIVATE_ERROR',
            'error_message' => 'private provider error',
            'failure_type' => 'provider',
            'attempts' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $prefix = "memory-cards/{$this->userId}/{$cardId}/fixture";
        Db::table('memory_card_images')->insert([
            'user_id' => $this->userId,
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
    }

    private function card(int $userId): int
    {
        $now = date('Y-m-d H:i:s');
        return (int) Db::table('memory_cards')->insertGetId([
            'user_id' => $userId,
            'sync_version' => 1,
            'content_version' => 1,
            'source_text' => 'ambition',
            'normalized_text' => 'ambition',
            'content_type' => 'word',
            'memory_style' => 'auto',
            'is_favorite' => 1,
            'card_payload' => json_encode(['word' => 'ambition', 'story' => 'private story'], JSON_THROW_ON_ERROR),
            'image_url' => 'http://e.test/storage/private.webp',
            'image_storage_key' => 'memory-cards/private.webp',
            'next_review_at' => $now,
            'review_stage' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function user(string $email): int
    {
        return (int) Db::table('users')->insertGetId([
            'email' => $email,
            'password_hash' => password_hash('SecurePass123!', PASSWORD_DEFAULT),
            'status' => 'active',
            'sync_version' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function payload(\Webman\Http\Response $response): array
    {
        return json_decode($response->rawBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}

final class DeleteCardStorage implements ImageStorage
{
    public array $deletedBatches = [];

    public function __construct(private readonly bool $fail = false) {}

    public function store(int $userId, int $cardId, ProcessedImageSetEntity $images): StoredImageSetEntity
    {
        throw new \LogicException('Not used.');
    }

    public function deleteKeys(array $keys): void
    {
        if ($this->fail) {
            throw new ImageImportException(BusinessCode::ImageStorageFailed, 'image_storage', '图片删除失败。');
        }
        $this->deletedBatches[] = $keys;
    }
}

final class DeleteCardQueue implements AiGenerationQueue
{
    public function publish(int $jobId): void { throw new \LogicException('Deleted cards must not enqueue.'); }
    public function consume(string $consumer, int $blockMs = 5000): ?QueueMessageEntity { return null; }
    public function ack(QueueMessageEntity $message): void {}
    public function claimStale(string $consumer, int $idleMs): ?QueueMessageEntity { return null; }
}
