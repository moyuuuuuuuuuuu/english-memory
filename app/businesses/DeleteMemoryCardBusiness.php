<?php

declare(strict_types=1);

namespace app\businesses;

use app\common\enums\BusinessCode;
use app\common\exceptions\ImageImportException;
use app\entities\MemoryCardMutationResultEntity;
use app\models\MemoryCard;
use app\models\MemoryCardTag;
use app\models\User;
use support\Db;

final class DeleteMemoryCardBusiness
{
    private readonly SyncVersionBusiness $syncVersions;
    private readonly MemoryCardViewBusiness $views;

    public function __construct(
        private readonly DeleteStoredMemoryCardImagesBusiness $images,
        ?SyncVersionBusiness $syncVersions = null,
        ?MemoryCardViewBusiness $views = null,
    ) {
        $this->syncVersions = $syncVersions ?? new SyncVersionBusiness();
        $this->views = $views ?? new MemoryCardViewBusiness();
    }

    public function delete(int $userId, int $cardId, int $contentVersion): MemoryCardMutationResultEntity
    {
        $result = Db::transaction(function () use ($userId, $cardId, $contentVersion): MemoryCardMutationResultEntity|array {
            $user = User::query()->whereKey($userId)->lockForUpdate()->first();
            /** @var MemoryCard|null $card */
            $card = MemoryCard::query()
                ->where('user_id', $userId)
                ->where('id', $cardId)
                ->lockForUpdate()
                ->first();
            if ($user === null || $card === null) {
                return MemoryCardMutationResultEntity::failure(404, BusinessCode::CardNotFound, '记忆卡不存在。');
            }

            if ($card->deleted_at !== null) {
                return $this->tombstone($card);
            }
            if ($contentVersion < 1) {
                return MemoryCardMutationResultEntity::failure(422, BusinessCode::InvalidInput, '请求参数不正确。');
            }
            if ((int) $card->content_version !== $contentVersion) {
                return MemoryCardMutationResultEntity::conflict($this->views->build($userId, $card));
            }

            $version = $this->syncVersions->nextLocked($user);
            $now = date('Y-m-d H:i:s');
            $card->forceFill([
                'sync_version' => $version,
                'content_version' => (int) $card->content_version + 1,
                'source_text' => '',
                'normalized_text' => '',
                'card_payload' => [],
                'image_url' => null,
                'image_storage_key' => null,
                'next_review_at' => null,
                'review_stage' => 0,
                'is_favorite' => false,
                'deleted_at' => $now,
            ])->save();

            Db::table('ai_generation_jobs')
                ->where('user_id', $userId)
                ->where('memory_card_id', $cardId)
                ->update([
                    'request_payload' => json_encode([], JSON_THROW_ON_ERROR),
                    'provider_payload' => null,
                    'pending_card_payload' => null,
                    'error_code' => null,
                    'error_message' => null,
                    'failure_type' => null,
                ]);
            Db::table('review_events')
                ->where('user_id', $userId)
                ->where('memory_card_id', $cardId)
                ->delete();
            MemoryCardTag::forUser($userId)
                ->where('memory_card_id', $cardId)
                ->whereNull('deleted_at')
                ->update(['sync_version' => $version, 'deleted_at' => $now]);

            return $this->tombstone($card);
        });

        if ($result instanceof MemoryCardMutationResultEntity) {
            return $result;
        }

        try {
            $this->images->deleteCard($userId, $cardId);
        } catch (ImageImportException $exception) {
            return MemoryCardMutationResultEntity::failure(
                503,
                $exception->businessCode(),
                $exception->safeMessage(),
            );
        }

        return MemoryCardMutationResultEntity::deleted($result);
    }

    private function tombstone(MemoryCard $card): array
    {
        return [
            'deleted' => true,
            'card_id' => (int) $card->id,
            'content_version' => (int) $card->content_version,
            'sync_version' => (int) $card->sync_version,
            'deleted_at' => (string) $card->getRawOriginal('deleted_at'),
        ];
    }
}
