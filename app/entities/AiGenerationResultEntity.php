<?php

namespace app\entities;

final class AiGenerationResultEntity
{
    private function __construct(
        private readonly bool $success,
        private readonly array $card,
        private readonly string $imageUrl,
        private readonly string $executeId,
        private readonly string $error,
    ) {
    }

    public static function success(array $card, string $imageUrl, string $executeId): self
    {
        return new self(true, $card, $imageUrl, $executeId, '');
    }

    public static function failure(array $card, string $error, string $executeId = ''): self
    {
        return new self(false, $card, '', $executeId, $error);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function card(): array
    {
        return $this->card;
    }

    public function imageUrl(): string
    {
        return $this->imageUrl;
    }

    public function executeId(): string
    {
        return $this->executeId;
    }

    public function error(): string
    {
        return $this->error;
    }
}
