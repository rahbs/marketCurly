<?php

function getKakaoUserInfo($accessToken){
    $app_url= "https://kapi.kakao.com/v2/user/me";
    $opts = array( CURLOPT_URL => $app_url, CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_POST => true, CURLOPT_POSTFIELDS => false,
                    CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => array( "Authorization: Bearer " . $accessToken ) );
    $ch = curl_init();
    curl_setopt_array($ch, $opts);
    $res = curl_exec($ch);
    curl_close($ch);
    //var_dump($res);
    $res = json_decode($res);
    $userId = $res->id;
    $kakaoUserInfo['id'] = (string)$userId;
    $kakaoUserInfo['name'] = $res->properties->nickname;
    $kakaoUserInfo['email'] = $res->kakao_account->email;
    return $kakaoUserInfo;
}

//READ
function getUsers()
{
    $pdo = pdoSqlConnect();
    $query = "select * from user;";
    //$query = "select * from testTable where name like concat('%',?,'%');";
    $st = $pdo->prepare($query);
    //    $st->execute([$param,$param]);
    //$st->execute([$keyword]);
    $st->execute([]);
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res;
}

//READ
function getUserDetail($userIdx)
{
    $pdo = pdoSqlConnect();
    $query = '
            SELECT user.user_idx,user.id, user.name, grade.name as grade, credit, COUNT(coupon_id) as coupon_cnt
            FROM user
            JOIN grade
            ON user.grade_id = grade.id
            LEFT JOIN user_coupon
            ON user_coupon.user_idx = user.user_idx
            WHERE user.user_idx = ?
            GROUP BY user_idx;';

    $st = $pdo->prepare($query);
    $st->execute([$userIdx]);
    //    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;
    return $res[0];
}

function createUser($id, $name, $pwd, $email, $phoneNum)
{
    $pdo = pdoSqlConnect();
    $query = "INSERT INTO user (id, name, password, email, phone_num) VALUES (? , ? , ? , ? , ?);";
    $st = $pdo->prepare($query);
    $st->execute([$id, $name, $pwd, $email, $phoneNum]);

    $st = null;
    $pdo = null;

}

function editUserDetail($userIdx, $userId = Null, $userPwd = Null){
    $pdo = pdoSqlConnect();
    if($userId){
        $query = "UPDATE user SET id = ? WHERE  user_idx = ?;";
        $st = $pdo->prepare($query);
        $st->execute([$userId, $userIdx]);
    }
    if($userPwd){
        $query = "UPDATE user SET password = ? WHERE  user_idx = ?;";
        $st = $pdo->prepare($query);
        $st->execute([$userPwd, $userIdx]);
    }

    $st = null;
    $pdo = null;
}

function deleteUser($userIdx){
    $pdo = pdoSqlConnect();
    $query = "UPDATE user SET is_deleted = 'Y' WHERE  user_idx = ?;";
    $st = $pdo->prepare($query);
    $st->execute([$userIdx]);

    $st = null;
    $pdo = null;
}

function getUserCoupons($userIdx){
    $pdo = pdoSqlConnect();
    $query = "SELECT coupon.id as coupon_id, coupon.name as coupun_name, DATE(start_date) as start_date, DATE(end_date) as end_date
              FROM user
              JOIN user_coupon
              ON user_coupon.user_idx = user.user_idx
              JOIN coupon
              ON user_coupon.coupon_id = coupon.id
              WHERE user.user_idx = ? and coupon.is_available = 'Y';";
    $st = $pdo->prepare($query);
    $st->execute([$userIdx]);
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;
    return $res;
}

