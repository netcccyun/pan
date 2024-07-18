<?php
/**
 * 登录
**/
$verifycode = 1;//验证码开关

if(!function_exists("imagecreate") || !file_exists('code.php'))$verifycode=0;
define('IN_ADMIN', true);
include("../includes/common.php");
if(isset($_POST['user']) && isset($_POST['pass'])){
	if(!$_SESSION['pass_error'])$_SESSION['pass_error']=0;
	$user=daddslashes($_POST['user']);
	$pass=daddslashes($_POST['pass']);
	$code=daddslashes($_POST['code']);
	if ($verifycode==1 && (!$code || strtolower($code) != $_SESSION['vc_code'])) {
		unset($_SESSION['vc_code']);
		@header('Content-Type: text/html; charset=UTF-8');
		exit("<script language='javascript'>alert('验证码错误！');history.go(-1);</script>");
	}elseif($_SESSION['pass_error']>5) {
		@header('Content-Type: text/html; charset=UTF-8');
		exit("<script language='javascript'>alert('用户名或密码不正确！');history.go(-1);</script>");
	}elseif($user==$conf['admin_user'] && $pass==$conf['admin_pwd']) {
		$session=md5($user.$pass.$password_hash);
		$expiretime=time()+2592000;
		$token=authcode("{$user}\t{$session}\t{$expiretime}", 'ENCODE', SYS_KEY);
		ob_clean();
		setcookie("admin_token", $token, time() + 2592000);
		@header('Content-Type: text/html; charset=UTF-8');
		exit("<script language='javascript'>alert('登陆管理中心成功！');window.location.href='./';</script>");
	}else {
		$_SESSION['pass_error']++;
		@header('Content-Type: text/html; charset=UTF-8');
		exit("<script language='javascript'>alert('用户名或密码不正确！');history.go(-1);</script>");
	}
}elseif(isset($_GET['logout'])){
	setcookie("admin_token", "", time() - 2592000);
	@header('Content-Type: text/html; charset=UTF-8');
	exit("<script language='javascript'>alert('您已成功注销本次登陆！');window.location.href='./login.php';</script>");
}elseif($islogin==1){
	exit("<script language='javascript'>alert('您已登陆！');window.location.href='./';</script>");
}
$title='用户登录';
?>
<!DOCTYPE html>
<html lang="zh">
<head>
	<meta charset="UTF-8">
	<meta name="renderer" content="webkit">
	<meta name="viewport" content="width=device-width,height=device-height,inital-scale=1.0,maximum-scale=1.0,user-scalable=no;">
	<title>管理员登录</title>
	<link href="https://s4.zstatic.net/ajax/libs/twitter-bootstrap/3.4.1/css/bootstrap.min.css" rel="stylesheet"/>
	<link href="https://s4.zstatic.net/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet"/>
	<style>
body{background:linear-gradient(to right,#49bdad,#6a67c7) fixed}
.form-horizontal{background-color:#fff;text-align:center;padding:50px 30px 30px;box-shadow:12px 12px 0 0 rgba(0,0,0,.3);margin-top:50%}
.form-horizontal .heading{color:#555;font-size:30px;font-weight:600;letter-spacing:1px;text-transform:capitalize;margin:0 0 50px 0}
.form-horizontal .form-group{margin:0 auto 30px;position:relative}
.form-horizontal .form-group:nth-last-child(2){margin-bottom:20px}
.form-horizontal .form-group:last-child{margin:0}
.form-horizontal .form-group>i{color:#999;transform:translateY(-50%);position:absolute;left:5px;top:50%}
.form-horizontal .form-control{color:#7ab6b6;background-color:#fff;font-size:17px;letter-spacing:1px;height:40px;padding:5px 10px 2px 25px;box-shadow:0 0 0 0 transparent;border:none;border-bottom:1px solid rgba(0,0,0,.1);border-radius:0;display:inline-block}
.form-control::placeholder{color:rgba(0,0,0,.2);font-size:16px}
.form-horizontal .form-control:focus{border-bottom:1px solid #7ab6b6;box-shadow:none}
.form-horizontal .btn{color:#7ab6b6;background-color:#edf6f5;font-size:18px;font-weight:700;letter-spacing:1px;border-radius:5px;width:50%;height:45px;padding:7px 30px;margin:0 auto 25px;border:none;display:block;position:relative;transition:all .3s ease}
.form-horizontal .btn:focus,.form-horizontal .btn:hover{color:#fff;background-color:#7ab6b6}
.form-horizontal .btn:after,.form-horizontal .btn:before{content:'';background-color:#7ab6b6;height:50%;width:2px;position:absolute;left:0;bottom:0;z-index:1;transition:all .3s}
.form-horizontal .btn:after{bottom:auto;top:0;left:auto;right:0}
.form-horizontal .btn:hover:after,.form-horizontal .btn:hover:before{height:100%;width:50%;opacity:0}
	</style>
</head>
<body>
  <div class="container">
      <div class="row">
          <div class="col-md-offset-4 col-md-4 col-sm-offset-3 col-sm-6">
              <form class="form-horizontal" method="post">
                  <div class="heading">管理员登录</div>
                  <div class="form-group">
                      <i class="fa fa-user"></i><input required name="user" type="text" class="form-control" placeholder="用户名">
                  </div>
                  <div class="form-group">
                      <i class="fa fa-lock"></i><input required name="pass" type="password" class="form-control" placeholder="密码"/>
                  </div>
                  <div class="form-group">
                      <button type="submit" class="btn btn-default"><i class="fa fa-arrow-right"></i></button>
                  </div>
              </form>
          </div>
      </div>
  </div>
</body>
</html>