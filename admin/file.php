<?php
include("../includes/common.php");
$title='文件管理';
include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
?>
<style>
.table>tbody>tr>td {
	vertical-align: middle;
    max-width: 360px;
	word-break: break-all;
}
</style>
<div class="modal" id="modal-store" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content animated flipInX">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal"><span
							aria-hidden="true">&times;</span><span
							class="sr-only">Close</span></button>
				<h4 class="modal-title" id="modal-title">文件信息修改</h4>
			</div>
			<div class="modal-body">
				<form class="form-horizontal" id="form-store">
					<input type="hidden" name="action" id="action"/>
					<input type="hidden" name="id" id="id"/>
					<div class="form-group">
						<label class="col-sm-2 control-label no-padding-right">文件名称</label>
						<div class="col-sm-10">
							<input type="text" class="form-control" name="name" id="name">
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label no-padding-right">文件类型</label>
						<div class="col-sm-10">
							<input type="text" class="form-control" name="type" id="type">
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label no-padding-right">文件大小</label>
						<div class="col-sm-10">
							<input type="text" class="form-control" name="size" id="size" disabled>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label no-padding-right">文件MD5</label>
						<div class="col-sm-10">
							<input type="text" class="form-control" name="hash" id="hash" disabled>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label no-padding-right">是否隐藏</label>
						<div class="col-sm-10">
							<select id="hide" name="hide" class="form-control"><option value="0">0_否</option><option value="1">1_是</option></select>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label no-padding-right">开启密码</label>
						<div class="col-sm-10">
							<select id="ispwd" name="ispwd" class="form-control" onchange="change_ispwd(this)"><option value="0">0_否</option><option value="1">1_是</option></select>
						</div>
					</div>
					<div class="form-group" id="pwd_frame">
						<label class="col-sm-2 control-label no-padding-right">下载密码</label>
						<div class="col-sm-10">
							<input type="text" class="form-control" name="pwd" id="pwd">
						</div>
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-white" data-dismiss="modal">关闭</button>
				<button type="button" class="btn btn-primary" id="store" onclick="save()">保存</button>
			</div>
		</div>
	</div>
</div>
  <div class="container" style="padding-top:70px;">
    <div class="col-xs-12 center-block" style="float: none;">
	    <form onsubmit="return searchSubmit()" method="GET" class="form-inline" id="searchToolbar">
	        <div class="form-group">
          <label>搜索</label>
		  <select name="type" class="form-control"><option value="1">文件名</option><option value="2">文件Hash</option><option value="3">文件格式</option><option value="4">上传者IP</option></select>
		    </div>
			<div class="form-group">
			<input type="text" class="form-control" name="kw" placeholder="搜索内容">
			</div>
			<div class="form-group">
			<input type="text" class="form-control" name="uid" style="width: 100px;" placeholder="UID">
			</div>
			<div class="form-group">
			<select id="dstatus" name="dstatus" class="form-control"><option value="-1">全部状态</option><option value="0">正常文件</option><option value="1">已屏蔽文件</option><option value="2">待审核文件</option></select>
		    </div>
			<div class="form-group">
				<button class="btn btn-primary" type="submit"><i class="fa fa-search"></i> 搜索</button>
				<a href="javascript:searchClear()" class="btn btn-default"><i class="fa fa-repeat"></i> 重置</a>
			</div>
			<div class="btn-group" role="group">
				<button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">批量操作 <span class="caret"></span></button>
				<ul class="dropdown-menu"><li><a href="javascript:operation(0)"><i class="fa fa-trash"></i>  删除</a></li><li><a href="javascript:operation(1)"><i class="fa fa-times-circle"></i>  封禁</a></li><li><a href="javascript:operation(2)"><i class="fa fa-check-circle"></i>  解封</a></li></ul>
			</div>
		</form>
		<table id="listTable">
	  	</table>
    </div>
  </div>
