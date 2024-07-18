<?php
/**
 * 系统设置
**/
define('IN_ADMIN', true);
include("../includes/common.php");
$title='系统设置';
include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
?>
  <div class="container" style="padding-top:70px;">
    <div class="col-xs-12 col-sm-10 col-lg-8 center-block" style="float: none;">
<?php
$mod=isset($_GET['mod'])?$_GET['mod']:null;
?>
<?php
if($mod=='site'){
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">网站信息设置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<div class="form-group">
	  <label class="col-sm-2 control-label">网站标题</label>
	  <div class="col-sm-10"><input type="text" name="title" value="<?php echo $conf['title']; ?>" class="form-control" required/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">关键字</label>
	  <div class="col-sm-10"><input type="text" name="keywords" value="<?php echo $conf['keywords']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">网站描述</label>
	  <div class="col-sm-10"><input type="text" name="description" value="<?php echo $conf['description']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">禁止访问IP</label>
	  <div class="col-sm-10"><textarea class="form-control" name="blackip" rows="2" placeholder="多个IP用|隔开"><?php echo $conf['blackip']?></textarea></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">首页公告</label>
	  <div class="col-sm-10"><textarea class="form-control" name="gonggao" rows="3" placeholder="不填写则不显示首页公告"><?php echo htmlspecialchars($conf['gonggao'])?></textarea></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">文件查看页公告</label>
	  <div class="col-sm-10"><textarea class="form-control" name="gg_file" rows="3" placeholder="不填写则不显示"><?php echo htmlspecialchars($conf['gg_file'])?></textarea></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">统计代码</label>
	  <div class="col-sm-10"><textarea class="form-control" name="tongji" rows="3" placeholder="不填写则不显示统计代码"><?php echo htmlspecialchars($conf['tongji'])?></textarea></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">文件搜索功能</label>
	  <div class="col-sm-10"><select class="form-control" name="filesearch" default="<?php echo $conf['filesearch']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
</div>
<?php
}elseif($mod=='api'){
$scriptpath=str_replace('\\','/',$_SERVER['SCRIPT_NAME']);
$sitepath = substr($scriptpath, 0, strrpos($scriptpath, '/'));
$admin_path = substr($sitepath, strrpos($sitepath, '/'));
$siteurl = (is_https() ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].str_replace($admin_path,'',$sitepath).'/';
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">上传API设置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<div class="form-group">
	  <label class="col-sm-3 control-label">上传API开关</label>
	  <div class="col-sm-9"><select class="form-control" name="api_open" default="<?php echo $conf['api_open']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">来源域名白名单</label>
	  <div class="col-sm-9"><input type="text" name="api_referer" value="<?php echo $conf['api_referer']; ?>" class="form-control" placeholder="多个域名用|隔开"/><font color="green">多个域名用|隔开，不填写则不限制来源域名</font></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
</div>
<div class="panel panel-info">
<div class="panel-heading"><h3 class="panel-title">上传API文档</h3></div>
<div class="panel-body">
<pre>
API接口地址：<?php echo $siteurl?>api.php

当前API支持JSON、JSONP、FORM 3种返回方式，支持Web跨域调用，也支持程序中直接调用。

请求方式：POST  multipart/form-data

请求参数说明：
<table class="table table-bordered table-hover">
  <thead><tr><th>字段名</th><th>变量名</th><th>是否必填</th><th>示例值</th><th>描述</th></tr></thead>
  <tbody>
  <tr><td>文件</td><td>file</td><td>是</td><td></td><td>multipart格式文件</td></tr>
  <tr><td>是否首页显示</td><td>show</td><td>否</td><td>1</td><td>默认为是</td></tr>
  <tr><td>是否设置密码</td><td>ispwd</td><td>否</td><td>0</td><td>默认为否</td></tr>
  <tr><td>下载密码</td><td>pwd</td><td>否</td><td>123456</td><td>默认留空</td></tr>
  <tr><td>返回格式</td><td>format</td><td>否</td><td>json</td><td>有json、jsonp、form三种选择
默认为json</td></tr>
  <tr><td>跳转页面url</td><td>backurl</td><td>否</td><td>http://...</td><td>上传成功后的跳转地址
只在form格式有效</td></tr>
  <tr><td>callback</td><td>callback</td><td>否</td><td>callback</td><td>只在jsonp格式有效</td></tr>
  </tbody>
</table>
返回参数说明：
<table class="table table-bordered table-hover">
  <thead><tr><th>字段名</th><th>变量名</th><th>类型</th><th>示例值</th><th>描述</th></tr></thead>
  <tbody>
  <tr><td>上传状态</td><td>code</td><td>Int</td><td>0</td><td>0为成功，其他为失败</td></tr>
  <tr><td>提示信息</td><td>msg</td><td>String</td><td>上传成功！</td><td>如果上传失败会有错误提示</td></tr>
  <tr><td>文件MD5</td><td>hash</td><td>String</td><td>f1e807cb0d6ba52d71bdb02864e6bda8</td><td></td></tr>
  <tr><td>文件名称</td><td>name</td><td>String</td><td>exapmle1.jpg</td><td></td></tr>
  <tr><td>文件大小</td><td>size</td><td>Int</td><td>58937</td><td>单位：字节</td></tr>
  <tr><td>文件格式</td><td>type</td><td>String</td><td>jpg</td><td></td></tr>
  <tr><td>下载地址</td><td>downurl</td><td>String</td><td>http://.....</td><td></td></tr>
  <tr><td>预览地址</td><td>viewurl</td><td>String</td><td>http://.....</td><td>只有图片、音乐、视频文件才有</td></tr>
  </tbody>
</table>
</pre>
</div>
</div>
<?php
}elseif($mod=='account_n' && $_POST['do']=='submit'){
	if(!checkRefererHost())exit;
	$user=$_POST['user'];
	$oldpwd=$_POST['oldpwd'];
	$newpwd=$_POST['newpwd'];
	$newpwd2=$_POST['newpwd2'];
	if($user==null)showmsg('用户名不能为空！',3);
	saveSetting('admin_user',$user);
	if(!empty($newpwd) && !empty($newpwd2)){
		if($oldpwd!=$conf['admin_pwd'])showmsg('旧密码不正确！',3);
		if($newpwd!=$newpwd2)showmsg('两次输入的密码不一致！',3);
		saveSetting('admin_pwd',$newpwd);
	}
	showmsg('修改成功！请重新登录',1);
}elseif($mod=='account'){
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">管理员账号设置</h3></div>
<div class="panel-body">
  <form action="./set.php?mod=account_n" method="post" class="form-horizontal" role="form"><input type="hidden" name="do" value="submit"/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">用户名</label>
	  <div class="col-sm-10"><input type="text" name="user" value="<?php echo $conf['admin_user']; ?>" class="form-control" required/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">旧密码</label>
	  <div class="col-sm-10"><input type="password" name="oldpwd" value="" class="form-control" placeholder="请输入当前的管理员密码"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">新密码</label>
	  <div class="col-sm-10"><input type="password" name="newpwd" value="" class="form-control" placeholder="不修改请留空"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">重输密码</label>
	  <div class="col-sm-10"><input type="password" name="newpwd2" value="" class="form-control" placeholder="不修改请留空"/></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
</div>
<?php
}elseif($mod=='iptype'){
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">用户IP地址获取设置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
    <div class="form-group">
	  <label class="col-sm-2 control-label">用户IP地址获取方式</label>
	  <div class="col-sm-10"><select class="form-control" name="ip_type" default="<?php echo $conf['ip_type']?>"><option value="0">0_X_FORWARDED_FOR</option><option value="1">1_X_REAL_IP</option><option value="2">2_REMOTE_ADDR</option></select></div>
	</div>
	<div class="form-group">
	  <div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
<div class="panel-footer">
<span class="glyphicon glyphicon-info-sign"></span>
此功能设置用于防止用户伪造IP请求。<br/>
X_FORWARDED_FOR：之前的获取真实IP方式，极易被伪造IP<br/>
X_REAL_IP：在网站使用CDN的情况下选择此项，在不使用CDN的情况下也会被伪造<br/>
REMOTE_ADDR：直接获取真实请求IP，无法被伪造，但可能获取到的是CDN节点IP<br/>
<b>你可以从中选择一个能显示你真实地址的IP，优先选下方的选项。</b>
</div>
</div>
<script>
$(document).ready(function(){
	$.ajax({
		type : "GET",
		url : "ajax.php?act=iptype",
		dataType : 'json',
		async: true,
		success : function(data) {
			$("select[name='ip_type']").empty();
			var defaultv = $("select[name='ip_type']").attr('default');
			$.each(data, function(k, item){
				$("select[name='ip_type']").append('<option value="'+k+'" '+(defaultv==k?'selected':'')+'>'+ item.name +' - '+ item.ip +' '+ item.city +'</option>');
			})
		}
	});
})
</script>
<?php
}elseif($mod=='file'){
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">文件上传设置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<div class="form-group">
	  <label class="col-sm-3 control-label">图片文件类型</label>
	  <div class="col-sm-9"><input type="text" name="type_image" value="<?php echo $conf['type_image']; ?>" class="form-control" placeholder="多个文件类型用|隔开"/><font color="green">在文件预览页面，以上文件类型将以图片的形式展示</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">音频文件类型</label>
	  <div class="col-sm-9"><input type="text" name="type_audio" value="<?php echo $conf['type_audio']; ?>" class="form-control" placeholder="多个文件类型用|隔开"/><font color="green">在文件预览页面，以上文件类型将以音频的形式展示</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">视频文件类型</label>
	  <div class="col-sm-9"><input type="text" name="type_video" value="<?php echo $conf['type_video']; ?>" class="form-control" placeholder="多个文件类型用|隔开"/><font color="green">在文件预览页面，以上文件类型将以视频的形式展示</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">禁止上传的文件类型</label>
	  <div class="col-sm-9"><input type="text" name="type_block" value="<?php echo $conf['type_block']; ?>" class="form-control" placeholder="多个文件类型用|隔开"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">文件名屏蔽关键词</label>
	  <div class="col-sm-9"><input type="text" name="name_block" value="<?php echo $conf['name_block']; ?>" class="form-control" placeholder="多个关键词用|隔开"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">每IP每天限制上传数量</label>
	  <div class="col-sm-9"><input type="text" name="upload_limit" value="<?php echo $conf['upload_limit']; ?>" class="form-control" placeholder="0或留空为不限制"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">视频文件需要审核</label>
	  <div class="col-sm-9"><select class="form-control" name="videoreview" default="<?php echo $conf['videoreview']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">上传大小限制</label>
	  <div class="col-sm-9"><div class="input-group"><input type="text" name="upload_size" value="<?php echo $conf['upload_size']; ?>" class="form-control" placeholder="不填写则不限制大小"/><span class="input-group-addon">MB</span></div></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">仅限登录用户上传</label>
	  <div class="col-sm-9"><select class="form-control" name="forcelogin" default="<?php echo $conf['forcelogin']?>"><option value="0">0_否</option><option value="1">1_是</option></select></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
</div>
<script>
</script>
<?php
}elseif($mod=='user'){
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">用户登录设置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
  	<div class="form-group">
	  <label class="col-sm-3 control-label">用户登录开关</label>
	  <div class="col-sm-9"><select class="form-control" name="userlogin" default="<?php echo $conf['userlogin']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">聚合登录接口地址</label>
	  <div class="col-sm-9"><input type="text" name="login_apiurl" value="<?php echo $conf['login_apiurl']; ?>" class="form-control" placeholder="接口地址要以http://或https://开头，以/结尾"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">应用APPID</label>
	  <div class="col-sm-9"><input type="text" name="login_appid" value="<?php echo $conf['login_appid']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">应用APPKEY</label>
	  <div class="col-sm-9"><input type="text" name="login_appkey" value="<?php echo $conf['login_appkey']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">开启的登录方式</label>
	  <div class="col-sm-9">
	  <input type="hidden" name="login_qq" value="0"/>
	  <input type="hidden" name="login_wx" value="0"/>
	  <label class="checkbox-inline"><input type="checkbox" name="login_qq" value="1" <?php echo $conf['login_qq']?'checked':null;?>> QQ</label>
	  <label class="checkbox-inline"><input type="checkbox" name="login_wx" value="1" <?php echo $conf['login_wx']?'checked':null;?>> 微信</label>
	  </div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
<div class="panel-footer">
<span class="glyphicon glyphicon-info-sign"></span>
聚合登录接口是使用<a href="https://www.clogin.cc/recommend.php" target="_blank">彩虹聚合登录系统搭建的站点</a>。<br/>
开启后请勿随意更换登录接口站点，否则会导致之前注册的用户全部无法登录。
</div>
</div>
<script>
</script>
<?php
}elseif($mod=='green'){
	$green_label_porn = explode(',', $conf['green_label_porn']);
	$green_label_terrorism = explode(',', $conf['green_label_terrorism']);
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">图片检测设置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
    <div class="form-group">
	  <label class="col-sm-3 control-label">图片违规检测</label>
	  <div class="col-sm-9"><select class="form-control" name="green_check" default="<?php echo $conf['green_check']?>"><option value="0">关闭</option><option value="1">阿里云内容安全接口</option><option value="2">腾讯云内容安全接口</option></select></div>
	</div><br/>
	<div id="green_aliyun" style="<?php echo $conf['green_check']!='1'?'display:none;':null; ?>">
	<div class="form-group">
	  <label class="col-sm-3 control-label">阿里云AccessKey Id</label>
	  <div class="col-sm-9"><input type="text" name="aliyun_ak" value="<?php echo $conf['aliyun_ak']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">阿里云AccessKey Secret</label>
	  <div class="col-sm-9"><input type="text" name="aliyun_sk" value="<?php echo $conf['aliyun_sk']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">图片检测接入区域</label>
	  <div class="col-sm-9"><select class="form-control" name="green_check_region" default="<?php echo $conf['green_check_region']?>"><option value="cn-beijing">华北2（北京）</option><option value="cn-shanghai">华东2（上海）</option><option value="cn-shenzhen">华南1（深圳）</option><option value="ap-southeast-1">新加坡</option><option value="us-west-1">美西</option></select><font color="green">你可以选择一个离本站服务器最近的以提升检测速度</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">图片智能鉴黄</label>
	  <div class="col-sm-9"><select class="form-control" name="green_check_porn" default="<?php echo $conf['green_check_porn']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group" id="green_check_porn_" style="<?php echo $conf['green_check_porn']!=1?'display:none;':null; ?>">
	  <label class="col-sm-3 control-label">图片智能鉴黄屏蔽类型</label>
	  <div class="col-sm-9">
	  <label class="checkbox-inline"><input type="checkbox" name="green_label_porn[]" value="porn" <?php echo in_array('porn',$green_label_porn)?'checked':null;?>> 色情图片（porn）</label>
	  <label class="checkbox-inline"><input type="checkbox" name="green_label_porn[]" value="sexy" <?php echo in_array('sexy',$green_label_porn)?'checked':null;?>> 性感图片（sexy）</label>
	  </div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">图片暴恐涉政识别</label>
	  <div class="col-sm-9"><select class="form-control" name="green_check_terrorism" default="<?php echo $conf['green_check_terrorism']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group" id="green_check_terrorism_" style="<?php echo $conf['green_check_terrorism']!=1?'display:none;':null; ?>">
	  <label class="col-sm-3 control-label">图片暴恐涉政识别屏蔽类型</label>
	  <div class="col-sm-9">
	  <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="bloody" <?php echo in_array('bloody',$green_label_terrorism)?'checked':null;?>> 血腥（bloody）</label>
	  <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="explosion" <?php echo in_array('explosion',$green_label_terrorism)?'checked':null;?>> 爆炸烟光（explosion）</label>
	  <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="outfit" <?php echo in_array('outfit',$green_label_terrorism)?'checked':null;?>> 特殊装束（outfit）</label>
	  <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="logo" <?php echo in_array('logo',$green_label_terrorism)?'checked':null;?>> 特殊标识（logo）</label>
	  <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="weapon" <?php echo in_array('weapon',$green_label_terrorism)?'checked':null;?>> 武器（weapon）</label>
	  <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="politics" <?php echo in_array('politics',$green_label_terrorism)?'checked':null;?>> 涉政（politics）</label>
	  <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="violence" <?php echo in_array('violence',$green_label_terrorism)?'checked':null;?>> 打斗（violence）</label>
	  <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="crowd" <?php echo in_array('crowd',$green_label_terrorism)?'checked':null;?>> 聚众（crowd）</label>
	  <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="parade" <?php echo in_array('parade',$green_label_terrorism)?'checked':null;?>> 游行（parade）</label>
	  <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="carcrash" <?php echo in_array('carcrash',$green_label_terrorism)?'checked':null;?>> 车祸现场（carcrash）</label>
	  <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="flag" <?php echo in_array('flag',$green_label_terrorism)?'checked':null;?>> 旗帜（flag）</label>
	  <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="location" <?php echo in_array('location',$green_label_terrorism)?'checked':null;?>> 地标（location）</label>
	  <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="drug" <?php echo in_array('drug',$green_label_terrorism)?'checked':null;?>> 涉毒（drug）</label>
	  <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="gamble" <?php echo in_array('gamble',$green_label_terrorism)?'checked':null;?>> 赌博（gamble）</label>
	  </div>
	</div><br/>
	</div>
	<div id="green_qcloud" style="<?php echo $conf['green_check']!='2'?'display:none;':null; ?>">
	<div class="form-group">
	  <label class="col-sm-3 control-label">腾讯云SecretId</label>
	  <div class="col-sm-9"><input type="text" name="qcloud_green_id" value="<?php echo $conf['qcloud_green_id']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">腾讯云SecretKey</label>
	  <div class="col-sm-9"><input type="text" name="qcloud_green_key" value="<?php echo $conf['qcloud_green_key']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">图片检测接入区域</label>
	  <div class="col-sm-9"><select class="form-control" name="green_check_region" default="<?php echo $conf['green_check_region']?>"><option value="ap-beijing">华北地区(北京)</option><option value="ap-shanghai">华东地区(上海)</option><option value="ap-guangzhou">华南地区(广州)</option><option value="ap-mumbai">亚太南部(孟买)</option><option value="ap-singapore">亚太东南(新加坡)</option><option value="eu-frankfurt">欧洲地区(法兰克福)</option><option value="na-ashburn">美国东部(弗吉尼亚)</option><option value="na-siliconvalley">美国西部(硅谷)</option></select><font color="green">你可以选择一个离本站服务器最近的以提升检测速度</font></div>
	</div><br/>
	</div>
	<div class="form-group">
	  <label class="col-sm-3 control-label">图片检测访问网址</label>
	  <div class="col-sm-9"><input type="text" name="apiurl" value="<?php echo $conf['apiurl']; ?>" class="form-control" placeholder="不填写则默认使用当前网址"/><font color="green">此处是图片检测的时候阿里云访问本站的网址，不填写则默认使用当前网址，如果填写必需以http://开头，以/结尾</font></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
<div class="panel-footer">
<span class="glyphicon glyphicon-info-sign"></span>
阿里云内容安全接口：<a href="https://yundun.console.aliyun.com/?p=cts#/api/statistics" target="_blank" rel="noreferrer">点此进入</a>｜<a href="https://usercenter.console.aliyun.com/#/manage/ak" target="_blank" rel="noreferrer">获取密钥</a><br/>
腾讯云内容安全接口：<a href="https://cloud.tencent.com/product/ims" target="_blank" rel="noreferrer">点此进入</a>｜<a href="https://console.cloud.tencent.com/cam/capi" target="_blank" rel="noreferrer">获取密钥</a><br/>
屏蔽类型选不选都可以，会同时根据返回的建议结果进行屏蔽
</div>
</div>
<script>
$("select[name='green_check']").change(function(){
	if($(this).val() == 1){
		$("#green_aliyun").show();
		$("#green_qcloud").hide();
	}else if($(this).val() == 2){
		$("#green_aliyun").hide();
		$("#green_qcloud").show();
	}else{
		$("#green_aliyun").hide();
		$("#green_qcloud").hide();
	}
});
$("select[name='green_check_porn']").change(function(){
	if($(this).val() == 1){
		$("#green_check_porn_").show();
	}else{
		$("#green_check_porn_").hide();
	}
});
$("select[name='green_check_terrorism']").change(function(){
	if($(this).val() == 1){
		$("#green_check_terrorism_").show();
	}else{
		$("#green_check_terrorism_").hide();
	}
});
</script>
<?php
}
?>
    </div>
  </div>
<script src="https://s4.zstatic.net/ajax/libs/layer/2.3/layer.js"></script>
<script>
var items = $("select[default]");
for (i = 0; i < items.length; i++) {
	$(items[i]).val($(items[i]).attr("default")||0);
}
function saveSetting(obj){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax.php?act=set',
		data : $(obj).serialize(),
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				layer.alert('设置保存成功！', {
					icon: 1,
					closeBtn: false
				}, function(){
				  window.location.reload()
				});
			}else{
				layer.alert(data.msg, {icon: 2})
			}
		},
		error:function(data){
			layer.msg('服务器错误');
			return false;
		}
	});
	return false;
}
</script>