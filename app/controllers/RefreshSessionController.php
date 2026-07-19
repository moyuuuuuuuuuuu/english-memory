<?php

declare(strict_types=1);

namespace app\controllers;

use app\businesses\RefreshSessionBusiness;
use support\Request;
use Webman\Http\Response;

final class RefreshSessionController
{
    public function __construct(private readonly RefreshSessionBusiness $business)
    {
    }

    public function __invoke(Request $request): Response
    {
        $result = $this->business->refresh((string) $request->post('refresh_token', ''));

        return json($result->toResponseArray())->withStatus($result->httpStatus());
    }
}
