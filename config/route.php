<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use app\businesses\CurrentUserBusiness;
use app\common\base\SyncGenerationRoutePolicy;
use app\businesses\CreateMemoryCardBusiness;
use app\businesses\GetMemoryCardBusiness;
use app\businesses\ListMemoryCardsBusiness;
use app\businesses\UpdateMemoryCardBusiness;
use app\businesses\RetryMemoryCardBusiness;
use app\businesses\ForgotPasswordBusiness;
use app\businesses\RegisterBusiness;
use app\businesses\GenerateMemoryCardBusiness;
use app\businesses\LoginBusiness;
use app\businesses\LogoutBusiness;
use app\businesses\RefreshSessionBusiness;
use app\businesses\ResetPasswordBusiness;
use app\controllers\CurrentUserController;
use app\controllers\CreateMemoryCardController;
use app\controllers\GetMemoryCardController;
use app\controllers\ListMemoryCardsController;
use app\controllers\UpdateMemoryCardController;
use app\controllers\RetryMemoryCardController;
use app\controllers\ForgotPasswordController;
use app\controllers\RegisterController;
use app\controllers\GenerateMemoryCardController;
use app\controllers\LoginController;
use app\controllers\LogoutController;
use app\controllers\RefreshSessionController;
use app\controllers\ResetPasswordController;
use app\middleware\Authenticate;
use app\services\CozeWorkflowService;
use app\services\TokenService;
use app\services\contracts\PasswordResetMail;
use app\services\contracts\AiGenerationQueue;
use GuzzleHttp\Client;
use support\Container;
use support\Request;
use Webman\Route;

Route::post('/api/auth/register', static function (Request $request) {
    return (new RegisterController(new RegisterBusiness()))($request);
});

Route::post('/api/auth/login', static function (Request $request) {
    return (new LoginController(new LoginBusiness(
        Container::get(TokenService::class),
        (int) config('auth.refresh_token_ttl', 2592000),
    )))($request);
});

Route::get('/api/auth/me', static function (Request $request) {
    return (new CurrentUserController(new CurrentUserBusiness()))($request);
})->middleware([Authenticate::class]);

Route::post('/api/auth/refresh', static function (Request $request) {
    return (new RefreshSessionController(new RefreshSessionBusiness(
        Container::get(TokenService::class),
        (int) config('auth.refresh_token_ttl', 2592000),
    )))($request);
});

Route::post('/api/auth/logout', static function (Request $request) {
    return (new LogoutController(new LogoutBusiness(Container::get(TokenService::class))))($request);
})->middleware([Authenticate::class]);

Route::post('/api/auth/forgot-password', static function (Request $request) {
    return (new ForgotPasswordController(new ForgotPasswordBusiness(
        Container::get(PasswordResetMail::class),
        (int) config('auth.password_reset_ttl', 1800),
    )))($request);
});

Route::post('/api/auth/reset-password', static function (Request $request) {
    return (new ResetPasswordController(new ResetPasswordBusiness()))($request);
});

Route::post('/api/memory-cards', static function (Request $request) {
    return (new CreateMemoryCardController(new CreateMemoryCardBusiness(
        Container::get(AiGenerationQueue::class),
    )))($request);
})->middleware([Authenticate::class]);

Route::get('/api/memory-cards', static function (Request $request) {
    return (new ListMemoryCardsController(new ListMemoryCardsBusiness()))($request);
})->middleware([Authenticate::class]);

Route::get('/api/memory-cards/{id}', static function (Request $request, string $id) {
    return (new GetMemoryCardController(new GetMemoryCardBusiness()))($request, $id);
})->middleware([Authenticate::class]);

Route::patch('/api/memory-cards/{id}', static function (Request $request, string $id) {
    return (new UpdateMemoryCardController(new UpdateMemoryCardBusiness()))($request, $id);
})->middleware([Authenticate::class]);

Route::post('/api/memory-cards/{id}/retry', static function (Request $request, string $id) {
    return (new RetryMemoryCardController(new RetryMemoryCardBusiness(
        Container::get(AiGenerationQueue::class),
    )))($request, $id);
})->middleware([Authenticate::class]);

if (SyncGenerationRoutePolicy::enabled(
    (string) (getenv('APP_ENV') ?: 'production'),
    getenv('ENABLE_SYNC_GENERATION') ?: false,
)) {
    Route::post('/api/memory-cards/generate', static function (Request $request) {
        $coze = config('coze');
        $generator = new CozeWorkflowService(
            new Client(),
            $coze['api_base'],
            $coze['workflow_id'],
            $coze['access_token'],
            $coze['timeout'],
        );
        $business = new GenerateMemoryCardBusiness($generator);
        $controller = new GenerateMemoryCardController($business);
        return $controller($request);
    });
}
