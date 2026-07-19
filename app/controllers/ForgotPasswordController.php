<?php

declare(strict_types=1);

namespace app\controllers;

use app\businesses\ForgotPasswordBusiness;
use support\Request;
use Webman\Http\Response;

final class ForgotPasswordController
{
    public function __construct(private readonly ForgotPasswordBusiness $business)
    {
    }

    public function __invoke(Request $request): Response
    {
        $result = $this->business->request((string) $request->post('email', ''));

        return json($result->toResponseArray())->withStatus($result->httpStatus());
    }
}
