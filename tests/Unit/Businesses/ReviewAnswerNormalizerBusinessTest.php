<?php

declare(strict_types=1);

namespace Tests\Unit\Businesses;

use app\businesses\ReviewAnswerNormalizerBusiness;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReviewAnswerNormalizerBusinessTest extends TestCase
{
    public static function cases(): array
    {
        return [
            ['  HELLO   World!  ', 'hello world'],
            ["Hello\u{3000}world。", 'hello world'],
            ["Don't-stop!", "don't-stop"],
            ['A sentence，！？', 'a sentence'],
        ];
    }

    #[DataProvider('cases')]
    public function test_it_normalizes_answers_without_removing_internal_apostrophes_or_hyphens(string $input, string $expected): void
    {
        self::assertSame($expected, (new ReviewAnswerNormalizerBusiness())->normalize($input));
    }
}
