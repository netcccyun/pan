<?php

require dirname(__FILE__, 2) . '/vendor/autoload.php';

$secretId = "SECRETID"; //替换为用户的 secretId，请登录访问管理控制台进行查看和管理，https://console.cloud.tencent.com/cam/capi
$secretKey = "SECRETKEY"; //替换为用户的 secretKey，请登录访问管理控制台进行查看和管理，https://console.cloud.tencent.com/cam/capi
$region = "ap-beijing"; //替换为用户的 region，已创建桶归属的region可以在控制台查看，https://console.cloud.tencent.com/cos5/bucket
$cosClient = new Qcloud\Cos\Client(
    array(
        'region' => $region,
        'scheme' => 'https', //协议头部，默认为http
        'credentials'=> array(
            'secretId'  => $secretId,
            'secretKey' => $secretKey)));
$local_path = "/data/exampleobject";
try {
    // -------------------- 1. 卡证识别 原图存储在COS -------------------- //
    $result = $cosClient->aILicenseRecProcess(array(
        'Bucket' => 'examplebucket-125000000', //存储桶名称，由BucketName-Appid 组成，可以在COS控制台查看 https://console.cloud.tencent.com/cos5/bucket
        'Key' => 'test.jpg',
        'CardType' => 'IDCard', // 卡证识别类型，有效值为IDCard，DriverLicense。<br>IDCard表示身份证；DriverLicense表示驾驶证，默认：DriverLicense
    ));
    // 请求成功
    print_r($result);
    // -------------------- 1. 卡证识别 原图存储在COS -------------------- //

    // -------------------- 2. 卡证识别 原图来自其他链接 暂不支持 -------------------- //
    $result = $cosClient->aILicenseRecProcess(array(
        'Bucket' => 'examplebucket-125000000', //存储桶名称，由BucketName-Appid 组成，可以在COS控制台查看 https://console.cloud.tencent.com/cos5/bucket
        'Key' => '', // 该值为空即可
        'DetectUrl' => 'https://www.xxx.com/xxx.jpg',
        'CardType' => 'IDCard', // 卡证识别类型，有效值为IDCard，DriverLicense。<br>IDCard表示身份证；DriverLicense表示驾驶证，默认：DriverLicense
    ));
    // 请求成功
    print_r($result);
    // -------------------- 2. 卡证识别 原图来自其他链接 暂不支持 -------------------- //
} catch (\Exception $e) {
    // 请求失败
    echo($e);
}
