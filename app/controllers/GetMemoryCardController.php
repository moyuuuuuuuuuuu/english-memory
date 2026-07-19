<?php

declare(strict_types=1);

namespace app\controllers;

use app\businesses\GetMemoryCardBusiness;
use support\Request;
use Webman\Http\Response;

final class GetMemoryCardController
{
    public function __construct(private readonly GetMemoryCardBusiness $business)
    {
    }

    public function __invoke(Request $request, int|string $id): Response
    {
        $result = $this->business->get((int) $request->authenticatedUserId(), (int) $id);

        return json($result->toResponseArray())->withStatus($result->httpStatus());
    }
}
