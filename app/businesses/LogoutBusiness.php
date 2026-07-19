<?php

declare(strict_types=1);

namespace app\businesses;

use app\entities\AuthResultEntity;
use app\models\RefreshToken;
use app\services\TokenService;

final class LogoutBusiness
{
    public function __construct(private readonly TokenService $tokens)
    {
    }

    public function logout(int $userId, string $accessToken, string $refreshToken): AuthResultEntity
    {
        $this->tokens->revokeAccessToken($accessToken);

        if ($refreshToken !== '') {
            RefreshToken::query()
                ->where('user_id', $userId)
                ->where('token_hash', hash('sha256', $refreshToken))
                ->whereNull('revoked_at')
                ->update(['revoked_at' => date('Y-m-d H:i:s')]);
        }

        return AuthResultEntity::loggedOut();
    }
}
