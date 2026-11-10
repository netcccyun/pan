<?php

require dirname(__FILE__, 2) . '/vendor/autoload.php';

$secretId = "SECRETID"; //替换为用户的 secretId，请登录访问管理控制台进行查看和管理，https://console.cloud.tencent.com/cam/capi
$secretKey = "SECRETKEY"; //替换为用户的 secretKey，请登录访问管理控制台进行查看和管理，https://console.cloud.tencent.com/cam/capi
$region = "ap-beijing"; //替换为用户的 region，已创建桶归属的region可以在控制台查看，https://console.cloud.tencent.com/cos5/bucket
$cosClient = new Qcloud\Cos\Client(
    array(
        'region' => $region,
        'scheme' => 'https', //协议头部，默认为http
        'credentials' => array(
            'secretId' => $secretId,
            'secretKey' => $secretKey)));
try {
    // 实时文字翻译
    $result = $cosClient->autoTranslationBlockProcess(array(
        'Bucket' => 'examplebucket-125000000', //存储桶名称，由BucketName-Appid 组成，可以在COS控制台查看 https://console.cloud.tencent.com/cos5/bucket
        'InputText' => '', // 待翻译的文本
        'SourceLang' => '', // 输入语言，如 "zh"
        'TargetLang' => '', // 输出语言，如 "en"
//        'TextDomain' => '', // 文本所属业务领域，如: "ecommerce", //缺省值为 general
//        'TextStyle' => '', // 文本类型，如: "title", //缺省值为 sentence
    ));
    print_r($result);
} catch (\Exception $e) {
    // 请求失败
    echo($e);
}
