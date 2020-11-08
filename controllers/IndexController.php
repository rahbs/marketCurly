<?php
require 'function.php';

const JWT_SECRET_KEY = "TEST_KEYTEST_KEYTEST_KEYTEST_KEYTEST_KEYTEST_KEYTEST_KEYTEST_KEYTEST_KEYTEST_KEYTEST_KEYTEST_KEYTEST_KEY";

$res = (object)array();
header('Content-Type: json');
$req = json_decode(file_get_contents("php://input"));
try {
    #addAccessLogs($accessLogs, $req);
    switch ($handler) {
        case "index":
            echo "API Server";
            break;
        case "ACCESS_LOGS":
            //            header('content-type text/html charset=utf-8');
            header('Content-Type: text/html; charset=UTF-8');
            getLogs("./logs/access.log");
            break;
        case "ERROR_LOGS":
            //            header('content-type text/html charset=utf-8');
            header('Content-Type: text/html; charset=UTF-8');
            getLogs("./logs/errors.log");
            break;

        case "kakaoSignIn":
            http_response_code(200);
            $accessToken = $req->access_token;
            $kakaoUserInfo = getKakaoUserInfo($accessToken);
            $userId = $kakaoUserInfo['id'];
            $userName = $kakaoUserInfo['name'];
            $userEmail = $kakaoUserInfo['email'];
            if ($userId == null){
                $res->is_success = FALSE;
                $res->code = 200;
                $res->message = "access_token이 올바르지 않습니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                break;
            }
            if(isExistingUserId($userId)){
                if(checkUserInfo($userId, $userName, $userEmail)){
                    $res->code = 100;
                    $res->message = "카카오 로그인 성공";
                }
                else{
                    $res->code = 300;
                    $res->message = "카카오 로그인 실패";
                }
            }
            else{
                $pwd = "0000";
                $phoneNum = "010-0000-0000";
                createUser($userId, $userName, $pwd, $userEmail, $phoneNum);
                $res->code = 101;
                $res->message = "카카오 회원 등록 성공";
            }
            // JWT 발급
            // Payload에 맞게 다시 설정 요함, 아래는 Payload에 userIdx를 넣기 위한 과정
            $userIdx = getUserIdxByID($userId);  // JWTPdo.php 에 구현
            // JWT 발급
            $jwt = getJWT($userIdx, JWT_SECRET_KEY); // function.php 에 구현
            $res->result->jwt = $jwt;
            $res->is_success = TRUE;

            echo json_encode($res, JSON_NUMERIC_CHECK);
            break;

        /*
         * API No. 4
         * API Name : 모든 유저 조회 테스트 API
         * 마지막 수정 날짜 : 20.10.15
         */
        case "getUsers":
            http_response_code(200);

            $res->result = getUsers();
            $res->is_success = TRUE;
            $res->code = 100;
            $res->message = "테스트 성공";
            echo json_encode($res, JSON_NUMERIC_CHECK);
            break;
        /*
         * API No. 5
         * API Name : 유저 정보 등록 (회원 가입)
         * 마지막 수정 날짜 : 20.10.21
         */
        case "createUser":
            http_response_code(200);
            // body에 값들이 잘 들어왔는지 check
            if (!isset($req->id)) {
                $res->is_success = FALSE;
                $res->code = 200;
                $res->message = "user_id 값이 없습니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                break;
            }
            if (!isset($req->name)){
                $res->is_success = FALSE;
                $res->code = 201;
                $res->message = "name 값이 없습니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                break;
            }
            if (!isset($req->pwd)){
                $res->is_success = FALSE;
                $res->code = 202;
                $res->message = "pwd 값이 없습니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                break;
            }
            if (!isset($req->email)){
                $res->is_success = FALSE;
                $res->code = 203;
                $res->message = "email 값이 없습니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                break;
            }
            if (!isset($req->phone_num)){
                $res->is_success = FALSE;
                $res->code = 204;
                $res->message = "phone_num 값이 없습니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                break;
            }

            // Packet의 Body에서 데이터 파싱
            $userId = $req->id;
            $userName = $req->name;
            $userPwdHash  = password_hash($req->pwd, PASSWORD_DEFAULT); //password hash
            $userEmail = $req->email;
            $userPhoneNum = $req->phone_num;

            // id type 체크
            if (gettype($userId) != 'string'){
                $res->is_success = FALSE;
                $res->code = 300;
                $res->message = "id의 type이 올바르지 않습니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                break;
            }
            // name type 체크
            if (gettype($userName) != 'string'){
                $res->is_success = FALSE;
                $res->code = 301;
                $res->message = "name의 type이 올바르지 않습니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                break;
            }
            // pwd type 체크
            if (gettype($req->pwd) != 'string'){
                $res->is_success = FALSE;
                $res->code = 302;
                $res->message = "pwd의 type이 올바르지 않습니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                break;
            }
            // email type 체크
            if (gettype($userEmail) != 'string'){
                $res->is_success = FALSE;
                $res->code = 303;
                $res->message = "email의 type이 올바르지 않습니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                break;
            }
            // id 중복 체크
            if(isExistingUserId($userId)){
                $res->is_success = FALSE;
                $res->code = 400;
                $res->message = "이미 존재하는 id 입니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                break;
            }
            // email 형식 체크
            $check_email = preg_match("/^[_\.0-9a-zA-Z-]+@([0-9a-zA-Z][0-9a-zA-Z-]+\.)+[a-zA-Z]{2,6}$/i", $userEmail);
            if(!$check_email){
                $res->is_success = FALSE;
                $res->code = 401;
                $res->message = "올바르지 않은 email 형식입니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                break;
            }

            // user 생성 (회원가입)
            createUser($userId, $userName, $userPwdHash, $userEmail, $userPhoneNum);
            // 생성된 user의 user_idx 반환
            $res->result->user_idx = getUserIdxByID($userId);
            $res->is_success = TRUE;
            $res->code = 100;
            $res->message = "회원 등록 성공";
            echo json_encode($res, JSON_NUMERIC_CHECK);
            break;

        /*
         * API No. 6
         * API Name : 로그인된 user 정보 조회
         * 마지막 수정 날짜 : 20.10.21
         */
        case "getUserDetail":
            http_response_code(200);

            // header에서 jwt token 받아온다
            $jwt = $_SERVER['HTTP_X_ACCESS_TOKEN'];
            // Payload에 있는 userIdx 가져오기
            $userIdxInToken = getDataByJWToken($jwt,JWT_SECRET_KEY)->userIdx;

            if (!isValidJWT($jwt, JWT_SECRET_KEY)) { // function.php 에 구현
                $res->isSuccess = FALSE;
                $res->code = 200;
                $res->message = "권한이 없는 유저입니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                addErrorLogs($errorLogs, $res, $req);
                return;
            }
//            if ($userIdxInToken != $vars['userIdx']){
//                $res->is_success = FALSE;
//                $res->code = 202;
//                $res->message = "권한이 없는 유저입니다.";
//                echo json_encode($res, JSON_NUMERIC_CHECK);
//                break;
//            }
//            $userIdx = $vars['userIdx'];
//            // user_idx 체크
//            if(!isExistingUserIdx($userIdx)){
//                $res->is_success = FALSE;
//                $res->code = 201;
//                $res->message = "유효하지 않은 user_idx 입니다.";
//                echo json_encode($res, JSON_NUMERIC_CHECK);
//                break;
//            }

            $res->result = getUserDetail($userIdxInToken);
            $res->is_success = TRUE;
            $res->code = 100;
            $res->message = "회원 조회 성공";
            echo json_encode($res, JSON_NUMERIC_CHECK);
            break;

        /*
         * API No. 7
         * API Name : 로그인된 user 정보 수정
         * 마지막 수정 날짜 : 20.10.21
         */
        case "editUserDetail":
            http_response_code(200);

            // header에서 jwt token 받아온다
            $jwt = $_SERVER['HTTP_X_ACCESS_TOKEN'];
            // Payload에 있는 userIdx 가져오기
            $userIdxInToken = getDataByJWToken($jwt,JWT_SECRET_KEY)->userIdx;

            if (!isValidJWT($jwt, JWT_SECRET_KEY)) { // function.php 에 구현
                $res->isSuccess = FALSE;
                $res->code = 200;
                $res->message = "권한이 없는 유저입니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                addErrorLogs($errorLogs, $res, $req);
                return;
            }
            // Packet의 Body에서 데이터 파싱
            $userId = Null;
            $userPwd = Null;
            if (isset($req->id)){
                $userId = $req->id;
            }
            if (isset($req->pwd)){
                $userPwd = $req->pwd;
            }

            // id 중복 체크
            if(!is_null($userId) and isExistingUserId($userId)){
                $res->is_success = FALSE;
                $res->code = 300;
                $res->message = "이미 존재하는 id 입니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                break;
            }

            editUserDetail($userIdxInToken, $userId, $userPwd);
            $res->is_success = TRUE;
            $res->code = 100;
            $res->message = "회원 정보 수정 완료";
            echo json_encode($res, JSON_NUMERIC_CHECK);
            break;

        /*
         * API No. 8
         * API Name : 로그인된 user 정보 삭제
         * 마지막 수정 날짜 : 20.10.21
         */
        case "deleteUser":
            http_response_code(200);

            // header에서 jwt token 받아온다
            $jwt = $_SERVER['HTTP_X_ACCESS_TOKEN'];
            // Payload에 있는 userIdx 가져오기
            $userIdxInToken = getDataByJWToken($jwt,JWT_SECRET_KEY)->userIdx;
            //body에서 pwd 가져오기
            $inputPwd = $req->pwd;

            if (!isValidJWT($jwt, JWT_SECRET_KEY) or !isExistingUserIdx($userIdxInToken)) { // function.php 에 구현
                $res->isSuccess = FALSE;
                $res->code = 200;
                $res->message = "권한이 없는 user입니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                addErrorLogs($errorLogs, $res, $req);
                break;
            }

            if(!isValidPwd($userIdxInToken,$inputPwd)){
                $res->isSuccess = FALSE;
                $res->code = 300;
                $res->message = "pwd 값이 틀렸습니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                addErrorLogs($errorLogs, $res, $req);
                break;
            }

            deleteUser($userIdxInToken);
            $res->is_success = TRUE;
            $res->code = 100;
            $res->message = "회원 정보 삭제 완료";
            echo json_encode($res, JSON_NUMERIC_CHECK);
            break;
        /*
        * API No. 9
        * API Name : 로그인된 user의 모든 coupon 정보 조회
        * 마지막 수정 날짜 : 20.10.21
        */
        case "getUserCoupons":
            http_response_code(200);
            // header에서 jwt token 받아온다
            $jwt = $_SERVER['HTTP_X_ACCESS_TOKEN'];
            // Payload에 있는 userIdx 가져오기
            $userIdxInToken = getDataByJWToken($jwt,JWT_SECRET_KEY)->userIdx;

            // 토큰 유효성 검사
            if (!isValidJWT($jwt, JWT_SECRET_KEY)) { // function.php 에 구현
                $res->isSuccess = FALSE;
                $res->code = 200;
                $res->message = "권한이 없는 user입니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                addErrorLogs($errorLogs, $res, $req);
                break;
            }
            $res->result = getUserCoupons($userIdxInToken);
            $res->is_success = TRUE;
            $res->code = 100;
            $res->message = "user의 coupon 정보 조회 성공";
            echo json_encode($res, JSON_NUMERIC_CHECK);
            break;
        /*
        * API No. 10
        * API Name : 로그인된 user의 모든 주문 정보 조회
        * 마지막 수정 날짜 : 20.10.21
        */
        case "getUserOrders":
            http_response_code(200);
            // header에서 jwt token 받아온다
            $jwt = $_SERVER['HTTP_X_ACCESS_TOKEN'];
            // Payload에 있는 userIdx 가져오기
            $userIdxInToken = getDataByJWToken($jwt,JWT_SECRET_KEY)->userIdx;

            // 토큰 유효성 검사
            if (!isValidJWT($jwt, JWT_SECRET_KEY)) { // function.php 에 구현
                $res->isSuccess = FALSE;
                $res->code = 200;
                $res->message = "권한이 없는 user입니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                addErrorLogs($errorLogs, $res, $req);
                break;
            }
            $res->result = getUserOrders($userIdxInToken);
            $res->is_success = TRUE;
            $res->code = 100;
            $res->message = "user의 모든 주문 정보 조회 성공";
            echo json_encode($res, JSON_NUMERIC_CHECK);
            break;
        /*
        * API No. 11
        * API Name : 주문 생성
        * 마지막 수정 날짜 : 20.10.21
        */
        case "createOrder":
            http_response_code(200);
            // header에서 jwt token 받아온다
            $jwt = $_SERVER['HTTP_X_ACCESS_TOKEN'];
            // Payload에 있는 userIdx 가져오기
            $userIdxInToken = getDataByJWToken($jwt,JWT_SECRET_KEY)->userIdx;
            // 토큰 유효성 검사
            if (!isValidJWT($jwt, JWT_SECRET_KEY)) { // function.php 에 구현
                $res->isSuccess = FALSE;
                $res->code = 200;
                $res->message = "권한이 없는 user입니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                addErrorLogs($errorLogs, $res, $req);
                break;
            }

            // Packet의 Body에서 데이터 파싱
            $paymentMethod = $req->payment_method;
            $shippingFee = $req->shipping_fee;
            $totalPrice = $req->total_price;
            $products = $req->products;
            $address = $req->products;
            $entrance_access_method = $req->entrance_access_method;
            $receiving_place = $req->receiving_place;
            $couponId = Null;
            $usedCredit = Null;
            $exitOuterLoop = FALSE;
            if(isset($req->coupon_id)) {
                $couponId = $req->coupon_id;
            }
            if(isset($req->used_credit)) {
                $usedCredit = $req->used_credit;
            }
            // products validation
            foreach ($products as $product){
                $productId = $product->product_id;
                $productNum = $product->product_num;
                // product_id validation
                if(!isExistingProductId($productId)){
                    $res->is_success = FALSE;
                    $res->code = 300;
                    $res->message = "유효하지 않은 product_id 입니다.";
                    $exitOuterLoop = TRUE;
                    echo json_encode($res, JSON_NUMERIC_CHECK);
                    break;
                }
                // product_num validation
                else if(!isAvailableProductNum($productId, $productNum)){
                    $res->is_success = FALSE;
                    $res->code = 301;
                    $res->message = "재고가 부족합니다.";
                    $exitOuterLoop = TRUE;
                    echo json_encode($res, JSON_NUMERIC_CHECK);
                    break;
                }
            }
            if ($exitOuterLoop == TRUE){
                break;
            }

            $res->order_id = createOrder($userIdxInToken, $paymentMethod, $shippingFee, $totalPrice, $address, $entrance_access_method, $receiving_place, $products, $couponId, $usedCredit);
            $res->is_success = TRUE;
            $res->code = 100;
            $res->message = "주문 성공";
            echo json_encode($res, JSON_NUMERIC_CHECK);
            break;

        /*
        * API No. 12
        * API Name : 주문 삭제
        * 마지막 수정 날짜 : 20.10.21
        */
        case "deleteOrder":
            http_response_code(200);
            // header에서 jwt token 받아온다
            $jwt = $_SERVER['HTTP_X_ACCESS_TOKEN'];
            // Payload에 있는 userIdx 가져오기
            $userIdxInToken = getDataByJWToken($jwt,JWT_SECRET_KEY)->userIdx;
            // 토큰 유효성 검사
            if (!isValidJWT($jwt, JWT_SECRET_KEY)) { // function.php 에 구현
                $res->isSuccess = FALSE;
                $res->code = 200;
                $res->message = "권한이 없는 user입니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                addErrorLogs($errorLogs, $res, $req);
                break;
            }
            // order_id validation
            $orderId = $vars['orderId'];
            if(!isExistingOrderId($orderId)){
                $res->is_success = FALSE;
                $res->code = 201;
                $res->message = "유효하지 않은 order_id 입니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                break;
            }
            // delivery_status 가 'C' (입금확인) 일 때만 삭제 가능
            if(!isDeletableOrder($orderId)){
                $res->is_success = FALSE;
                $res->code = 202;
                $res->message = "삭제가 불가능한 order입니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                break;
            }

            deleteOrder($userIdxInToken, $orderId);
            $res->is_success = TRUE;
            $res->code = 100;
            $res->message = "주문 삭제 완료";
            echo json_encode($res, JSON_NUMERIC_CHECK);
            break;
        /*
        * API No. 13
        * API Name : 주문 내역 상세 조회
        * 마지막 수정 날짜 : 20.10.21
        */
        case "getOrderDetail":
            http_response_code(200);
            // header에서 jwt token 받아온다
            $jwt = $_SERVER['HTTP_X_ACCESS_TOKEN'];
            // Payload에 있는 userIdx 가져오기
            $userIdxInToken = getDataByJWToken($jwt,JWT_SECRET_KEY)->userIdx;
            // 토큰 유효성 검사
            if (!isValidJWT($jwt, JWT_SECRET_KEY)) { // function.php 에 구현
                $res->isSuccess = FALSE;
                $res->code = 200;
                $res->message = "권한이 없는 user입니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                addErrorLogs($errorLogs, $res, $req);
                break;
            }
            $orderId = $vars['orderId'];
            // orderId validation
            if(!isExistingOrderId($orderId)){
                $res->is_success = FALSE;
                $res->code = 200;
                $res->message = "유효하지 않은 order_id 입니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                break;
            }
            $res->result = getOrderDetail($userIdxInToken,$orderId);
            $res->is_success = TRUE;
            $res->code = 100;
            $res->message = "주문 내역 상세 조회 성공";
            echo json_encode($res, JSON_NUMERIC_CHECK);
            break;

        /*
        * API No. 14
        * API Name : 전체 카테고리 리스트 조회
        * 마지막 수정 날짜 : 20.10.21
        */
        case "getCategories":
            http_response_code(200);
            $res->result = getCategories();
            $res->is_success = TRUE;
            $res->code = 100;
            $res->message = "카테고리 리스트 조회 성공";
            echo json_encode($res, JSON_NUMERIC_CHECK);
            break;
        /*
        * API No. 15
        * API Name : 특정 카테고리의 서브카테고리 리스트 조회
        * 마지막 수정 날짜 : 20.10.21
        */
        case "getSubcategories":
            $categoryId = $vars['categoryId'];
            $res->result = getSubcategories($categoryId);
            $res->is_success = TRUE;
            $res->code = 100;
            $res->message = "서브카테고리 리스트 조회 성공";
            echo json_encode($res, JSON_NUMERIC_CHECK);
            break;
        /*
        * API No. 16
        * API Name : 상품 리스트 조회
        * 마지막 수정 날짜 : 20.10.21
        */
        case "getProducts":
            //default value of order : new
            $order = 'new';
            $filter = 'all';
            if(isset($_GET['order'])){
                $order = $_GET['order'];
                // query string의 order에 올바른 값이 들어왔는지 확인
                if(!isValidOrderType($order)){
                    $res->is_success = FALSE;
                    $res->code = 200;
                    $res->message = "유효하지 않은 order 값입니다.";
                    echo json_encode($res, JSON_NUMERIC_CHECK);
                    break;
                }
            }
            if(isset($_GET['filter'])){
                $filter = $_GET['filter'];
                // query string의 filter에 올바른 값이 들어왔는지 확인
                if(!isValidFilterType($filter)){
                    $res->is_success = FALSE;
                    $res->code = 201;
                    $res->message = "유효하지 않은 filter 값입니다.";
                    echo json_encode($res, JSON_NUMERIC_CHECK);
                    break;
                }
            }
            $res->result = getProducts($order, $filter);
            $res->is_success = TRUE;
            $res->code = 100;
            $res->message = "상품 리스트 조회 성공";
            echo json_encode($res, JSON_NUMERIC_CHECK);
            break;
        /*
        * API No. 17
        * API Name : 특정 상품의 모든 옵션 조회
        * 마지막 수정 날짜 : 20.10.21
        */
        case "getProductOptions":
            $productId = $vars['productId'];
            //productId validation
            if(!isExistingProductInfoId($productId)){
                $res->is_success = FALSE;
                $res->code = 200;
                $res->message = "유효하지 않은 product_id 입니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                break;
            }
            $res->result = getProductOptions($productId);
            $res->is_success = TRUE;
            $res->code = 100;
            $res->message = "상품 옵션리스트 조회 성공";
            echo json_encode($res, JSON_NUMERIC_CHECK);
            break;
        /*
        * API No. 18
        * API Name : 특정 상품의 상세이미지(영양성분표) 조회
        * 마지막 수정 날짜 : 20.10.21
        */
        case "getProductImg":
            $productId = $vars['productId'];
            //productId validation
            if(!isExistingProductInfoId($productId)){
                $res->is_success = FALSE;
                $res->code = 200;
                $res->message = "유효하지 않은 product_id 입니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                break;
            }
            $res->result = getProductImg($productId);
            $res->is_success = TRUE;
            $res->code = 100;
            $res->message = "상품 상세 이미지 조회 성공";
            echo json_encode($res, JSON_NUMERIC_CHECK);
            break;
        /*
        * API No. 19
        * API Name : 특정 상품의 상품설명 조회
        * 마지막 수정 날짜 : 20.10.21
        */
        case "getProductDescription":
            http_response_code(200);
            $productId = $vars['productId'];
            // header에서 jwt token 받아온다
            $jwt = $_SERVER['HTTP_X_ACCESS_TOKEN'];
            // Payload에 있는 userIdx 가져오기
            $userIdxInToken = getDataByJWToken($jwt,JWT_SECRET_KEY)->userIdx;
            // 토큰 유효성 검사
            if (!isValidJWT($jwt, JWT_SECRET_KEY)) { // function.php 에 구현
                $res->isSuccess = FALSE;
                $res->code = 200;
                $res->message = "권한이 없는 user입니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                addErrorLogs($errorLogs, $res, $req);
                break;
            }
            //productId validation
            if(!isExistingProductInfoId($productId)){
                $res->is_success = FALSE;
                $res->code = 201;
                $res->message = "유효하지 않은 product_id 입니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                break;
            }

            $res->result = getProductDescription($userIdxInToken,$productId);
            $res->is_success = TRUE;
            $res->code = 100;
            $res->message = "상품 설명 조회 성공";
            echo json_encode($res, JSON_NUMERIC_CHECK);
            break;
        /*
        * API No. 20
        * API Name : 상품 등록
        * 마지막 수정 날짜 : 20.10.21
        */
        case "addProduct":
            http_response_code(200);
            $title = $req->title;
            $sub_title = $req->sub_title;
            $image_url = $req->image_url;
            $subcategory_id = $req->subcategory_id;
            $description_html_url = $req->description_html_url;
            $nutrition_facts_image_url = $req->nutrition_facts_image_url;
            $options = $req->options;

            $delivery_method = Null;
            $origin = Null;
            $unit = Null;
            $packing_type = Null;
            if (isset($req->delivery_method)){
                $delivery_method = $req->delivery_method;
            }
            if (isset($req->origin)){
                $origin = $req->origin;
            }
            if (isset($req->unit)){
                $unit = $req->unit;
            }
            if (isset($req->packing_type)){
                $packing_type = $req->packing_type;
            }

            $res->product_id = addProduct($title, $sub_title, $image_url, $subcategory_id, $description_html_url, $nutrition_facts_image_url,$options, $delivery_method, $origin, $unit, $packing_type);
            $res->is_success = TRUE;
            $res->code = 100;
            $res->message = "상품 등록 성공";
            echo json_encode($res, JSON_NUMERIC_CHECK);
            break;
        /*
        * API No. 21
        * API Name : 상품 삭제
        * 마지막 수정 날짜 : 20.10.21
        */
        case "deleteProduct":
            http_response_code(200);
            $productId = $vars['productId'];
            //productId validation
            if(!isExistingProductInfoId($productId)){
                $res->is_success = FALSE;
                $res->code = 200;
                $res->message = "유효하지 않은 product_id 입니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                break;
            }
            deleteProduct($productId);
            $res->is_success = TRUE;
            $res->code = 100;
            $res->message = "상품 삭제 성공";
            echo json_encode($res, JSON_NUMERIC_CHECK);
            break;
        /*
        * API No. 22
        * API Name : 상품 옵션 삭제
        * 마지막 수정 날짜 : 20.10.21
        */
        case "deleteProductOption":
            http_response_code(200);
            $productOptionId = $vars['productOptionId'];
            //productId validation
            if(!isExistingProductOptionId($productOptionId)){
                $res->is_success = FALSE;
                $res->code = 200;
                $res->message = "유효하지 않은 product_option_id 입니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                break;
            }
            deleteProductOption($productOptionId);
            $res->is_success = TRUE;
            $res->code = 100;
            $res->message = "상품 옵션 삭제 성공";
            echo json_encode($res, JSON_NUMERIC_CHECK);
            break;
        /*
        * API No. 23
        * API Name : 상품 리뷰 등록
        * 마지막 수정 날짜 : 20.10.23
        */
        case "createReview":
            http_response_code(200);
            // header에서 jwt token 받아온다
            $jwt = $_SERVER['HTTP_X_ACCESS_TOKEN'];
            // Payload에 있는 userIdx 가져오기
            $userIdxInToken = getDataByJWToken($jwt,JWT_SECRET_KEY)->userIdx;

            if (!isValidJWT($jwt, JWT_SECRET_KEY)) { // function.php 에 구현
                $res->isSuccess = FALSE;
                $res->code = 200;
                $res->message = "권한이 없는 유저입니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                addErrorLogs($errorLogs, $res, $req);
                return;
            }
            $productId = $vars['optionId'];

            if (!isset($req->title)){
                $res->is_success = FALSE;
                $res->code = 201;
                $res->message = "제목을 입력해 주세요.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                break;
            }
            if (!isset($req->contents)){
                $res->is_success = FALSE;
                $res->code = 202;
                $res->message = "후기를 입력해주세요";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                break;
            }

            $title = $req->title;
            $contents = $req->contents;
            $img_url = Null;
            if (isset($req->img_url)){
                $img_url = $req->img_url;
            }
            createReview($userIdxInToken, $productId, $title, $contents, $img_url);
            $res->is_success = TRUE;
            $res->code = 100;
            $res->message = "상품 후기 등록 성공";
            echo json_encode($res, JSON_NUMERIC_CHECK);
            break;
        /*
        * API No. 24
        * API Name : 상품 리뷰 수정
        * 마지막 수정 날짜 : 20.10.23
        */
        case "modifyReview":
            http_response_code(200);
            // header에서 jwt token 받아온다
            $jwt = $_SERVER['HTTP_X_ACCESS_TOKEN'];
            // Payload에 있는 userIdx 가져오기
            $userIdxInToken = getDataByJWToken($jwt,JWT_SECRET_KEY)->userIdx;

            if (!isValidJWT($jwt, JWT_SECRET_KEY)) { // function.php 에 구현
                $res->isSuccess = FALSE;
                $res->code = 200;
                $res->message = "권한이 없는 유저입니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                return;
            }
            $reviewId = (int)$vars['reviewId'];
            if(!isExistingReviewId($reviewId)){
                $res->isSuccess = FALSE;
                $res->code = 201;
                $res->message = "존재하지 않는 review_id 입니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                return;
            }
            $contents = Null;
            $title = Null;
            $img_url = Null;
            if (isset($req->contents)){
                $contents =  $req->contents;
            }
            if (isset($req->title)){
                $title = $req->title;
            }

            if (isset($req->img_url)){
                $img_url = $req->img_url;
            }
            modifyReview($reviewId, $title, $contents, $img_url);
            $res->is_success = TRUE;
            $res->code = 100;
            $res->message = "상품 후기 변경 성공";
            echo json_encode($res, JSON_NUMERIC_CHECK);
            break;
        /*
        * API No. 25
        * API Name : 상품 후기 리스트 조회
        * 마지막 수정 날짜 : 20.10.23
        */
        case "getReviews":
            http_response_code(200);
            // header에서 jwt token 받아온다
            $jwt = $_SERVER['HTTP_X_ACCESS_TOKEN'];
            // Payload에 있는 userIdx 가져오기
            $userIdxInToken = getDataByJWToken($jwt,JWT_SECRET_KEY)->userIdx;

            if (!isValidJWT($jwt, JWT_SECRET_KEY)) { // function.php 에 구현
                $res->isSuccess = FALSE;
                $res->code = 200;
                $res->message = "권한이 없는 유저입니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                break;
            }
            $productId = (int)$vars['productId'];
            if(!isExistingProductId($productId)){
                $res->isSuccess = FALSE;
                $res->code = 201;
                $res->message = "존재하지 않는 product_id 입니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                break;
            }
            $res->result = getReviews($productId);
            $res->is_success = TRUE;
            $res->code = 100;
            $res->message = "상품 후기 리스트 조회 성공";
            echo json_encode($res, JSON_NUMERIC_CHECK);
            break;
        /*
        * API No. 26
        * API Name : 상품 후기 상세 보기
        * 마지막 수정 날짜 : 20.10.23
        */
        case "getReviewDetail":
            http_response_code(200);
            // header에서 jwt token 받아온다
            $jwt = $_SERVER['HTTP_X_ACCESS_TOKEN'];
            // Payload에 있는 userIdx 가져오기
            $userIdxInToken = getDataByJWToken($jwt,JWT_SECRET_KEY)->userIdx;

            if (!isValidJWT($jwt, JWT_SECRET_KEY)) { // function.php 에 구현
                $res->isSuccess = FALSE;
                $res->code = 200;
                $res->message = "권한이 없는 유저입니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                break;
            }
            $reviewId = (int)$vars['reviewId'];
            if(!isExistingReviewId($reviewId)){
                $res->isSuccess = FALSE;
                $res->code = 201;
                $res->message = "존재하지 않는 review_id 입니다.";
                echo json_encode($res, JSON_NUMERIC_CHECK);
                break;
            }
            $res->result = getReviewDetail($reviewId);
            $res->is_success = TRUE;
            $res->code = 100;
            $res->message = "상품 후기 리스트 조회 성공";
            echo json_encode($res, JSON_NUMERIC_CHECK);
            break;

    }



} catch (\Exception $e) {
    return getSQLErrorException($errorLogs, $e, $req);
}
