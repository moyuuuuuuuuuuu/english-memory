<?php

declare(strict_types=1);

namespace app\services;

use app\services\contracts\PasswordResetMail;
use support\Log;

final class LogPasswordResetMail implements PasswordResetMail
{
    public function send(string $email, string $plainToken): void
    {
        Log::info('Password reset delivery requested.', [
            'email_hash' => hash('sha256', strtolower($email)),
        ]);
    }
}
