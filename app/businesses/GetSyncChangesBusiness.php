<?php

declare(strict_types=1);

namespace app\businesses;

use app\common\enums\BusinessCode;
use app\entities\SyncChangesResultEntity;
use app\models\MemoryCard;
use app\models\User;
use support\Db;

final class GetSyncChangesBusiness
{
    private readonly MemoryCardViewBusiness $views;

    public function __construct(?MemoryCardViewBusiness $views = null)
    {
        $this->views = $views ?? new MemoryCardViewBusiness();
    }

    public function get(int $userId, int $cursor, int $limit = 200): SyncChangesResultEntity
    {
        $upperBound = (int) (User::query()->whereKey($userId)->value('sync_version') ?? 0);
        if ($cursor < 0 || $cursor > $upperBound || $limit < 1 || $limit > 1000) {
            return SyncChangesResultEntity::failure(BusinessCode::InvalidCursor, '同步游标或数量不正确。');
        }

        $counts = [];
        foreach (['memory_cards', 'tags', 'memory_card_tags'] as $table) {
            $rows = Db::table($table)
                ->where('user_id', $userId)
                ->where('sync_version', '>', $cursor)
                ->where('sync_version', '<=', $upperBound)
                ->selectRaw('sync_version, COUNT(*) AS aggregate')
                ->groupBy('sync_version')
                ->get();
            foreach ($rows as $row) {
                $version = (int) $row->sync_version;
                $counts[$version] = ($counts[$version] ?? 0) + (int) $row->aggregate;
            }
        }
        ksort($counts, SORT_NUMERIC);

        $versions = [];
        $total = 0;
        foreach ($counts as $version => $count) {
            if ($versions !== [] && $total + $count > $limit) {
                break;
            }
            $versions[] = (int) $version;
            $total += $count;
        }
        if ($versions === []) {
            return SyncChangesResultEntity::success($this->emptyChanges(), $upperBound, false);
        }

        $nextCursor = max($versions);
        return SyncChangesResultEntity::success(
            [
                'cards' => $this->cards($userId, $versions),
                'tags' => $this->tags($userId, $versions),
                'card_tags' => $this->relations($userId, $versions),
            ],
            $nextCursor,
            count($versions) < count($counts),
        );
    }

    private function cards(int $userId, array $versions): array
    {
        return MemoryCard::query()->where('user_id', $userId)->whereIn('sync_version', $versions)
            ->orderBy('sync_version')->orderBy('id')->get()->map(function (MemoryCard $card) use ($userId): array {
                if ($card->deleted_at !== null) {
                    return [
                        'id' => (int) $card->id,
                        'content_version' => (int) $card->content_version,
                        'sync_version' => (int) $card->sync_version,
                        'deleted_at' => $card->deleted_at->format('Y-m-d H:i:s'),
                    ];
                }
                return $this->views->build($userId, $card)->toArray();
            })->all();
    }

    private function tags(int $userId, array $versions): array
    {
        return Db::table('tags')->where('user_id', $userId)->whereIn('sync_version', $versions)
            ->orderBy('sync_version')->orderBy('id')->get()->map(static function (object $tag): array {
                if ($tag->deleted_at !== null) {
                    return ['id' => (int) $tag->id, 'sync_version' => (int) $tag->sync_version, 'deleted_at' => (string) $tag->deleted_at];
                }
                return ['id' => (int) $tag->id, 'name' => (string) $tag->name, 'sync_version' => (int) $tag->sync_version];
            })->all();
    }

    private function relations(int $userId, array $versions): array
    {
        return Db::table('memory_card_tags')->where('user_id', $userId)->whereIn('sync_version', $versions)
            ->orderBy('sync_version')->orderBy('id')->get()->map(static function (object $relation): array {
                if ($relation->deleted_at !== null) {
                    return ['id' => (int) $relation->id, 'sync_version' => (int) $relation->sync_version, 'deleted_at' => (string) $relation->deleted_at];
                }
                return [
                    'id' => (int) $relation->id,
                    'memory_card_id' => (int) $relation->memory_card_id,
                    'tag_id' => (int) $relation->tag_id,
                    'sync_version' => (int) $relation->sync_version,
                ];
            })->all();
    }

    private function emptyChanges(): array
    {
        return ['cards' => [], 'tags' => [], 'card_tags' => []];
    }
}
