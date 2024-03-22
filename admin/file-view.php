<?php
/**
 * 文件预览
**/
include("../includes/common.php");
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");

$id = isset($_GET['id'])?intval($_GET['id']):exit();
$row = $DB->getRow("SELECT * FROM pre_file WHERE id=:id", [':id'=>$id]);
if(!$row)exit();
$name = $row['name'];
$type = $row['type'];
$viewurl_all = $siteurl.'view.php/'.$row['hash'].'.'.$type;

$view_type = get_view_type($type);

@header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
  <meta charset="utf-8"/>
  <meta name="renderer" content="webkit">
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title><?php echo $title ?></title>
  <link rel="stylesheet" href="//cdn.staticfile.net/aplayer/1.10.1/APlayer.min.css">
  <link href="../assets/css/ckplayer.css" rel="stylesheet">
  <script src="//cdn.staticfile.net/jquery/2.1.4/jquery.min.js"></script>
<style type="text/css">
body{margin:0;}
</style>
</head>
<body>
<div id="preview" align="center">
<?php
if($view_type == 'image'){
  echo '<a href="'.$viewurl_all.'" title="点击查看原图" target="_blank"><img alt="loading" src="'.$viewurl_all.'" class="image_view"></a>';
}elseif($view_type == 'audio'){
  echo '<div class="view"><div id="aplayer"></div></div>';
}elseif($view_type == 'video'){
  echo '<div class="videoplayer" style="width:100%"></div>';
}else{
  exit;
}
?>
</div>
<?php if($view_type == 'audio'){?>
<script type="text/javascript" src="//cdn.staticfile.net/aplayer/1.10.1/APlayer.min.js"></script>
<script type="text/javascript">
var ap = new APlayer({
  container: document.getElementById('aplayer'),
  loop: 'none',
  theme: '#b2dae6',
  audio: [{
      title: '<?php echo $name?>',
      author: 'none',
      url: '<?php echo $viewurl_all?>',
      cover: '../assets/img/music.png',
  }]
});
</script>
<?php }elseif($view_type == 'video'){?>
<script type="text/javascript" src="../assets/js/ckplayer.min.js"></script>
<?php if($type=='m3u8'){$plug='hls.js';?><script src="//cdn.staticfile.net/hls.js/1.2.4/hls.min.js"></script><?php }?>
<?php if($type=='flv'||$type=='f4v'){$plug='flv.js';?><script src="//cdn.staticfile.net/flv.js/1.6.2/flv.min.js"></script><?php }?>
<script type="text/javascript">
  $(".videoplayer").height($(window).height());
  var videoObject = {
    container: '.videoplayer',
    plug:'<?php echo $plug?>',
    video:'<?php echo $viewurl_all?>',
    webFull:true,
  };
  var player=new ckplayer(videoObject);
</script>
<?php }?>
</body>
</html>