function getUserOrders($userIdx){
    $pdo = pdoSqlConnect();
    $query = "SELECT order_id, DATE(payment_date) as payment_date, total_price,
        CASE
            # 주문 상품 개수가 1개일 경우, '해당 상품 이름' 출력
            WHEN product_num = 1
            THEN (SELECT name
                FROM order_
                JOIN order_product
                ON order_.id = order_product.order_id
                JOIN product
                ON order_product.product_id = product.id
                WHERE order_.user_idx=?)
            # 주문 상품 개수가 1개 이상일 경우, '해당 상품 이름 + 외 ()건' 출력
            WHEN product_num > 1
            THEN (SELECT CONCAT ((SELECT name
                FROM order_
                JOIN order_product
                ON order_.id = order_product.order_id
                JOIN product
                ON order_product.product_id = product.id
                WHERE order_.user_idx=?
                LIMIT 1),' 외', product_num-1,'건'))
        END AS product_name,
        CASE
            WHEN delivery_status = 'A'
            THEN '배송완료'
            WHEN delivery_status = 'B'
            THEN '배송중'
            WHEN delivery_status = 'C'
            THEN '입금확인'
        END AS delivery_status
    FROM
        (SELECT distinct(order_.id) as order_id, payment_date, total_price,delivery_status, COUNT(*) as product_num
        FROM order_
        JOIN order_product
        ON order_.id = order_product.order_id
        JOIN product
        ON order_product.product_id = product.id
        WHERE order_.user_idx=?) as order_list;";
    $st = $pdo->prepare($query);
    $st->execute([$userIdx,$userIdx,$userIdx]);
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;
    return $res;
}

function createOrder($userIdx, $paymentMethod, $shippingFee, $totalPrice, $address, $entrance_access_method, $receiving_place, $products, $couponId = Null, $usedCredit = Null){
    $pdo = pdoSqlConnect();
    // transaction 추가
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $query = "INSERT INTO order_ (user_idx, payment_method, shipping_fee, total_price, address, entrance_access_method, receiving_place ) VALUES (? , ? , ? , ? , ? , ? , ?);";
    $st = $pdo->prepare($query);
    $st->execute([$userIdx, $paymentMethod, $shippingFee, $totalPrice, $address, $entrance_access_method, $receiving_place]);
    try {
        $pdo->beginTransaction();
        //현재 주문 번호 구하기
        $query = "SELECT MAX(id) as max_id FROM order_;";
        $st = $pdo->prepare($query);
        $st->execute([]);
        $st->setFetchMode(PDO::FETCH_ASSOC);
        $current_order_id = $st->fetchAll()[0]['max_id'];

        //couponId, usedCredit 등록
        if (!is_null($couponId) or !is_null($usedCredit)) {
            if ($couponId) {
                $query = 'UPDATE  order_ SET coupon_id = ? WHERE order_.id = ' . $current_order_id . ';';
                $st = $pdo->prepare($query);
                $st->execute([$couponId]);
            }
            if ($usedCredit) {
                $query = 'UPDATE  order_ SET used_credit = ? WHERE order_.id = ' . $current_order_id . ';';
                $st = $pdo->prepare($query);
                $st->execute([$usedCredit]);
            }
        }

        foreach ($products as $product) {
            $productId = $product->product_id;
            $productNum = $product->product_num;
            // order_product에 추가 (사용자 - 주문번호 연결)
            $query = 'INSERT INTO order_product (order_id, product_id, product_num) values(' . $current_order_id . ', ' . $productId . ', ' . $productNum . ');';
            $st = $pdo->prepare($query);
            $st->execute([]);
            // product의 재고 1줄이기
            $query = 'UPDATE product SET stock = stock - ' . $productNum . ' WHERE id = ?; ';
            $st = $pdo->prepare($query);
            $st->execute([$productId]);
        }
    }
    catch(PDOException $e) {
        // Failed to insert the order into the database so we rollback any changes
        $pdo->rollback();
        throw $e;
    }
    $st = null;
    $pdo = null;

    return $current_order_id;
}
function deleteOrder($userIdx, $orderId){
    $pdo = pdoSqlConnect();
    $query = "UPDATE order_ SET is_deleted = 'Y' WHERE  id = ? and user_idx = ?;";
    $st = $pdo->prepare($query);
    $st->execute([$orderId, $userIdx]);

    $query = "UPDATE order_product SET is_deleted = 'Y' WHERE order_id = ?";
    $st = $pdo->prepare($query);
    $st->execute([$orderId]);
    $st = null;
    $pdo = null;
}
function getProducts($order, $filter){
    $pdo = pdoSqlConnect();
    if ($filter == 'all'){
        $query = "SELECT product_info.id , image_url, title, MIN(price) as price, MIN(price)*(1-discount_rate*0.01) as discounted_price,discount_rate
                  FROM product
                  JOIN product_info
                  ON product_info_id = product_info.id
                  GROUP BY product_info_id ";
    }
    if ($filter == 'new'){ # 3일 이내에 등록된 상품 조회
        $query = "SELECT product_info.id , image_url, title, MIN(price) as price, MIN(price)*(1-discount_rate*0.01) as discounted_price,discount_rate
                  FROM
                       (SELECT *
                        FROM product_info
                        WHERE product_info.created_at >= date(subdate(now(), INTERVAL 3 DAY)) and product_info.created_at <= date(now()) and is_deleted = 'N')
                           as product_info
                  JOIN product
                  ON product.product_info_id = product_info.id
                  GROUP BY product.product_info_id ";
    }
    else if ($filter == 'discount'){ # 할인 상품 조회
        $query = "SELECT product_info.id , image_url, title, MIN(price) as price, MIN(price)*(1-discount_rate*0.01) as discounted_price,discount_rate
                  FROM
                      (SELECT *
                      FROM product
                      WHERE discount_rate>0) as discounted_product
                  JOIN product_info
                  ON product_info_id = product_info.id
                  GROUP BY product_info_id ";
                 # ORDER BY discount_rate DESC;";
    }
    switch ($order){
        case "new":
            $addQuery = "ORDER BY product_info.created_at DESC;";
            break;
        case "discount":
            $addQuery = "ORDER BY product_info.discount_rate DESC;";
            break;
        case "cheap":
            $addQuery = "ORDER BY price;";
            break;
        case "expensive":
            $addQuery = "ORDER BY price DESC;";
            break;
        case null:
            $addQuery = ";";
    }
    $query = $query.$addQuery;
    //echo $query;
    $st = $pdo->prepare($query);
    $st->execute([]);
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;
    return $res;
}
function getCategories(){
    $pdo = pdoSqlConnect();
    $query = "SELECT * FROM category;";
    $st = $pdo->prepare($query);
    $st->execute([]);
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res;
}

