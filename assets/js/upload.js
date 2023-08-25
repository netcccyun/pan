var xhr;
var isBlock = false;

//上传文件方法
function UploadFile(fileObj) {
	var fileObj = fileObj || $("#file")[0].files[0]; // js 获取文件对象
	if (typeof (fileObj) == "undefined" || fileObj.size <= 0) {
		return;
    }
    $("#success").hide();
    $("#error").hide();
	var size = fileObj.size;
	var units = 'B';
	if(size/1024>1){
		size = size/1024;
		units = 'KB';
	}
	if(size/1024>1){
		size = size/1024;
		units = 'MB';
	}
	if(size/1024>1){
		size = size/1024;
		units = 'GB';
	}
	var filesize = size.toFixed(2)+units;
	var url =  "./ajax.php?act=upload"; // 接收上传文件的后台地址

	var form = new FormData(); // FormData 对象
    form.append("file", fileObj); // 文件对象
    form.append("show", $("#show").prop('checked')?1:0);
    form.append("ispwd", $("#ispwd").prop('checked')?1:0);
    form.append("pwd", $("#pwd").val());
    form.append("csrf_token", $("#csrf_token").val());

	xhr = new XMLHttpRequest();  // XMLHttpRequest 对象
	xhr.open("post", url, true); //post方式，url为服务器请求地址，true 该参数规定请求是否异步处理。
	xhr.onload = function(evt) { //请求完成
        //服务断接收完文件返回的结果
        var nt = (new Date().getTime() - ot) / 1000;
        var data = evt.target.responseText;
        try{
            var json = JSON.parse(data);
            if(json.code == 0){
				var jumpurl = "file.php?hash="+json.hash;
				if($("#ispwd").prop('checked') && $("#pwd").val()!=''){
					jumpurl+='&pwd='+$("#pwd").val();
				}
                show_msg('上传成功！总用时：'+nt.toFixed(2)+'秒。正在跳转到文件查看页面...');
                setTimeout(function(){ window.location.href=jumpurl; }, 800);
            }else{
                show_msg(json.msg, 1);
            }
        }catch(e){
            show_msg('上传失败，请稍后再试或联系站长', 1);
        }
	};
    xhr.onerror = function(evt) { //请求失败
        show_msg('上传失败，请稍后再试或联系站长', 1);
	};

	xhr.upload.onprogress = progressFunction;//【上传进度调用方法实现】
    xhr.upload.onloadstart = function(){//上传开始执行方法
        isBlock = true;
		ot = new Date().getTime();   //设置上传开始时间
        oloaded = 0;//设置上传开始时，以上传的文件大小为0
        $("#progressBar").html('<div class="progress progress-striped active"><div class="progress-bar" style="width: 0%"></div></div><div class="row"><div class="col-xs-3" style="text-align:left;" id="percentage">0%</div><div class="col-xs-6 filename">'+fileObj.name+' ('+filesize+')</div><div class="col-xs-3" style="text-align:right;" id="uploadspeed">0 KB/s</div></div>');
	};

	xhr.send(form); //开始上传，发送form数据
}

//取消上传
function cancleUploadFile(){
    xhr.abort();
    $("#progressBar").html("");
	alert("已中止上传");
}

//上传进度实现方法，上传过程中会频繁调用该方法
function progressFunction(evt) {
	// event.total是需要传输的总字节，event.loaded是已经传输的字节。如果event.lengthComputable不为真，则event.total等于0
	if (evt.lengthComputable) {//
        var percentage = Math.round(evt.loaded / evt.total * 100);
        $(".progress-bar").css("width", percentage + "%");
        $("#percentage").html(percentage + "%");
	}
	var nt = new Date().getTime();//获取当前时间
	var pertime = (nt-ot)/1000; //计算出上次调用该方法时到现在的时间差，单位为s
	ot = new Date().getTime(); //重新赋值时间，用于下次计算
	var perload = evt.loaded - oloaded; //计算该分段上传的文件大小，单位B
	oloaded = evt.loaded;//重新赋值已上传文件大小，用以下次计算
	//上传速度计算
	var speed = perload/pertime;
	var bspeed = speed;
	var units = 'B/s';//单位名称
	if(speed/1024>1){
		speed = speed/1024;
		units = 'KB/s';
	}
	if(speed/1024>1){
		speed = speed/1024;
		units = 'MB/s';
	}
	speed = speed.toFixed(2);
	//剩余时间
    var resttime = ((evt.total-evt.loaded)/bspeed).toFixed(2);
    $("#uploadspeed").html(speed+units);
	if(resttime==0)$(".progress-bar").html('正在保存中');
}

function show_msg(msg, error){
    isBlock=false;
    error = error || 0;
    $("#progressBar").hide();
    $("#progressBar").html('<div class="alert alert-dismissible alert-'+(error==1?'danger':'success')+'"><button type="button" class="close" data-dismiss="alert">×</button><strong>'+msg+'</strong></div>');
    $("#progressBar").fadeIn();
}

$(document).ready(function(){
	$("#uploadFile").click(function () {
        if(isBlock==true) return;
        $("#upload_block").html('<input type="file" id="file" name="myfile" onchange="UploadFile()" style="display:none"/>');
		$("#file").trigger("click");
    });
    $("#ispwd").click(function () {
        if ($(this).prop("checked")) {
            $("#pwd_frame").show();
        } else {
            $("#pwd_frame").hide();
        }
    });
	var fileInput = $("#fileInput");
	var elemetnNode="";
	//拖拽外部文件，进入目标元素触发
	fileInput.on("dragenter",function(e){
		elemetnNode=e.originalEvent.target;
		$("#uploadTitle").text("释放鼠标立即上传");
		$("#fileInput").css("background","#ccc");
	});
	//拖拽外部文件，离开目标元素触发
	fileInput.on("dragleave",function(e){
		if(elemetnNode===e.originalEvent.target){
			$("#uploadTitle").text("选择一个文件开始上传");
			$("#fileInput").css("background","#fff");
		}
	});
	//拖拽外部文件，在目标元素上释放鼠标触发
	fileInput.on('dragover', false).on("drop",function(e){
		$("#uploadTitle").text("选择一个文件开始上传");
		$("#fileInput").css("background","#fff");
		var fs = e.originalEvent.dataTransfer.files;
		if(fs.length>0){
			UploadFile(fs[0])
		}
		return false;
	});

	document.addEventListener('paste', function(e) {
		var items = ((e.clipboardData || window.clipboardData).items) || [];
		var file = null;

		if (items && items.length) {
			for (var i = 0; i < items.length; i++) {
				if (items[i].type.indexOf('text/') === -1) {
					file = items[i].getAsFile();
					break;
				}
			}
		}

		if (!file) {
			return;
		}
		UploadFile(file)
	});
})