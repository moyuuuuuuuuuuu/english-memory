<?php

declare(strict_types=1);

namespace Tests\Unit\Businesses;

use app\businesses\ReviewModeBusiness;
use app\models\MemoryCard;
use PHPUnit\Framework\TestCase;

final class ReviewModeBusinessTest extends TestCase
{
    public function test_image_card_exposes_three_modes_and_rotates_recommendation(): void
    {
        $card = new MemoryCard([
            'normalized_text' => 'ambition', 'content_type' => 'word',
            'image_url' => 'http://e.test/storage/card.webp', 'review_count' => 1,
        ]);
        $projection = (new ReviewModeBusiness())->project($card);
        self::assertSame(['image_recall', 'listening_spelling', 'zh_to_en'], $projection['available_modes']);
        self::assertSame('listening_spelling', $projection['recommended_mode']);
        self::assertSame('word', $projection['expected_answer_kind']);
    }

    public function test_card_without_image_exposes_two_text_modes(): void
    {
        $card = new MemoryCard(['normalized_text' => 'A sentence', 'content_type' => 'sentence', 'review_count' => 2]);
        $projection = (new ReviewModeBusiness())->project($card);
        self::assertSame(['listening_spelling', 'zh_to_en'], $projection['available_modes']);
        self::assertSame('listening_spelling', $projection['recommended_mode']);
        self::assertSame('sentence', $projection['expected_answer_kind']);
    }

    public function test_card_without_expected_answer_is_not_reviewable(): void
    {
        self::assertSame([], (new ReviewModeBusiness())->project(new MemoryCard(['normalized_text' => '', 'card_payload' => []]))['available_modes']);
    }
}
