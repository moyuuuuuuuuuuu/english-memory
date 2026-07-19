<?php

declare(strict_types=1);

namespace app\businesses;

use app\common\enums\BusinessCode;

use app\entities\AuthenticatedUserEntity;
use app\entities\AuthResultEntity;
use app\models\User;

final class CurrentUserBusiness
{
    public function currentUser(int $userId): AuthResultEntity
    {
        $user = User::query()->whereKey($userId)->where('status', 'active')->first();
        if ($user === null) {
            return AuthResultEntity::failure(401, BusinessCode::Unauthenticated, '请先登录。');
        }

        return AuthResultEntity::currentUser(
            new AuthenticatedUserEntity((int) $user->id, $user->email, $user->username, (string) $user->timezone),
        );
    }
}
