<?php

declare(strict_types=1);

namespace app\businesses;

use app\common\enums\BusinessCode;

use app\entities\AuthenticatedUserEntity;
use app\entities\AuthResultEntity;
use app\models\User;
use app\models\RefreshToken;
use app\services\TokenService;

final class LoginBusiness
{
    public function __construct(
        private readonly TokenService $tokens,
        private readonly int $refreshTtlSeconds = 2592000,
    ) {
    }

    public function login(string $identity, string $password, ?string $deviceName = null): AuthResultEntity
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
            return AuthResultEntity::failure(403, BusinessCode::AccountDisabled, '该账户当前不可用。');
        }

        $user->last_login_at = date('Y-m-d H:i:s');
        $user->save();

        $plainRefreshToken = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        RefreshToken::query()->create([
            'user_id' => (int) $user->id,
            'token_hash' => hash('sha256', $plainRefreshToken),
            'device_name' => $deviceName === null ? null : substr(trim($deviceName), 0, 128),
            'expires_at' => date('Y-m-d H:i:s', time() + $this->refreshTtlSeconds),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return AuthResultEntity::authenticated(
            new AuthenticatedUserEntity((int) $user->id, $user->email, $user->username),
            $this->tokens->issueAccessToken((int) $user->id),
            ['token' => $plainRefreshToken, 'expires_in' => $this->refreshTtlSeconds],
        );
    }

    private function invalidCredentials(): AuthResultEntity
    {
        return AuthResultEntity::failure(401, BusinessCode::InvalidCredentials, '账号或密码错误。');
    }
}
