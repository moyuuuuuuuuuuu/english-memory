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
    ];

    protected $hidden = ['password_hash'];
}