function getSubcategories($categoryId){
    $pdo = pdoSqlConnect();
    $query = "SELECT category.id, category.name, subcategory.id, subcategory.name
              FROM category
              JOIN subcategory
              ON category.id = subcategory.category_id
              WHERE category.id=".$categoryId.";";
    $st = $pdo->prepare($query);
    $st->execute([]);
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res;
}

function getProductDescription($userIdx,$productId){
    $pdo = pdoSqlConnect();
    $query = "SELECT *
FROM
     (SELECT product_info.id, product_info.title, product_info.image_url, product_info.sub_title,
        # unit
        # A:1팩, B:1봉, C:1박스, D:1개
        CASE
            WHEN unit = 'A'
            THEN '1팩'
            WHEN unit = 'B'
            THEN '1봉'
            WHEN unit = 'C'
            THEN '1박스'
            WHEN unit = 'D'
            THEN '1개'
            WHEN unit = 'E'
            THEN '1kg'
        END AS unit,
        # delivery_method
        # A:샛별배송/택배배송 S: 샛별배송, T:택배배송
        CASE
            WHEN delivery_method = 'A'
            THEN '샛별배송/택배배송'
            WHEN delivery_method = 'S'
            THEN '샛별배송'
            WHEN delivery_method = 'T'
            THEN '택배배송'
        END AS delivery_method,
        # packing_type
        # A:냉동/종이, B: 냉장/종이, C:상온/종이 D:기타
        CASE
            WHEN packing_type = 'A'
            THEN '냉동/종이'
            WHEN packing_type = 'B'
            THEN '냉장/종이'
            WHEN packing_type = 'C'
            THEN '상온/종이'
            WHEN packing_type = 'D'
            THEN '기타'
        END AS packing_type,
            allergy_info, expiration_date, livestock_info,
            instructions, product_info.description_html_url as product_info_description_html_url,
            brand.description_html_url as brand_description_html_url, nutrition_facts_image_url
    FROM  product_info
    INNER JOIN brand
    ON brand.id = product_info.brand_id) as product_info
JOIN
    (SELECT grade, credit_rate,product_info_price as price,discounted_price, CEIL(product_info_price*credit_rate*0.01) as credit
        FROM
            (SELECT grade.name as grade, credit_rate # 사용자의 등급, 적립률
            FROM user
            INNER JOIN grade
            ON user.grade_id = grade.id
            WHERE user.user_idx = ?) as user_info
        JOIN
            (SELECT MIN(price) as product_info_price, MIN(price)*(1-discount_rate*0.01) as discounted_price # 상품(옵션)들 중 최소 가격, 할인된 가격
            FROM product
            JOIN product_info
            ON product.product_info_id = product_info.id
            WHERE product_info.id =?) as price
    ) as grade_price
JOIN
    (SELECT # 상품 중량
        CASE
            WHEN count(distinct(weight)) = 0 # 중량이 없을 경우
            THEN NULL
            WHEN count(distinct(weight)) = 1 # 옵션별로 모든 중량이 같을 경우 해당 중량 반환
            THEN (SELECT distinct(weight)
                FROM (SELECT *
                        FROM product
                     WHERE product_info_id = ?) as my_product)
            WHEN count(distinct(weight)) != 1 # 옵션별로 중량이 상이할 경우
            THEN '옵션별 상이'
        END AS weight
    FROM product
    WHERE product_info_id = ?) as weight;
";
    $st = $pdo->prepare($query);
    $st->execute([$userIdx,$productId,$productId,$productId]);
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res;
}
function getProductImg($productId){
    $pdo = pdoSqlConnect();
    $query = "SELECT title, nutrition_facts_image_url
              FROM product_info
              WHERE id = ?;";
    $st = $pdo->prepare($query);
    $st->execute([$productId]);
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res;
}
function getProductOptions($productId){
    $pdo = pdoSqlConnect();
    $query = "SELECT id, name, price, price*(1-discount_rate*0.01) as discounted_price,
    CASE
        WHEN stock > 0
        THEN 'Y'
        WHEN stock = 0
        THEN 'N'
    END AS is_available
FROM product
WHERE product_info_id = ?;";
    $st = $pdo->prepare($query);
    $st->execute([$productId]);
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res;
}
function deleteProduct($productId){
    $pdo = pdoSqlConnect();
    $query = "UPDATE product_info SET is_deleted = 'Y' WHERE  id = ?;";
    $st = $pdo->prepare($query);
    $st->execute([$productId]);
    $query = "UPDATE product SET is_deleted = 'Y' WHERE  product_info_id = ?;";
    $st = $pdo->prepare($query);
    $st->execute([$productId]);
    $st = null;
    $pdo = null;

}
function addProduct($title, $sub_title, $image_url, $subcategory_id, $description_html_url, $nutrition_facts_image_url, $options, $delivery_method, $origin, $unit, $packing_type ){
    $pdo = pdoSqlConnect();
    // transaction 추가
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    try{
        // product 생성
        $query = "INSERT INTO product_info (title, sub_title, image_url, subcategory_id,description_html_url, nutrition_facts_image_url,delivery_method, origin, unit, packing_type ) VALUES (?,?,?,?,?,?,?,?,?,?);";
        $st = $pdo->prepare($query);
        $st->execute([$title, $sub_title, $image_url, $subcategory_id, $description_html_url, $nutrition_facts_image_url, $delivery_method, $origin, $unit, $packing_type ]);

        // current_product_id 구하기
        $query = "SELECT MAX(id) as max_id FROM product_info;";
        $st = $pdo->prepare($query);
        $st->execute([]);
        $st->setFetchMode(PDO::FETCH_ASSOC);
        $current_product_id = $st->fetchAll()[0]['max_id'];

        // 해당 product에 옵션 생성
        foreach ($options as $option){
            $query = "INSERT INTO product (product_info_id, name, price, weight, discount_rate, stock) VALUES(?,?,?,?,?,?)";
            $st = $pdo->prepare($query);
            $st->execute([$current_product_id, $option->name, $option->price,$option->weight,$option->discount_rate,$option->stock]);
        }
    }
    catch(PDOException $e) {
        // Failed to insert the order into the database so we rollback any changes
        $pdo->rollback();
        throw $e;
    }
    $st = null;
    $pdo = null;

    return $current_product_id;
}

