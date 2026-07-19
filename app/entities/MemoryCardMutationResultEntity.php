<?php

declare(strict_types=1);

namespace app\entities;

use app\common\enums\BusinessCode;

final readonly class MemoryCardMutationResultEntity
{
    private function __construct(
        private int $httpStatus,
        private array $response,
    ) {
    }

    public static function success(MemoryCardViewEntity $view): self
    {
        return new self(200, ['success' => true, 'data' => $view->toArray()]);
    }

    public static function failure(int $httpStatus, BusinessCode $code, string $message): self
    {
        return new self($httpStatus, [
            'success' => false,
            'error' => ['code' => $code->value, 'message' => $message],
        ]);
    }

    public static function conflict(MemoryCardViewEntity $view): self
    {
        return new self(409, [
            'success' => false,
            'error' => [
                'code' => BusinessCode::CardVersionConflict->value,
                'message' => '记忆卡已更新，请刷新后重试。',
                'server_card' => $view->card(),
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
