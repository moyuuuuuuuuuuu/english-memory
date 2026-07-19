<?php

declare(strict_types=1);

namespace app\businesses;

use app\entities\AuthResultEntity;
use app\models\PasswordResetToken;
use app\models\User;
use app\services\contracts\PasswordResetMail;

final class ForgotPasswordBusiness
{
    public function __construct(
        private readonly PasswordResetMail $mail,
        private readonly int $ttlSeconds = 1800,
    ) {
    }

    public function request(string $email): AuthResultEntity
    {
        $email = strtolower(trim($email));
        $user = filter_var($email, FILTER_VALIDATE_EMAIL) === false
            ? null
            : User::query()->where('email', $email)->where('status', 'active')->first();

        if ($user !== null) {
            $plainToken = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
            PasswordResetToken::query()->create([
                'user_id' => (int) $user->id,
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => date('Y-m-d H:i:s', time() + $this->ttlSeconds),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            try {
                $this->mail->send($email, $plainToken);
            } catch (\Throwable) {
                // The public response remains neutral to avoid account enumeration.
            }
        }

        return AuthResultEntity::passwordResetRequested();
    }
}
