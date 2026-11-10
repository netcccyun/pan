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
try {
    // -------------------- 1. 图片二维码识别 下载时识别 -------------------- //
    $result = $cosClient->Qrcode(array(
        'Bucket' => 'examplebucket-125000000', //存储桶名称，由BucketName-Appid 组成，可以在COS控制台查看 https://console.cloud.tencent.com/cos5/bucket
        'Key' => 'exampleobject',
        'Cover' => 0,
    ));
    // 请求成功
    print_r($result);
    // -------------------- 1. 图片二维码识别 下载时识别 -------------------- //

    // -------------------- 2. 图片二维码识别 上传时识别 -------------------- //
    $imageQrcodeTemplate = new Qcloud\Cos\ImageParamTemplate\ImageQrcodeTemplate();
    $imageQrcodeTemplate->setCover(0); // 二维码覆盖功能。可为0或1，功能开启后，将对识别出的二维码覆盖上马赛克，默认值0
    $imageQrcodeTemplate->setBarType(0); // 二维码/条形码识别功能，将对识别出的二维码/条形码 覆盖马赛克。取值为0，1，2，默认值0
    $imageQrcodeTemplate->setSegment(0); // 通用的切片开关参数，指定是否需要切片，默认值0，需要切片时，后台会根据图片尺寸进行切片识别
    $imageQrcodeTemplate->setSize(100); // 当segment取值为1时生效，默认1000像素，取值范围为大于等于500的整数。当size指定的数值大于图片像素时，则不进行切片，直接识别

    $picOperations = new Qcloud\Cos\ImageParamTemplate\PicOperationsTransformation();
    $picOperations->setIsPicInfo(1); // is_pic_info
    $picOperations->addRule($imageQrcodeTemplate, "output.png"); // rules
    $result = $cosClient->putObject(array(
        'Bucket' => 'examplebucket-125000000', //存储桶名称，由BucketName-Appid 组成，可以在COS控制台查看 https://console.cloud.tencent.com/cos5/bucket
        'Key' => 'object.jpg',
        'Body' => fopen('/tmp/local.jpg', 'rb'), // 本地文件
        'PicOperations' => $picOperations->queryString(),
    ));
    // 请求成功
    print_r($result);
    // -------------------- 2. 图片二维码识别 上传时识别 -------------------- //
} catch (\Exception $e) {
    // 请求失败
    echo($e);
}
