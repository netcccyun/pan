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
    //生成边转边播的播放列表能够分析视频文件产出 m3u8 文件。生成播放列表后即时播放，并根据播放进度实施按需转码，相比离线转码能极大减少了转码等待时间并大幅度降低了转码和存储开销
    $result = $cosClient->GeneratePlayList(array(
        'Bucket' => '###bucketName###', //存储桶名称，由BucketName-Appid 组成，可以在COS控制台查看 https://console.cloud.tencent.com/cos5/bucket
		'Headers' => array(
			'Content-Type' => 'application/xml',
		),
		'Tag'=> 'undefined', // 创建任务的Tag：GeneratePlayList;是否必传：是
		// 待操作的文件信息;是否必传：是
		'Input'=> array(
		  'Object'=> 'undefined', // 文件路径;是否必传：否
		),
		// 操作规则;是否必传：是
		'Operation'=> array(
		  'UserData'=> 'undefined', // 透传用户信息, 可打印的 ASCII 码, 长度不超过1024;是否必传：否
		  'JobLevel'=> 'undefined', // 任务优先级，级别限制：0 、1 、2 。级别越大任务优先级越高，默认为0;是否必传：否
		),
		'CallBack'=> 'undefined', // 任务回调地址，优先级高于队列的回调地址。设置为 no 时，表示队列的回调地址不产生回调;是否必传：否
		'CallBackFormat'=> 'undefined', // 任务回调格式，JSON 或 XML，默认 XML，优先级高于队列的回调格式;是否必传：否
		'QueueType'=> 'undefined', // 任务所在的队列类型，限制为 SpeedTranscoding, 表示为开启倍速转码;是否必传：否
		'CallBackType'=> 'undefined', // 任务回调类型，Url 或 TDMQ，默认 Url，优先级高于队列的回调类型;是否必传：否
		// 任务回调TDMQ配置，当 CallBackType 为 TDMQ 时必填。详情见 CallBackMqConfig﻿;是否必传：否
		'CallBackMqConfig'=> array(
		  'MqRegion'=> 'undefined', // 消息队列所属园区，目前支持园区 sh（上海）、bj（北京）、gz（广州）、cd（成都）、hk（中国香港）;是否必传：是
		  'MqMode'=> 'undefined', // 消息队列使用模式，默认 Queue ：主题订阅：Topic队列服务: Queue;是否必传：是
		  'MqName'=> 'undefined', // TDMQ 主题名称;是否必传：是
		),

    ));
    // 请求成功
    print_r($result);
} catch (\Exception $e) {
    // 请求失败
    echo($e);
}
