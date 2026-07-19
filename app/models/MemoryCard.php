<?php

declare(strict_types=1);

namespace app\models;

use support\Model;

final class MemoryCard extends Model
{
    protected $table = 'memory_cards';

    protected $fillable = [
        'user_id',
        'sync_version',
        'content_version',
        'source_text',
        'normalized_text',
        'content_type',
        'memory_style',
        'is_favorite',
        'card_payload',
        'image_url',
        'image_storage_key',
        'next_review_at',
        'review_stage',
        'deleted_at',
    ];

    protected $casts = [
        'card_payload' => 'array',
        'sync_version' => 'integer',
        'content_version' => 'integer',
        'is_favorite' => 'boolean',
        'next_review_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
