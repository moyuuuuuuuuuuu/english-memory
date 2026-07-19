<?php

declare(strict_types=1);

namespace app\models;

use support\Model;

final class RefreshToken extends Model
{
    protected $table = 'refresh_tokens';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'token_hash',
        'device_name',
        'session_version',
        'expires_at',
        'revoked_at',
        'created_at',
    ];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'session_version' => 'integer',
    ];
}
