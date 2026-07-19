<?php

declare(strict_types=1);

namespace app\businesses;

use app\common\enums\BusinessCode;
use app\entities\TagResultEntity;
use app\models\MemoryCardTag;
use app\models\Tag;
use app\models\User;
use support\Db;

final class DeleteTagBusiness
{
    private readonly SyncVersionBusiness $syncVersions;

    public function __construct(?SyncVersionBusiness $syncVersions = null)
    {
        $this->syncVersions = $syncVersions ?? new SyncVersionBusiness();
    }

    public function delete(int $userId, int $tagId, int $currentVersion): TagResultEntity
    {
        return Db::transaction(function () use ($userId, $tagId, $currentVersion): TagResultEntity {
            $user = User::query()->whereKey($userId)->lockForUpdate()->first();
            $tag = Tag::forUser($userId)->where('id', $tagId)->lockForUpdate()->first();
            if ($user === null || $tag === null) {
                return TagResultEntity::failure(404, BusinessCode::TagNotFound, '标签不存在。');
            }
            if ($tag->deleted_at !== null) {
                return TagResultEntity::success([
                    'deleted' => true,
                    'tag_id' => (int) $tag->id,
                    'sync_version' => (int) $tag->sync_version,
                ]);
            }
            if ($currentVersion < 1 || (int) $tag->sync_version !== $currentVersion) {
                return TagResultEntity::failure(409, BusinessCode::TagVersionConflict, '标签已更新，请刷新后重试。');
            }
            $version = $this->syncVersions->nextLocked($user);
            $now = date('Y-m-d H:i:s');
            $tag->forceFill(['sync_version' => $version, 'deleted_at' => $now])->save();
            MemoryCardTag::forUser($userId)
                ->where('tag_id', $tagId)
                ->whereNull('deleted_at')
                ->update(['sync_version' => $version, 'deleted_at' => $now]);

            return TagResultEntity::success([
                'deleted' => true,
                'tag_id' => (int) $tag->id,
                'sync_version' => $version,
            ]);
        });
    }
}
