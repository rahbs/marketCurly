<?php

function isValidUser($id, $pw){
    $pdo = pdoSqlConnect();
    $query = "SELECT EXISTS(SELECT * FROM UserInfo WHERE email= ? AND password = ?) AS exist;";


    $st = $pdo->prepare($query);
    $st->execute([$id, $pw]);
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st=null;$pdo = null;

    return intval($res[0]["exist"]);

}
function getUserIdxByEmail($email)
{
    $pdo = pdoSqlConnect();
    $query = "SELECT idx FROM UserInfo WHERE email = ?;";

    $st = $pdo->prepare($query);
    $st->execute([$email]);
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res[0]['idx'];
}