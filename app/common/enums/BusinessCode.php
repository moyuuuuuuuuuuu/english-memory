<?php

declare(strict_types=1);

namespace app\common\enums;

enum BusinessCode: string
{
    case InvalidInput = 'INVALID_INPUT';
    case IdentityTaken = 'IDENTITY_TAKEN';
    case InvalidCredentials = 'INVALID_CREDENTIALS';
    case AccountDisabled = 'ACCOUNT_DISABLED';
    case Unauthenticated = 'UNAUTHENTICATED';
    case InvalidRefreshToken = 'INVALID_REFRESH_TOKEN';
    case InvalidResetToken = 'INVALID_RESET_TOKEN';
    case AiProviderError = 'AI_PROVIDER_ERROR';
    case GenerationFailed = 'GENERATION_FAILED';
    case InvalidAiResult = 'INVALID_AI_RESULT';
    case IdempotencyKeyRequired = 'IDEMPOTENCY_KEY_REQUIRED';
    case IdempotencyConflict = 'IDEMPOTENCY_CONFLICT';
    case CardNotFound = 'CARD_NOT_FOUND';
    case JobStateConflict = 'JOB_STATE_CONFLICT';
    case QueueUnavailable = 'QUEUE_UNAVAILABLE';
}
