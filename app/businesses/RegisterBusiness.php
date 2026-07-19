<?php

declare(strict_types=1);

namespace app\businesses;

use app\common\enums\BusinessCode;

use app\entities\AuthResultEntity;
use app\models\User;
use Illuminate\Database\UniqueConstraintViolationException;

final class RegisterBusiness
{
    public function register(?string $email, ?string $username, string $password): AuthResultEntity
    {
        $email = $email === null ? null : strtolower(trim($email));
        $username = $username === null ? null : trim($username);
        $email = $email === '' ? null : $email;
        $username = $username === '' ? null : $username;

        if ($email === null && $username === null) {
            return AuthResultEntity::failure(422, BusinessCode::InvalidInput, '请输入邮箱或用户名。');
        }

        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return AuthResultEntity::failure(422, BusinessCode::InvalidInput, '邮箱格式不正确。');
        }

        if ($username !== null && preg_match('/^[A-Za-z0-9_]{3,32}$/', $username) !== 1) {
            return AuthResultEntity::failure(422, BusinessCode::InvalidInput, '用户名须为 3-32 位字母、数字或下划线。');
        }

        if (strlen($password) < 8) {
            return AuthResultEntity::failure(422, BusinessCode::InvalidInput, '密码至少需要 8 个字符。');
        }

        $identityExists = User::query()
            ->where(static function ($query) use ($email, $username): void {
                if ($email !== null) {
                    $query->where('email', $email);
                }
                if ($username !== null) {
                    $email === null
                        ? $query->where('username', $username)
                        : $query->orWhere('username', $username);
                }
            })
            ->exists();

        if ($identityExists) {
            return AuthResultEntity::failure(409, BusinessCode::IdentityTaken, '该邮箱或用户名已被使用。');
        }

        try {
            $user = User::query()->create([
                'email' => $email,
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
        } catch (UniqueConstraintViolationException) {
            return AuthResultEntity::failure(409, BusinessCode::IdentityTaken, '该邮箱或用户名已被使用。');
        }

        return AuthResultEntity::registered((int) $user->id, $user->email, $user->username);
    }
}
