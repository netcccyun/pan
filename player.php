<?php
// 兼容旧版播放器嵌入代码，跳转到新版前端播放器页面
$hash = isset($_GET['hash']) && is_string($_GET['hash']) ? trim($_GET['hash']) : '';
if(!preg_match('/^[0-9a-z]{32}$/i', $hash)){
    header('HTTP/1.1 404 Not Found');
    exit('404 Not Found');
}
header('Location: ./#/player?hash='.$hash, true, 301);
