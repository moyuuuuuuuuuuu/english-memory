<?php

declare(strict_types=1);

namespace app\models;

use support\Model;

final class MemoryCardImage extends Model
{
    protected $table = 'memory_card_images';

    protected $fillable = [
        'user_id',
        'memory_card_id',
        'storage_driver',
        'original_key',
        'large_key',
        'small_key',
        'original_sha256',
        'large_sha256',
        'small_sha256',
        'original_mime',
        'original_width',
        'original_height',
        'original_bytes',
    ];

    public static function forOwnedCard(int $userId, int $cardId): ?self
    {
        /** @var self|null $image */
        $image = self::query()
            ->where('user_id', $userId)
            ->where('memory_card_id', $cardId)
            ->first();

        return $image;
    }
}
