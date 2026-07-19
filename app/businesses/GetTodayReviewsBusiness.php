<?php

declare(strict_types=1);

namespace app\businesses;

use app\entities\TodayReviewsResultEntity;
use app\models\MemoryCard;
use app\models\User;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;

final class GetTodayReviewsBusiness
{
    public function __construct(
        private readonly MemoryCardViewBusiness $views = new MemoryCardViewBusiness(),
        private readonly ReviewModeBusiness $modes = new ReviewModeBusiness(),
    ) {}

    public function get(int $userId, ?DateTimeImmutable $nowUtc = null): TodayReviewsResultEntity
    {
        $user = User::query()->whereKey($userId)->firstOrFail();
        $nowUtc ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $base = fn (): Builder => MemoryCard::query()
            ->where('user_id', $userId)->whereNull('deleted_at')
            ->whereRaw("(SELECT job.status FROM ai_generation_jobs job WHERE job.user_id = ? AND job.memory_card_id = memory_cards.id ORDER BY job.id DESC LIMIT 1) = 'completed'", [$userId]);
        $due = $base()->whereNotNull('first_reviewed_at')->whereNotNull('next_review_at')
            ->where('next_review_at', '<=', $nowUtc->format('Y-m-d H:i:s'))
            ->orderBy('next_review_at')->orderBy('id')->get();
        $new = $base()->whereNull('first_reviewed_at')->orderBy('created_at')->orderBy('id')->limit(20)->get();
        $items = $due->concat($new)->map(function (MemoryCard $card) use ($userId): array {
            return array_merge($this->views->build($userId, $card)->toArray(), $this->modes->project($card));
        })->filter(static fn (array $item): bool => $item['available_modes'] !== [])->values()->all();
        $timezone = (string) $user->timezone;
        return new TodayReviewsResultEntity([
            'timezone' => $timezone,
            'local_date' => $nowUtc->setTimezone(new DateTimeZone($timezone))->format('Y-m-d'),
            'items' => $items,
        ]);
    }
}
