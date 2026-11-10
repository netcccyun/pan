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
    //更新一个数据集（Dataset）信息。
    $result = $cosClient->UpdateDataset(array(
        'AppId' => 'AppId', // 其中 APPID 获取参考 https://console.cloud.tencent.com/developer
		'Headers' => array(
			'Accept' => 'application/json',
			'Content-Type' => 'application/json',
		),
		'DatasetName'=> 'test', // 数据集名称，同一个账户下唯一。;是否必传：是
		'Description'=> 'test', // 数据集描述信息。长度为1~256个英文或中文字符，默认值为空。;是否必传：否
		'TemplateId'=> 'Official:COSBasicMeta', // 该参数表示模板，在建立元数据索引时，后端将根据模板来决定收集哪些元数据。每个模板都包含一个或多个算子，不同的算子表示不同的元数据。目前支持的模板： Official:Empty：默认为空的模板，表示不进行元数据的采集。 Official:COSBasicMeta：基础信息模板，包含COS文件基础元信息算子，表示采集cos文件的名称、类型、acl等基础元信息数据。;是否必传：否

    ));
    // 请求成功
    print_r($result);
} catch (\Exception $e) {
    // 请求失败
    echo($e);
}
