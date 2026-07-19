<?php

declare(strict_types=1);

namespace app\businesses;

use app\entities\AuthenticatedUserEntity;
use app\entities\AuthResultEntity;
use app\models\User;
use app\services\TokenService;

final class LoginBusiness
{
    public function __construct(private readonly TokenService $tokens)
    {
    }

    public function login(string $identity, string $password): AuthResultEntity
    {
        $identity = trim($identity);
        if ($identity === '' || $password === '') {
            return $this->invalidCredentials();
        }

        $user = User::query()
            ->where('email', strtolower($identity))
            ->orWhere('username', $identity)
            ->first();

        if ($user === null || !password_verify($password, (string) $user->password_hash)) {
            return $this->invalidCredentials();
        }

        if ($user->status !== 'active') {
            return AuthResultEntity::failure(403, 'ACCOUNT_DISABLED', '该账户当前不可用。');
        }

        $user->last_login_at = date('Y-m-d H:i:s');
        $user->save();

        return AuthResultEntity::authenticated(
            new AuthenticatedUserEntity((int) $user->id, $user->email, $user->username),
            $this->tokens->issueAccessToken((int) $user->id),
        );
    }

    private function invalidCredentials(): AuthResultEntity
    {
        return AuthResultEntity::failure(401, 'INVALID_CREDENTIALS', '账号或密码错误。');
    }
}
