<?php

declare(strict_types=1);

namespace app\models;

use support\Model;

final class PasswordResetToken extends Model
{
    protected $table = 'password_reset_tokens';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'token_hash',
        'expires_at',
        'used_at',
        'created_at',
    ];

    protected $hidden = ['token_hash'];
}
