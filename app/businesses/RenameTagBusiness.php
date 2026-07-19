<?php

declare(strict_types=1);

namespace app\businesses;

use app\common\enums\BusinessCode;
use app\entities\TagResultEntity;
use app\models\Tag;
use app\models\User;
use support\Db;

final class RenameTagBusiness
{
    private readonly SyncVersionBusiness $syncVersions;
    private readonly TagNameBusiness $tagNames;

    public function __construct(?SyncVersionBusiness $syncVersions = null, ?TagNameBusiness $tagNames = null)
    {
        $this->syncVersions = $syncVersions ?? new SyncVersionBusiness();
        $this->tagNames = $tagNames ?? new TagNameBusiness();
    }

    public function rename(int $userId, int $tagId, int $currentVersion, string $name): TagResultEntity
    {
        $values = $this->tagNames->normalizeList([$name]);
        if ($currentVersion < 1 || $values === null || count($values) !== 1) {
            return TagResultEntity::failure(422, BusinessCode::InvalidInput, '请求参数不正确。');
        }

        return Db::transaction(function () use ($userId, $tagId, $currentVersion, $values): TagResultEntity {
            $user = User::query()->whereKey($userId)->lockForUpdate()->first();
            $tag = Tag::forUser($userId)->where('id', $tagId)->whereNull('deleted_at')->lockForUpdate()->first();
            if ($user === null || $tag === null) {
                return TagResultEntity::failure(404, BusinessCode::TagNotFound, '标签不存在。');
            }
            if ((int) $tag->sync_version !== $currentVersion) {
                return TagResultEntity::failure(409, BusinessCode::TagVersionConflict, '标签已更新，请刷新后重试。');
            }
            $value = $values[0];
            $collision = Tag::forUser($userId)
                ->where('normalized_name', $value['normalized_name'])
                ->where('id', '!=', $tagId)
                ->whereNull('deleted_at')
                ->exists();
            if ($collision) {
                return TagResultEntity::failure(409, BusinessCode::TagNameConflict, '同名标签已存在。');
            }
            $version = $this->syncVersions->nextLocked($user);
            $tag->forceFill([
                'name' => $value['name'],
                'normalized_name' => $value['normalized_name'],
                'sync_version' => $version,
            ])->save();

            return TagResultEntity::success(['tag' => [
                'id' => (int) $tag->id,
                'name' => (string) $tag->name,
                'sync_version' => (int) $tag->sync_version,
            ]]);
        });
    }
}
