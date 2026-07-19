<?php

namespace app\entities;

use app\common\enums\BusinessCode;

final class MemoryCardGenerationEntity
{
    private function __construct(
        private readonly int $httpStatus,
        private readonly array $response,
    ) {
    }

    public static function success(AiGenerationResultEntity $result): self
    {
        return new self(200, [
            'success' => true,
            'data' => [
                'card' => $result->card(),
                'image_url' => $result->imageUrl(),
                'execute_id' => $result->executeId(),
            ],
        ]);
    }

    public static function failure(int $httpStatus, BusinessCode $code, string $message): self
    {
        return new self($httpStatus, [
            'success' => false,
            'error' => [
                'code' => $code->value,
                'message' => $message,
            ],
        ]);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public function toResponseArray(): array
    {
        return $this->response;
    }
}
