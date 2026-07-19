<?php

declare(strict_types=1);

namespace app\controllers;

use app\businesses\RegenerateMemoryCardBusiness;
use support\Request;
use Webman\Http\Response;

final class RegenerateMemoryCardController
{
    public function __construct(private readonly RegenerateMemoryCardBusiness $business) {}

    public function __invoke(Request $request, int|string $id): Response
    {
        $result = $this->business->regenerate(
            (int) $request->authenticatedUserId(),
            (int) $id,
            (string) $request->header('Idempotency-Key', ''),
        );
        return json($result->toResponseArray())->withStatus($result->httpStatus());
    }
}
