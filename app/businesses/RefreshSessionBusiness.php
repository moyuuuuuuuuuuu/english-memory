<?php

declare(strict_types=1);

namespace app\businesses;

use app\common\enums\BusinessCode;

use app\entities\AuthResultEntity;
use app\models\RefreshToken;
use app\models\User;
use app\services\TokenService;
use support\Db;

final class RefreshSessionBusiness
{
    public function __construct(
        private readonly TokenService $tokens,
        private readonly int $refreshTtlSeconds = 2592000,
    ) {
    }

    public function refresh(string $plainToken): AuthResultEntity
    {
        $plainToken = trim($plainToken);
        if ($plainToken === '') {
            return $this->invalidToken();
        }

        $candidate = RefreshToken::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->first();
        if ($candidate === null) {
            return $this->invalidToken();
        }

        $rotated = Db::transaction(function () use ($plainToken, $candidate): ?array {
            $user = User::query()->whereKey($candidate->user_id)->where('status', 'active')->lockForUpdate()->first();
            if ($user === null) {
                return null;
            }

            $current = RefreshToken::query()
                ->where('token_hash', hash('sha256', $plainToken))
                ->where('user_id', (int) $user->id)
                ->whereNull('revoked_at')
                ->where('expires_at', '>', date('Y-m-d H:i:s'))
                ->lockForUpdate()
                ->first();

            if ($current === null) {
                return null;
            }

            if ((int) $current->session_version !== (int) $user->session_version) {
                return null;
            }

            $now = date('Y-m-d H:i:s');
            $current->revoked_at = $now;
            $current->save();

            $nextPlainToken = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
            RefreshToken::query()->create([
                'user_id' => (int) $user->id,
                'token_hash' => hash('sha256', $nextPlainToken),
                'device_name' => $current->device_name,
                'session_version' => (int) $user->session_version,
                'expires_at' => date('Y-m-d H:i:s', time() + $this->refreshTtlSeconds),
                'created_at' => $now,
            ]);

            return [
                'user_id' => (int) $user->id,
                'session_version' => (int) $user->session_version,
                'token' => $nextPlainToken,
            ];
        });

        if ($rotated === null) {
            return $this->invalidToken();
        }

        return AuthResultEntity::refreshed(
            $this->tokens->issueAccessToken($rotated['user_id'], $rotated['session_version']),
            ['token' => $rotated['token'], 'expires_in' => $this->refreshTtlSeconds],
        );
    }

    private function invalidToken(): AuthResultEntity
    {
        return AuthResultEntity::failure(401, BusinessCode::InvalidRefreshToken, '刷新凭证无效或已过期。');
    }
}
