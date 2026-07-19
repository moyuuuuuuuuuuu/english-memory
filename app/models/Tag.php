<?php

declare(strict_types=1);

namespace app\models;

use Illuminate\Database\Eloquent\Builder;
use support\Model;

final class Tag extends Model
{
    protected $table = 'tags';

    protected $fillable = [
        'user_id',
        'name',
        'normalized_name',
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
