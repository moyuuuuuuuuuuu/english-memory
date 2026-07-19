<?php

declare(strict_types=1);

namespace app\models;

use Illuminate\Database\Eloquent\Builder;
use support\Model;

final class ReviewEvent extends Model
{
    public const UPDATED_AT = null;
    protected $table = 'review_events';
    protected $guarded = [];
    protected $casts = [
        'is_correct' => 'boolean',
        'response_payload' => 'array',
        'reviewed_at' => 'datetime',
        'previous_due_at' => 'datetime',
        'next_due_at' => 'datetime',
    ];
    public static function forUser(int $userId): Builder { return self::query()->where('user_id', $userId); }
}
