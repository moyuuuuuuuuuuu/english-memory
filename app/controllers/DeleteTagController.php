<?php
declare(strict_types=1);
namespace app\controllers;
use app\businesses\DeleteTagBusiness;
use support\Request;
use Webman\Http\Response;
final class DeleteTagController
{
    public function __construct(private readonly DeleteTagBusiness $business) {}
    public function __invoke(Request $request, int|string $id): Response
    {
        $version = $request->post('sync_version');
        $result = $this->business->delete(
            (int) $request->authenticatedUserId(),
            (int) $id,
            is_int($version) ? $version : 0,
        );
        return json($result->toResponseArray())->withStatus($result->httpStatus());
    }
}
