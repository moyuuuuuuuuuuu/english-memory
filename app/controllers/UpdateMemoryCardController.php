<?php

declare(strict_types=1);

namespace app\controllers;

use app\businesses\UpdateMemoryCardBusiness;
use support\Request;
use Webman\Http\Response;

final class UpdateMemoryCardController
{
    public function __construct(private readonly UpdateMemoryCardBusiness $business)
    {
    }

    public function __invoke(Request $request, int|string $id): Response
    {
        $payload = (array) $request->post();
        $contentVersion = is_int($payload['content_version'] ?? null) ? $payload['content_version'] : 0;
        unset($payload['content_version']);
        $result = $this->business->update(
            (int) $request->authenticatedUserId(),
            (int) $id,
            $contentVersion,
            $payload,
        );

        return json($result->toResponseArray())->withStatus($result->httpStatus());
    }
}
