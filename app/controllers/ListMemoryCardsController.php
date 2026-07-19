<?php

declare(strict_types=1);

namespace app\controllers;

use app\businesses\ListMemoryCardsBusiness;
use support\Request;
use Webman\Http\Response;

final class ListMemoryCardsController
{
    public function __construct(private readonly ListMemoryCardsBusiness $business)
    {
    }

    public function __invoke(Request $request): Response
    {
        $result = $this->business->list((int) $request->authenticatedUserId(), [
            'q' => $request->get('q', ''),
            'content_type' => $request->get('content_type', ''),
            'is_favorite' => $request->get('is_favorite', ''),
            'tag_id' => $request->get('tag_id', ''),
            'status' => $request->get('status', ''),
            'cursor' => $request->get('cursor', ''),
            'limit' => $request->get('limit', '20'),
        ]);

        return json($result->toResponseArray())->withStatus($result->httpStatus());
    }
}
