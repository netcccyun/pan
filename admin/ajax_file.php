<?php
include("../includes/common.php");
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
$act=isset($_GET['act'])?daddslashes($_GET['act']):null;

if(!checkRefererHost())exit('{"code":403}');

@header('Content-Type: application/json; charset=UTF-8');

switch($act){
case 'fileList':
	$sql=" 1=1";
	if(isset($_POST['uid']) && !empty($_POST['uid'])) {
		$uid = intval($_POST['uid']);
		$sql.=" AND `uid`='$uid'";
	}
	if(isset($_POST['dstatus']) && $_POST['dstatus']>-1) {
		$dstatus = intval($_POST['dstatus']);
		$sql.=" AND `block`={$dstatus}";
	}
	if(isset($_POST['kw']) && !empty($_POST['kw'])) {
		$type = intval($_POST['type']);
		$kw = trim(daddslashes($_POST['kw']));
		if($type == 1){
			$sql.=" AND `name` LIKE '%{$kw}%'";
		}elseif($type == 2){
			$sql.=" AND `hash`='{$kw}'";
		}elseif($type == 3){
			$sql.=" AND `type`='{$kw}'";
		}elseif($type == 4){
			$sql.=" AND `ip`='{$kw}'";
		}
	}
	if($_POST['orderby'] == 1){
		$orderby = 'count desc';
	}else{
		$orderby = 'id desc';
	}
	$offset = intval($_POST['offset']);
	$limit = intval($_POST['limit']);
	$total = $DB->getColumn("SELECT count(*) from pre_file WHERE{$sql}");
	$list = $DB->getAll("SELECT * FROM pre_file WHERE{$sql} order by {$orderby} limit $offset,$limit");
	$list2 = [];
	foreach($list as $row){
		$row['icon'] = type_to_icon($row['type']);
		$row['view'] = is_view($row['type']);
		$row['view_type'] = get_view_type($row['type']);
		$row['size'] = size_format($row['size']);

		$pwd_ext2='';
		if(!empty($row['pwd'])){
			$pwd_ext2='&pwd='.$row['pwd'];
		}
		$row['fileurl'] = './down.php/'.$row['hash'].'.'.($row['type']?$row['type']:'file');
		$row['viewurl'] = './view.php/'.$row['hash'].'.'.($row['type']?$row['type']:'file');
		$row['pageurl'] = '../file.php?hash='.$row['hash'].$pwd_ext2;

		$list2[] = $row;
	}

	exit(json_encode(['total'=>$total, 'rows'=>$list2]));
break;
case 'setBlock':
	$id=intval($_GET['id']);
	$status=intval($_GET['status']);
	$sql = "UPDATE pre_file SET `block`='$status' WHERE id='$id'";
	if($DB->exec($sql)!==false)exit('{"code":0,"msg":"修改成功！"}');
	else exit('{"code":-1,"msg":"修改失败['.$DB->error().']"}');
break;
case 'delFile':
	$id=intval($_GET['id']);
	$row=$DB->getRow("select * from pre_file where id='$id' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"当前文件不存在！"}');
	$result = $stor->delete($row['hash']);
	$sql = "DELETE FROM pre_file WHERE id='$id'";
	if($DB->exec($sql))exit('{"code":0,"msg":"删除文件成功！"}');
	else exit('{"code":-1,"msg":"删除文件失败['.$DB->error().']"}');
break;
case 'operation':
	$status=intval($_POST['status']);
	$checkbox=$_POST['checkbox'];
	if(!$checkbox)exit('{"code":-1,"msg":"未选中文件"}');
	$i=0;
	if($status == 2)$opname = '解封';
	elseif($status == 1)$opname = '封禁';
	else $opname = '删除';
	foreach($checkbox as $id){
		if($status == 0){
			$hash=$DB->getColumn("select hash from pre_file where id='$id' limit 1");
			$stor->delete($hash);
			$DB->exec("DELETE FROM pre_file WHERE id='$id'");
		}elseif($status == 1){
			$DB->exec("UPDATE pre_file SET `block`=1 WHERE id='$id'");
		}elseif($status == 2){
			$DB->exec("UPDATE pre_file SET `block`=0 WHERE id='$id'");
		}
		$i++;
	}
	exit('{"code":0,"msg":"成功'.$opname.$i.'个文件"}');
break;
case 'getFileInfo':
	$id=intval($_GET['id']);
	$row=$DB->getRow("select * from pre_file where id='$id' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"当前文件不存在！"}');
	$row['code'] = 0;
	$row['size2'] = size_format($row['size']);
	exit(json_encode($row));
break;
case 'saveFileInfo':
	$id = intval($_POST['id']);
	$name = trim(htmlspecialchars($_POST['name']));
	$type = trim(htmlspecialchars($_POST['type']));
	$hide = intval($_POST['hide']);
	$ispwd = intval($_POST['ispwd']);
	$pwd = $ispwd==1?trim(htmlspecialchars($_POST['pwd'])):null;
	if(empty($name))exit('{"code":-1,"msg":"文件名称不能为空"}');
	if($ispwd==1 && !empty($pwd)){
        if (!preg_match('/^[a-zA-Z0-9]+$/', $pwd)) {
			exit('{"code":-1,"msg":"下载密码只能为字母和数字"}');
        }
	}
	$data = [':id'=>$id, ':name'=>$name, ':type'=>$type, ':hide'=>$hide, ':pwd'=>$pwd];
	$sql = "UPDATE `pre_file` SET `name`=:name,`type`=:type,`hide`=:hide,`pwd`=:pwd WHERE `id`=:id";
	if($DB->exec($sql, $data)!==false)exit('{"code":0,"msg":"修改文件信息成功！"}');
	else exit('{"code":-1,"msg":"修改文件信息失败['.$DB->error().']"}');
break;
default:
	exit('{"code":-4,"msg":"No Act"}');
break;
}