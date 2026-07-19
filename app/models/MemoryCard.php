<?php

declare(strict_types=1);

namespace app\models;

use support\Model;

final class MemoryCard extends Model
{
    protected $table = 'memory_cards';

    protected $fillable = [
        'user_id',
        'source_text',
        'normalized_text',
        'content_type',
        'memory_style',
        'card_payload',
        'image_url',
        'image_storage_key',
        'next_review_at',
        'review_stage',
    ];

    protected $casts = [
        'card_payload' => 'array',
        'next_review_at' => 'datetime',
    ];
}
