<?php
define('IN_API', true);
$nosecu = true;
include("./includes/common.php");
$act=isset($_GET['act'])?daddslashes($_GET['act']):null;

if(!checkRefererHost())exit('{"code":403}');

@header('Content-Type: application/json; charset=UTF-8');

if($islogin2 && $userrow['level']>0){
	$conf['upload_limit']=0;
	$conf['videoreview']=0;
	$conf['type_block']=null;
	$conf['name_block']=null;
}

/**
 * 判断当前用户能否管理（修改、删除）该文件
 * 登录用户可以管理自己上传的文件，未登录用户只能管理本次会话中 7 天内上传的文件
 * @param string $action 操作名称，用于错误提示
 * @return true|string 不能管理时返回原因
 */
function file_manage_check($row, $action = '修改'){
	global $islogin2, $uid;
	if($islogin2 && $row['uid']!=$uid || !$islogin2 && (!isset($_SESSION['fileids']) || !in_array($row['id'], $_SESSION['fileids']))) return '无权限';
	if($row['block']==1) return '文件已被冻结，无法'.$action;
	if(!$islogin2 && strtotime($row['addtime'])<strtotime("-7 days")) return '无法'.$action.'7天前的文件';
	return true;
}

//删除文件（存储中的文件和数据库记录）
function file_delete_row($row){
	global $DB;
	$stor = \lib\StorHelper::getFileStor($row);
	if($stor) $stor->delete($row['hash']);
	return $DB->exec("DELETE FROM pre_file WHERE id=:id", [':id'=>$row['id']]);
}

