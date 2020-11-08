<?php

require './pdos/DatabasePdo.php';
require './pdos/IndexPdo.php';
require './pdos/JWTPdo.php';
require './vendor/autoload.php';

use \Monolog\Logger as Logger;
use Monolog\Handler\StreamHandler;

date_default_timezone_set('Asia/Seoul');
ini_set('default_charset', 'utf8mb4');

//에러출력하게 하는 코드
//error_reporting(E_ALL); ini_set("display_errors", 1);

//Main Server API
$dispatcher = FastRoute\simpleDispatcher(function (FastRoute\RouteCollector $r) {
    /* ******************   social sign-up   ****************** */
    $r->addRoute('POST', '/kakao-sign-in', ['IndexController', 'kakaoSignIn']);

    /* ******************   JWT   ****************** */
    //API no: 1
    $r->addRoute('POST', '/jwt', ['JWTController', 'createJwt']);   // JWT 생성: 로그인 + 해싱된 패스워드 검증 내용 추가
    //API no: 2
    $r->addRoute('GET', '/jwt', ['JWTController', 'validateJwt']);  // JWT 유효성 검사

    /* ******************   Test   ****************** */
    //API no: 3
    $r->addRoute('GET', '/', ['IndexController', 'index']);
    //API no: 4
    $r->addRoute('GET', '/users', ['IndexController', 'getUsers']);

    /* ******************   API   ****************** */
    //API no: 5
    $r->addRoute('POST', '/user', ['IndexController', 'createUser']); // 비밀번호 해싱 예시 추가
    //API no: 6
    $r->addRoute('GET', '/user', ['IndexController', 'getUserDetail']);
    //API no: 7
    $r->addRoute('PATCH', '/user', ['IndexController', 'editUserDetail']);
    //API no: 8
    $r->addRoute('DELETE', '/user', ['IndexController', 'deleteUser']);
    //API no: 9
    $r->addRoute('GET', '/coupons', ['IndexController', 'getUserCoupons']);
    //API no: 10
    $r->addRoute('GET', '/orders', ['IndexController', 'getUserOrders']);
    //API no: 11
    $r->addRoute('POST', '/order', ['IndexController', 'createOrder']);
    //API no: 12
    $r->addRoute('DELETE', '/order/{orderId}', ['IndexController', 'deleteOrder']);
    //API no: 13
    $r->addRoute('GET', '/order/{orderId}', ['IndexController', 'getOrderDetail']);
    //API no: 14
    $r->addRoute('GET', '/categories', ['IndexController', 'getCategories']);
    //API no: 15
    $r->addRoute('GET', '/categories/{categoryId}/subcategories', ['IndexController', 'getSubcategories']);
    //API no: 16
    $r->addRoute('GET', '/products', ['IndexController', 'getProducts']);
    //API no: 17
    $r->addRoute('GET', '/product/{productId}/options', ['IndexController', 'getProductOptions']);
    //API no: 18
    $r->addRoute('GET', '/product/{productId}/img', ['IndexController', 'getProductImg']);
    //API no: 19
    $r->addRoute('GET', '/product/{productId}/description', ['IndexController', 'getProductDescription']);
    //API no: 20
    $r->addRoute('POST', '/product', ['IndexController', 'addProduct']);
    //API no: 21
    $r->addRoute('DELETE', '/product/{productId}', ['IndexController', 'deleteProduct']);
    //API no: 22
    $r->addRoute('DELETE', '/product/option/{optionId}', ['IndexController', 'deleteProductOption']);
    //API no: 23
    $r->addRoute('POST', '/product/option/{optionId}/review', ['IndexController', 'createReview']);
    //API no: 24
    $r->addRoute('PATCH', '/product/option/review/{reviewId}', ['IndexController', 'modifyReview']);
    //API no: 25
    $r->addRoute('GET', '/product/{productId}/reviews', ['IndexController', 'getReviews']);
    //API no: 26
    $r->addRoute('GET', '/product/review/{reviewId}', ['IndexController', 'getReviewDetail']);



//    $r->addRoute('POST', '/product', ['IndexController', 'addProduct']);
//    $r->addRoute('DELETE', '/product/{productId}', ['IndexController', 'deleteProduct']);
//    $r->addRoute('POST', '/product/{productId}/option/{optionId}', ['IndexController', 'addProductOption']);
//    $r->addRoute('DELETE', '/product/{productId}/option/{optionId}', ['IndexController', 'deleteProductOption']);




//    $r->addRoute('GET', '/users', 'get_all_users_handler');
//    // {id} must be a number (\d+)
//    $r->addRoute('GET', '/user/{id:\d+}', 'get_user_handler');
//    // The /{title} suffix is optional
//    $r->addRoute('GET', '/articles/{id:\d+}[/{title}]', 'get_article_handler');
});

// Fetch method and URI from somewhere
$httpMethod = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Strip query string (?foo=bar) and decode URI
if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}
$uri = rawurldecode($uri);

$routeInfo = $dispatcher->dispatch($httpMethod, $uri);

// 로거 채널 생성
$accessLogs = new Logger('ACCESS_LOGS');
$errorLogs = new Logger('ERROR_LOGS');
// log/your.log 파일에 로그 생성. 로그 레벨은 Info
$accessLogs->pushHandler(new StreamHandler('logs/access.log', Logger::INFO));
$errorLogs->pushHandler(new StreamHandler('logs/errors.log', Logger::ERROR));
// add records to the log
//$log->addInfo('Info log');
// Debug 는 Info 레벨보다 낮으므로 아래 로그는 출력되지 않음
//$log->addDebug('Debug log');
//$log->addError('Error log');

switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::NOT_FOUND:
        // ... 404 Not Found
        echo "404 Not Found";
        break;
    case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        $allowedMethods = $routeInfo[1];
        // ... 405 Method Not Allowed
        echo "405 Method Not Allowed";
        break;
    case FastRoute\Dispatcher::FOUND:
        $handler = $routeInfo[1];
        $vars = $routeInfo[2];

        switch ($routeInfo[1][0]) {
            case 'IndexController':
                $handler = $routeInfo[1][1];
                $vars = $routeInfo[2];
                require './controllers/IndexController.php';
                break;
            case 'JWTController':
                $handler = $routeInfo[1][1];
                $vars = $routeInfo[2];
                require './controllers/JWTController.php';
                break;
            /*case 'EventController':
                $handler = $routeInfo[1][1]; $vars = $routeInfo[2];
                require './controllers/EventController.php';
                break;
            case 'ProductController':
                $handler = $routeInfo[1][1]; $vars = $routeInfo[2];
                require './controllers/ProductController.php';
                break;
            case 'SearchController':
                $handler = $routeInfo[1][1]; $vars = $routeInfo[2];
                require './controllers/SearchController.php';
                break;
            case 'ReviewController':
                $handler = $routeInfo[1][1]; $vars = $routeInfo[2];
                require './controllers/ReviewController.php';
                break;
            case 'ElementController':
                $handler = $routeInfo[1][1]; $vars = $routeInfo[2];
                require './controllers/ElementController.php';
                break;
            case 'AskFAQController':
                $handler = $routeInfo[1][1]; $vars = $routeInfo[2];
                require './controllers/AskFAQController.php';
                break;*/
        }

        break;
}