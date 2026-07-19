<?php

declare(strict_types=1);

namespace app\controllers;

use app\businesses\RetryMemoryCardBusiness;
use support\Request;
use Webman\Http\Response;

final class RetryMemoryCardController
{
    public function __construct(private readonly RetryMemoryCardBusiness $business)
    {
    }

    public function __invoke(Request $request, int|string $id): Response
    {
        $result = $this->business->retry(
            (int) $request->authenticatedUserId(),
            (int) $id,
            (string) $request->header('Idempotency-Key', ''),
        );

        return json($result->toResponseArray())->withStatus($result->httpStatus());
    }
}
