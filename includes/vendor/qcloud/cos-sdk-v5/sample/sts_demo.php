<?php
/**
 * 第一步：获取临时密钥
 * 该demo为临时密钥获取sdk的使用示例，具体情参考sdk git地址 https://github.com/tencentyun/qcloud-cos-sts-sdk
 * 参考文档 https://cloud.tencent.com/document/product/436/14048
 * $config 配置中的 allowCiSource 字段为万象资源配置，为true时授予万象资源权限
 * 拿到临时密钥后，可以在cos php sdk中使用 https://github.com/tencentyun/cos-php-sdk-v5
 * Array
 * (
 *     [expiredTime] => 1700828878
 *     [expiration] => 2023-11-24T12:27:58Z
 *     [credentials] => Array
 *     (
 *         [sessionToken] => token
 *         [tmpSecretId] => secretId
 *         [tmpSecretKey] => secretKey
 *     )
 *
 *     [requestId] => 2a521211-b212-xxxx-xxxx-c9976a3966bd
 *     [startTime] => 1700810878
 * )
 */

require_once __DIR__ . '/vendor/autoload.php';

$bucket = 'examplebucket-1250000000';
$secretKey = 'SECRETKEY';
$secretId = 'SECRETID';
$region = "ap-beijing";

$sts = new QCloud\COSSTS\Sts();
$config = array(
    'url' => 'https://sts.tencentcloudapi.com/', // url和domain保持一致
    'domain' => 'sts.tencentcloudapi.com', // 域名，非必须，默认为 sts.tencentcloudapi.com
    'proxy' => '',
    'secretId' => $secretId, // 固定密钥,若为明文密钥，请直接以'xxx'形式填入，不要填写到getenv()函数中
    'secretKey' => $secretKey, // 固定密钥,若为明文密钥，请直接以'xxx'形式填入，不要填写到getenv()函数中
    'bucket' => $bucket, // 换成你的 bucket
    'region' => $region, // 换成 bucket 所在园区
    'durationSeconds' => 1800*10, // 密钥有效期
    'allowPrefix' => array('/*'), // 这里改成允许的路径前缀，可以根据自己网站的用户登录态判断允许上传的具体路径，例子： a.jpg 或者 a/* 或者 * (使用通配符*存在重大安全风险, 请谨慎评估使用)
    'allowCiSource' => false, // 万象资源配置
    'allowActions' => array (
        'name/cos:*',
        'name/ci:*',
        // 具体action按需设置
    ),
//    // 临时密钥生效条件，关于condition的详细设置规则和COS支持的condition类型可以参考 https://cloud.tencent.com/document/product/436/71306
//    "condition" => array(
//        "ip_equal" => array(
//            "qcs:ip" => array(
//                "10.217.182.3/24",
//                "111.21.33.72/24",
//            )
//        )
//    )
);


try {
    // 获取临时密钥，计算签名
    $tempKeys = $sts->getTempKeys($config);
    print_r($tempKeys);
} catch (Exception $e) {
    echo $e;
}


/**
 * 第二步：在cos php sdk中使用临时密钥
 * 创建临时密钥生成的Client，以文本同步审核为例
 */
// 临时密钥
$tmpSecretId = 'secretId'; // 第一步获取到的 $tempKeys['credentials']['tmpSecretId']
$tmpSecretKey = 'secretKey'; // 第一步获取到的 $tempKeys['credentials']['tmpSecretKey']
$token = 'token'; // 第一步获取到的 $tempKeys['credentials']['sessionToken']
$tokenClient = new Qcloud\Cos\Client(
    array(
        'region' => $region,
        'scheme' => 'https', //协议头部，默认为http
        'credentials'=> array(
            'secretId'  => $tmpSecretId ,
            'secretKey' => $tmpSecretKey,
            'token' => $token,
        )
    )
);

try {
    $content = '敏感词';
    $result = $tokenClient->detectText(array(
        'Bucket' => 'examplebucket-1250000000', //存储桶名称，由BucketName-Appid 组成，可以在COS控制台查看 https://console.cloud.tencent.com/cos5/bucket
        'Input' => array(
            'Content' => base64_encode($content), // 文本需base64_encode
        ),
    ));
    // 请求成功
    print_r($result);
} catch (\Exception $e) {
    // 请求失败
    echo($e);
}
