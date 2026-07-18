<?php

namespace app\controllers;

use app\businesses\GenerateMemoryCardBusiness;
use support\Request;
use Webman\Http\Response;

final class GenerateMemoryCardController
{
    public function __construct(private readonly GenerateMemoryCardBusiness $business)
    {
    }

    public function __invoke(Request $request): Response
    {
        $result = $this->business->generate(
            (string) $request->post('text', ''),
            (string) $request->post('content_type', 'word'),
            (string) $request->post('memory_style', 'auto'),
        );

        return json($result->toResponseArray())->withStatus($result->httpStatus());
    }
}
