<?php

declare(strict_types=1);

namespace Tests\Unit;

use app\common\enums\BusinessCode;
use app\entities\AuthResultEntity;
use PHPUnit\Framework\TestCase;

final class BusinessCodeTest extends TestCase
{
    public function test_it_defines_the_stable_business_code_contract(): void
    {
        self::assertSame([
            'INVALID_INPUT',
            'IDENTITY_TAKEN',
            'INVALID_CREDENTIALS',
            'ACCOUNT_DISABLED',
            'UNAUTHENTICATED',
            'INVALID_REFRESH_TOKEN',
            'INVALID_RESET_TOKEN',
            'AI_PROVIDER_ERROR',
            'GENERATION_FAILED',
            'INVALID_AI_RESULT',
            'IDEMPOTENCY_KEY_REQUIRED',
            'IDEMPOTENCY_CONFLICT',
            'CARD_NOT_FOUND',
            'JOB_STATE_CONFLICT',
            'QUEUE_UNAVAILABLE',
            'IMAGE_SOURCE_NOT_ALLOWED',
            'IMAGE_DOWNLOAD_FAILED',
            'INVALID_IMAGE',
            'IMAGE_PROCESSING_FAILED',
            'IMAGE_STORAGE_FAILED',
        ], array_column(BusinessCode::cases(), 'value'));
    }

    public function test_result_entities_serialize_the_enum_value(): void
    {
        $result = AuthResultEntity::failure(
            401,
            BusinessCode::Unauthenticated,
            '请先登录。',
        );

        self::assertSame('UNAUTHENTICATED', $result->toResponseArray()['error']['code']);
    }
}
