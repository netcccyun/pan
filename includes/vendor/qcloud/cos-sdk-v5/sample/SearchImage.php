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
    //可通过输入自然语言或图片，基于语义对数据集内文件进行图像检索。
    $result = $cosClient->SearchImage(array(
        'AppId' => 'AppId', // 其中 APPID 获取参考 https://console.cloud.tencent.com/developer
		'Headers' => array(
			'Accept' => 'application/json',
			'Content-Type' => 'application/json',
		),
		'DatasetName'=> 'ImageSearch001', // 数据集名称，同一个账户下唯一。;是否必传：是
		'Mode'=> 'pic', // 指定检索方式为图片或文本，pic 为图片检索，text 为文本检索，默认为 pic。;是否必传：否
		'URI'=> 'cos://examplebucket-1250000000/huge_base.jpg', // 资源标识字段，表示需要建立索引的文件地址(Mode 为 pic 时必选)。;是否必传：否
		'Limit'=> 10, // 返回相关图片的数量，默认值为10，最大值为100。;是否必传：否
		'MatchThreshold'=> 1, // 出参 Score（相关图片匹配得分） 中，只有超过 MatchThreshold 值的结果才会返回。默认值为0，推荐值为80。;是否必传：否

    ));
    // 请求成功
    print_r($result);
} catch (\Exception $e) {
    // 请求失败
    echo($e);
}
