<?php
/**
 * 存储账号授权回调（如 OneDrive）
 * 授权结果通过 postMessage 发回后台的存储编辑窗口，由管理员确认后保存
 */
define('IN_ADMIN', true);
include("../includes/common.php");

$ok = false;
$config = null;
if($islogin != 1){
	$msg = '请先登录后台';
}elseif(!empty($_GET['error'])){
	$msg = '授权失败：'.(isset($_GET['error_description']) ? $_GET['error_description'] : $_GET['error']);
}else{
	$config = \lib\StorHelper::oauthFinish(isset($_GET['code']) ? (string)$_GET['code'] : '', isset($_GET['state']) ? (string)$_GET['state'] : '');
	if($config){
		$ok = true;
		$msg = '授权成功，请回到存储编辑窗口点击保存';
	}else{
		$msg = \lib\StorHelper::errmsg();
	}
}
$state = isset($_GET['state']) ? (string)$_GET['state'] : '';
if($islogin == 1 && $state !== ''){
	//保存结果，供存储编辑窗口查询
	$_SESSION['storage_oauth_result'] = ['state' => $state, 'ok' => $ok, 'msg' => $msg, 'config' => $config];
}
$message = json_encode(['source' => 'pan-storage-oauth', 'state' => $state, 'ok' => $ok, 'msg' => $msg, 'config' => $config], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo $ok ? '授权成功' : '授权失败'; ?></title>
<style>
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:system-ui,-apple-system,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f8fafc;color:#0f172a}
.box{max-width:420px;margin:16px;padding:32px 28px;text-align:center;background:#fff;border:1px solid #e2e8f0;border-radius:12px}
.icon{font-size:40px;line-height:1}
h1{margin:12px 0 8px;font-size:20px}
p{margin:0;color:#64748b;font-size:14px;line-height:1.6;word-break:break-all}
@media (prefers-color-scheme: dark){body{background:#0f172a;color:#e2e8f0}.box{background:#1e293b;border-color:#334155}p{color:#94a3b8}}
</style>
</head>
<body>
<div class="box">
	<div class="icon"><?php echo $ok ? '✅' : '❌'; ?></div>
	<h1><?php echo $ok ? '授权成功' : '授权失败'; ?></h1>
	<p id="msg"><?php echo htmlspecialchars($msg); ?></p>
</div>
<script>
(function(){
	var data = <?php echo $message; ?>;
	try {
		if (window.opener && !window.opener.closed) window.opener.postMessage(data, location.origin);
	} catch (e) {}
	if (data.ok) {
		// 编辑窗口会自动获取授权结果
		document.getElementById('msg').textContent = '授权成功，窗口即将关闭，请回到存储编辑窗口点击保存';
		setTimeout(function(){ window.close(); }, 1500);
	}
})();
</script>
</body>
</html>
