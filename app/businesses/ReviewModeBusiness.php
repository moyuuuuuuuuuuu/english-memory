<?php

declare(strict_types=1);

namespace app\businesses;

use app\common\enums\ReviewMode;
use app\models\MemoryCard;

final class ReviewModeBusiness
{
    public function project(MemoryCard $card): array
    {
        $expected = trim((string) $card->normalized_text);
        if ($expected === '') {
            $expected = trim((string) (($card->card_payload ?? [])['word'] ?? ''));
        }
        $modes = [];
        if ($expected !== '') {
            if (trim((string) $card->image_url) !== '') {
                $modes[] = ReviewMode::ImageRecall->value;
            }
            $modes[] = ReviewMode::ListeningSpelling->value;
            $modes[] = ReviewMode::ZhToEn->value;
        }
        return [
            'available_modes' => $modes,
            'recommended_mode' => $modes === [] ? '' : $modes[(int) $card->review_count % count($modes)],
            'expected_answer_kind' => (string) $card->content_type === 'sentence' ? 'sentence' : 'word',
        ];
    }
}
