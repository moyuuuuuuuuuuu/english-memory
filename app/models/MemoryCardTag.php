<?php

declare(strict_types=1);

namespace app\models;

use Illuminate\Database\Eloquent\Builder;
use support\Model;

final class MemoryCardTag extends Model
{
    protected $table = 'memory_card_tags';

    protected $fillable = [
        'user_id',
        'memory_card_id',
        'tag_id',
        'sync_version',
        'deleted_at',
    ];

    protected $casts = [
        'sync_version' => 'integer',
        'deleted_at' => 'datetime',
    ];

    public static function forUser(int $userId): Builder
    {
        return self::query()->where('user_id', $userId);
    }
}
