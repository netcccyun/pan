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
    $result = $cosClient->DescribeDatasetBindings(array(
        'AppId' => 'AppId', // 其中 APPID 获取参考 https://console.cloud.tencent.com/developer
		'Headers' => array(
			'Accept' => 'application/json',
			'Content-Type' => 'application/json',
		),
		'datasetname' => '数据集名称', // 数据集名称，同一个账户下唯一。
		'maxresults' => '最大个数', // 返回绑定关系的最大个数，取值范围为0~200。不设置此参数或者设置为0时，则默认值为100。
		'nexttoken' => '下一页', // 当绑定关系总数大于设置的MaxResults时，用于翻页的token。从NextToken开始按字典序返回绑定关系信息列表。第一次调用此接口时，设置为空。

    ));
    // 请求成功
    print_r($result);
} catch (\Exception $e) {
    // 请求失败
    echo($e);
}
