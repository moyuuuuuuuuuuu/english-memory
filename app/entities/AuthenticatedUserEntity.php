<?php

declare(strict_types=1);

namespace app\entities;

final class AuthenticatedUserEntity
{
    public function __construct(
        private readonly int $id,
        private readonly ?string $email,
        private readonly ?string $username,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'username' => $this->username,
        ];
    }
}
