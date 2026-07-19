<?php

declare(strict_types=1);

namespace app\models;

use support\Model;

final class User extends Model
{
    protected $table = 'users';

    protected $fillable = [
        'email',
        'username',
        'password_hash',
        'status',
        'last_login_at',
        'sync_version',
        'session_version',
    ];

    protected $hidden = ['password_hash'];

    protected $casts = [
        'sync_version' => 'integer',
        'session_version' => 'integer',
    ];
}
