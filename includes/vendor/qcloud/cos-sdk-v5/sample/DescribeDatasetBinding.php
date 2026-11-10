<?php

require dirname(__FILE__, 2) . '/vendor/autoload.php';

$secretId = "SECRETID"; //替换为用户的 secretId，请登录访问管理控制台进行查看和管理，https://console.cloud.tencent.com/cam/capi
$secretKey = "SECRETKEY"; //替换为用户的 secretKey，请登录访问管理控制台进行查看和管理，https://console.cloud.tencent.com/cam/capi
$region = "ap-beijing"; //替换为用户的 region，已创建桶归属的region可以在控制台查看，https://console.cloud.tencent.com/cos5/bucket
$cosClient = new Qcloud\Cos\Client(
    array(
        'region' => $region,
        'scheme' => 'https', // 审核时必须为https
        'credentials'=> array(
            'secretId'  => $secretId ,
            'secretKey' => $secretKey)));
try {
    //查询数据集和对象存储（COS）Bucket 绑定关系列表。
    $result = $cosClient->DescribeDatasetBinding(array(
        'AppId' => 'AppId', // 其中 APPID 获取参考 https://console.cloud.tencent.com/developer
		'Headers' => array(
			'Accept' => 'application/json',
			'Content-Type' => 'application/json',
		),
		'datasetname' => '数据集名称', // 数据集名称，同一个账户下唯一。
		'uri' => 'uri', // 资源标识字段，表示需要与数据集绑定的资源，当前仅支持COS存储桶，字段规则：cos://，其中BucketName表示COS存储桶名称，例如（需要进行urlencode）：cos%3A%2F%2Fexample-125000

    ));
    // 请求成功
    print_r($result);
} catch (\Exception $e) {
    // 请求失败
    echo($e);
}
