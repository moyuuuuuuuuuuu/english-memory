<?php

declare(strict_types=1);

namespace app\controllers;

use app\businesses\GetStatsOverviewBusiness;
use support\Request;
use Webman\Http\Response;

final class GetStatsOverviewController
{
    public function __construct(private readonly GetStatsOverviewBusiness $business) {}
    public function __invoke(Request $request): Response
    {
        $result = $this->business->get((int) $request->authenticatedUserId());
        return json($result->toResponseArray())->withStatus($result->httpStatus());
    }
}
