<?php

declare(strict_types=1);

namespace app\businesses;

use app\models\MemoryCardImage;
use app\services\contracts\ImageStorage;

final class DeleteStoredMemoryCardImagesBusiness
{
    public function __construct(private readonly ImageStorage $storage)
    {
    }

    public function deleteCard(int $userId, int $cardId): void
    {
        $image = MemoryCardImage::forOwnedCard($userId, $cardId);
        if ($image === null) {
            return;
        }

        $this->storage->deleteKeys([
            (string) $image->original_key,
            (string) $image->large_key,
            (string) $image->small_key,
        ]);
        MemoryCardImage::query()
            ->where('id', (int) $image->id)
            ->where('user_id', $userId)
            ->delete();
    }

    public function deleteUser(int $userId): void
    {
        while (true) {
            $cardIds = MemoryCardImage::query()
                ->where('user_id', $userId)
                ->orderBy('id')
                ->limit(100)
                ->pluck('memory_card_id')
                ->map(static fn ($id): int => (int) $id)
                ->all();
            if ($cardIds === []) {
                return;
            }
            foreach ($cardIds as $cardId) {
                $this->deleteCard($userId, $cardId);
            }
        }
    }
}
