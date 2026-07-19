<?php

declare(strict_types=1);

namespace app\entities;

final readonly class AccessTokenIdentityEntity
{
    public function __construct(
        private int $userId,
        private int $sessionVersion,
    ) {
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function sessionVersion(): int
    {
        return $this->sessionVersion;
    }
}
