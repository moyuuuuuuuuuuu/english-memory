<?php

declare(strict_types=1);

namespace app\services\contracts;

interface PasswordResetMail
{
    public function send(string $email, string $plainToken): void;
}
