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
use app\businesses\RegisterBusiness;
use app\businesses\GenerateMemoryCardBusiness;
use app\businesses\LoginBusiness;
use app\controllers\CurrentUserController;
use app\controllers\RegisterController;
use app\controllers\GenerateMemoryCardController;
use app\controllers\LoginController;
use app\middleware\Authenticate;
use app\services\CozeWorkflowService;
use app\services\TokenService;
use GuzzleHttp\Client;
use support\Container;
use support\Request;
use Webman\Route;

Route::post('/api/auth/register', static function (Request $request) {
    return (new RegisterController(new RegisterBusiness()))($request);
});

Route::post('/api/auth/login', static function (Request $request) {
    return (new LoginController(new LoginBusiness(Container::get(TokenService::class))))($request);
});

Route::get('/api/auth/me', static function (Request $request) {
    return (new CurrentUserController(new CurrentUserBusiness()))($request);
})->middleware([Authenticate::class]);

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
