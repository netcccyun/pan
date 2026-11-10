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
    //可以根据已提取的文件元数据（包含文件名、标签、路径、自定义标签、文本等字段）查询和统计数据集内文件，支持逻辑关系表达方式。
    $result = $cosClient->DatasetSimpleQuery(array(
        'AppId' => 'AppId', // 其中 APPID 获取参考 https://console.cloud.tencent.com/developer
		'Headers' => array(
			'Accept' => 'application/json',
			'Content-Type' => 'application/json',
		),
		'DatasetName'=> 'test', // 数据集名称，同一个账户下唯一。;是否必传：是
		// 简单查询参数条件，可自嵌套。;是否必传：是
        'Query'=> array(
            'Operation'=> 'and', // 操作运算符。枚举值： not：逻辑非。 or：逻辑或。 and：逻辑与。 lt：小于。 lte：小于等于。 gt：大于。 gte：大于等于。 eq：等于。 exist：存在性查询。 prefix：前缀查询。 match-phrase：字符串匹配查询。 nested：字段为数组时，其中同一对象内逻辑条件查询。;是否必传：是
            'SubQueries' => array(
                array(
                    'Field'=> 'ContentType',
                    'Value'=> 'image/jpeg',
                    'Operation'=> 'eq',
                ),
                array(
                    'Field'=> 'Size',
                    'Value'=> '1000',
                    'Operation'=> 'gt',
                ),
            ),
        ),

		'MaxResults'=> 100, // 返回文件元数据的最大个数，取值范围为0200。 使用聚合参数时，该值表示返回分组的最大个数，取值范围为02000。 不设置此参数或者设置为0时，则取默认值100。;是否必传：否
		'Sort'=> 'CustomId', // 排序字段列表。请参考字段和操作符的支持列表。 多个排序字段可使用半角逗号（,）分隔，例如：Size,Filename。 最多可设置5个排序字段。 排序字段顺序即为排序优先级顺序。;是否必传：是
		'Order'=> 'desc', // 排序字段的排序方式。取值如下： asc：升序； desc（默认）：降序。 多个排序方式可使用半角逗号（,）分隔，例如：asc,desc。 排序方式不可多于排序字段，即参数Order的元素数量需小于等于参数Sort的元素数量。例如Sort取值为Size,Filename时，Order可取值为asc,desc或asc。 排序方式少于排序字段时，未排序的字段默认取值asc。例如Sort取值为Size,Filename，Order取值为asc时，Filename默认排序方式为asc，即升序排列;是否必传：是
		// 聚合字段信息列表。 当您使用聚合查询时，仅返回聚合结果，不再返回匹配到的元信息列表。;是否必传：是
		'Aggregations'=> array(
		),

    ));
    // 请求成功
    print_r($result);
} catch (\Exception $e) {
    // 请求失败
    echo($e);
}