<script src="//cdn.staticfile.org/layer/3.1.1/layer.min.js"></script>
<script src="//cdn.staticfile.org/bootstrap-table/1.20.2/bootstrap-table.min.js"></script>
<script src="//cdn.staticfile.org/bootstrap-table/1.20.2/extensions/page-jump-to/bootstrap-table-page-jump-to.min.js"></script>
<script src="../assets/js/custom.js"></script>
<script>
$(document).ready(function(){
	updateToolbar();
	const defaultPageSize = 15;
	const pageNumber = typeof window.$_GET['pageNumber'] != 'undefined' ? parseInt(window.$_GET['pageNumber']) : 1;
	const pageSize = typeof window.$_GET['pageSize'] != 'undefined' ? parseInt(window.$_GET['pageSize']) : defaultPageSize;

	$("#listTable").bootstrapTable({
		url: 'ajax_file.php?act=fileList',
		pageNumber: pageNumber,
		pageSize: pageSize,
		classes: 'table table-striped table-hover table-bordered',
		columns: [
			{
				field: '',
				checkbox: true
			},
			{
				field: 'id',
				title: 'ID',
				formatter: function(value, row, index) {
					return '<b>'+value+'</b>';
				}
			},
			{
				field: 'uid',
				title: '用户',
				formatter: function(value, row, index) {
					return value>0?'<a href="./user.php?type=1&kw='+value+'" target="_blank">'+value+'</a>':'游客';
				}
			},
			{
				field: 'name',
				title: '文件名',
				formatter: function(value, row, index) {
					var html = '<a href="'+row.fileurl+'" title="点击下载"><i class="fa '+row.icon+' fa-fw"></i>'+value+'</a>';
					if(row.view){
						if(row.view_type == 'image'){
							html += ' [<a href="javascript:showimage(\''+row.viewurl+'\')">预览</a>]';
						}else{
							html += ' [<a href="javascript:showfile('+row.id+',\''+row.view_type+'\')">预览</a>]';
						}
					}
					return html;
				}
			},
			{
				field: 'size',
				title: '文件大小'
			},
			{
				field: 'type',
				title: '文件格式',
				formatter: function(value, row, index) {
					return value ? value : '未知';
				}
			},
			{
				field: 'addtime',
				title: '上传日期/上次下载',
				formatter: function(value, row, index) {
					return value + '<br/>' + row.lasttime;
				}
			},
			{
				field: 'ip',
				title: '上传IP/下载量',
				formatter: function(value, row, index) {
					return '<a href="https://m.ip138.com/iplookup.asp?ip='+value+'" target="_blank" rel="noreferrer">'+value+'</a><br/><b>'+row.count+'</b>';
				}
			},
			{
				field: 'block',
				title: '状态',
				formatter: function(value, row, index) {
					if(value == '2'){
						return '<a href="javascript:setBlock('+row.id+',0)" class="btn btn-xs btn-warning">待审</a>';
					}else if(value == '1'){
						return '<a href="javascript:setBlock('+row.id+',0)" class="btn btn-xs btn-danger">封禁</a>';
					}else{
						return '<a href="javascript:setBlock('+row.id+',1)" class="btn btn-xs btn-success">正常</a>';
					}
				}
			},
			{
				field: 'status',
				title: '操作',
				formatter: function(value, row, index) {
					return '<a href="javascript:editframe('+row.id+')" class="btn btn-xs btn-info">编辑</a>&nbsp;<a href="'+row.pageurl+'" class="btn btn-xs btn-warning" target="_blank">查看</a>&nbsp;<a href="javascript:delFile('+row.id+')" class="btn btn-xs btn-danger">删除</a></td></tr>';
				}
			},
		],
	})
})

