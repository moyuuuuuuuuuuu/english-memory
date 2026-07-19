<?php

declare(strict_types=1);

namespace app\controllers;

use app\businesses\RegisterBusiness;
use support\Request;
use Webman\Http\Response;

final class RegisterController
{
    public function __construct(private readonly RegisterBusiness $business)
    {
    }

    public function __invoke(Request $request): Response
    {
        $result = $this->business->register(
            $request->post('email'),
            $request->post('username'),
            (string) $request->post('password', ''),
        );

        return json($result->toResponseArray())->withStatus($result->httpStatus());
    }
}
