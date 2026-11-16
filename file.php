<?php
// 兼容旧版文件查看链接，跳转到新版前端页面
$hash = isset($_GET['hash']) && is_string($_GET['hash']) ? trim($_GET['hash']) : '';
$pwd = isset($_GET['pwd']) && is_string($_GET['pwd']) ? trim($_GET['pwd']) : '';
if(!preg_match('/^[0-9a-z]{32}$/i', $hash)){
    header('Location: ./#/');
    exit;
}
$url = './#/detail?hash='.$hash;
if($pwd !== '') $url .= '&pwd='.rawurlencode($pwd);
header('Location: '.$url, true, 301);
