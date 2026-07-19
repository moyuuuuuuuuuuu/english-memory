<?php

declare(strict_types=1);

namespace app\controllers;

use app\businesses\UpdateTimezoneBusiness;
use support\Request;
use Webman\Http\Response;

final class UpdateTimezoneController
{
    public function __construct(private readonly UpdateTimezoneBusiness $business) {}
    public function __invoke(Request $request): Response
    {
        $result = $this->business->update((int) $request->authenticatedUserId(), (string) $request->post('timezone', ''));
        return json($result->toResponseArray())->withStatus($result->httpStatus());
    }
}
