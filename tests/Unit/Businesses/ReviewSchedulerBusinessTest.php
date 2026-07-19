<?php

declare(strict_types=1);

namespace Tests\Unit\Businesses;

use app\businesses\ReviewSchedulerBusiness;
use app\common\enums\ReviewRating;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReviewSchedulerBusinessTest extends TestCase
{
    public static function schedules(): array
    {
        return [
            [true, 0, ReviewRating::Again, 0, 600],
            [true, 0, ReviewRating::Hard, 0, 600],
            [true, 0, ReviewRating::Good, 1, 86400],
            [true, 0, ReviewRating::Easy, 2, 259200],
            [false, 3, ReviewRating::Again, 0, 600],
            [false, 3, ReviewRating::Hard, 3, 604800],
            [false, 3, ReviewRating::Good, 4, 1209600],
            [false, 3, ReviewRating::Easy, 5, 2592000],
            [false, 7, ReviewRating::Easy, 7, 10368000],
        ];
    }

    #[DataProvider('schedules')]
    public function test_it_calculates_deterministic_stage_and_due_time(bool $new, int $stage, ReviewRating $rating, int $expectedStage, int $seconds): void
    {
        $now = new DateTimeImmutable('2026-07-19 10:00:00', new DateTimeZone('UTC'));
        $result = (new ReviewSchedulerBusiness())->schedule($new, $stage, $rating, $now);
        self::assertSame($expectedStage, $result->nextStage());
        self::assertSame($now->getTimestamp() + $seconds, $result->nextDueAt()->getTimestamp());
        self::assertSame('UTC', $result->nextDueAt()->getTimezone()->getName());
    }
}
