<?php

declare(strict_types=1);

namespace app\controllers;

use app\businesses\LoginBusiness;
use support\Request;
use Webman\Http\Response;

final class LoginController
{
    public function __construct(private readonly LoginBusiness $business)
    {
    }

    public function __invoke(Request $request): Response
    {
        $result = $this->business->login(
            (string) $request->post('identity', ''),
            (string) $request->post('password', ''),
            $request->post('device_name'),
        );

        return json($result->toResponseArray())->withStatus($result->httpStatus());
    }
}
