<?php

//DB 정보
function pdoSqlConnect()
{
    try {
        $DB_HOST = "softsquared.ci5qh07mzbfo.ap-northeast-2.rds.amazonaws.com";
        $DB_NAME = "MarketCurlyDB";
        $DB_USER = "borah";
        $DB_PW = "qhfk303513";
        $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME", $DB_USER, $DB_PW);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (\Exception $e) {
        echo $e->getMessage();
    }
}