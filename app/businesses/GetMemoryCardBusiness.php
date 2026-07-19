<?php

declare(strict_types=1);

namespace app\businesses;

use app\common\enums\BusinessCode;
use app\entities\AsyncMemoryCardResultEntity;
use app\models\MemoryCard;

final class GetMemoryCardBusiness
{
    private readonly MemoryCardViewBusiness $views;

    public function __construct(?MemoryCardViewBusiness $views = null)
    {
        $this->views = $views ?? new MemoryCardViewBusiness();
    }

    public function get(int $userId, int $cardId): AsyncMemoryCardResultEntity
    {
        /** @var MemoryCard|null $card */
        $card = MemoryCard::query()
            ->where('user_id', $userId)
            ->where('id', $cardId)
            ->whereNull('deleted_at')
            ->first();

        if ($card === null) {
            return AsyncMemoryCardResultEntity::failure(404, BusinessCode::CardNotFound, '记忆卡不存在。');
        }

        return AsyncMemoryCardResultEntity::detail($this->views->build($userId, $card));
    }
}
