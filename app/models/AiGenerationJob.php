<?php

declare(strict_types=1);

namespace app\models;

use support\Model;

final class AiGenerationJob extends Model
{
    protected $table = 'ai_generation_jobs';

    protected $fillable = [
        'user_id',
        'memory_card_id',
        'idempotency_key',
        'request_hash',
        'operation',
        'request_payload',
        'provider_payload',
        'pending_card_payload',
        'status',
        'error_code',
        'error_message',
        'failure_type',
        'parent_job_id',
        'attempts',
        'dispatched_at',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'provider_payload' => 'array',
        'pending_card_payload' => 'array',
        'dispatched_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $hidden = [
        'provider_payload',
        'pending_card_payload',
        'error_message',
    ];

    public static function latestForCard(int $userId, int $cardId): ?self
    {
        /** @var self|null $job */
        $job = self::query()
            ->where('user_id', $userId)
            ->where('memory_card_id', $cardId)
            ->orderByDesc('id')
            ->first();

        return $job;
    }
}
