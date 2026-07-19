<?php

declare(strict_types=1);

namespace app\controllers;

use app\businesses\CreateMemoryCardBusiness;
use support\Request;
use Webman\Http\Response;

final class CreateMemoryCardController
{
    public function __construct(private readonly CreateMemoryCardBusiness $business)
    {
    }

    public function __invoke(Request $request): Response
    {
        $result = $this->business->create(
            (int) $request->authenticatedUserId(),
            (string) $request->header('Idempotency-Key', ''),
            (string) $request->post('text', ''),
            (string) $request->post('content_type', 'word'),
            (string) $request->post('memory_style', 'auto'),
        );

        return json($result->toResponseArray())->withStatus($result->httpStatus());
    }
}
