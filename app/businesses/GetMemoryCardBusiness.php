<?php

declare(strict_types=1);

namespace app\businesses;

use app\common\enums\BusinessCode;
use app\entities\AsyncMemoryCardResultEntity;
use app\models\AiGenerationJob;
use app\models\MemoryCard;

final class GetMemoryCardBusiness
{
    public function get(int $userId, int $cardId): AsyncMemoryCardResultEntity
    {
        /** @var MemoryCard|null $card */
        $card = MemoryCard::query()
            ->where('user_id', $userId)
            ->where('id', $cardId)
            ->first();

        if ($card === null) {
            return AsyncMemoryCardResultEntity::failure(404, BusinessCode::CardNotFound, '记忆卡不存在。');
        }

        $job = AiGenerationJob::latestForCard($userId, $cardId);

        return AsyncMemoryCardResultEntity::detail(
            [
                'id' => (int) $card->id,
                'source_text' => (string) $card->source_text,
                'normalized_text' => (string) $card->normalized_text,
                'content_type' => (string) $card->content_type,
                'memory_style' => (string) $card->memory_style,
                'card_payload' => $card->card_payload ?? [],
                'image_url' => $card->image_url,
                'next_review_at' => $card->next_review_at?->format('Y-m-d H:i:s'),
                'review_stage' => (int) $card->review_stage,
                'created_at' => $card->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $card->updated_at?->format('Y-m-d H:i:s'),
            ],
            $job === null ? null : [
                'id' => (int) $job->id,
                'status' => (string) $job->status,
                'error_code' => $job->error_code,
                'failure_type' => $job->failure_type,
                'attempts' => (int) $job->attempts,
                'started_at' => $job->started_at?->format('Y-m-d H:i:s'),
                'completed_at' => $job->completed_at?->format('Y-m-d H:i:s'),
                'created_at' => $job->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $job->updated_at?->format('Y-m-d H:i:s'),
            ],
        );
    }
}
