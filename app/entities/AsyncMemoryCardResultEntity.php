<?php

declare(strict_types=1);

namespace app\entities;

use app\common\enums\BusinessCode;

final readonly class AsyncMemoryCardResultEntity
{
    private function __construct(
        private int $httpStatus,
        private array $response,
    ) {
    }

    public static function accepted(
        int $cardId,
        int $jobId,
        string $status,
        bool $replayed,
        bool $dispatchPending,
    ): self {
        return new self(202, [
            'success' => true,
            'data' => [
                'card_id' => $cardId,
                'job_id' => $jobId,
                'status' => $status,
                'replayed' => $replayed,
                'dispatch_pending' => $dispatchPending,
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

    public static function detail(MemoryCardViewEntity $view): self
    {
        return new self(200, [
            'success' => true,
            'data' => $view->toArray(),
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
