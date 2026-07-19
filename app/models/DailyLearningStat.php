<?php

declare(strict_types=1);

namespace app\models;

use Illuminate\Database\Eloquent\Builder;
use support\Model;

final class DailyLearningStat extends Model
{
    protected $table = 'daily_learning_stats';
    protected $guarded = [];
    public static function forUser(int $userId): Builder { return self::query()->where('user_id', $userId); }
}
