<?php
/**
 * 存储类型设置
**/
define('IN_ADMIN', true);
include("../includes/common.php");
$title='存储类型设置';
include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
?>
<div class="container" style="padding-top:70px;">
<div class="col-xs-12 col-sm-10 col-lg-8 center-block" style="float: none;">
	<div class="panel panel-success">
		<div class="panel-heading"><h3 class="panel-title">存储类型设置</h3></div>
		<div class="panel-body">
			<form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
				<div class="form-group">
					<label class="col-sm-3 control-label">切换存储类型</label>
					<div class="col-sm-9"><select class="form-control" name="storage" default="<?php echo $conf['storage']?>"><option value="local">本地存储</option><option value="oss">阿里云OSS</option><option value="qcloud">腾讯云COS</option><option value="obs">华为云OBS</option><option value="upyun">又拍云</option><option value="qiniu">七牛云</option><?php if (defined('SAE_ACCESSKEY')) {?><option value="sae">SaeStorage</option><?php }?></select><font color="green">已有文件的情况下请勿随意变更，否则之前上传的文件全部无法下载</font></div>
				</div><br/>
				<div id="cloud_stor" style="display:none;">
				<div class="form-group">
					<label class="col-sm-3 control-label">文件上传方式</label>
					<div class="col-sm-9"><select class="form-control" name="uploadfile_type" default="<?php echo $conf['uploadfile_type']?>"><option value="0">网站中转</option><option value="1">直接链接</option></select></div>
				</div><br/>
				<div class="form-group">
					<label class="col-sm-3 control-label">文件下载方式</label>
					<div class="col-sm-9"><select class="form-control" name="downfile_type" default="<?php echo $conf['downfile_type']?>"><option value="0">网站中转</option><option value="1">直接链接</option></select></div>
				</div><br/>
				<div class="form-group" id="downfile_type_form" style="<?php echo $conf['downfile_type']!='1'?'display:none;':null; ?>">
					<label class="col-sm-3 control-label">文件下载域名</label>
					<div class="col-sm-9">
						<div class="row">
						<div class="col-xs-4 col-md-3" style="padding-right: 0px;">
							<select class="form-control" name="downfile_protocol" default="<?php echo $conf['downfile_protocol']; ?>"><option value="0">http://</option><option value="1">https://</option></select>
						</div>
						<div class="col-xs-8 col-md-9" style="padding-left: 0px;">
							<input type="text" class="form-control" name="downfile_domain" value="<?php echo $conf['downfile_domain']; ?>" placeholder="留空则使用云存储默认域名">
						</div>
						</div>
						<font color="green">填写Bucket绑定的域名，也可使用CDN域名</font>
					</div>
				</div><br/>
				</div>
				<div class="form-group">
					<div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/>
					</div>
				</div>
			</form>
		</div>
		<div class="panel-footer">
		<p><b>文件上传方式说明：</b>
		<br/>网站中转：上传文件先经过本站服务器，然后上传到云存储，速度较慢。
		<br/>直接链接：文件直接上传到云存储，不经过本站服务器，上传速度更快，支持更大文件。需要先在云存储设置跨域！</p>
		<p><b>文件下载方式说明：</b>
		<br/>网站中转：下载文件经过本站服务器中转，如果本机与云存储是内网连接则不消耗流量。
		<br/>直接链接：需支付流量费用，下载速度更快。</p>
		</div>
	</div>

<div id="accordion">
	<div class="panel panel-info">
		<div class="panel-heading"><h3 class="panel-title"><a data-toggle="collapse" data-parent="#accordion" href="#stor_local" class="collapsed">本地存储</a></h3></div>
		<div id="stor_local" class="panel-collapse collapse">
		<div class="panel-body">
			<form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
				<div class="form-group">
					<label class="col-sm-3 control-label">本地存储路径</label>
					<div class="col-sm-9"><input type="text" name="filepath" value="<?php echo $conf['filepath']; ?>" class="form-control" placeholder="默认存储在网站file目录"/><font color="green">不填写则默认存储在网站file目录下，如需填写，只能填写以服务器根目录/开始的绝对路径。</font></div>
				</div>
				<div class="form-group">
					<div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/>
					</div>
				</div>
			</form>
		</div>
		</div>
	</div>

