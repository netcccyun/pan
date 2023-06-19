<?php
$nosession = true;
$nosecu = true;
include("./includes/common.php");

function showresult($arr, $format='json'){
	$format = isset($_POST['format'])?$_POST['format']:'json';
	if($format == 'json'){
		@header('Content-Type: application/json; charset=UTF-8');
		exit(json_encode($arr));
	}elseif($format == 'jsonp'){
		$callback = isset($_POST['callback'])?$_POST['callback']:'callback';
		@header('Content-Type: application/javascript; charset=UTF-8');
		exit($callback.'('.json_encode($arr).')');
	}else{
		@header('Content-Type: text/html; charset=UTF-8');
		if($arr['code']==0){
			$backurl = isset($_POST['backurl'])?$_POST['backurl']:$_SERVER['HTTP_REFERER'];
echo '<html>
<head>
<meta http-equiv="content-type" content="text/html;charset=utf-8"/>
<meta name="viewport" content="width=device-width">
<title>文件上传页面</title>
</head>
<body>
<form action="'.$backurl.'" method="post">
<input name="file" type="hidden" value="'.$arr['downurl'].'" />
<input name="type" type="hidden" value="'.$arr['type'].'" />
<input name="name" type="hidden" value="'.$arr['name'].'" />
<input name="submit" type="submit" value="下一步" />
</form>
</body></html>';
exit;
		}else{
			sysmsg($arr['msg']);
		}
	}
}

if(!$conf['api_open'])showresult(['code'=>-4, 'msg'=>'当前站点未开启上传API']);

if(!empty($conf['api_referer'])){
	$referers = explode('|',$conf['api_referer']);
	$url_arr = parse_url($_SERVER['HTTP_REFERER']);
	if(!in_array($url_arr['host'], $url_arr))showresult(['code'=>-4, 'msg'=>'来源地址不正确']);
}


if(!isset($_FILES['file']))showresult(['code'=>-1, 'msg'=>'请选择文件']);
$name=trim(htmlspecialchars($_FILES['file']['name']));
$size=intval($_FILES['file']['size']);
$hide = $_POST['show']==1?0:1;
$ispwd = intval($_POST['ispwd']);
$pwd = $ispwd==1?trim(htmlspecialchars($_POST['pwd'])):null;
$name = str_replace(['/','\\',':','*','"','<','>','|','?'],'',$name);
if(empty($name))showresult(['code'=>-1, 'msg'=>'文件名不能为空']);
if($ispwd==1 && !empty($pwd)){
	if (!preg_match('/^[a-zA-Z0-9]+$/', $pwd)) {
		showresult(['code'=>-1, 'msg'=>'文件密码只能为字母和数字']);
	}
}
$ext=get_file_ext($name);
if($conf['type_block']){
	$type_block = explode('|',$conf['type_block']);
	if(in_array($ext,$type_block)){
		showresult(['code'=>-1, 'msg'=>'文件上传失败', 'error'=>'block']);
	}
}
if($conf['name_block']){
	$name_block = explode('|',$conf['name_block']);
	foreach($name_block as $row){
		if(strpos($name,$row)!==false){
			showresult(['code'=>-1, 'msg'=>'文件上传失败', 'error'=>'block']);
		}
	}
}
$hash = md5_file($_FILES['file']['tmp_name']);
$row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash", [':hash'=>$hash]);
if($row){
	unset($_SESSION['csrf_token']);
	$downurl = $siteurl.'down.php/'.$row['hash'].'.'.$row['type'];
	if(!empty($row['pwd']))$downurl .= '&'.$row['pwd'];
	$result = ['code'=>0, 'msg'=>'本站已存在该文件', 'exists'=>1, 'hash'=>$hash, 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$row['id'], 'downurl'=>$downurl];
	if(is_view($row['type']))$result['viewurl'] = $siteurl.'view.php/'.$hash.'.'.$row['type'];
	showresult($result);
}
$result = $stor->upload($hash, $_FILES['file']['tmp_name'], minetype($ext));
if(!$result)showresult(['code'=>-1, 'msg'=>'文件上传失败', 'error'=>'stor']);
$sds = $DB->exec("INSERT INTO `pre_file` (`name`,`type`,`size`,`hash`,`addtime`,`ip`,`hide`,`pwd`) values (:name,:type,:size,:hash,NOW(),:ip,:hide,:pwd)", [':name'=>$name, ':type'=>$ext, ':size'=>$size, ':hash'=>$hash, ':ip'=>$clientip, ':hide'=>$hide, ':pwd'=>$pwd]);
if(!$sds)showresult(['code'=>-1, 'msg'=>'上传失败'.$DB->error(), 'error'=>'database']);
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

$downurl = $siteurl.'down.php/'.$hash.'.'.$ext;
if(!empty($pwd))$downurl .= '&'.$pwd;
$result = ['code'=>0, 'msg'=>'文件上传成功！', 'exists'=>0, 'hash'=>$hash, 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$id, 'downurl'=>$downurl];
if(is_view($ext))$result['viewurl'] = $siteurl.'view.php/'.$hash.'.'.$ext;
showresult($result);
