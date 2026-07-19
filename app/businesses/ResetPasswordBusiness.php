<?php

declare(strict_types=1);

namespace app\businesses;

use app\common\enums\BusinessCode;

use app\entities\AuthResultEntity;
use app\models\PasswordResetToken;
use app\models\User;
use support\Db;

final class ResetPasswordBusiness
{
    public function reset(string $plainToken, string $password): AuthResultEntity
    {
        if (trim($plainToken) === '' || strlen($password) < 8) {
            return $this->invalidToken();
        }

        $reset = Db::transaction(function () use ($plainToken, $password): bool {
            $token = PasswordResetToken::query()
                ->where('token_hash', hash('sha256', trim($plainToken)))
                ->whereNull('used_at')
                ->where('expires_at', '>', date('Y-m-d H:i:s'))
                ->lockForUpdate()
                ->first();

            if ($token === null) {
                return false;
            }

            $user = User::query()->whereKey($token->user_id)->where('status', 'active')->first();
            if ($user === null) {
                return false;
            }

            $user->password_hash = password_hash($password, PASSWORD_DEFAULT);
            $user->save();
            $token->used_at = date('Y-m-d H:i:s');
            $token->save();

            return true;
        });

        return $reset ? AuthResultEntity::passwordReset() : $this->invalidToken();
    }

    private function invalidToken(): AuthResultEntity
    {
        return AuthResultEntity::failure(422, BusinessCode::InvalidResetToken, '重置凭证无效或已过期。');
    }
}