function change_ispwd(obj){
	if($(obj).val()==1){
		$('#pwd_frame').show()
	}else{
		$('#pwd_frame').hide()
	}
}
function setBlock(id,status) {
	$.ajax({
		type : 'GET',
		url : 'ajax_file.php?act=setBlock&id='+id+'&status='+status,
		dataType : 'json',
		success : function(data) {
			searchSubmit();
		},
		error:function(data){
			layer.msg('服务器错误');
		}
	});
}
function editframe(id){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'GET',
		url : 'ajax_file.php?act=getFileInfo&id='+id,
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				$("#modal-store").modal('show');
				$("#action").val("edit");
				$("#form-store #id").val(data.id);
				$("#form-store #name").val(data.name);
				$("#form-store #type").val(data.type);
				$("#form-store #size").val(data.size2+" ("+data.size+" 字节)");
				$("#form-store #hash").val(data.hash);
				$("#form-store #hide").val(data.hide);
				if(data.pwd==null||data.pwd==""){
					$("#form-store #ispwd").val(0);
					$("#form-store #pwd").val("");
					$('#pwd_frame').hide()
				}else{
					$("#form-store #ispwd").val(1);
					$("#form-store #pwd").val(data.pwd);
					$('#pwd_frame').show()
				}
			}else{
				layer.alert(data.msg, {icon: 2})
			}
		},
		error:function(data){
			layer.msg('服务器错误');
		}
	});
}
function save(){
	if($("#name").val()==''){
		layer.alert('请确保各项不能为空！');return false;
	}
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax_file.php?act=saveFileInfo',
		data : $("#form-store").serialize(),
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				layer.alert(data.msg,{
					icon: 1,
					closeBtn: false
				}, function(){
					$("#modal-store").modal('hide');
					searchSubmit();
					layer.closeAll();
				});
			}else{
				layer.alert(data.msg, {icon: 2})
			}
		},
		error:function(data){
			layer.msg('服务器错误');
		}
	});
}
function delFile(id) {
	var confirmobj = layer.confirm('你确定要删除此文件吗？', {
	  btn: ['确定','取消'], icon: 0
	}, function(){
	  $.ajax({
		type : 'GET',
		url : 'ajax_file.php?act=delFile&id='+id,
		dataType : 'json',
		success : function(data) {
			if(data.code == 0){
				searchSubmit();
				layer.alert('删除成功', {icon:1});
			}else{
				layer.alert(data.msg, {icon:2});
			}
		},
		error:function(data){
			layer.msg('服务器错误');
		}
	  });
	}, function(){
	  layer.close(confirmobj);
	});
}
function operation(status){
	var selected = $('#listTable').bootstrapTable('getSelections');
	if(selected.length == 0){
		layer.msg('未选中文件', {time:1500});return;
	}
	if(status == 0 && !confirm('确定要删除已选中的'+selected.length+'个文件吗？')) return;
	var checkbox = new Array();
	$.each(selected, function(key, item){
		checkbox.push(item.id)
	})
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax_file.php?act=operation',
		data : {checkbox: checkbox, status: status},
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				searchSubmit();
				layer.alert(data.msg, {icon:1});
			}else{
				layer.alert(data.msg, {icon:2});
			}
		},
		error:function(data){
			layer.msg('请求超时');
			searchSubmit();
		}
	});
}
function showfile(id, type) {
	if(type == 'image'){
		view_image();
	}else if(type == 'audio'){
		if($(window).width() >= 1200){
			var area = ['50%','120px'];
		}else if($(window).width() >= 992){
			var area = ['75%','120px'];
		}else if($(window).width() >= 768){
			var area = ['95%','120px'];
		}else{
			var area = ['100%','120px'];
		}
	}else if(type == 'video'){
		if($(window).width() >= 1200){
			var area = ['50%', '60%'];
		}else if($(window).width() >= 992){
			var area = ['75%', '70%'];
		}else if($(window).width() >= 768){
			var area = ['95%', '75%'];
		}else{
			var area = ['100%', '55%'];
		}
	}
	layer.open({
	   type: 2,
	   title: '文件预览',
	   shadeClose: true,
	   area: area,
	   content: './file-view.php?id='+id
	});
}
function showimage(resourcesUrl){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
    var img = new Image();
    img.onload = function () {//避免图片还未加载完成无法获取到图片的大小。
        //避免图片太大，导致弹出展示超出了网页显示访问，所以图片大于浏览器时下窗口可视区域时，进行等比例缩小。
        var max_height = $(window).height() - 200;
        var max_width = $(window).width();

        //rate1，rate2，rate3 三个比例中取最小的。
        var rate1 = max_height / img.height;
        var rate2 = max_width / img.width;
        var rate3 = 1;
        var rate = Math.min(rate1, rate2, rate3);
        //等比例缩放
        var imgHeight = img.height * rate; //获取图片高度
        var imgWidth = img.width * rate; //获取图片宽度

		var imgHtml = '<div id="showimg" style="width:'+imgWidth+'px; height:'+imgHeight+'px;"></div>';
		img.style = 'width:100%';
        //弹出层
		layer.close(ii);
        layer.open({
            type:1,
            shade: 0.6,
            title: false,
            area: ['auto', 'auto'],
            shadeClose: true,
            content: imgHtml,
			success: function(){
				$("#showimg").append(img)
			}
        });
    }
	img.onerror = function(){ layer.close(ii);layer.msg('图片加载错误'); }
    img.src = resourcesUrl;
}

</script>