<?php if (defined('SAE_ACCESSKEY')) {?>
	<div class="panel panel-info">
		<div class="panel-heading"><h3 class="panel-title"><a data-toggle="collapse" data-parent="#accordion" href="#stor_sae" class="collapsed">SaeStorage</a></h3></div>
		<div id="stor_sae" class="panel-collapse collapse">
		<div class="panel-body">
			<form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
				<div class="form-group">
					<label class="col-sm-3 control-label">SAE Storage名称</label>
					<div class="col-sm-9"><input type="text" name="storagename" value="<?php echo $conf['storagename']; ?>" class="form-control" placeholder=""/></div>
				</div><br/>
				<div class="form-group">
					<div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/>
					</div>
				</div>
			</form>
		</div>
		</div>
	</div>
<?php }?>

	<div class="panel panel-info">
		<div class="panel-heading"><h3 class="panel-title"><a data-toggle="collapse" data-parent="#accordion" href="#stor_oss" class="collapsed">阿里云OSS</a><span class="pull-right"><a href="https://www.aliyun.com/product/oss?userCode=1cyrqim7" rel="noreferrer" target="_blank" class="btn btn-default btn-xs">开通地址</a></span></h3></div>
		<div id="stor_oss" class="panel-collapse collapse">
		<div class="panel-body">
			<form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
				<div class="form-group">
					<label class="col-sm-3 control-label">阿里云AccessKey Id</label>
					<div class="col-sm-9"><input type="text" name="oss_ak" value="<?php echo $conf['oss_ak']; ?>" class="form-control"/></div>
				</div><br/>
					<div class="form-group">
					<label class="col-sm-3 control-label">阿里云AccessKey Secret</label>
					<div class="col-sm-9"><input type="text" name="oss_sk" value="<?php echo $conf['oss_sk']; ?>" class="form-control"/></div>
				</div><br/>
					<div class="form-group">
					<label class="col-sm-3 control-label">阿里云OSS EndPoint</label>
					<div class="col-sm-9"><input type="text" name="oss_endpoint" value="<?php echo $conf['oss_endpoint']; ?>" class="form-control"/></div>
				</div><br/>
				<div class="form-group">
					<label class="col-sm-3 control-label">阿里云OSS Bucket</label>
					<div class="col-sm-9"><input type="text" name="oss_bucket" value="<?php echo $conf['oss_bucket']; ?>" class="form-control"/></div>
				</div><br/>
				<div class="form-group">
					<div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/>
					</div>
				</div>
			</form>
		</div>
		</div>
	</div>

	<div class="panel panel-info">
		<div class="panel-heading"><h3 class="panel-title"><a data-toggle="collapse" data-parent="#accordion" href="#stor_qcloud" class="collapsed">腾讯云COS</a><span class="pull-right"><a href="https://cloud.tencent.com/act/cps/redirect?redirect=10042&cps_key=11eaac2f518cd09a6288f4b1912228b8" rel="noreferrer" target="_blank" class="btn btn-default btn-xs">开通地址</a></span></h3></div>
		<div id="stor_qcloud" class="panel-collapse collapse">
		<div class="panel-body">
			<form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
				<div class="form-group">
					<label class="col-sm-3 control-label">腾讯云SecretId</label>
					<div class="col-sm-9"><input type="text" name="qcloud_id" value="<?php echo $conf['qcloud_id']; ?>" class="form-control"/></div>
				</div><br/>
				<div class="form-group">
					<label class="col-sm-3 control-label">腾讯云SecretKey</label>
					<div class="col-sm-9"><input type="text" name="qcloud_key" value="<?php echo $conf['qcloud_key']; ?>" class="form-control"/></div>
				</div><br/>
				<div class="form-group">
					<label class="col-sm-3 control-label">COS存储桶地域</label>
					<div class="col-sm-9"><input type="text" name="qcloud_region" value="<?php echo $conf['qcloud_region']; ?>" class="form-control" placeholder="填写英文名称，例如：ap-shanghai"/></div>
				</div><br/>
				<div class="form-group">
					<label class="col-sm-3 control-label">COS存储桶名称</label>
					<div class="col-sm-9"><input type="text" name="qcloud_bucket" value="<?php echo $conf['qcloud_bucket']; ?>" class="form-control" placeholder="格式：BucketName-APPID"/></div>
				</div><br/>
				<div class="form-group">
					<div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/>
					</div>
				</div>
			</form>
		</div>
		</div>
	</div>
	<div class="panel panel-info">
		<div class="panel-heading"><h3 class="panel-title"><a data-toggle="collapse" data-parent="#accordion" href="#stor_obs" class="collapsed">华为云OBS</a><span class="pull-right"><a href="https://www.huaweicloud.com/product/obs.html?fromacct=b70162c8-fbde-42ca-9f3d-5d99dc1951ba&utm_source=bmV0MjAy=&utm_medium=cps&utm_campaign=201905" rel="noreferrer" target="_blank" class="btn btn-default btn-xs">开通地址</a></span></h3></div>
		<div id="stor_obs" class="panel-collapse collapse">
		<div class="panel-body">
			<form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
				<div class="form-group">
					<label class="col-sm-3 control-label">华为云AccessKeyId</label>
					<div class="col-sm-9"><input type="text" name="obs_ak" value="<?php echo $conf['obs_ak']; ?>" class="form-control"/></div>
				</div><br/>
				<div class="form-group">
					<label class="col-sm-3 control-label">华为云SecretAccessKey</label>
					<div class="col-sm-9"><input type="text" name="obs_sk" value="<?php echo $conf['obs_sk']; ?>" class="form-control"/></div>
				</div><br/>
				<div class="form-group">
					<label class="col-sm-3 control-label">OBS EndPoint</label>
					<div class="col-sm-9"><input type="text" name="obs_endpoint" value="<?php echo $conf['obs_endpoint']; ?>" class="form-control"/></div>
				</div><br/>
				<div class="form-group">
					<label class="col-sm-3 control-label">OBS桶名称</label>
					<div class="col-sm-9"><input type="text" name="obs_bucket" value="<?php echo $conf['obs_bucket']; ?>" class="form-control"/></div>
				</div><br/>
				<div class="form-group">
					<div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/>
					</div>
				</div>
			</form>
		</div>
		</div>
	</div>

	<div class="panel panel-info">
		<div class="panel-heading"><h3 class="panel-title"><a data-toggle="collapse" data-parent="#accordion" href="#stor_upyun" class="collapsed">又拍云</a><span class="pull-right"><a href="https://console.upyun.com/register/?invite=jUSQy3jyE" rel="noreferrer" target="_blank" class="btn btn-default btn-xs">开通地址</a></span></h3></div>
		<div id="stor_upyun" class="panel-collapse collapse">
		<div class="panel-body">
			<form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
				<div class="form-group">
					<label class="col-sm-3 control-label">云存储服务名称</label>
					<div class="col-sm-9"><input type="text" name="upyun_name" value="<?php echo $conf['upyun_name']; ?>" class="form-control"/></div>
				</div><br/>
				<div class="form-group">
					<label class="col-sm-3 control-label">操作员名称</label>
					<div class="col-sm-9"><input type="text" name="upyun_user" value="<?php echo $conf['upyun_user']; ?>" class="form-control"/></div>
				</div><br/>
				<div class="form-group">
					<label class="col-sm-3 control-label">操作员密码</label>
					<div class="col-sm-9"><input type="text" name="upyun_pwd" value="<?php echo $conf['upyun_pwd']; ?>" class="form-control"/></div>
				</div><br/>
				<div class="form-group">
					<div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/>
					</div>
				</div>
			</form>
		</div>
		</div>
	</div>

	<div class="panel panel-info">
		<div class="panel-heading"><h3 class="panel-title"><a data-toggle="collapse" data-parent="#accordion" href="#stor_qiniu" class="collapsed">七牛云</a><span class="pull-right"><a href="https://s.qiniu.com/j6zy63" rel="noreferrer" target="_blank" class="btn btn-default btn-xs">开通地址</a></span></h3></div>
		<div id="stor_qiniu" class="panel-collapse collapse">
		<div class="panel-body">
			<form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
				<div class="form-group">
					<label class="col-sm-3 control-label">AccessKey</label>
					<div class="col-sm-9"><input type="text" name="qiniu_ak" value="<?php echo $conf['qiniu_ak']; ?>" class="form-control"/></div>
				</div><br/>
				<div class="form-group">
					<label class="col-sm-3 control-label">SecretKey</label>
					<div class="col-sm-9"><input type="text" name="qiniu_sk" value="<?php echo $conf['qiniu_sk']; ?>" class="form-control"/></div>
				</div><br/>
				<div class="form-group">
					<label class="col-sm-3 control-label">存储空间名称</label>
					<div class="col-sm-9"><input type="text" name="qiniu_bucket" value="<?php echo $conf['qiniu_bucket']; ?>" class="form-control"/></div>
				</div><br/>
				<div class="form-group">
					<label class="col-sm-3 control-label">空间绑定域名</label>
					<div class="col-sm-9"><input type="text" name="qiniu_domain" value="<?php echo $conf['qiniu_domain']; ?>" class="form-control"/></div>
				</div><br/>
				<div class="form-group">
					<div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/>
					</div>
				</div>
			</form>
		</div>
		</div>
	</div>
