<?php
//kakao_login/login.php
$restAPIKey = "44c81b0240a2582555db19b06ce43f65"; //본인의 REST API KEY를 입력해주세요
$callbacURI = urlencode("http://15.164.165.18/kakao_login/call_back.php"); //본인의 Call Back URL을 입력해주세요
$kakaoLoginUrl = "https://kauth.kakao.com/oauth/authorize?client_id=".$restAPIKey."&redirect_uri=".$callbacURI."&response_type=code";
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8"/>
</head>

<body>
<a href="<?= $kakaoLoginUrl ?>">
    카카오톡으로 로그인
</a>
</body>
</html>