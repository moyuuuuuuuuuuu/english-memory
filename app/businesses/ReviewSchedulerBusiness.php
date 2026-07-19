<?php

declare(strict_types=1);

namespace app\businesses;

use app\common\enums\ReviewRating;
use app\entities\ReviewScheduleEntity;
use DateTimeImmutable;

final class ReviewSchedulerBusiness
{
    private const INTERVALS = [600, 86400, 259200, 604800, 1209600, 2592000, 5184000, 10368000];

    public function schedule(bool $isNew, int $currentStage, ReviewRating $rating, DateTimeImmutable $reviewedAtUtc): ReviewScheduleEntity
    {
        $currentStage = max(0, min(7, $currentStage));
        $next = match ($rating) {
            ReviewRating::Again => 0,
            ReviewRating::Hard => $isNew ? 0 : $currentStage,
            ReviewRating::Good => $isNew ? 1 : min(7, $currentStage + 1),
            ReviewRating::Easy => $isNew ? 2 : min(7, $currentStage + 2),
        };
        $due = $reviewedAtUtc->setTimestamp($reviewedAtUtc->getTimestamp() + self::INTERVALS[$next]);
        return new ReviewScheduleEntity($isNew ? -1 : $currentStage, $next, $due);
    }
}
