<?php
webscan_error();
//引用配置文件
require_once('webscan_cache.php');
//get拦截规则
$getfilter = "\\<.+javascript:window\\[.{1}\\\\x|<.*=(&#\\d+?;?)+?>|<.*(data|src)=data:text\\/html.*>|\\b(alert\\(|confirm\\(|expression\\(|prompt\\(|benchmark\s*?\(.*\)|sleep\s*?\(.*\)|\\b(group_)?concat[\\s\\/\\*]*?\\([^\\)]+?\\)|\bcase[\s\/\*]*?when[\s\/\*]*?\([^\)]+?\)|load_file\s*?\\()|<[a-z]+?\\b[^>]*?\\bon([a-z]{4,})\s*?=|^\\+\\/v(8|9)|\\b(and|or)\\b\\s*?([\\(\\)'\"\\d]+?=[\\(\\)'\"\\d]+?|[\\(\\)'\"a-zA-Z]+?=[\\(\\)'\"a-zA-Z]+?|>|<|\s+?[\\w]+?\\s+?\\bin\\b\\s*?\(|\\blike\\b\\s+?[\"'])|\\/\\*.*\\*\\/|<\\s*script\\b|\\bEXEC\\b|UNION.+?SELECT\s*(\(.+\)\s*|@{1,2}.+?\s*|\s+?.+?|(`|'|\").*?(`|'|\")\s*)|UPDATE\s*(\(.+\)\s*|@{1,2}.+?\s*|\s+?.+?|(`|'|\").*?(`|'|\")\s*)SET|INSERT\\s+INTO.+?VALUES|(SELECT|DELETE)@{0,2}(\\(.+\\)|\\s+?.+?\\s+?|(`|'|\").*?(`|'|\"))FROM(\\(.+\\)|\\s+?.+?|(`|'|\").*?(`|'|\"))|(CREATE|ALTER|DROP|TRUNCATE)\\s+(TABLE|DATABASE)|<.*(iframe|frame|style|embed|object|frameset|meta|xml)";
//post拦截规则
$postfilter = "<.*=(&#\\d+?;?)+?>|<.*data=data:text\\/html.*>|\\b(alert\\(|confirm\\(|expression\\(|prompt\\(|benchmark\s*?\(.*\)|sleep\s*?\(.*\)|\\b(group_)?concat[\\s\\/\\*]*?\\([^\\)]+?\\)|\bcase[\s\/\*]*?when[\s\/\*]*?\([^\)]+?\)|load_file\s*?\\()|<[^>]*?\\b(onerror|onmousemove|onload|onclick|onmouseover)\\b|\\b(and|or)\\b\\s*?([\\(\\)'\"\\d]+?=[\\(\\)'\"\\d]+?|[\\(\\)'\"a-zA-Z]+?=[\\(\\)'\"a-zA-Z]+?|>|<|\s+?[\\w]+?\\s+?\\bin\\b\\s*?\(|\\blike\\b\\s+?[\"'])|\\/\\*.*\\*\\/|<\\s*script\\b|\\bEXEC\\b|UNION.+?SELECT\s*(\(.+\)\s*|@{1,2}.+?\s*|\s+?.+?|(`|'|\").*?(`|'|\")\s*)|UPDATE\s*(\(.+\)\s*|@{1,2}.+?\s*|\s+?.+?|(`|'|\").*?(`|'|\")\s*)SET|INSERT\\s+INTO.+?VALUES|(SELECT|DELETE)(\\(.+\\)|\\s+?.+?\\s+?|(`|'|\").*?(`|'|\"))FROM(\\(.+\\)|\\s+?.+?|(`|'|\").*?(`|'|\"))|(CREATE|ALTER|DROP|TRUNCATE)\\s+(TABLE|DATABASE)|<.*(iframe|frame|style|embed|object|frameset|meta|xml)";
//cookie拦截规则
$cookiefilter = "benchmark\s*?\(.*\)|sleep\s*?\(.*\)|load_file\s*?\\(|\\b(and|or)\\b\\s*?([\\(\\)'\"\\d]+?=[\\(\\)'\"\\d]+?|[\\(\\)'\"a-zA-Z]+?=[\\(\\)'\"a-zA-Z]+?|>|<|\s+?[\\w]+?\\s+?\\bin\\b\\s*?\(|\\blike\\b\\s+?[\"'])|\\/\\*.*\\*\\/|<\\s*script\\b|\\bEXEC\\b|UNION.+?SELECT\s*(\(.+\)\s*|@{1,2}.+?\s*|\s+?.+?|(`|'|\").*?(`|'|\")\s*)|UPDATE\s*(\(.+\)\s*|@{1,2}.+?\s*|\s+?.+?|(`|'|\").*?(`|'|\")\s*)SET|INSERT\\s+INTO.+?VALUES|(SELECT|DELETE)@{0,2}(\\(.+\\)|\\s+?.+?\\s+?|(`|'|\").*?(`|'|\"))FROM(\\(.+\\)|\\s+?.+?|(`|'|\").*?(`|'|\"))|(CREATE|ALTER|DROP|TRUNCATE)\\s+(TABLE|DATABASE)";
//referer获取
$webscan_referer = empty($_SERVER['HTTP_REFERER']) ? array() : array('HTTP_REFERER'=>$_SERVER['HTTP_REFERER']);

