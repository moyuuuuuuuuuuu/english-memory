<?php

declare(strict_types=1);

namespace app\businesses;

use app\common\enums\BusinessCode;
use app\entities\AuthenticatedUserEntity;
use app\entities\TimezoneResultEntity;
use app\models\User;
use DateTimeZone;

final class UpdateTimezoneBusiness
{
    public function update(int $userId, string $timezone): TimezoneResultEntity
    {
        $timezone = trim($timezone);
        if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            return TimezoneResultEntity::failure(BusinessCode::InvalidTimezone, '时区标识无效。');
        }
        $user = User::query()->whereKey($userId)->where('status', 'active')->first();
        if ($user === null) {
            return TimezoneResultEntity::failure(BusinessCode::InvalidTimezone, '时区标识无效。');
        }
        $user->forceFill(['timezone' => $timezone])->save();
        return TimezoneResultEntity::success(new AuthenticatedUserEntity((int) $user->id, $user->email, $user->username, $timezone));
    }
}
