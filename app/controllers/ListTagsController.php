<?php
declare(strict_types=1);
namespace app\controllers;
use app\businesses\ListTagsBusiness;
use support\Request;
use Webman\Http\Response;
final class ListTagsController
{
    public function __construct(private readonly ListTagsBusiness $business) {}
    public function __invoke(Request $request): Response
    {
        $result = $this->business->list((int) $request->authenticatedUserId());
        return json($result->toResponseArray())->withStatus($result->httpStatus());
    }
}