function deleteProductOption($productOptionId){
    $pdo = pdoSqlConnect();
    $query = "UPDATE product SET is_deleted = 'Y' WHERE  id = ?;";
    $st = $pdo->prepare($query);
    $st->execute([$productOptionId]);
    $st = null;
    $pdo = null;
}
function getOrderDetail($userIdx, $orderId){
    $pdo = pdoSqlConnect();
    $query = "SELECT product_info_id , name, price, product_num, delivery_status
              FROM order_product
              JOIN product
              ON product.id = order_product.product_id
              JOIN order_
              ON order_.id = order_product.order_id
              where order_id = ?;";
    $st = $pdo->prepare($query);
    $st->execute([$orderId]);
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res['product_list'] =  $st->fetchAll();
    $query = "SELECT id as order_id, total_price, shipping_fee, used_credit,
              total_price + shipping_fee -used_credit as payed_price,
              saved_credit,receiver_name,payment_date,receiver_phone_num,
              delivery_method,zip_num,address,receiving_place,
              entrance_access_method,texting_point,payback_method
              FROM order_ where id = ?;";
    $st = $pdo->prepare($query);
    $st->execute([$orderId]);
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res['details'] = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res;
}
function createReview($userIdx, $productId, $title, $contents, $imgUrl=Null){
    $pdo = pdoSqlConnect();
    $query = "INSERT INTO review (user_idx, product_id, title, contents) VALUES (? , ? , ? , ? );";
    $st = $pdo->prepare($query);
    $st->execute([$userIdx, $productId, $title, $contents]);

    if($imgUrl){
        //현재 리뷰 id 구하기
        $query = "SELECT MAX(id) as max_id FROM review;";
        $st = $pdo->prepare($query);
        $st->execute([]);
        $st->setFetchMode(PDO::FETCH_ASSOC);
        $current_review_id = $st->fetchAll()[0]['max_id'];
        // review table에 imgUrl 추가
        $query = "UPDATE review SET image_url = ? WHERE  id = $current_review_id;";
        $st = $pdo->prepare($query);
        $st->execute([$imgUrl]);
    }
    $st = null;
    $pdo = null;
}
function modifyReview($reviewId, $title=Null, $contents=Null,$img_url=Null){
    $pdo = pdoSqlConnect();
    if($title){
        $query = "UPDATE review SET title = ? WHERE  id = ?;";
        $st = $pdo->prepare($query);
        $st->execute([$title, $reviewId]);
    }
    if($contents){
        $query = "UPDATE review SET contents = ? WHERE  id = ?;";
        $st = $pdo->prepare($query);
        $st->execute([$contents, $reviewId]);
    }
    if($img_url){
        $query = "UPDATE review SET image_url = ? WHERE  id = ?;";
        $st = $pdo->prepare($query);
        $st->execute([$img_url, $reviewId]);
    }

    $st = null;
    $pdo = null;
}
function getReviews($productId){
    $pdo = pdoSqlConnect();
    $query = "select pi.title as product_name, count(*) as review_num
              from review
              join product as p
              on review.product_id = p.id
              join product_info as pi
              on p.product_info_id = pi.id
              where pi.id = ?;";
    $st = $pdo->prepare($query);
    $st->execute([$productId]);
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res['info'] = $st->fetchAll()[0];
    $query = "select review.title, user.name as name, is_best, grade.name as grade, DATE(review.created_at) as created_at
              from review
              join product
              on review.product_id = product.id
              join product_info
              on product.product_info_id = product_info.id
              join user
              on user.user_idx = review.user_idx
              join grade
              on grade.id = user.grade_id;";
    $st = $pdo->prepare($query);
    $st->execute([$productId]);
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res['reviews'] = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res;
}
function getReviewDetail($reviewId){
    $pdo = pdoSqlConnect();

    $query = "select product_info.title as product_name, product.name as option_name, review.title as title, review.contents, date(review.created_at) as created_at
              from review
              join product
              on review.product_id = product.id
              join product_info
              on product.product_info_id = product_info.id
              where review.id = ?;";
    $st = $pdo->prepare($query);
    $st->execute([$reviewId]);
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll()[0];

    $st = null;
    $pdo = null;

    return $res;


}
///* ******************   validation functions   ****************** */
///
function isExistingUserIdx($userIdx)
{
    $pdo = pdoSqlConnect();
    $query = "select EXISTS(select * from user where user_idx = ? and is_deleted='N') as exist;";
    $st = $pdo->prepare($query);
    $st->execute([$userIdx]);
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res[0]['exist'];
}

