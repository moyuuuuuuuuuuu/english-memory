<?php
declare(strict_types=1);
namespace app\controllers;
use app\businesses\RenameTagBusiness;
use support\Request;
use Webman\Http\Response;
final class RenameTagController
{
    public function __construct(private readonly RenameTagBusiness $business) {}
    public function __invoke(Request $request, int|string $id): Response
    {
        $result = $this->business->rename(
            (int) $request->authenticatedUserId(),
            (int) $id,
            is_int($request->post('sync_version')) ? $request->post('sync_version') : 0,
            (string) $request->post('name', ''),
        );
        return json($result->toResponseArray())->withStatus($result->httpStatus());
    }
}
