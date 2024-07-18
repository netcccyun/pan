<?php
include("./includes/common.php");

$title = '上传文件 - '.$conf['title'];
include SYSTEM_ROOT.'header.php';

$csrf_token = md5(mt_rand(0,999).time());
$_SESSION['csrf_token'] = $csrf_token;
?>
<div class="container" id="app">
    <div class="row">
    
      <div class="col-sm-9">
        <div class="well infobox" align="center" id="fileInput" :style="{background: background}">
        <div style="min-height:50px;">
            <div id="progressBar" v-if="showtype==1">
                <div class="progress progress-striped active"><div class="progress-bar" style="width: 0%" :style="{ width: progress + '%' }">{{progress_tip}}</div></div><div class="row"><div class="col-xs-3" style="text-align:left;" id="percentage"><span v-if="progress>0">{{progress}}%</span></div><div class="col-xs-6 filename">{{filename}}</div><div class="col-xs-3" style="text-align:right;" id="uploadspeed">{{uploadspeed}}</div></div>
            </div>
            <div class="alert alert-dismissible" :class="'alert-'+alert.type" v-if="showtype==2">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{alert.msg}}</strong>
            </div>
        </div>

         <br><br>
         <h1 style="color:#8d8b8b;" id="uploadTitle">{{uploadTitle}}</h1>

         <input type="hidden" id="csrf_token" name="csrf_token" value="<?php echo $csrf_token?>">
         <input type="file" id="file" name="myfile" @change="selectFile" style="display:none"/>
         

         <div id="upload_frame">
<?php if($conf['forcelogin']==1 && !$islogin2){?>
         <button id="uploadFile" class="btn btn-raised btn-primary" style="height:50px;font-size:20px;" onclick="window.location.href='./login.php'"><i class="fa fa-sign-in"></i> 请先登录<div class="ripple-container"></div></button>
         <script>var forbid = true;</script>
<?php }else{?>
         <button id="uploadFile" class="btn btn-raised btn-primary" style="height:50px;font-size:20px;" @click="clickUpload"><i class="fa fa-upload"></i> 选择文件<div class="ripple-container"></div></button>
<?php }?>
<div class="form-group">
<div class="checkbox">
<label>
<input type="checkbox" id="show" v-model="input.show"> 在首页文件列表显示
</label>
</div>
</div>
<div class="form-group">
<div class="checkbox">
<label>
<input type="checkbox" id="ispwd" v-model="input.ispwd"> 设定密码
</label>
</div>
</div>
<div class="form-group" style="max-width:220px;" id="pwd_frame" v-if="input.ispwd">
<input type="text" class="form-control" id="pwd" placeholder="请输入密码" autocomplete="off" v-model="input.pwd">
<p class="help-block">密码只能为字母或数字</p>
</div>
         </div>
         
        <br><br><br><br>
        </div>
      </div>
      <div class="col-sm-3">
      <div class="panel panel-primary">
<div class="panel-heading">
<h3 class="panel-title"><i class="fa fa-exclamation-circle"></i> 上传提示</h3>
</div>
<div class="list-group-item">
**您的IP是<?php echo $clientip?>，请不要上传违规文件！
</div>
<?php if($conf['upload_size']>0){?>
<div class="list-group-item">**上传无格式限制，当前服务器单个文件上传最大支持<b><?php echo $conf['upload_size']?>MB</b>！
</div>
<?php }else{?>
<div class="list-group-item">**上传无格式限制，无大小限制</b>！
</div>
<?php }?>
<?php if($conf['videoreview']==1){?>
<div class="list-group-item">**当前网站已开启视频文件审核，如果上传的是视频文件，需要等待审核通过后才能下载和播放。
</div>
<?php }?>
</div>
      </div>
    </div>
  </div>
<div class="colorful_loading_frame">
  <div class="colorful_loading"><i class="rect1"></i><i class="rect2"></i><i class="rect3"></i><i class="rect4"></i><i class="rect5"></i></div>
</div>
<?php include SYSTEM_ROOT.'footer.php';?>
<script src="https://s4.zstatic.net/ajax/libs/vue/2.6.14/vue.min.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/layer/3.1.1/layer.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/spark-md5/3.0.2/spark-md5.min.js"></script>
<script>var upload_max_filesize = '<?php echo $conf['upload_size']?>';</script>
<script src="./assets/js/uploadnew.js?v=<?php echo VERSION?>"></script>
</body>
</html>