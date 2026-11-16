<?php
$nosession=true;
$nosecu=true;
include("../includes/common.php");
if($islogin==1){}else exit("<script language='javascript'>window.location.href='".rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'])),'/')."/#/login';</script>");

$url = '';
$pwd = null;
$urlarr=explode('/',isset($_SERVER['PATH_INFO'])?$_SERVER['PATH_INFO']:'');
if (($length = count($urlarr)) > 1) {
$url = $urlarr[$length-1];
}
$extension=explode('&',$url);
if (($length = count($extension)) > 1) {
$pwd = $extension[$length-1];
$url = $extension[0];
}

if(strpos($url,".")){
    $hash=substr($url,0,strpos($url,"."));
}else{
    $hash=$url;
}

$row = $DB->getRow("SELECT * FROM `pre_file` WHERE `hash`=:hash limit 1", [':hash'=>$hash]);
if(!$row)exit('404 Not Found');

$stor = $row ? \lib\StorHelper::getFileStor($row) : false;
if ($stor && $stor->exists($row['hash']))
{
    $DB->exec("UPDATE `pre_file` SET `lasttime`=NOW(),`count`=`count`+1 WHERE `id`='{$row['id']}'");
    
    file_output($row, false, true);
}
else{
    exit('File Not Found');
}