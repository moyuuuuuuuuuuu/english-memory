<?php

declare(strict_types=1);

namespace app\common\exceptions;

use app\common\enums\BusinessCode;
use RuntimeException;

final class ImageImportException extends RuntimeException
{
    public function __construct(
        private readonly BusinessCode $businessCode,
        private readonly string $failureType,
        private readonly string $safeMessage,
    ) {
        parent::__construct($safeMessage);
    }

    public function businessCode(): BusinessCode
    {
        return $this->businessCode;
    }

    public function failureType(): string
    {
        return $this->failureType;
    }

    public function safeMessage(): string
    {
        return $this->safeMessage;
    }
}
