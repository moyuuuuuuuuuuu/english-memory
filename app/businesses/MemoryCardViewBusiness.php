<?php

declare(strict_types=1);

namespace app\businesses;

use app\entities\MemoryCardViewEntity;
use app\models\AiGenerationJob;
use app\models\MemoryCard;
use support\Db;

final class MemoryCardViewBusiness
{
    public function build(
        int $userId,
        MemoryCard $card,
        bool $includeLatestJob = true,
    ): MemoryCardViewEntity {
        $tags = Db::table('memory_card_tags as relation')
            ->join('tags as tag', 'tag.id', '=', 'relation.tag_id')
            ->where('relation.user_id', $userId)
            ->where('relation.memory_card_id', (int) $card->id)
            ->whereNull('relation.deleted_at')
            ->whereNull('tag.deleted_at')
            ->orderBy('tag.normalized_name')
            ->orderBy('tag.id')
            ->get([
                'tag.id',
                'tag.name',
                'tag.sync_version',
            ])
            ->map(static fn (object $tag): array => [
                'id' => (int) $tag->id,
                'name' => (string) $tag->name,
                'sync_version' => (int) $tag->sync_version,
            ])
            ->all();

        $job = $includeLatestJob ? AiGenerationJob::latestForCard($userId, (int) $card->id) : null;

        return new MemoryCardViewEntity([
            'id' => (int) $card->id,
            'source_text' => (string) $card->source_text,
            'normalized_text' => (string) $card->normalized_text,
            'content_type' => (string) $card->content_type,
            'memory_style' => (string) $card->memory_style,
            'card_payload' => $card->card_payload ?? [],
            'image_url' => $card->image_url,
            'is_favorite' => (bool) $card->is_favorite,
            'content_version' => (int) $card->content_version,
            'sync_version' => (int) $card->sync_version,
            'tags' => $tags,
            'next_review_at' => $card->next_review_at?->format('Y-m-d H:i:s'),
            'review_stage' => (int) $card->review_stage,
            'created_at' => $card->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $card->updated_at?->format('Y-m-d H:i:s'),
        ], $job === null ? null : [
            'id' => (int) $job->id,
            'status' => (string) $job->status,
            'operation' => (string) $job->operation,
            'error_code' => $job->error_code,
            'failure_type' => $job->failure_type,
            'attempts' => (int) $job->attempts,
            'started_at' => $job->started_at?->format('Y-m-d H:i:s'),
            'completed_at' => $job->completed_at?->format('Y-m-d H:i:s'),
            'created_at' => $job->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $job->updated_at?->format('Y-m-d H:i:s'),
        ]);
    }
}
