<?php

declare(strict_types=1);

namespace app\controllers;

use app\businesses\ResetPasswordBusiness;
use support\Request;
use Webman\Http\Response;

final class ResetPasswordController
{
    public function __construct(private readonly ResetPasswordBusiness $business)
    {
    }

    public function __invoke(Request $request): Response
    {
        $result = $this->business->reset(
            (string) $request->post('token', ''),
            (string) $request->post('password', ''),
        );

        return json($result->toResponseArray())->withStatus($result->httpStatus());
    }
}
