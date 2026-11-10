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
    //创建明水印模板
    $result = $cosClient->CreateWatermarkTemplate(array(
        'Bucket' => '###bucketName###', //存储桶名称，由BucketName-Appid 组成，可以在COS控制台查看 https://console.cloud.tencent.com/cos5/bucket
		'Headers' => array(
			'Content-Type' => 'application/xml',
		),
		'Tag'=> 'undefined', // 模板类型: Watermark;是否必传：否
		'Name'=> 'undefined', // 模板名称，仅支持中文、英文、数字、_、-和*，长度不超过 64;是否必传：否
		// 水印信息;是否必传：否
		'Watermark'=> array(
		  'Type'=> 'undefined', // 水印类型Text：文字水印Image：图片水印;是否必传：是
		  'Pos'=> 'undefined', // 基准位置TopRightTopLeftBottomRightBottomLeftLeftRightTopBottomCenter;是否必传：是
		  'LocMode'=> 'undefined', // 偏移方式Relativity：按比例Absolute：固定位置;是否必传：是
		  'Dx'=> 'undefined', // 水平偏移在图片水印中，如果 Background 为 true，当 locMode 为 Relativity 时，为%，值范围：[-300 0]；当 locMode 为 Absolute 时，为 px，值范围：[-4096 0]。在图片水印中，如果 Background 为 false，当 locMode 为 Relativity 时，为%，值范围：[0 100]；当 locMode 为 Absolute 时，为 px，值范围：[0 4096]。在文字水印中，当 locMode 为 Relativity 时，为%，值范围：[0 100]；当 locMode 为 Absolute 时，为 px，值范围：[0 4096]。当Pos为Top、Bottom和Center时，该参数无效。;是否必传：是
		  'Dy'=> 'undefined', // 垂直偏移在图片水印中，如果 Background 为 true，当 locMode 为 Relativity 时，为%，值范围：[-300 0]；当 locMode 为 Absolute 时，为 px，值范围：[-4096 0]。在图片水印中，如果 Background 为 false，当 locMode 为 Relativity 时，为%，值范围：[0 100]；当 locMode 为 Absolute 时，为 px，值范围：[0 4096]。在文字水印中，当 locMode 为 Relativity 时，为%，值范围：[0 100]；当 locMode 为 Absolute 时，为 px，值范围：[0 4096]。当Pos为Left、Right和Center时，该参数无效。;是否必传：是
		  'StartTime'=> 'undefined', // 水印开始时间[0，视频时长]  单位为秒 支持 float 格式，执行精度精确到毫秒;是否必传：否
		  'EndTime'=> 'undefined', // 水印结束时间[0，视频时长] 单位为秒 支持 float 格式，执行精度精确到毫秒;是否必传：否
		),

    ));
    // 请求成功
    print_r($result);
} catch (\Exception $e) {
    // 请求失败
    echo($e);
}