/**
 *   关闭用户错误提示
 */
function webscan_error() {
  if (ini_get('display_errors')) {
    ini_set('display_errors', '0');
  }
}

/**
 *  数据统计回传
 */
function webscan_slog($logs) {
  //日志记录
  return true;
}
/**
 *  参数拆分
 */
function webscan_arr_foreach($arr) {
  static $str;
  static $keystr;
  if (!is_array($arr)) {
    return $arr;
  }
  foreach ($arr as $key => $val ) {
    $keystr=$keystr.$key;
    if (is_array($val)) {

      webscan_arr_foreach($val);
    } else {

      $str[] = $val.$keystr;
    }
  }
  return implode($str);
}

/**
 *  防护提示页
 */
function webscan_pape(){
  $pape=<<<HTML
<html>
<head>
<meta http-equiv="content-type" content="text/html;charset=utf-8"/>
<title>输入内容存在危险字符，安全起见，已被本站拦截</title>
<style>
body, h1, h2, p,dl,dd,dt{margin: 0;padding: 0;font: 12px/1.5 微软雅黑,tahoma,arial;}
body{background:#efefef;}
h1, h2, h3, h4, h5, h6 {font-size: 100%;cursor:default;}
ul, ol {list-style: none outside none;}
a {text-decoration: none;color:#447BC4}
a:hover {text-decoration: underline;}
.ip-attack{width:600px; margin:200px auto 0;}
.ip-attack dl{ background:#fff; padding:30px; border-radius:10px;border: 1px solid #CDCDCD;-webkit-box-shadow: 0 0 8px #CDCDCD;-moz-box-shadow: 0 0 8px #cdcdcd;box-shadow: 0 0 8px #CDCDCD;}
.ip-attack dt{text-align:center;}
.ip-attack dd{font-size:16px; color:#333; text-align:center;}
.tips{text-align:center; font-size:14px; line-height:50px; color:#999;}
</style>
</head>
<body>
<div class="ip-attack">
<dl>
<dt><img  src='http://p2.qhimg.com/t016dd70ac04d942b1b.png' /></dt>
<dt><a href="javascript:history.go(-1)">返回上一页</a></dt>
</dl>
</div>
</body>
</html>
HTML;
  echo $pape;
}

/**
 *  攻击检查拦截
 */
function webscan_StopAttack($StrFiltKey,$StrFiltValue,$ArrFiltReq,$method) {
  $StrFiltValue=webscan_arr_foreach($StrFiltValue);
  if (preg_match("/".$ArrFiltReq."/is",$StrFiltValue)==1){
    webscan_slog(array('ip' => $_SERVER["REMOTE_ADDR"],'time'=>strftime("%Y-%m-%d %H:%M:%S"),'page'=>$_SERVER["PHP_SELF"],'method'=>$method,'rkey'=>$StrFiltKey,'rdata'=>$StrFiltValue,'user_agent'=>$_SERVER['HTTP_USER_AGENT'],'request_url'=>$_SERVER["REQUEST_URI"]));
    exit(webscan_pape());
  }
  if (preg_match("/".$ArrFiltReq."/is",$StrFiltKey)==1){
    webscan_slog(array('ip' => $_SERVER["REMOTE_ADDR"],'time'=>strftime("%Y-%m-%d %H:%M:%S"),'page'=>$_SERVER["PHP_SELF"],'method'=>$method,'rkey'=>$StrFiltKey,'rdata'=>$StrFiltKey,'user_agent'=>$_SERVER['HTTP_USER_AGENT'],'request_url'=>$_SERVER["REQUEST_URI"]));
    exit(webscan_pape());
  }

}
/**
 *  拦截目录白名单
 */
function webscan_white($webscan_white_name,$webscan_white_url=array()) {
  $url_path=$_SERVER['SCRIPT_NAME'];
  $url_var=$_SERVER['QUERY_STRING'];
  if (preg_match("/".$webscan_white_name."/is",$url_path)==1&&!empty($webscan_white_name)) {
    return false;
  }
  foreach ($webscan_white_url as $key => $value) {
    if(!empty($url_var)&&!empty($value)){
      if (stristr($url_path,$key)&&stristr($url_var,$value)) {
        return false;
      }
    }
    elseif (empty($url_var)&&empty($value)) {
      if (stristr($url_path,$key)) {
        return false;
      }
    }

  }

  return true;
}

if ($webscan_switch&&webscan_white($webscan_white_directory,$webscan_white_url)) {
  if ($webscan_get) {
    foreach($_GET as $key=>$value) {
      webscan_StopAttack($key,$value,$getfilter,"GET");
    }
  }
  if ($webscan_post) {
    foreach($_POST as $key=>$value) {
      webscan_StopAttack($key,$value,$postfilter,"POST");
    }
  }
  if ($webscan_cookie) {
    foreach($_COOKIE as $key=>$value) {
      webscan_StopAttack($key,$value,$cookiefilter,"COOKIE");
    }
  }
  if ($webscan_referre) {
    foreach($webscan_referer as $key=>$value) {
      webscan_StopAttack($key,$value,$postfilter,"REFERRER");
    }
  }
}

?>