switch($act){
case 'pre_upload':
	if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	if($conf['forcelogin']==1 && !$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	if(!isset($_POST['name']) || !is_string($_POST['name']) || !isset($_POST['hash']) || !is_string($_POST['hash']))exit('{"code":-1,"msg":"参数错误"}');
	$name = trim(htmlspecialchars(clean_file_name($_POST['name'])));
	$hash = trim($_POST['hash']);
	$size = intval($_POST['size']);
	$hide = $_POST['show']==1?0:1;
	$ispwd = intval($_POST['ispwd']);
	$pwd = $ispwd==1 && is_string($_POST['pwd'])?trim(htmlspecialchars($_POST['pwd'])):null;
	$name = str_replace(['/','\\',':','*','"','<','>','|','?'],'',$name);
	if(mb_strlen($name, 'UTF-8') > 200)exit('{"code":-1,"msg":"文件名太长"}');
	if($size <= 0)exit('{"code":-1,"msg":"不能上传空文件"}');
	if(empty($name))exit('{"code":-1,"msg":"文件名不能为空"}');
	if(!preg_match('/^[0-9a-z]{32}$/i', $hash))exit('{"code":-1,"msg":"hash error"}');
	if($ispwd==1 && !empty($pwd)){
		if (!preg_match('/^[a-zA-Z0-9]+$/', $pwd)) {
			exit('{"code":-1,"msg":"文件密码只能为字母和数字"}');
		}
	}
	$ext=get_file_ext($name);
	if($conf['type_block']){
		$type_block = explode('|',$conf['type_block']);
		if(in_array($ext,$type_block)){
			exit('{"code":-1,"msg":"文件上传失败，不支持上传该格式文件","error":"block"}');
		}
	}
	if($conf['name_block']){
		$name_block = explode('|',$conf['name_block']);
		foreach($name_block as $row){
			if(strpos($name,$row)!==false){
				exit('{"code":-1,"msg":"文件上传失败","error":"block"}');
			}
		}
	}
	$limit_size = intval($conf['upload_size']);
	if($limit_size > 0 && $size > $limit_size * 1024 * 1024){
		exit('{"code":-1,"msg":"上传文件大小限制'.$limit_size.'MB"}');
	}
	if($conf['upload_limit']>0){
		$thisday = date("Y-m-d 00:00:00");
		if($islogin2){
			$ipcount=$DB->getColumn("SELECT count(*) from pre_file WHERE uid='$uid' AND addtime>='".$thisday."'");
		}else{
			$ipcount=$DB->getColumn("SELECT count(*) from pre_file WHERE ip='$clientip' AND addtime>='".$thisday."'");
		}
		if($ipcount>=$conf['upload_limit']){
			exit('{"code":-1,"msg":"你今天上传文件的数量已超过限制"}');
		}
	}
	$row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash", [':hash'=>$hash]);
	if($row){
		unset($_SESSION['csrf_token']);
		$result = ['code'=>1, 'msg'=>'本站已存在该文件', 'exists'=>1, 'hash'=>$hash, 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$row['id']];
		exit(json_encode($result));
	}

	$storage = \lib\StorHelper::getUploadStorage(isset($_POST['storage_id']) ? intval($_POST['storage_id']) : 0);
	if(!$storage)exit(json_encode(['code'=>-1, 'msg'=>\lib\StorHelper::errmsg()]));
	$stor = \lib\StorHelper::getModelByStorage($storage);
	if(!$stor)exit(json_encode(['code'=>-1, 'msg'=>\lib\StorHelper::errmsg()]));

	if(\lib\StorHelper::is_direct_upload($storage)){
		$param = $stor->getUploadParam($hash, $name, $limit_size * 1024 * 1024);
		if(!$param)exit(json_encode(['code'=>-1, 'msg'=>'获取上传参数失败', 'errmsg'=>$stor->errmsg()]));
		$_SESSION['upload'] = [
			'direct' => true,
			'chunks' => 1,
			'name' => $name,
			'hash' => $hash,
			'size' => $size,
			'ext' => $ext,
			'hide' => $hide,
			'pwd' => $pwd,
			'storage_id' => $storage['id']
		];
		//method 为 PUT 时（如 Azure）浏览器直接以文件内容作为请求体上传，headers 为需要附加的请求头
		$result = ['code'=>0, 'third'=>true, 'hash'=>$hash, 'url'=>$param['url'], 'post'=>$param['post'] ?? [], 'method'=>$param['method'] ?? 'POST', 'headers'=>$param['headers'] ?? (object)[]];
		exit(json_encode($result));
	}else{
		$chunksize = 8 * 1024 * 1024; //分块上传，每块大小
		$chunks = ceil($size / $chunksize);
		$_SESSION['upload'] = [
			'chunks' => $chunks,
			'name' => $name,
			'hash' => $hash,
			'size' => $size,
			'ext' => $ext,
			'hide' => $hide,
			'pwd' => $pwd,
			'storage_id' => $storage['id']
		];
		$result = ['code'=>0, 'third'=>false, 'hash'=>$hash, 'chunksize'=>$chunksize, 'chunks'=>$chunks];
		exit(json_encode($result));
	}
break;

case 'upload_part':
	if(!isset($_FILES['file']))exit('{"code":-1,"msg":"请选择文件"}');
	if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	if($conf['forcelogin']==1 && !$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	if($_FILES['file']['error']!=UPLOAD_ERR_OK || !is_uploaded_file($_FILES['file']['tmp_name']))exit('{"code":-1,"msg":"文件上传失败，错误码：'.intval($_FILES['file']['error']).'"}');
	$chunk = intval($_POST['chunk']);
	$hash = trim($_POST['hash']);
	if(!$_SESSION['upload'] || !$_SESSION['upload']['hash'] || $_SESSION['upload']['hash']!=$hash){
		exit('{"code":-1,"msg":"参数校验失败，请刷新页面重试"}');
	}
	if(!preg_match('/^[0-9a-z]{32}$/i', $hash))exit('{"code":-1,"msg":"hash error"}');
	$storage_id = intval($_SESSION['upload']['storage_id']);
	$stor = \lib\StorHelper::getModelByStorage($storage_id);
	if(!$stor)exit(json_encode(['code'=>-1, 'msg'=>\lib\StorHelper::errmsg() ?: '存储不存在，请刷新页面重试']));
	$chunks = intval($_SESSION['upload']['chunks']);
	$ext = $_SESSION['upload']['ext'];
	$size = $_SESSION['upload']['size'];
	if(!empty($_SESSION['upload']['direct']))exit('{"code":-1,"msg":"参数校验失败，请刷新页面重试"}');
	if($chunk < 1 || $chunk > max(1, $chunks))exit('{"code":-1,"msg":"分块序号错误"}');
	if($chunks > 1){
		$tempFile = sys_get_temp_dir() . '/' . $hash. '.part'.$chunk;
		if(!move_uploaded_file($_FILES['file']['tmp_name'], $tempFile)){
			exit('{"code":-1,"msg":"文件第'.$chunk.'分块上传失败"}');
		}
		if($chunks == $chunk){
			$savePathTemp = file_part_merge($hash, $chunks);
			$real_hash = md5_file($savePathTemp);
			$real_size = filesize($savePathTemp);
			if($real_size != $size){
				unlink($savePathTemp);
				exit('{"code":-1,"msg":"文件大小校验失败"}');
			}
			if($real_hash != $hash){
				unlink($savePathTemp);
				exit('{"code":-1,"msg":"文件MD5校验失败"}');
			}
			$result = $stor->savefile($hash, $savePathTemp, minetype($ext));
			if(!$result)exit(json_encode(['code'=>-1, 'msg'=>'文件上传失败', 'error'=>'stor', 'errmsg'=>$stor->errmsg()]));
		}else{
			$result = ['code'=>0, 'chunk'=>$chunk];
			exit(json_encode($result));
		}
	}else{
		$real_hash = md5_file($_FILES['file']['tmp_name']);
		$real_size = filesize($_FILES['file']['tmp_name']);
		if($real_size != $size){
			exit('{"code":-1,"msg":"文件大小校验失败"}');
		}
		if($real_hash != $hash){
			exit('{"code":-1,"msg":"文件MD5校验失败"}');
		}
		$result = $stor->upload($hash, $_FILES['file']['tmp_name'], minetype($ext));
		if(!$result)exit(json_encode(['code'=>-1, 'msg'=>'文件上传失败', 'error'=>'stor', 'errmsg'=>$stor->errmsg()]));
	}

	$name = $_SESSION['upload']['name'];
	$hide = $_SESSION['upload']['hide'];
	$pwd = $_SESSION['upload']['pwd'];

	$row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash", [':hash'=>$hash]);
	if($row){
		unset($_SESSION['csrf_token']);
		unset($_SESSION['upload']);
		$result = ['code'=>1, 'msg'=>'本站已存在该文件', 'exists'=>1, 'hash'=>$hash, 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$row['id']];
		exit(json_encode($result));
	}

	$sds = $DB->exec("INSERT INTO `pre_file` (`name`,`type`,`size`,`hash`,`addtime`,`ip`,`hide`,`pwd`,`uid`,`storage_id`) values (:name,:type,:size,:hash,NOW(),:ip,:hide,:pwd,:uid,:storage_id)", [':name'=>$name, ':type'=>$ext, ':size'=>$size, ':hash'=>$hash, ':ip'=>$clientip, ':hide'=>$hide, ':pwd'=>$pwd, ':uid'=>($islogin2?$uid:0), ':storage_id'=>$storage_id]);
	if(!$sds)exit('{"code":-1,"msg":"上传失败'.$DB->error().'","error":"database"}');
	$id = $DB->lastInsertId();

	$type_image = explode('|',$conf['type_image']);
	$type_video = explode('|',$conf['type_video']);
	if($conf['green_check']>0 && in_array($ext,$type_image)){
		if(checkImage($hash, $ext)){
			$DB->exec("UPDATE `pre_file` SET `block`=1 WHERE `id`='{$id}' LIMIT 1");
		}
	}
	if($conf['videoreview']==1 && in_array($ext,$type_video)){
		$DB->exec("UPDATE `pre_file` SET `block`=2 WHERE `id`='{$id}' LIMIT 1");
	}
	
	$_SESSION['fileids'][] = $id;
	unset($_SESSION['csrf_token']);
	unset($_SESSION['upload']);
	$result = ['code'=>1, 'msg'=>'文件上传成功！', 'exists'=>0, 'hash'=>$hash, 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$id];
	exit(json_encode($result));
break;

case 'complete_upload':
	if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	if($conf['forcelogin']==1 && !$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	$hash = trim($_POST['hash']);
	if(!$_SESSION['upload'] || !$_SESSION['upload']['hash'] || $_SESSION['upload']['hash']!=$hash){
		exit('{"code":-1,"msg":"参数校验失败，请刷新页面重试"}');
	}
	if(!preg_match('/^[0-9a-z]{32}$/i', $hash))exit('{"code":-1,"msg":"hash error"}');
	$storage_id = intval($_SESSION['upload']['storage_id']);
	$stor = \lib\StorHelper::getModelByStorage($storage_id);
	if(!$stor)exit(json_encode(['code'=>-1, 'msg'=>\lib\StorHelper::errmsg() ?: '存储不存在，请刷新页面重试']));
	//只有直接上传到云存储的文件才通过这个接口完成，网站中转的文件在 upload_part 中完成
	if(empty($_SESSION['upload']['direct']))exit('{"code":-1,"msg":"参数校验失败，请刷新页面重试"}');

	$name = $_SESSION['upload']['name'];
	$size = $_SESSION['upload']['size'];
	$ext = $_SESSION['upload']['ext'];
	$hide = $_SESSION['upload']['hide'];
	$pwd = $_SESSION['upload']['pwd'];

	$row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash", [':hash'=>$hash]);
	if($row){
		unset($_SESSION['csrf_token']);
		unset($_SESSION['upload']);
		$result = ['code'=>1, 'msg'=>'本站已存在该文件', 'exists'=>1, 'hash'=>$hash, 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$row['id']];
		exit(json_encode($result));
	}

	//浏览器直传的文件没有经过网站，需要核对实际大小，防止上传与声明不符的文件绕过大小限制
	$info = $stor->getinfo($hash);
	if(!$info){
		exit(json_encode(['code'=>-1, 'msg'=>'文件上传失败', 'error'=>'stor', 'errmsg'=>$stor->errmsg()]));
	}
	if(intval($info['length']) != intval($size)){
		$stor->delete($hash);
		unset($_SESSION['upload']);
		exit('{"code":-1,"msg":"文件大小校验失败"}');
	}

	$sds = $DB->exec("INSERT INTO `pre_file` (`name`,`type`,`size`,`hash`,`addtime`,`ip`,`hide`,`pwd`,`uid`,`storage_id`) values (:name,:type,:size,:hash,NOW(),:ip,:hide,:pwd,:uid,:storage_id)", [':name'=>$name, ':type'=>$ext, ':size'=>$size, ':hash'=>$hash, ':ip'=>$clientip, ':hide'=>$hide, ':pwd'=>$pwd, ':uid'=>($islogin2?$uid:0), ':storage_id'=>$storage_id]);
	if(!$sds)exit('{"code":-1,"msg":"上传失败'.$DB->error().'","error":"database"}');
	$id = $DB->lastInsertId();

	$type_image = explode('|',$conf['type_image']);
	$type_video = explode('|',$conf['type_video']);
	if($conf['green_check']>0 && in_array($ext,$type_image)){
		if(checkImage($hash, $ext)){
			$DB->exec("UPDATE `pre_file` SET `block`=1 WHERE `id`='{$id}' LIMIT 1");
		}
	}
	if($conf['videoreview']==1 && in_array($ext,$type_video)){
		$DB->exec("UPDATE `pre_file` SET `block`=2 WHERE `id`='{$id}' LIMIT 1");
	}
	
	$_SESSION['fileids'][] = $id;
	unset($_SESSION['csrf_token']);
	unset($_SESSION['upload']);
	$result = ['code'=>1, 'msg'=>'文件上传成功！', 'exists'=>0, 'hash'=>$hash, 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$id];
	exit(json_encode($result));
break;

case 'deleteFile':
	$hash = isset($_POST['hash'])?trim($_POST['hash']):exit('{"code":-1,"msg":"no hash"}');
	if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	if(!preg_match('/^[0-9a-z]{32}$/i', $hash))exit('{"code":-1,"msg":"hash error"}');
	$row = $DB->getRow("SELECT * FROM `pre_file` WHERE `hash`=:hash", [':hash'=>$hash]);
	if(!$row)exit('{"code":-1,"msg":"文件不存在"}');
	$check = file_manage_check($row, '删除');
	if($check!==true)exit(json_encode(['code'=>-1, 'msg'=>$check]));
	if(file_delete_row($row))exit('{"code":0,"msg":"删除文件成功！"}');
	else exit('{"code":-1,"msg":"删除文件失败['.$DB->error().']"}');
break;

case 'fileEdit':
	if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	$hash = isset($_POST['hash'])?trim($_POST['hash']):'';
	if(!preg_match('/^[0-9a-z]{32}$/i', $hash))exit('{"code":-1,"msg":"hash error"}');
	$row = $DB->getRow("SELECT * FROM `pre_file` WHERE `hash`=:hash", [':hash'=>$hash]);
	if(!$row)exit('{"code":-1,"msg":"文件不存在"}');
	$check = file_manage_check($row);
	if($check!==true)exit(json_encode(['code'=>-1, 'msg'=>$check]));

	$name = isset($_POST['name']) && is_string($_POST['name'])?clean_file_name($_POST['name']):'';
	$name = trim(str_replace(['/','\\',':','*','"','<','>','|','?'],'',$name));
	//扩展名决定文件类型和外链地址，不允许通过改名修改
	$type = (string)$row['type'];
	if($type!=='' && get_file_ext($name)!==$type) $name .= '.'.$type;
	if($name==='' || $name==='.'.$type)exit('{"code":-1,"msg":"文件名不能为空"}');
	if(mb_strlen($name, 'UTF-8') > 200)exit('{"code":-1,"msg":"文件名太长"}');
	if($conf['name_block']){
		foreach(explode('|',$conf['name_block']) as $word){
			if($word!=='' && strpos($name,$word)!==false)exit('{"code":-1,"msg":"文件名包含屏蔽关键词"}');
		}
	}
	$hide = isset($_POST['show']) && $_POST['show']==1 ? 0 : 1;
	$ispwd = isset($_POST['ispwd']) && $_POST['ispwd']==1;
	$pwd = $ispwd && isset($_POST['pwd']) && is_string($_POST['pwd'])?trim($_POST['pwd']):'';
	if($ispwd){
		if($pwd==='')exit('{"code":-1,"msg":"请填写文件密码"}');
		if(!preg_match('/^[a-zA-Z0-9]{1,32}$/', $pwd))exit('{"code":-1,"msg":"文件密码只能为字母和数字，最多32位"}');
	}
	$data = [':id'=>$row['id'], ':name'=>htmlspecialchars($name), ':hide'=>$hide, ':pwd'=>$ispwd?$pwd:null];
	if($DB->exec("UPDATE `pre_file` SET `name`=:name,`hide`=:hide,`pwd`=:pwd WHERE `id`=:id", $data)===false){
		exit('{"code":-1,"msg":"保存失败['.$DB->error().']"}');
	}
	exit(json_encode(['code'=>0, 'msg'=>'修改成功', 'data'=>['name'=>$name, 'hide'=>$hide, 'pwd'=>$ispwd?$pwd:'']]));
break;

case 'fileBatch':
	if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	$op = isset($_POST['op'])?$_POST['op']:'';
	$names = ['delete'=>'删除', 'show'=>'公开', 'hide'=>'隐藏'];
	if(!isset($names[$op]))exit('{"code":-1,"msg":"不支持的操作"}');
	$hashes = isset($_POST['hashes']) && is_array($_POST['hashes'])?array_values(array_unique(array_filter($_POST['hashes'], 'is_string'))):[];
	if(!$hashes)exit('{"code":-1,"msg":"请选择文件"}');
	if(count($hashes) > 100)exit('{"code":-1,"msg":"一次最多操作100个文件"}');
	$success = 0;
	$failed = [];
	foreach($hashes as $hash){
		if(!preg_match('/^[0-9a-z]{32}$/i', $hash))continue;
		$row = $DB->getRow("SELECT * FROM `pre_file` WHERE `hash`=:hash", [':hash'=>$hash]);
		if(!$row){
			$failed[] = ['hash'=>$hash, 'name'=>'', 'msg'=>'文件不存在'];
			continue;
		}
		$check = file_manage_check($row, $names[$op]);
		if($check===true){
			if($op=='delete') $ok = file_delete_row($row);
			else $ok = $DB->exec("UPDATE `pre_file` SET `hide`=:hide WHERE `id`=:id", [':hide'=>$op=='hide'?1:0, ':id'=>$row['id']])!==false;
			if($ok){
				$success++;
				continue;
			}
			$check = $names[$op].'失败';
		}
		$failed[] = ['hash'=>$hash, 'name'=>htmlspecialchars_decode($row['name']), 'msg'=>$check];
	}
	$msg = $success ? '成功'.$names[$op].' '.$success.' 个文件' : $names[$op].'失败';
	if($failed) $msg .= '，'.count($failed).' 个文件失败';
	exit(json_encode(['code'=>$success ? 0 : -1, 'msg'=>$msg, 'success'=>$success, 'failed'=>$failed]));
break;

case 'config':
	$user = null;
	if($islogin2){
		$user = ['uid'=>intval($userrow['uid']), 'nickname'=>$userrow['nickname'], 'type'=>$userrow['type'], 'faceimg'=>$userrow['faceimg'], 'level'=>intval($userrow['level'])];
	}
	$result = [
		'code' => 0,
		'title' => $conf['title'],
		'keywords' => $conf['keywords'],
		'description' => $conf['description'],
		'gonggao' => $conf['gonggao'],
		'gg_file' => $conf['gg_file'],
		'tongji' => $conf['tongji'],
		'userlogin' => intval($conf['userlogin']),
		'login_qq' => intval($conf['login_qq']),
		'login_wx' => intval($conf['login_wx']),
		'forcelogin' => intval($conf['forcelogin']),
		'filesearch' => intval($conf['filesearch']),
		'upload_size' => intval($conf['upload_size']),
		'videoreview' => intval($conf['videoreview']),
		'upload_choose_storage' => intval($conf['upload_choose_storage']),
		'storages' => $conf['upload_choose_storage'] == 1 ? \lib\StorHelper::getEnabledList() : [],
		'type_image' => $conf['type_image'],
		'type_audio' => $conf['type_audio'],
		'type_video' => $conf['type_video'],
		'clientip' => $clientip,
		'siteurl' => $siteurl,
		'islogin' => $islogin2 ? 1 : 0,
		'user' => $user,
	];
	exit(json_encode($result));
break;

case 'csrf':
	$csrf_token = bin2hex(random_bytes(16));
	$_SESSION['csrf_token'] = $csrf_token;
	exit(json_encode(['code'=>0, 'csrf_token'=>$csrf_token]));
break;

case 'filelist':
	$mine = isset($_GET['m']) && $_GET['m']=='mine';
	$searchable = $conf['filesearch']==1 || $mine;
	if($mine){
		if($islogin2){
			$sql = " uid='".intval($uid)."'";
		}else{
			if(isset($_SESSION['fileids']) && count($_SESSION['fileids'])>0){
				$ids = array_reverse($_SESSION['fileids']);
				if(count($ids) > 60){
					$ids = array_splice($ids, 0, 60);
				}
				$ids = implode(',', array_map('intval', $ids));
				$sql = " id IN ($ids)";
			}else{
				$sql = " 1=2";
			}
		}
	}else{
		$sql = " hide=0";
	}
	$kw = isset($_GET['kw']) && is_string($_GET['kw'])?trim(strip_tags($_GET['kw'])):null;
	if($searchable && $kw){
		$sql .= " AND name LIKE '%".daddslashes($kw)."%'";
	}
	$pagesize = isset($_GET['pagesize'])?intval($_GET['pagesize']):15;
	if($pagesize < 1 || $pagesize > 50) $pagesize = 15;
	$page = isset($_GET['page'])?intval($_GET['page']):1;
	if($page < 1) $page = 1;
	$offset = $pagesize * ($page - 1);
	$total = intval($DB->getColumn("SELECT count(*) from pre_file WHERE{$sql}"));
	$list = [];
	$rs = $DB->query("SELECT id,name,type,size,hash,addtime,ip,block,hide,pwd,count,uid FROM pre_file WHERE{$sql} ORDER BY id DESC LIMIT $offset,$pagesize");
	while($rs && $res = $rs->fetch()){
		$item = [
			'id' => intval($res['id']),
			'name' => htmlspecialchars_decode($res['name']),
			'type' => (string)$res['type'],
			'size' => intval($res['size']),
			'hash' => $res['hash'],
			'addtime' => $res['addtime'],
			'ip' => preg_replace('/\d+$/','*',$res['ip']),
			'block' => intval($res['block']),
		];
		//我的文件：返回管理所需的信息（密码只返回给文件所有者）
		if($mine){
			$item['hide'] = intval($res['hide']);
			$item['pwd'] = (string)$res['pwd'];
			$item['count'] = intval($res['count']);
			$item['manage'] = file_manage_check($res)===true ? 1 : 0;
		}
		$list[] = $item;
	}
	$result = [
		'code' => 0,
		'total' => $total,
		'page' => $page,
		'pagesize' => $pagesize,
		'pages' => intval(ceil($total / $pagesize)),
		'searchable' => $searchable ? 1 : 0,
		'list' => $list,
	];
	exit(json_encode($result));
break;

case 'fileinfo':
	$hash = isset($_GET['hash'])?trim($_GET['hash']):exit('{"code":-1,"msg":"no hash"}');
	$pwd = isset($_GET['pwd']) && is_string($_GET['pwd'])?trim($_GET['pwd']):null;
	if(!preg_match('/^[0-9a-z]{32}$/i', $hash))exit('{"code":-1,"msg":"文件不存在"}');
	$row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash", [':hash'=>$hash]);
	if(!$row)exit('{"code":-1,"msg":"文件不存在"}');
	if(isset($_GET['player']) && $_GET['player']=='1'){
		$view_type = get_view_type((string)$row['type']);
		if($view_type != 'audio' && $view_type != 'video')exit('{"code":-1,"msg":"NO player"}');
		if($row['block']!=0)exit('{"code":-1,"msg":"File is blocked!"}');
		$result = ['code'=>0, 'data'=>[
			'name' => htmlspecialchars_decode($row['name']),
			'type' => (string)$row['type'],
			'view_type' => $view_type,
			'viewurl' => $siteurl.'view.php/'.$row['hash'].'.'.($row['type']?$row['type']:'file'),
		]];
		exit(json_encode($result));
	}
	if($row['pwd']!=null && $row['pwd']!==$pwd){
		exit(json_encode(['code'=>-2, 'msg'=>empty($pwd)?'该文件需要密码才能查看':'文件密码错误']));
	}
	$name = htmlspecialchars_decode($row['name']);
	$type = (string)$row['type'];
	$typename = $type ? $type : 'file';

	$downurl = 'down.php/'.$row['hash'].'.'.$typename;
	if(!empty($row['pwd']))$downurl .= '&'.$row['pwd'];
	$viewurl = 'view.php/'.$row['hash'].'.'.$typename;
	$downurl_all = $siteurl.$downurl;
	$viewurl_all = $siteurl.$viewurl;
	$pageurl = $siteurl.'#/detail?hash='.$row['hash'];
	if(!empty($row['pwd']))$pageurl .= '&pwd='.urlencode($row['pwd']);
	$playerurl = $siteurl.'#/player?hash='.$row['hash'];

	$is_mine = $islogin2 && $row['uid']==$uid || !$islogin2 && isset($_SESSION['fileids']) && in_array($row['id'], $_SESSION['fileids']) && strtotime($row['addtime'])>strtotime("-7 days");

	$view_type = get_view_type($type);
	$htmlcode2 = null;
	$office_url = null;
	if($view_type == 'image'){
		$htmlcode = '<img src="'.$viewurl_all.'"/>';
		$ubbcode = '[img]'.$viewurl_all.'[/img]';
	}elseif($view_type == 'audio'){
		$htmlcode = '<audio src="'.$viewurl_all.'" autoplay="autoplay" loop="loop" preload="auto"></audio>';
		$htmlcode2 = '<iframe src="'.$playerurl.'" width="407" height="70" scrolling="no" frameborder="0"></iframe>';
		$ubbcode = '[audio=X]'.$viewurl_all.'[/audio]';
	}elseif($view_type == 'video'){
		$htmlcode = '<video src="'.$viewurl_all.'" controls="" width="100%"></video>';
		$htmlcode2 = '<iframe src="'.$playerurl.'" width="800" height="500" scrolling="no" frameborder="0" allowfullscreen="true"></iframe>';
		$ubbcode = '[movie=320*180]'.$viewurl_all.'[/movie]';
	}else{
		$htmlcode = '<a href="'.$downurl_all.'" target="_blank">'.htmlspecialchars($name).'</a>';
		$ubbcode = '[url='.$downurl_all.']'.$name.'[/url]';
		if($view_type == 'office'){
			$office_url = 'https://view.officeapps.live.com/op/view.aspx?src='.rawurlencode($downurl_all);
		}
	}
	$result = [
		'code' => 0,
		'data' => [
			'id' => intval($row['id']),
			'name' => $name,
			'type' => $type,
			'size' => intval($row['size']),
			'hash' => $row['hash'],
			'addtime' => $row['addtime'],
			'ip' => preg_replace('/\d+$/','*',$row['ip']),
			'count' => intval($row['count']),
			'block' => intval($row['block']),
			'haspwd' => !empty($row['pwd']) ? 1 : 0,
			'view_type' => $view_type ? $view_type : 'other',
			'viewurl' => $viewurl_all,
			'downurl' => $downurl_all,
			'pageurl' => $pageurl,
			'playerurl' => $playerurl,
			'office_url' => $office_url,
			'text_view' => $view_type == 'text' && is_text_view($row) ? 1 : 0,
			'htmlcode' => $htmlcode,
			'htmlcode2' => $htmlcode2,
			'ubbcode' => $ubbcode,
			'is_mine' => $is_mine ? 1 : 0,
			//文件所有者编辑文件信息时使用
			'hide' => $is_mine ? intval($row['hide']) : null,
			'pwd' => $is_mine ? (string)$row['pwd'] : null,
		],
	];
	exit(json_encode($result));
break;

case 'filecontent':
	$hash = isset($_GET['hash'])?trim($_GET['hash']):exit('{"code":-1,"msg":"no hash"}');
	$pwd = isset($_GET['pwd']) && is_string($_GET['pwd'])?trim($_GET['pwd']):null;
	if(!preg_match('/^[0-9a-z]{32}$/i', $hash))exit('{"code":-1,"msg":"文件不存在"}');
	$row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash", [':hash'=>$hash]);
	if(!$row)exit('{"code":-1,"msg":"文件不存在"}');
	if($row['pwd']!=null && $row['pwd']!==$pwd)exit('{"code":-2,"msg":"文件密码错误"}');
	if(!is_text_view($row))exit('{"code":-1,"msg":"该文件不支持在线预览"}');
	$content = get_text_content($row);
	if($content === false)exit(json_encode(['code'=>-1, 'msg'=>'读取文件失败'.(\lib\StorHelper::errmsg() ? '，'.\lib\StorHelper::errmsg() : '')], JSON_UNESCAPED_UNICODE));
	exit(json_encode(['code'=>0, 'content'=>$content], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
break;

default:
	exit('{"code":-4,"msg":"No Act"}');
break;
}