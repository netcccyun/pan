<?php
error_reporting(E_ERROR | E_PARSE | E_COMPILE_ERROR);
if(defined('IN_CRONLITE'))return;
define('IN_CRONLITE', true);
define('SYSTEM_ROOT', dirname(__FILE__).'/');
define('ROOT', dirname(SYSTEM_ROOT).'/');
define('VERSION', '1534');
define('DB_VERSION', '1002');
date_default_timezone_set('Asia/Shanghai');
$date = date("Y-m-d H:i:s");

if(!$nosession)session_start();

include_once(SYSTEM_ROOT.'txprotect.php');
include_once(SYSTEM_ROOT."autoloader.php");
Autoloader::register();

require ROOT.'config.php';

//接口请求时以JSON返回安装状态，由前端提示跳转
function api_halt($msg, $url, $auto = false) {
	if (!defined('IN_API')) return;
	header('Content-Type: application/json; charset=UTF-8');
	exit(json_encode(['code'=>-10, 'msg'=>$msg, 'url'=>$url, 'auto'=>$auto], JSON_UNESCAPED_UNICODE));
}

if(!$dbconfig['user']||!$dbconfig['pwd']||!$dbconfig['dbname'])//检测安装1
{
api_halt('网站还没有安装', './install/', true);
header('Content-type:text/html;charset=utf-8');
echo '你还没安装！<a href="./install/">点此安装</a>';
exit();
}

$DB = new \lib\PdoHelper($dbconfig);

if($DB->query("select * from pre_config where 1")==FALSE)//检测安装2
{
api_halt('网站还没有安装', './install/', true);
header('Content-type:text/html;charset=utf-8');
echo '你还没安装！<a href="./install/">点此安装</a>';
exit();
}

include_once(SYSTEM_ROOT."functions.php");

$conf=getAllSetting();
define('SYS_KEY', $conf['syskey']);
$password_hash='!@#%!s!0';

if (!$conf['version'] || $conf['version'] < DB_VERSION) {
    if (!$install) {
		api_halt('请先完成网站升级', './install/update.php', true);
		header('Content-type:text/html;charset=utf-8');
        echo '请先完成网站升级！<a href="/install/update.php"><font color=red>点此升级</font></a>';
        exit;
    }
}

$scriptpath=str_replace('\\','/',$_SERVER['SCRIPT_NAME']);
$sitepath = substr($scriptpath, 0, strrpos($scriptpath, '/'));
$siteurl = (is_https() ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].$sitepath.'/';

$clientip=real_ip($conf['ip_type']?$conf['ip_type']:0);
if(isset($_COOKIE["admin_token"]))
{
	$token=authcode(daddslashes($_COOKIE['admin_token']), 'DECODE', SYS_KEY);
	if($token){
		list($user, $sid, $expiretime) = explode("\t", $token);
		$session=md5($conf['admin_user'].$conf['admin_pwd'].$password_hash);
		if($session==$sid && $expiretime>time()) {
			$islogin=1;
		}
	}
}
if(isset($_COOKIE["user_token"]))
{
	$token=authcode(daddslashes($_COOKIE['user_token']), 'DECODE', SYS_KEY);
	if($token){
		list($uid, $sid, $expiretime) = explode("\t", $token);
		if($userrow = $DB->getRow("SELECT * FROM pre_user WHERE uid='".intval($uid)."' LIMIT 1")){
			$session=md5($userrow['type'].$userrow['openid'].$password_hash);
			if($session===$sid && $expiretime>time()) {
				if($userrow['enable']==1){
					$islogin2=1;
				}else{
					$_SESSION['user_block'] = true;
				}
			}
		}
	}
}

//Composer 依赖库（网站根目录 vendor），前台和后台统一在这里加载
//分发的源码中不包含 vendor 目录，需要执行 composer install 安装
if(!is_file(ROOT.'vendor/autoload.php')){
	api_halt('缺少依赖库，请在网站根目录执行 composer install 安装依赖', './install/');
	sysmsg('<h2>缺少依赖库</h2><p>网站根目录下没有 vendor 目录，请在网站根目录执行以下命令安装依赖：</p><pre>composer install --no-dev</pre><p>如果服务器无法使用 Composer，可以在本地执行后将 vendor 目录上传到网站根目录。</p>');exit;
}
include_once(ROOT.'vendor/autoload.php');

if(defined('IN_ADMIN')) return;

$denyip = explode('|',$conf['blackip']);
if(in_array($clientip,$denyip) && !$islogin){
	Header("HTTP/1.1 403 Forbidden");
	exit;
}

//存储模块按文件关联的存储（pre_file.storage_id）加载，见 \lib\StorHelper

if (!file_exists(ROOT.'install/install.lock') && file_exists(ROOT.'install/index.php')) {
	api_halt('检测到无 install.lock 文件。如果您尚未安装本程序，请前往安装；如果您已经安装本程序，请手动放置一个空的 install.lock 文件到 /install 文件夹下，为了您站点安全，在您完成它之前我们不会工作。', './install/');
	sysmsg('<h2>检测到无 install.lock 文件</h2><ul><li><font size="4">如果您尚未安装本程序，请<a href="./install/">前往安装</a></font></li><li><font size="4">如果您已经安装本程序，请手动放置一个空的 install.lock 文件到 /install 文件夹下，<b>为了您站点安全，在您完成它之前我们不会工作。</b></font></li></ul><br/><h4>为什么必须建立 install.lock 文件？</h4>它是安装保护文件，如果检测不到它，就会认为站点还没安装，此时任何人都可以安装/重装你的网站。<br/><br/>');exit;
}

if($conf['cdnpublic']==1){
	$cdnpublic = 'https://cdnjs.webstatic.cn/ajax/libs/';
}elseif($conf['cdnpublic']==2){
	$cdnpublic = 'https://s4.zstatic.net/ajax/libs/';
}elseif($conf['cdnpublic']==3){
	$cdnpublic = 'https://cdnjs.snrat.com/ajax/libs/';
}else{
	$cdnpublic = './assets/libs/';
}
