<?php

declare(strict_types=1);

namespace app\controllers;

use app\businesses\LogoutBusiness;
use support\Request;
use Webman\Http\Response;

final class LogoutController
{
    public function __construct(private readonly LogoutBusiness $business)
    {
    }

    public function __invoke(Request $request): Response
    {
        $authorization = (string) $request->header('authorization', '');
        preg_match('/^Bearer\s+(\S+)$/i', trim($authorization), $matches);
        $result = $this->business->logout(
            (int) $request->authenticatedUserId(),
            $matches[1] ?? '',
            (string) $request->post('refresh_token', ''),
        );

        return json($result->toResponseArray())->withStatus($result->httpStatus());
    }
}