function isExistingUserId($id)
{
    $pdo = pdoSqlConnect();
    $query = "select EXISTS(select * from user where id = ? and is_deleted='N') as exist;";
    $st = $pdo->prepare($query);
    $st->execute([$id]);
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;
    return $res[0]['exist'];
}


function isExistingProductId($productId){
    $pdo = pdoSqlConnect();
    $query = "select EXISTS(select * from product where id = ?) as exist;";
    $st = $pdo->prepare($query);
    $st->execute([$productId]);
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;
    return $res[0]['exist'];
}
function isAvailableProductNum($productId, $productNum){
    $pdo = pdoSqlConnect();
    $query = "SELECT EXISTS(SELECT * from product
              WHERE  id = ? and stock >= ?) as exist;";
    $st = $pdo->prepare($query);
    $st->execute([$productId, $productNum]);
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;
    return $res[0]['exist'];
}
function isExistingOrderId($orderId){
    $pdo = pdoSqlConnect();
    $query = "select EXISTS(select * from order_ where id = ? and is_deleted='N') as exist;";
    $st = $pdo->prepare($query);
    $st->execute([$orderId]);
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;
    return $res[0]['exist'];
}
function isValidOrderType($order){
    $orderType = array("new","discount","cheap","expensive");
    if(in_array($order,$orderType)){
        return TRUE;
    }
    return FALSE;
}
function isValidFilterType($filter){
    $filterType = array("all","new","discount");
    if(in_array($filter,$filterType)){
        return TRUE;
    }
    return FALSE;
}
function isExistingProductInfoId($productId){
    $pdo = pdoSqlConnect();
    $query = "select EXISTS(select * from product_info where id = ? and is_deleted='N') as exist;";
    $st = $pdo->prepare($query);
    $st->execute([$productId]);
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;
    return $res[0]['exist'];
}
function isDeletableOrder($orderId){
    $pdo = pdoSqlConnect();
    $query = "select EXISTS(select * from order_ where id = ? and is_deleted='N' and delivery_status ='C') as exist;";
    $st = $pdo->prepare($query);
    $st->execute([$orderId]);
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;
    return $res[0]['exist'];
}
function isExistingProductOptionId($productOptionId){
    $pdo = pdoSqlConnect();
    $query = "select EXISTS(select * from product where id = ? and is_deleted='N') as exist;";
    $st = $pdo->prepare($query);
    $st->execute([$productOptionId]);
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;
    return $res[0]['exist'];
}
function checkUserInfo($userId, $userName, $userEmail){
    $pdo = pdoSqlConnect();
    $query = "select EXISTS(select * from user where id = ? and name = ? and email = ? and is_deleted='N') as exist;";
    $st = $pdo->prepare($query);
    $st->execute([$userId,$userName,$userEmail]);
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;
    return $res[0]['exist'];
}
function isExistingReviewId($reviewId){
    $pdo = pdoSqlConnect();
    $query = "select EXISTS(select * from review where id = ? and is_deleted='N') as exist;";
    $st = $pdo->prepare($query);
    $st->execute([$reviewId]);
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;
    return $res[0]['exist'];
}
// CREATE
//    function addMaintenance($message){
//        $pdo = pdoSqlConnect();
//        $query = "INSERT INTO MAINTENANCE (MESSAGE) VALUES (?);";
//
//        $st = $pdo->prepare($query);
//        $st->execute([$message]);
//
//        $st = null;
//        $pdo = null;
//
//    }


// UPDATE
//    function updateMaintenanceStatus($message, $status, $no){
//        $pdo = pdoSqlConnect();
//        $query = "UPDATE MAINTENANCE
//                        SET MESSAGE = ?,
//                            STATUS  = ?
//                        WHERE NO = ?";
//
//        $st = $pdo->prepare($query);
//        $st->execute([$message, $status, $no]);
//        $st = null;
//        $pdo = null;
//    }

// RETURN BOOLEAN
//    function isRedundantEmail($email){
//        $pdo = pdoSqlConnect();
//        $query = "SELECT EXISTS(SELECT * FROM USER_TB WHERE EMAIL= ?) AS exist;";
//
//
//        $st = $pdo->prepare($query);
//        //    $st->execute([$param,$param]);
//        $st->execute([$email]);
//        $st->setFetchMode(PDO::FETCH_ASSOC);
//        $res = $st->fetchAll();
//
//        $st=null;$pdo = null;
//
//        return intval($res[0]["exist"]);
//
//    }
