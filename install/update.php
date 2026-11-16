<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);
require '../config.php';

@header('Content-Type: text/html; charset=UTF-8');

function random($length, $numeric = 0) {
	$seed = base_convert(md5(microtime().$_SERVER['DOCUMENT_ROOT']), 16, $numeric ? 10 : 35);
	$seed = $numeric ? (str_replace('0', '', $seed).'012340567890') : ($seed.'zZ'.strtoupper($seed));
	$hash = '';
	$max = strlen($seed) - 1;
	for($i = 0; $i < $length; $i++) {
		$hash .= $seed[mt_rand(0, $max)];
	}
	return $hash;
}

try{
	$db=new PDO("mysql:host=".$dbconfig['host'].";dbname=".$dbconfig['dbname'].";port=".$dbconfig['port'],$dbconfig['user'],$dbconfig['pwd']);
}catch(Exception $e){
	exit('链接数据库失败:'.$e->getMessage());
}
date_default_timezone_set("PRC");
$date = date("Y-m-d");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
$db->exec("set sql_mode = ''");
$db->exec("set names utf8mb4");

$version = 0;
if($rs = $db->query("SELECT v FROM pre_config WHERE k='version'")){
	$version = intval($rs->fetchColumn());
}

/**
 * 数据库版本号 => 需要执行的更新SQL文件
 * 按版本号从小到大依次执行所有高于当前版本的更新
 */
$updates = [
	1001 => 'update.sql',
	1002 => 'update2.sql',
];
ksort($updates);

if($version >= max(array_keys($updates))){
	exit('你的网站已经升级到最新版本了');
}

//执行SQL语句
$success=0;$error=0;$errorMsg=null;
function run_sqls($sqls){
	global $db, $success, $error, $errorMsg;
	foreach ($sqls as $value) {
		$value=trim($value);
		if(empty($value))continue;
		if($db->exec($value)===false){
			$error++;
			$dberror=$db->errorInfo();
			$errorMsg.=$dberror[2]."<br>";
		}else{
			$success++;
		}
	}
}

//1002：将原有存储设置迁移到存储管理表，已上传的文件关联到该存储
function migrate_storage(){
	global $db, $success, $error, $errorMsg;
	$count = $db->query("SELECT count(*) FROM pre_storage");
	if($count === false){
		$error++;
		$errorMsg.='存储表 pre_storage 不存在，无法迁移存储设置<br>';
		return;
	}
	if($count->fetchColumn() > 0) return;

	$conf = [];
	if($rs = $db->query("SELECT k,v FROM pre_config")){
		while($row = $rs->fetch(PDO::FETCH_ASSOC)){
			$conf[$row['k']] = $row['v'];
		}
	}
	$get = function($key, $default = '') use ($conf){
		return isset($conf[$key]) && $conf[$key] !== null ? trim($conf[$key]) : $default;
	};
	$cloud = [
		'uploadfile_type' => $get('uploadfile_type', '0') == '1' ? '1' : '0',
		'downfile_type' => $get('downfile_type', '0') == '1' ? '1' : '0',
		'downfile_protocol' => $get('downfile_protocol', '0') == '1' ? '1' : '0',
		'downfile_domain' => $get('downfile_domain'),
	];

	$type = $get('storage', 'local') ?: 'local';
	switch($type){
		case 'oss':
			$name = '阿里云OSS';
			$config = ['accessKeyId' => $get('oss_ak'), 'accessKeySecret' => $get('oss_sk'), 'endpoint' => $get('oss_endpoint'), 'bucket' => $get('oss_bucket')] + $cloud;
			break;
		case 'qcloud':
			$name = '腾讯云COS';
			$config = ['secretId' => $get('qcloud_id'), 'secretKey' => $get('qcloud_key'), 'region' => $get('qcloud_region'), 'bucket' => $get('qcloud_bucket')] + $cloud;
			break;
		case 'obs':
			$name = '华为云OBS';
			$config = ['accessKey' => $get('obs_ak'), 'secretKey' => $get('obs_sk'), 'endpoint' => $get('obs_endpoint'), 'bucket' => $get('obs_bucket')] + $cloud;
			break;
		case 'upyun':
			$name = '又拍云';
			$config = ['serviceName' => $get('upyun_name'), 'operatorName' => $get('upyun_user'), 'operatorPwd' => $get('upyun_pwd')] + $cloud;
			break;
		case 'qiniu':
			$name = '七牛云';
			$config = ['accessKey' => $get('qiniu_ak'), 'secretKey' => $get('qiniu_sk'), 'bucket' => $get('qiniu_bucket'), 'domain' => $get('qiniu_domain')] + $cloud;
			break;
		default:
			$type = 'local';
			$name = '本地存储';
			$config = ['filepath' => $get('filepath')];
			break;
	}

	$insert = $db->prepare("INSERT INTO pre_storage (name,type,config,status,isdefault,sort,addtime) VALUES (:name,:type,:config,:status,:isdefault,:sort,NOW())");
	$json = function($config){ return json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); };
	if(!$insert || !$insert->execute([':name'=>$name, ':type'=>$type, ':config'=>$json($config), ':status'=>1, ':isdefault'=>1, ':sort'=>0])){
		$error++;
		$dberror = $insert ? $insert->errorInfo() : $db->errorInfo();
		$errorMsg.='迁移存储设置失败：'.$dberror[2].'<br>';
		return;
	}
	$success++;
	$storage_id = intval($db->lastInsertId());

	//原来使用云存储的，额外添加一个未开启的本地存储，方便切换
	if($type != 'local'){
		if($insert->execute([':name'=>'本地存储', ':type'=>'local', ':config'=>$json(['filepath'=>'']), ':status'=>0, ':isdefault'=>0, ':sort'=>1])) $success++;
	}

	if($db->exec("UPDATE pre_file SET storage_id={$storage_id} WHERE storage_id=0")===false){
		$error++;
		$dberror=$db->errorInfo();
		$errorMsg.='关联已上传文件的存储失败：'.$dberror[2].'<br>';
	}else{
		$success++;
	}
}

foreach($updates as $ver => $file){
	if($version >= $ver) continue;
	$sqls = file_get_contents(__DIR__.'/'.$file);
	if($sqls === false) exit('更新文件 '.$file.' 不存在');
	run_sqls(explode(';', $sqls));

	if($ver == 1001){
		if(!$db->query("SELECT v FROM pre_config WHERE k='syskey'")->fetchColumn()){
			run_sqls(["REPLACE INTO `pre_config` VALUES ('syskey', '".random(32)."')"]);
		}
	}elseif($ver == 1002){
		migrate_storage();
	}

	run_sqls(["REPLACE INTO `pre_config` VALUES ('version', '".$ver."')"]);
	$version = $ver;
}

echo '成功执行SQL语句'.$success.'条！<br/>';
if($errorMsg){
//echo '<div class="alert alert-danger text-center" role="alert">'.$errorMsg.'</div>';
}
exit("<script language='javascript'>alert('网站数据库升级完成！');window.location.href='../';</script>");
?>