</div>

</div>
</div>
    </div>
  </div>
<script src="//cdn.staticfile.org/layer/2.3/layer.js"></script>
<script>
var items = $("select[default]");
for (i = 0; i < items.length; i++) {
	$(items[i]).val($(items[i]).attr("default")||0);
}
$('#stor_'+$("select[name='storage']").val()).collapse('show');
$("select[name='downfile_type']").change(function(){
	if($(this).val() == '1'){
		$("#downfile_type_form").show();
	}else{
		$("#downfile_type_form").hide();
	}
});
$("select[name='storage']").change(function(){
	if($(this).val() == 'local' || $(this).val() == 'sae'){
		$("#cloud_stor").hide();
	}else{
		$("#cloud_stor").show();
	}
});
$("select[name='storage']").change();
function checkURL(obj)
{
	var url = $(obj).val();
	if (url.indexOf(" ")>=0){
		url = url.replace(/ /g,"");
	}
	if (url.indexOf("，")>=0){
		url = url.replace(/，/g,",");
	}
	if (url.toLowerCase().indexOf("http://")==0){
		url = url.slice(7);
	}
	if (url.toLowerCase().indexOf("https://")==0){
		url = url.slice(8);
	}
	if (url.slice(url.length-1)=="/"){
		url = url.slice(0,url.length-1);
	}
	$(obj).val(url);
}
function saveSetting(obj){
	if($("input[name='downfile_domain']").length>0 && $("input[name='downfile_domain']").val()!=''){
		checkURL("input[name='downfile_domain']");
	}
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