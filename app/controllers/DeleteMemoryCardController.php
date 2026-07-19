<?php

declare(strict_types=1);

namespace app\controllers;

use app\businesses\DeleteMemoryCardBusiness;
use support\Request;
use Webman\Http\Response;

final class DeleteMemoryCardController
{
    public function __construct(private readonly DeleteMemoryCardBusiness $business) {}

    public function __invoke(Request $request, int|string $id): Response
    {
        $payload = (array) $request->post();
        $contentVersion = is_int($payload['content_version'] ?? null) ? $payload['content_version'] : 0;
        $result = $this->business->delete(
            (int) $request->authenticatedUserId(),
            (int) $id,
            $contentVersion,
        );

        return json($result->toResponseArray())->withStatus($result->httpStatus());
    }
}
