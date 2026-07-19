<?php

declare(strict_types=1);

namespace app\controllers;

use app\businesses\CurrentUserBusiness;
use support\Request;
use Webman\Http\Response;

final class CurrentUserController
{
    public function __construct(private readonly CurrentUserBusiness $business)
    {
    }

    public function __invoke(Request $request): Response
    {
        $result = $this->business->currentUser((int) $request->authenticatedUserId());

        return json($result->toResponseArray())->withStatus($result->httpStatus());
    }
}
