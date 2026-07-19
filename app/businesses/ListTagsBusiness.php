<?php

declare(strict_types=1);

namespace app\businesses;

use app\entities\TagResultEntity;
use support\Db;

final class ListTagsBusiness
{
    public function list(int $userId): TagResultEntity
    {
        $tags = Db::table('tags as tag')
            ->where('tag.user_id', $userId)
            ->whereNull('tag.deleted_at')
            ->orderBy('tag.normalized_name')
            ->get(['tag.id', 'tag.name', 'tag.sync_version'])
            ->map(static function (object $tag) use ($userId): array {
                $count = Db::table('memory_card_tags as relation')
                    ->join('memory_cards as card', 'card.id', '=', 'relation.memory_card_id')
                    ->where('relation.user_id', $userId)
                    ->where('relation.tag_id', (int) $tag->id)
                    ->whereNull('relation.deleted_at')
                    ->whereNull('card.deleted_at')
                    ->count();
                return [
                    'id' => (int) $tag->id,
                    'name' => (string) $tag->name,
                    'sync_version' => (int) $tag->sync_version,
                    'card_count' => $count,
                ];
            })->all();

        return TagResultEntity::success(['tags' => $tags]);
    }
}
