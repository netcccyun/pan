<?php
/**
 * 后台管理接口
**/
define('IN_ADMIN', true);
define('IN_API', true);
include("../includes/common.php");
$act=isset($_GET['act'])?daddslashes($_GET['act']):null;

if(!checkRefererHost())exit('{"code":403}');

@header('Content-Type: application/json; charset=UTF-8');

function json_out($data){
	exit(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
}

function json_msg($code, $msg){
	json_out(['code'=>$code, 'msg'=>$msg]);
}

//删除文件所在存储中的文件
function stor_delete($row){
	$stor = \lib\StorHelper::getFileStor($row);
	return $stor ? $stor->delete($row['hash']) : false;
}

//存储下的文件数量
function storage_file_count($id){
	global $DB;
	return intval($DB->getColumn("SELECT count(*) FROM pre_file WHERE storage_id=:id", [':id'=>intval($id)]));
}

//网站根目录地址（后台目录可任意改名）
function root_siteurl(){
	$scriptpath = str_replace('\\','/',$_SERVER['SCRIPT_NAME']);
	$adminpath = substr($scriptpath, 0, strrpos($scriptpath, '/'));
	$rootpath = substr($adminpath, 0, strrpos($adminpath, '/'));
	return (is_https() ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].$rootpath.'/';
}

//无需登录的接口
switch($act){
case 'status':
	json_out(['code'=>0, 'islogin'=>$islogin==1?1:0, 'title'=>$conf['title'], 'admin_user'=>$islogin==1?$conf['admin_user']:null]);
break;
case 'captcha':
	//服务器没有 GD 库时不启用验证码，避免无法登录后台
	if(!\lib\Captcha::available())json_out(['code'=>0, 'enabled'=>false]);
	json_out(['code'=>0, 'enabled'=>true, 'image'=>\lib\Captcha::create('admin_login')]);
break;
case 'login':
	$user=trim((string)$_POST['user']);
	$pass=(string)$_POST['pass'];
	if($user==='' || $pass==='')json_msg(-1, '用户名和密码不能为空');
	if(\lib\Captcha::available()){
		$result=\lib\Captcha::check('admin_login', isset($_POST['captcha'])?$_POST['captcha']:'');
		if($result!==true)json_out(['code'=>-1, 'msg'=>$result, 'captcha_error'=>1]);
	}
	if(!$_SESSION['pass_error'])$_SESSION['pass_error']=0;
	if($_SESSION['pass_error']>5)json_msg(-1, '密码错误次数过多，请稍后再试');
	if($user===$conf['admin_user'] && $pass===$conf['admin_pwd']){
		$session=md5($user.$pass.$password_hash);
		$expiretime=time()+2592000;
		$token=authcode("{$user}\t{$session}\t{$expiretime}", 'ENCODE', SYS_KEY);
		setcookie("admin_token", $token, time() + 2592000, '', '', false, true);
		unset($_SESSION['pass_error']);
		json_msg(0, '登录成功');
	}
	$_SESSION['pass_error']++;
	json_msg(-1, '用户名或密码不正确');
break;
}

if($islogin!=1)json_msg(-3, '请先登录');

switch($act){
case 'logout':
	setcookie("admin_token", "", time() - 2592000, '', '', false, true);
	json_msg(0, '已退出登录');
break;
case 'getcount':
	$thtime=date("Y-m-d").' 00:00:00';
	$lastday=date("Y-m-d",strtotime("-1 day")).' 00:00:00';
	json_out([
		'code'=>0,
		'count1'=>intval($DB->getColumn("SELECT count(*) from pre_file")),
		'count2'=>intval($DB->getColumn("SELECT count(*) from pre_file WHERE addtime>='$thtime'")),
		'count3'=>intval($DB->getColumn("SELECT count(*) from pre_file WHERE addtime>='$lastday' AND addtime<'$thtime'")),
		'count4'=>intval($DB->getColumn("SELECT count(*) from pre_user")),
		'server'=>[
			'php'=>phpversion().(ZEND_THREAD_SAFE?' TS':' NTS'),
			'mysql'=>$DB->getColumn("select VERSION()"),
			'software'=>$_SERVER['SERVER_SOFTWARE'],
			'time'=>$date,
			'post_max_size'=>ini_get('post_max_size'),
			'upload_max_filesize'=>ini_get('upload_max_filesize'),
		],
		'version'=>VERSION,
		'checkupdate'=>'//auth.cccyun.cc/app/pan.php?ver='.VERSION,
	]);
break;
case 'getSetting':
	$data=$conf;
	unset($data['admin_pwd'], $data['syskey']);
	json_out(['code'=>0, 'data'=>$data, 'siteurl'=>root_siteurl()]);
break;
case 'set':
	foreach($_POST as $k=>$v){
		if(in_array($k, ['admin_user', 'admin_pwd', 'syskey'])) continue;
		if(is_array($v)) $v = implode(',', $v);
		saveSetting($k, trim($v));
	}
	json_msg(0, '设置保存成功');
break;
case 'setAccount':
	$user=trim((string)$_POST['user']);
	$oldpwd=(string)$_POST['oldpwd'];
	$newpwd=(string)$_POST['newpwd'];
	$newpwd2=(string)$_POST['newpwd2'];
	if($user==='')json_msg(-1, '用户名不能为空');
	$changepwd = $newpwd!=='' || $newpwd2!=='';
	if($changepwd){
		if($oldpwd!==$conf['admin_pwd'])json_msg(-1, '旧密码不正确');
		if($newpwd!==$newpwd2)json_msg(-1, '两次输入的密码不一致');
	}
	saveSetting('admin_user', $user);
	if($changepwd)saveSetting('admin_pwd', $newpwd);
	if($user!==$conf['admin_user'] || $changepwd){
		setcookie("admin_token", "", time() - 2592000, '', '', false, true);
		json_out(['code'=>0, 'msg'=>'修改成功，请重新登录', 'relogin'=>1]);
	}
	json_out(['code'=>0, 'msg'=>'修改成功', 'relogin'=>0]);
break;
case 'iptype':
	$result=[];
	foreach(['0_X_FORWARDED_FOR', '1_X_REAL_IP', '2_REMOTE_ADDR'] as $i=>$name){
		$ip=real_ip($i);
		$result[]=['value'=>$i, 'name'=>$name, 'ip'=>$ip, 'city'=>get_ip_city($ip)];
	}
	json_out(['code'=>0, 'data'=>$result]);
break;

case 'userList':
	$sql=" 1=1";
	if(isset($_POST['dstatus']) && $_POST['dstatus']>-1) {
		$dstatus = intval($_POST['dstatus']);
		$sql.=" AND `enable`={$dstatus}";
	}
	if(isset($_POST['kw']) && $_POST['kw']!=='') {
		$type = intval($_POST['type']);
		$kw = trim(daddslashes($_POST['kw']));
		if($type == 1){
			$sql.=" AND `uid`='{$kw}'";
		}elseif($type == 2){
			$sql.=" AND `openid`='{$kw}'";
		}elseif($type == 3){
			$sql.=" AND `nickname` LIKE '%{$kw}%'";
		}elseif($type == 4){
			$sql.=" AND `loginip`='{$kw}'";
		}
	}
	$offset = max(0, intval($_POST['offset']));
	$limit = min(100, max(1, intval($_POST['limit'])));
	$total = intval($DB->getColumn("SELECT count(*) from pre_user WHERE{$sql}"));
	$list = $DB->getAll("SELECT * FROM pre_user WHERE{$sql} order by uid desc limit $offset,$limit");
	json_out(['code'=>0, 'total'=>$total, 'rows'=>$list]);
break;
case 'setUserEnable':
	$uid=intval($_POST['uid']);
	$enable=intval($_POST['enable']);
	if($DB->exec("UPDATE pre_user SET enable=:enable WHERE uid=:uid", [':enable'=>$enable, ':uid'=>$uid])!==false)json_msg(0, '修改用户成功');
	else json_msg(-1, '修改用户失败['.$DB->error().']');
break;
case 'saveUserInfo':
	$uid=intval($_POST['uid']);
	$level=intval($_POST['level']);
	if($DB->exec("UPDATE pre_user SET level=:level WHERE uid=:uid", [':level'=>$level, ':uid'=>$uid])!==false)json_msg(0, '修改用户成功');
	else json_msg(-1, '修改用户失败['.$DB->error().']');
break;
case 'delUser':
	$uid=intval($_POST['uid']);
	$row=$DB->getRow("select * from pre_user where uid=:uid limit 1", [':uid'=>$uid]);
	if(!$row)json_msg(-1, '当前用户不存在');
	if($DB->exec("DELETE FROM pre_user WHERE uid=:uid", [':uid'=>$uid]))json_msg(0, '删除用户成功');
	else json_msg(-1, '删除用户失败['.$DB->error().']');
break;

case 'fileList':
	$sql=" 1=1";
	if(isset($_POST['uid']) && $_POST['uid']!=='') {
		$uid = intval($_POST['uid']);
		$sql.=" AND `uid`='$uid'";
	}
	if(isset($_POST['dstatus']) && $_POST['dstatus']>-1) {
		$dstatus = intval($_POST['dstatus']);
		$sql.=" AND `block`={$dstatus}";
	}
	if(isset($_POST['storage_id']) && intval($_POST['storage_id'])>0) {
		$storage_id = intval($_POST['storage_id']);
		$sql.=" AND `storage_id`={$storage_id}";
	}
	if(isset($_POST['kw']) && $_POST['kw']!=='') {
		$type = intval($_POST['type']);
		$kw = trim(daddslashes($_POST['kw']));
		if($type == 1){
			$kw = daddslashes(htmlspecialchars(trim($_POST['kw'])));
			$sql.=" AND `name` LIKE '%{$kw}%'";
		}elseif($type == 2){
			$sql.=" AND `hash`='{$kw}'";
		}elseif($type == 3){
			$sql.=" AND `type`='{$kw}'";
		}elseif($type == 4){
			$sql.=" AND `ip`='{$kw}'";
		}
	}
	$orderby = $_POST['orderby'] == 1 ? 'count desc' : 'id desc';
	$offset = max(0, intval($_POST['offset']));
	$limit = min(100, max(1, intval($_POST['limit'])));
	$total = intval($DB->getColumn("SELECT count(*) from pre_file WHERE{$sql}"));
	$list = $DB->getAll("SELECT * FROM pre_file WHERE{$sql} order by {$orderby} limit $offset,$limit");
	$storage_names = [];
	foreach($DB->getAll("SELECT id,name FROM pre_storage") as $storage){
		$storage_names[$storage['id']] = $storage['name'];
	}
	$rows = [];
	foreach($list as $row){
		$ext = $row['type'] ? $row['type'] : 'file';
		$rows[] = [
			'id'=>intval($row['id']),
			'uid'=>intval($row['uid']),
			'storage_id'=>intval($row['storage_id']),
			'storage_name'=>isset($storage_names[$row['storage_id']]) ? $storage_names[$row['storage_id']] : '未知存储',
			'name'=>htmlspecialchars_decode($row['name']),
			'type'=>$row['type'],
			'size'=>intval($row['size']),
			'hash'=>$row['hash'],
			'addtime'=>$row['addtime'],
			'lasttime'=>$row['lasttime'],
			'ip'=>$row['ip'],
			'count'=>intval($row['count']),
			'block'=>intval($row['block']),
			'hide'=>intval($row['hide']),
			'haspwd'=>!empty($row['pwd']) ? 1 : 0,
			'view_type'=>is_view($row['type']) || in_array((string)$row['type'], get_text_types()) && intval($row['size']) <= TEXT_VIEW_MAXSIZE ? get_view_type($row['type']) : null,
			'fileurl'=>'./down.php/'.$row['hash'].'.'.$ext,
			'viewurl'=>'./view.php/'.$row['hash'].'.'.$ext,
			'pageurl'=>'../#/detail?hash='.$row['hash'].(!empty($row['pwd']) ? '&pwd='.urlencode(htmlspecialchars_decode($row['pwd'])) : ''),
		];
	}
	json_out(['code'=>0, 'total'=>$total, 'rows'=>$rows]);
break;
case 'setBlock':
	$id=intval($_POST['id']);
	$status=intval($_POST['status']);
	if($DB->exec("UPDATE pre_file SET `block`=:status WHERE id=:id", [':status'=>$status, ':id'=>$id])!==false)json_msg(0, '修改成功');
	else json_msg(-1, '修改失败['.$DB->error().']');
break;
case 'delFile':
	$id=intval($_POST['id']);
	$row=$DB->getRow("select * from pre_file where id=:id limit 1", [':id'=>$id]);
	if(!$row)json_msg(-1, '当前文件不存在');
	stor_delete($row);
	if($DB->exec("DELETE FROM pre_file WHERE id=:id", [':id'=>$id]))json_msg(0, '删除文件成功');
	else json_msg(-1, '删除文件失败['.$DB->error().']');
break;
case 'operation':
	$status=intval($_POST['status']);
	$checkbox=isset($_POST['checkbox']) && is_array($_POST['checkbox']) ? $_POST['checkbox'] : [];
	if(!$checkbox)json_msg(-1, '未选中文件');
	if($status == 2)$opname = '解封';
	elseif($status == 1)$opname = '封禁';
	else $opname = '删除';
	$i=0;
	foreach($checkbox as $id){
		$id = intval($id);
		if($status == 0){
			$row=$DB->getRow("select hash,storage_id from pre_file where id=:id limit 1", [':id' => $id]);
			if($row)stor_delete($row);
			$DB->exec("DELETE FROM pre_file WHERE id=:id", [':id' => $id]);
		}elseif($status == 1){
			$DB->exec("UPDATE pre_file SET `block`=1 WHERE id=:id", [':id' => $id]);
		}elseif($status == 2){
			$DB->exec("UPDATE pre_file SET `block`=0 WHERE id=:id", [':id' => $id]);
		}
		$i++;
	}
	json_msg(0, '成功'.$opname.$i.'个文件');
break;
case 'getFileInfo':
	$id=intval($_GET['id']);
	$row=$DB->getRow("select * from pre_file where id=:id limit 1", [':id'=>$id]);
	if(!$row)json_msg(-1, '当前文件不存在');
	json_out(['code'=>0, 'data'=>[
		'id'=>intval($row['id']),
		'name'=>htmlspecialchars_decode($row['name']),
		'type'=>htmlspecialchars_decode($row['type']),
		'size'=>intval($row['size']),
		'hash'=>$row['hash'],
		'hide'=>intval($row['hide']),
		'pwd'=>$row['pwd'] ? htmlspecialchars_decode($row['pwd']) : '',
	]]);
break;
case 'fileContent':
	$id=intval($_GET['id']);
	$row=$DB->getRow("select * from pre_file where id=:id limit 1", [':id'=>$id]);
	if(!$row)json_msg(-1, '当前文件不存在');
	if(!in_array((string)$row['type'], get_text_types()))json_msg(-1, '该文件不支持在线预览');
	if(intval($row['size']) > TEXT_VIEW_MAXSIZE)json_msg(-1, '文件超过'.size_format(TEXT_VIEW_MAXSIZE).'，请下载后查看');
	$content = get_text_content($row);
	if($content === false)json_msg(-1, '读取文件失败'.(\lib\StorHelper::errmsg() ? '，'.\lib\StorHelper::errmsg() : ''));
	json_out(['code'=>0, 'content'=>$content]);
break;
case 'saveFileInfo':
	$id = intval($_POST['id']);
	$name = trim(htmlspecialchars($_POST['name']));
	$type = trim(htmlspecialchars($_POST['type']));
	$hide = intval($_POST['hide']);
	$ispwd = intval($_POST['ispwd']);
	$pwd = $ispwd==1?trim(htmlspecialchars($_POST['pwd'])):null;
	if(empty($name))json_msg(-1, '文件名称不能为空');
	if($ispwd==1){
		if(empty($pwd))json_msg(-1, '请填写下载密码');
		if(!preg_match('/^[a-zA-Z0-9]+$/', $pwd))json_msg(-1, '下载密码只能为字母和数字');
	}
	$data = [':id'=>$id, ':name'=>$name, ':type'=>$type, ':hide'=>$hide, ':pwd'=>$pwd];
	if($DB->exec("UPDATE `pre_file` SET `name`=:name,`type`=:type,`hide`=:hide,`pwd`=:pwd WHERE `id`=:id", $data)!==false)json_msg(0, '修改文件信息成功');
	else json_msg(-1, '修改文件信息失败['.$DB->error().']');
break;

//存储管理
case 'storageTypes':
	$types = [];
	foreach(\lib\StorHelper::getTypes() as $type){
		unset($type['class'], $type['sort']);
		$types[] = $type;
	}
	json_out(['code'=>0, 'data'=>$types]);
break;
case 'storageOauthUrl':
	$type = trim((string)$_POST['type']);
	$input = json_decode((string)$_POST['config'], true);
	$redirect_uri = trim((string)$_POST['redirect_uri']);
	//回调地址只能是本站后台的 oauth.php，或手动授权使用的 http://localhost
	$parts = parse_url($redirect_uri);
	$isLocal = $redirect_uri === 'http://localhost';
	$isSelf = $parts && in_array($parts['scheme'] ?? '', ['http', 'https']) && strcasecmp(($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : ''), $_SERVER['HTTP_HOST']) === 0 && substr($parts['path'] ?? '', -10) === '/oauth.php' && empty($parts['query']);
	if(!$isLocal && !$isSelf)json_msg(-1, '回调地址不正确');
	$result = \lib\StorHelper::oauthStart($type, $input, $redirect_uri);
	if(!$result)json_msg(-1, \lib\StorHelper::errmsg());
	unset($_SESSION['storage_oauth_result']);
	json_out(['code'=>0, 'url'=>$result['url'], 'state'=>$result['state']]);
break;
case 'storageOauthResult':
	//查询回调页面的授权结果（授权窗口与后台失去联系时使用）
	$state = (string)$_POST['state'];
	$result = $_SESSION['storage_oauth_result'] ?? null;
	if(!$result || !$state || !hash_equals($result['state'], $state))json_out(['code'=>1, 'msg'=>'等待授权']);
	unset($_SESSION['storage_oauth_result']);
	if(!$result['ok'])json_msg(-1, $result['msg']);
	json_out(['code'=>0, 'msg'=>$result['msg'], 'config'=>$result['config']]);
break;
case 'storageOauthToken':
	//手动授权：粘贴授权后跳转的地址
	$url = trim((string)$_POST['url']);
	$query = [];
	parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
	if(!empty($query['error']))json_msg(-1, '授权失败：'.(isset($query['error_description']) ? $query['error_description'] : $query['error']));
	if(empty($query['code']) || empty($query['state']))json_msg(-1, '地址中没有授权码，请粘贴授权后浏览器地址栏中的完整网址');
	$result = \lib\StorHelper::oauthFinish((string)$query['code'], (string)$query['state']);
	if(!$result)json_msg(-1, \lib\StorHelper::errmsg());
	json_out(['code'=>0, 'msg'=>'授权成功', 'config'=>$result]);
break;
case 'storageList':
	$counts = [];
	foreach($DB->getAll("SELECT storage_id,count(*) AS num FROM pre_file GROUP BY storage_id") as $row){
		$counts[$row['storage_id']] = intval($row['num']);
	}
	$types = \lib\StorHelper::getTypes();
	$list = [];
	foreach($DB->getAll("SELECT * FROM pre_storage ORDER BY sort ASC,id ASC") as $row){
		$config = $row['config'] ? json_decode($row['config'], true) : [];
		$list[] = [
			'id'=>intval($row['id']),
			'name'=>$row['name'],
			'type'=>$row['type'],
			'type_name'=>isset($types[$row['type']]) ? $types[$row['type']]['name'] : $row['type'].'（不存在）',
			'icon'=>isset($types[$row['type']]) ? $types[$row['type']]['icon'] : 'i-lucide-circle-help',
			'available'=>isset($types[$row['type']]) && $types[$row['type']]['available'] ? 1 : 0,
			'config'=>is_array($config) ? $config : new stdClass(),
			'status'=>intval($row['status']),
			'isdefault'=>intval($row['isdefault']),
			'sort'=>intval($row['sort']),
			'addtime'=>$row['addtime'],
			'filecount'=>isset($counts[$row['id']]) ? $counts[$row['id']] : 0,
		];
	}
	json_out(['code'=>0, 'data'=>$list]);
break;
case 'saveStorage':
	$id = intval($_POST['id']);
	$name = trim(strip_tags((string)$_POST['name']));
	$type = trim((string)$_POST['type']);
	$status = intval($_POST['status']) == 1 ? 1 : 0;
	$sort = intval($_POST['sort']);
	$input = json_decode((string)$_POST['config'], true);
	if($name === '')json_msg(-1, '存储名称不能为空');
	if(mb_strlen($name) > 50)json_msg(-1, '存储名称不能超过50个字');
	$typeInfo = \lib\StorHelper::getType($type);
	if(!$typeInfo)json_msg(-1, '存储类型不存在');
	if(!$typeInfo['available'])json_msg(-1, $typeInfo['name'].'在当前环境不可用');
	if($id > 0){
		$row = $DB->getRow("SELECT * FROM pre_storage WHERE id=:id LIMIT 1", [':id'=>$id]);
		if(!$row)json_msg(-1, '存储不存在');
		if($row['type'] !== $type && storage_file_count($id) > 0)json_msg(-1, '该存储下已有文件，不能修改存储类型');
		if($row['isdefault'] == 1 && $status == 0)json_msg(-1, '默认存储不能关闭');
	}
	$config = \lib\StorHelper::filterConfig($type, $input);
	if(!is_array($config))json_msg(-1, $config);
	$data = [':name'=>$name, ':type'=>$type, ':config'=>json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ':status'=>$status, ':sort'=>$sort];
	if($id > 0){
		$data[':id'] = $id;
		if($DB->exec("UPDATE pre_storage SET name=:name,type=:type,config=:config,status=:status,sort=:sort WHERE id=:id", $data) === false)json_msg(-1, '保存失败['.$DB->error().']');
	}else{
		if(!$DB->exec("INSERT INTO pre_storage (name,type,config,status,isdefault,sort,addtime) VALUES (:name,:type,:config,:status,0,:sort,NOW())", $data))json_msg(-1, '添加失败['.$DB->error().']');
		$id = intval($DB->lastInsertId());
	}
	//没有默认存储时，将当前存储设为默认
	if($status == 1 && !$DB->getColumn("SELECT id FROM pre_storage WHERE isdefault=1 LIMIT 1")){
		$DB->exec("UPDATE pre_storage SET isdefault=1 WHERE id=:id", [':id'=>$id]);
	}
	json_out(['code'=>0, 'msg'=>'保存成功', 'id'=>$id]);
break;
case 'setStorageDefault':
	$id = intval($_POST['id']);
	$row = $DB->getRow("SELECT * FROM pre_storage WHERE id=:id LIMIT 1", [':id'=>$id]);
	if(!$row)json_msg(-1, '存储不存在');
	$typeInfo = \lib\StorHelper::getType($row['type']);
	if(!$typeInfo || !$typeInfo['available'])json_msg(-1, '该存储类型在当前环境不可用');
	$DB->exec("UPDATE pre_storage SET isdefault=0 WHERE id<>:id", [':id'=>$id]);
	$DB->exec("UPDATE pre_storage SET isdefault=1,status=1 WHERE id=:id", [':id'=>$id]);
	json_msg(0, '已将「'.$row['name'].'」设为默认存储');
break;
case 'setStorageStatus':
	$id = intval($_POST['id']);
	$status = intval($_POST['status']) == 1 ? 1 : 0;
	$row = $DB->getRow("SELECT * FROM pre_storage WHERE id=:id LIMIT 1", [':id'=>$id]);
	if(!$row)json_msg(-1, '存储不存在');
	if($row['isdefault'] == 1 && $status == 0)json_msg(-1, '默认存储不能关闭');
	if($DB->exec("UPDATE pre_storage SET status=:status WHERE id=:id", [':status'=>$status, ':id'=>$id]) === false)json_msg(-1, '修改失败['.$DB->error().']');
	json_msg(0, $status ? '已开启' : '已关闭');
break;
case 'delStorage':
	$id = intval($_POST['id']);
	$row = $DB->getRow("SELECT * FROM pre_storage WHERE id=:id LIMIT 1", [':id'=>$id]);
	if(!$row)json_msg(-1, '存储不存在');
	if($row['isdefault'] == 1)json_msg(-1, '默认存储不能删除');
	$count = storage_file_count($id);
	if($count > 0)json_msg(-1, '该存储下还有'.$count.'个文件，不能删除');
	if($DB->exec("DELETE FROM pre_storage WHERE id=:id", [':id'=>$id]))json_msg(0, '删除成功');
	else json_msg(-1, '删除失败['.$DB->error().']');
break;
default:
	json_msg(-4, 'No Act');
break;
}
