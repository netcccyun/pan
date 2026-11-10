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
    //从数据集中搜索与指定图片最相似的前N张图片并返回人脸坐标可对数据集内文件进行一个或多个人员的人脸识别。
    $result = $cosClient->DatasetFaceSearch(array(
        'AppId' => 'AppId', // 其中 APPID 获取参考 https://console.cloud.tencent.com/developer
		'Headers' => array(
			'Accept' => 'application/json',
			'Content-Type' => 'application/json',
		),
		'DatasetName'=> 'test', // 数据集名称，同一个账户下唯一。;是否必传：是
		'URI'=> 'cos://examplebucket-1250000000/test.jpg', // 资源标识字段，表示需要建立索引的文件地址。;是否必传：是
		'MaxFaceNum'=> 1, // 输入图片中检索的人脸数量，默认值为1(传0或不传采用默认值)，最大值为10。;是否必传：否
		'Limit'=> 10, // 检索的每张人脸返回相关人脸数量，默认值为10，最大值为100。;是否必传：否
		'MatchThreshold'=> 10, // 出参 Score 中，只有超过 MatchThreshold 值的结果才会返回。范围：1-100，默认值为0，推荐值为80。;是否必传：否

    ));
    // 请求成功
    print_r($result);
} catch (\Exception $e) {
    // 请求失败
    echo($e);
}
