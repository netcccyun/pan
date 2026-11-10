<?php

$playKey = 'playKey';//替换为用户的 playKey，请通过该接口进行查看和管理，https://cloud.tencent.com/document/product/460/104329
$bucket = 'examplebucket-1250000000';
$region = "ap-beijing"; //替换为用户的 region，已创建桶归属的region可以在控制台查看，https://console.cloud.tencent.com/cos5/bucket
$publicKey = "publicKey";
$header = array(
    'alg' => 'HS256',
    'typ' => 'JWT'
);
$payload = array(
    "Type" => "CosCiToken",
    "AppId" => "1250000000",
    "BucketId" => $bucket,
    "Object" => "exampleobject",
    "Issuer" => "client",
    "IssuedTimeStamp" => time(),
    "ExpireTimeStamp" => time() + 3600 * 6,
    "UsageLimit" => 3,
    "ProtectScheme" => "rsa1024",
    "PublicKey" => base64_encode($publicKey),
    "ProtectContentKey" => 1,
);
$base64header = base64UrlEncode(json_encode($header, JSON_UNESCAPED_UNICODE));
$base64payload = base64UrlEncode(json_encode($payload, JSON_UNESCAPED_UNICODE));
$token = $base64header . '.' . $base64payload . '.' . base64UrlEncode(hash_hmac('sha256', $base64header . '.' . $base64payload, $playKey, true));
echo $token;

function base64UrlEncode($input) {
    return str_replace('=', '', strtr(base64_encode($input), '+/', '-_'));
}
