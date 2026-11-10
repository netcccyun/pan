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
    //本文档介绍创建数据集（Dataset）和对象存储（COS）Bucket 的绑定关系，绑定后将使用创建数据集时所指定算子对文件进行处理。绑定关系创建后，将对 COS 中新增的文件进行准实时的增量追踪扫描，使用创建数据集时所指定算子对文件进行处理，抽取文件元数据信息进行索引。通过此方式为文件建立索引后，您可以使用元数据查询API对元数据进行查询、管理和统计。
    $result = $cosClient->CreateDatasetBinding(array(
        'AppId' => 'AppId', // 其中 APPID 获取参考 https://console.cloud.tencent.com/developer
		'Headers' => array(
			'Accept' => 'application/json',
			'Content-Type' => 'application/json',
		),
		'DatasetName'=> 'test', // 数据集名称，同一个账户下唯一。;是否必传：是
		'URI'=> 'cos://examplebucket-1250000000', // 资源标识字段，表示需要与数据集绑定的资源，当前仅支持COS存储桶，字段规则：cos://<BucketName>，其中BucketName表示COS存储桶名称，例如：cos://examplebucket-1250000000;是否必传：是

    ));
    // 请求成功
    print_r($result);
} catch (\Exception $e) {
    // 请求失败
    echo($e);
}
