<?php

declare(strict_types=1);

namespace app\controllers;

use app\businesses\SubmitReviewAnswerBusiness;
use support\Request;
use Webman\Http\Response;

final class SubmitReviewAnswerController
{
    public function __construct(private readonly SubmitReviewAnswerBusiness $business) {}
    public function __invoke(Request $request, int|string $id): Response
    {
        $responseMs = $request->post('response_ms');
        $result = $this->business->submit((int)$request->authenticatedUserId(), (int)$id, (string)$request->header('Idempotency-Key',''), (string)$request->post('mode',''), (string)$request->post('answer',''), (string)$request->post('difficulty',''), is_int($responseMs) ? $responseMs : -1);
        return json($result->toResponseArray())->withStatus($result->httpStatus());
    }
}
