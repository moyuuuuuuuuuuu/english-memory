<?php

declare(strict_types=1);

namespace app\controllers;

use app\businesses\GetSyncChangesBusiness;
use support\Request;
use Webman\Http\Response;

final class GetSyncChangesController
{
    public function __construct(private readonly GetSyncChangesBusiness $business) {}

    public function __invoke(Request $request): Response
    {
        $cursor = (string) $request->get('cursor', '0');
        $limit = (string) $request->get('limit', '200');
        $valid = preg_match('/^\d+$/', $cursor) === 1 && preg_match('/^\d+$/', $limit) === 1;
        $result = $this->business->get(
            (int) $request->authenticatedUserId(),
            $valid ? (int) $cursor : -1,
            $valid ? (int) $limit : 0,
        );
        return json($result->toResponseArray())->withStatus($result->httpStatus());
    }
}
