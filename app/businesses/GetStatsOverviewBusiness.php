<?php

declare(strict_types=1);

namespace app\businesses;

use app\entities\StatsOverviewResultEntity;
use app\models\DailyLearningStat;
use app\models\MemoryCard;
use app\models\ReviewEvent;
use app\models\User;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;

final class GetStatsOverviewBusiness
{
    private const DAILY_GOAL = 20;

    public function get(int $userId, ?DateTimeImmutable $nowUtc = null): StatsOverviewResultEntity
    {
        $user = User::query()->whereKey($userId)->firstOrFail();
        $nowUtc ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $timezone = (string) $user->timezone;
        $localDate = $nowUtc->setTimezone(new DateTimeZone($timezone))->format('Y-m-d');
        $today = DailyLearningStat::forUser($userId)->where('stat_date', $localDate)->first();
        $todayReviews = (int) ($today?->reviews_completed ?? 0);
        $todayCorrect = (int) ($today?->correct_reviews ?? 0);

        $lifetime = DailyLearningStat::forUser($userId)->selectRaw(
            'COALESCE(SUM(reviews_completed), 0) AS reviews, COALESCE(SUM(correct_reviews), 0) AS correct, COALESCE(SUM(xp_earned), 0) AS xp, COALESCE(MAX(best_correct_streak), 0) AS best_streak'
        )->first();
        $lifetimeReviews = (int) $lifetime->reviews;
        $lifetimeCorrect = (int) $lifetime->correct;
        $lifetimeXp = (int) $lifetime->xp;
        $base = $this->eligibleCards($userId);
        $now = $nowUtc->format('Y-m-d H:i:s');

        return new StatsOverviewResultEntity([
            'timezone' => $timezone,
            'local_date' => $localDate,
            'today' => [
                'reviews_completed' => $todayReviews,
                'correct_reviews' => $todayCorrect,
                'accuracy' => $this->accuracy($todayCorrect, $todayReviews),
                'xp_earned' => (int) ($today?->xp_earned ?? 0),
                'current_correct_streak' => (int) ($today?->current_correct_streak ?? 0),
                'best_correct_streak' => (int) ($today?->best_correct_streak ?? 0),
                'daily_goal' => self::DAILY_GOAL,
                'goal_progress' => min($todayReviews, self::DAILY_GOAL),
            ],
            'lifetime' => [
                'reviews_completed' => $lifetimeReviews,
                'correct_reviews' => $lifetimeCorrect,
                'accuracy' => $this->accuracy($lifetimeCorrect, $lifetimeReviews),
                'xp_earned' => $lifetimeXp,
                'level' => 1 + intdiv($lifetimeXp, 500),
                'distinct_cards_reviewed' => ReviewEvent::forUser($userId)->distinct()->count('memory_card_id'),
                'best_correct_streak' => (int) $lifetime->best_streak,
            ],
            'learning_day_streak' => $this->learningDayStreak($userId, $localDate),
            'due_count' => (clone $base)->whereNotNull('first_reviewed_at')->whereNotNull('next_review_at')->where('next_review_at', '<=', $now)->count(),
            'new_count' => (clone $base)->whereNull('first_reviewed_at')->count(),
        ]);
    }

    private function eligibleCards(int $userId): Builder
    {
        return MemoryCard::query()->where('user_id', $userId)->whereNull('deleted_at')
            ->whereRaw("(SELECT job.status FROM ai_generation_jobs job WHERE job.user_id = ? AND job.memory_card_id = memory_cards.id ORDER BY job.id DESC LIMIT 1) = 'completed'", [$userId]);
    }

    private function accuracy(int $correct, int $reviews): float
    {
        return $reviews === 0 ? 0.0 : round($correct * 100 / $reviews, 2);
    }

    private function learningDayStreak(int $userId, string $localDate): int
    {
        $dates = DailyLearningStat::forUser($userId)->where('reviews_completed', '>', 0)
            ->where('stat_date', '<=', $localDate)->orderByDesc('stat_date')->pluck('stat_date')->map(static fn ($date): string => (string) $date)->all();
        if ($dates === []) return 0;
        $today = new DateTimeImmutable($localDate);
        $yesterday = $today->sub(new DateInterval('P1D'));
        $cursor = $dates[0] === $localDate ? $today : ($dates[0] === $yesterday->format('Y-m-d') ? $yesterday : null);
        if ($cursor === null) return 0;
        $streak = 0;
        foreach ($dates as $date) {
            if ($date !== $cursor->format('Y-m-d')) break;
            ++$streak;
            $cursor = $cursor->sub(new DateInterval('P1D'));
        }
        return $streak;
    }
}
