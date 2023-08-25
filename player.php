<?php
include("./includes/common.php");

$hash = isset($_GET['hash'])?trim($_GET['hash']):exit();
$row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash", [':hash'=>$hash]);
if(!$row)exit('404 Not Found');
if($row['block']!=0)exit('File is blocked!');
$name = $row['name'];
$type = $row['type'];
$viewurl_all = $siteurl.'view.php/'.$row['hash'].'.'.$type;

$view_type = get_view_type($type);

if($view_type == 'audio'){
    $title = '音乐播放器 - '.$conf['title'];
}elseif($view_type == 'video'){
    $title = '视频播放器 - '.$conf['title'];
}else{
    exit('NO player');
}

@header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
  <meta charset="utf-8"/>
  <meta name="renderer" content="webkit">
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title><?php echo $title ?></title>
  <link rel="stylesheet" href="//cdn.staticfile.org/aplayer/1.10.1/APlayer.min.css">
  <link href="./assets/css/ckplayer.css" rel="stylesheet">
  <script src="//cdn.staticfile.org/jquery/2.1.4/jquery.min.js"></script>
<style type="text/css">
body{margin:0;}
</style>
</head>
<body>
<div id="preview" align="center">
<?php
if($view_type == 'audio'){
  echo '<div id="aplayer"></div>';
}elseif($view_type == 'video'){
  echo '<div class="videoplayer" style="width:100%"></div>';
}else{
  exit;
}
?>
</div>
<?php if($view_type == 'audio'){?>
<script type="text/javascript" src="//cdn.staticfile.org/aplayer/1.10.1/APlayer.min.js"></script>
<script type="text/javascript">
var ap = new APlayer({
  container: document.getElementById('aplayer'),
  loop: 'none',
  theme: '#b2dae6',
  audio: [{
      title: '<?php echo $name?>',
      author: 'none',
      url: '<?php echo $viewurl_all?>',
      cover: './assets/img/music.png',
  }]
});
</script>
<?php }elseif($view_type == 'video'){?>
<script type="text/javascript" src="./assets/js/ckplayer.min.js"></script>
<?php if($type=='m3u8'){$plug='hls.js';?><script src="//cdn.staticfile.org/hls.js/1.2.4/hls.min.js"></script><?php }?>
<?php if($type=='flv'||$type=='f4v'){$plug='flv.js';?><script src="//cdn.staticfile.org/flv.js/1.6.2/flv.min.js"></script><?php }?>
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