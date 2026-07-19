<?php

declare(strict_types=1);

namespace app\middleware;

use app\common\enums\BusinessCode;

use app\models\User;
use app\services\TokenService;
use support\Request as AppRequest;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

final class Authenticate implements MiddlewareInterface
{
    public function __construct(private readonly TokenService $tokens)
    {
    }

    public function process(Request $request, callable $handler): Response
    {
        $authorization = (string) $request->header('authorization', '');
        if (preg_match('/^Bearer\s+(\S+)$/i', trim($authorization), $matches) !== 1) {
            return $this->unauthenticated();
        }

        $identity = $this->tokens->resolveAccessToken($matches[1]);
        if ($identity === null) {
            return $this->unauthenticated();
        }

        $user = User::query()->whereKey($identity->userId())->where('status', 'active')->first();
        if ($user === null
            || (int) $user->session_version !== $identity->sessionVersion()
            || !$request instanceof AppRequest) {
            return $this->unauthenticated();
        }

        $request->setAuthenticatedUserId($identity->userId());

        return $handler($request);
    }

    private function unauthenticated(): Response
    {
        return json([
            'success' => false,
            'error' => [
                'code' => BusinessCode::Unauthenticated->value,
                'message' => '请先登录。',
            ],
        ])->withStatus(401);
    }
}
