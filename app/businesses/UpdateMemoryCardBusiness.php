<?php

declare(strict_types=1);

namespace app\businesses;

use app\common\enums\BusinessCode;
use app\entities\MemoryCardMutationResultEntity;
use app\models\MemoryCard;
use app\models\MemoryCardTag;
use app\models\Tag;
use app\models\User;
use support\Db;

final class UpdateMemoryCardBusiness
{
    private const ALLOWED_FIELDS = [
        'content_version',
        'meanings',
        'mnemonic_sentence',
        'story',
        'example',
        'is_favorite',
        'tags',
    ];

    private readonly SyncVersionBusiness $syncVersions;
    private readonly TagNameBusiness $tagNames;
    private readonly MemoryCardViewBusiness $views;

    public function __construct(
        ?SyncVersionBusiness $syncVersions = null,
        ?TagNameBusiness $tagNames = null,
        ?MemoryCardViewBusiness $views = null,
    ) {
        $this->syncVersions = $syncVersions ?? new SyncVersionBusiness();
        $this->tagNames = $tagNames ?? new TagNameBusiness();
        $this->views = $views ?? new MemoryCardViewBusiness();
    }

    public function update(
        int $userId,
        int $cardId,
        int $contentVersion,
        array $changes,
    ): MemoryCardMutationResultEntity {
        $validated = $this->validate($contentVersion, $changes);
        if ($validated === null) {
            return $this->invalid();
        }

        return Db::transaction(function () use ($userId, $cardId, $contentVersion, $validated): MemoryCardMutationResultEntity {
            $user = User::query()->whereKey($userId)->lockForUpdate()->first();
            /** @var MemoryCard|null $card */
            $card = MemoryCard::query()
                ->where('id', $cardId)
                ->where('user_id', $userId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();
            if ($user === null || $card === null) {
                return MemoryCardMutationResultEntity::failure(404, BusinessCode::CardNotFound, '记忆卡不存在。');
            }
            if ((int) $card->content_version !== $contentVersion) {
                return MemoryCardMutationResultEntity::conflict($this->views->build($userId, $card));
            }

            $payload = is_array($card->card_payload) ? $card->card_payload : [];
            foreach (['meanings', 'mnemonic_sentence', 'story', 'example'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $payload[$field] = $validated[$field];
                }
            }

            $syncVersion = $this->syncVersions->nextLocked($user);
            $card->card_payload = $payload;
            if (array_key_exists('is_favorite', $validated)) {
                $card->is_favorite = $validated['is_favorite'];
            }
            $card->content_version = (int) $card->content_version + 1;
            $card->sync_version = $syncVersion;
            $card->save();

            if (array_key_exists('tags', $validated)) {
                $this->reconcileTags($userId, (int) $card->id, $validated['tags'], $syncVersion);
            }

            return MemoryCardMutationResultEntity::success($this->views->build($userId, $card->refresh()));
        });
    }

    private function validate(int $contentVersion, array $changes): ?array
    {
        if ($contentVersion < 1 || $changes === []) {
            return null;
        }
        foreach (array_keys($changes) as $field) {
            if (!in_array($field, self::ALLOWED_FIELDS, true) || $field === 'content_version') {
                return null;
            }
        }

        $validated = [];
        if (array_key_exists('meanings', $changes)) {
            if (!is_array($changes['meanings']) || $changes['meanings'] === [] || count($changes['meanings']) > 10) {
                return null;
            }
            $meanings = [];
            foreach ($changes['meanings'] as $meaning) {
                if (!is_array($meaning)) {
                    return null;
                }
                $en = trim((string) ($meaning['en'] ?? ''));
                $zh = trim((string) ($meaning['zh'] ?? ''));
                if ($en === '' || $zh === '' || mb_strlen($en, 'UTF-8') > 500 || mb_strlen($zh, 'UTF-8') > 500) {
                    return null;
                }
                $meanings[] = ['en' => $en, 'zh' => $zh];
            }
            $validated['meanings'] = $meanings;
        }
        foreach (['mnemonic_sentence' => 500, 'story' => 2000] as $field => $limit) {
            if (array_key_exists($field, $changes)) {
                if (!is_string($changes[$field])) {
                    return null;
                }
                $value = trim($changes[$field]);
                if ($value === '' || mb_strlen($value, 'UTF-8') > $limit) {
                    return null;
                }
                $validated[$field] = $value;
            }
        }
        if (array_key_exists('example', $changes)) {
            if (!is_array($changes['example'])) {
                return null;
            }
            $en = trim((string) ($changes['example']['en'] ?? ''));
            $zh = trim((string) ($changes['example']['zh'] ?? ''));
            if ($en === '' || $zh === '' || mb_strlen($en, 'UTF-8') > 1000 || mb_strlen($zh, 'UTF-8') > 1000) {
                return null;
            }
            $validated['example'] = ['en' => $en, 'zh' => $zh];
        }
        if (array_key_exists('is_favorite', $changes)) {
            if (!is_bool($changes['is_favorite'])) {
                return null;
            }
            $validated['is_favorite'] = $changes['is_favorite'];
        }
        if (array_key_exists('tags', $changes)) {
            if (!is_array($changes['tags'])) {
                return null;
            }
            $tags = $this->tagNames->normalizeList($changes['tags']);
            if ($tags === null) {
                return null;
            }
            $validated['tags'] = $tags;
        }

        return $validated === [] ? null : $validated;
    }

    private function reconcileTags(int $userId, int $cardId, array $desired, int $syncVersion): void
    {
        $desiredTagIds = [];
        $now = date('Y-m-d H:i:s');
        foreach ($desired as $tagValue) {
            $tag = Tag::forUser($userId)
                ->where('normalized_name', $tagValue['normalized_name'])
                ->lockForUpdate()
                ->first();
            if ($tag === null) {
                $tag = Tag::query()->create([
                    'user_id' => $userId,
                    'name' => $tagValue['name'],
                    'normalized_name' => $tagValue['normalized_name'],
                    'sync_version' => $syncVersion,
                ]);
            } elseif ($tag->deleted_at !== null) {
                $tag->forceFill([
                    'name' => $tagValue['name'],
                    'sync_version' => $syncVersion,
                    'deleted_at' => null,
                ])->save();
            }
            $desiredTagIds[] = (int) $tag->id;
        }

        $relations = MemoryCardTag::forUser($userId)
            ->where('memory_card_id', $cardId)
            ->lockForUpdate()
            ->get()
            ->keyBy('tag_id');
        foreach ($desiredTagIds as $tagId) {
            /** @var MemoryCardTag|null $relation */
            $relation = $relations->get($tagId);
            if ($relation === null) {
                MemoryCardTag::query()->create([
                    'user_id' => $userId,
                    'memory_card_id' => $cardId,
                    'tag_id' => $tagId,
                    'sync_version' => $syncVersion,
                ]);
            } elseif ($relation->deleted_at !== null) {
                $relation->forceFill(['sync_version' => $syncVersion, 'deleted_at' => null])->save();
            }
        }

        MemoryCardTag::forUser($userId)
            ->where('memory_card_id', $cardId)
            ->whereNull('deleted_at')
            ->when($desiredTagIds !== [], static fn ($query) => $query->whereNotIn('tag_id', $desiredTagIds))
            ->update(['sync_version' => $syncVersion, 'deleted_at' => $now]);
    }

    private function invalid(): MemoryCardMutationResultEntity
    {
        return MemoryCardMutationResultEntity::failure(422, BusinessCode::InvalidInput, '请求参数不正确。');
    }
}
