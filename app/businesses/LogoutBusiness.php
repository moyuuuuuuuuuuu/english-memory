<?php

declare(strict_types=1);

namespace app\businesses;

use app\entities\AuthResultEntity;
use app\models\RefreshToken;
use app\models\User;
use app\services\TokenService;
use support\Db;

final class LogoutBusiness
{
    public function __construct(private readonly TokenService $tokens)
    {
    }

    public function logout(int $userId, string $accessToken, string $refreshToken): AuthResultEntity
    {
        Db::transaction(static function () use ($userId): void {
            $user = User::query()->whereKey($userId)->lockForUpdate()->first();
            if ($user === null) {
                return;
            }
            $now = date('Y-m-d H:i:s');
            $user->session_version = (int) $user->session_version + 1;
            $user->save();
            RefreshToken::query()
                ->where('user_id', $userId)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => $now]);
        });

        $this->tokens->revokeAccessToken($accessToken);

        return AuthResultEntity::loggedOut();
    }
}
