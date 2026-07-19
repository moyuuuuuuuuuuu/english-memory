<?php

declare(strict_types=1);

namespace app\businesses;

use app\common\enums\AiGenerationStatus;
use app\common\enums\BusinessCode;
use app\common\exceptions\ImageImportException;
use app\entities\DownloadedImageEntity;
use app\entities\ProcessedImageSetEntity;
use app\entities\StoredImageSetEntity;
use app\models\AiGenerationJob;
use app\models\MemoryCard;
use app\models\MemoryCardImage;
use app\models\User;
use app\services\contracts\ImageProcessor;
use app\services\contracts\ImageStorage;
use app\services\contracts\RemoteImageDownloader;
use support\Db;
use Throwable;

final class ImportMemoryCardImageBusiness
{
    public function __construct(
        private readonly RemoteImageDownloader $downloader,
        private readonly ImageProcessor $processor,
        private readonly ImageStorage $storage,
        private readonly SyncVersionBusiness $syncVersions = new SyncVersionBusiness(),
    ) {
    }

    public function import(
        int $userId,
        int $cardId,
        int $jobId,
        string $sourceUrl,
        ?array $replacementCardPayload = null,
    ): void
    {
        if (!$this->isImportable($userId, $cardId, $jobId)) {
            throw $this->storageFailure();
        }

        $downloaded = null;
        $processed = null;
        $stored = null;
        $committed = false;
        try {
            $downloaded = $this->downloader->download($sourceUrl);
            $processed = $this->processor->process($downloaded);
            $stored = $this->storage->store($userId, $cardId, $processed);

            $oldKeys = Db::transaction(function () use ($userId, $cardId, $jobId, $stored, $replacementCardPayload): array {
                /** @var User|null $user */
                $user = User::query()->whereKey($userId)->lockForUpdate()->first();
                /** @var MemoryCard|null $card */
                $card = MemoryCard::query()
                    ->where('id', $cardId)
                    ->where('user_id', $userId)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();
                /** @var AiGenerationJob|null $job */
                $job = AiGenerationJob::query()
                    ->where('id', $jobId)
                    ->where('user_id', $userId)
                    ->where('memory_card_id', $cardId)
                    ->lockForUpdate()
                    ->first();
                if ($user === null || $card === null || $job === null || $job->status !== AiGenerationStatus::GeneratingImage->value) {
                    throw $this->storageFailure();
                }
                if ($replacementCardPayload !== null && (string) $job->operation !== 'regenerate') {
                    throw $this->storageFailure();
                }

                $existing = MemoryCardImage::query()
                    ->where('user_id', $userId)
                    ->where('memory_card_id', $cardId)
                    ->lockForUpdate()
                    ->first();
                $oldKeys = $existing === null ? [] : [
                    (string) $existing->original_key,
                    (string) $existing->large_key,
                    (string) $existing->small_key,
                ];
                $values = $this->metadata($userId, $cardId, $stored);
                $existing === null
                    ? MemoryCardImage::query()->create($values)
                    : $existing->forceFill($values)->save();

                $cardValues = [
                    'sync_version' => $this->syncVersions->nextLocked($user),
                    'image_url' => $stored->largeUrl(),
                    'image_storage_key' => $stored->largeKey(),
                ];
                if ($replacementCardPayload !== null) {
                    $cardValues['normalized_text'] = (string) ($replacementCardPayload['normalized_text'] ?? $replacementCardPayload['word'] ?? '');
                    $cardValues['card_payload'] = $replacementCardPayload;
                    $cardValues['content_version'] = (int) $card->content_version + 1;
                }
                $card->forceFill($cardValues)->save();
                $job->forceFill([
                    'status' => AiGenerationStatus::Completed->value,
                    'error_code' => null,
                    'error_message' => null,
                    'failure_type' => null,
                    'completed_at' => date('Y-m-d H:i:s'),
                    'pending_card_payload' => null,
                ])->save();

                return $oldKeys;
            });
            $committed = true;

            if ($oldKeys !== []) {
                $this->storage->deleteKeys($oldKeys);
            }
        } catch (Throwable $throwable) {
            if (!$committed && $stored instanceof StoredImageSetEntity) {
                try {
                    $this->storage->deleteKeys($stored->keys());
                } catch (Throwable) {
                    // Preserve the original persistence/import failure.
                }
            }
            throw $throwable;
        } finally {
            $this->deleteTemporaryFiles($downloaded, $processed);
        }
    }

    private function isImportable(int $userId, int $cardId, int $jobId): bool
    {
        return MemoryCard::query()->where('id', $cardId)->where('user_id', $userId)->whereNull('deleted_at')->exists()
            && AiGenerationJob::query()
                ->where('id', $jobId)
                ->where('user_id', $userId)
                ->where('memory_card_id', $cardId)
                ->where('status', AiGenerationStatus::GeneratingImage->value)
                ->exists();
    }

    private function metadata(int $userId, int $cardId, StoredImageSetEntity $stored): array
    {
        return [
            'user_id' => $userId,
            'memory_card_id' => $cardId,
            'storage_driver' => $stored->driver(),
            'original_key' => $stored->originalKey(),
            'large_key' => $stored->largeKey(),
            'small_key' => $stored->smallKey(),
            'original_sha256' => $stored->originalSha256(),
            'large_sha256' => $stored->largeSha256(),
            'small_sha256' => $stored->smallSha256(),
            'original_mime' => $stored->originalMime(),
            'original_width' => $stored->originalWidth(),
            'original_height' => $stored->originalHeight(),
            'original_bytes' => $stored->originalBytes(),
        ];
    }

    private function deleteTemporaryFiles(
        ?DownloadedImageEntity $downloaded,
        ?ProcessedImageSetEntity $processed,
    ): void {
        $paths = $downloaded === null ? [] : [$downloaded->path()];
        if ($processed !== null) {
            foreach ($processed->all() as $artifact) {
                $paths[] = $artifact->path();
            }
        }
        foreach (array_unique($paths) as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function storageFailure(): ImageImportException
    {
        return new ImageImportException(
            BusinessCode::ImageStorageFailed,
            'image_storage',
            '图片保存失败，请手动重试。',
        );
    }
}
