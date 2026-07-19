<?php

declare(strict_types=1);

namespace app\businesses;

use app\models\User;

final class SyncVersionBusiness
{
    public function nextLocked(User $user): int
    {
        $next = (int) $user->sync_version + 1;
        $user->sync_version = $next;
        $user->save();

        return $next;
    }
}
