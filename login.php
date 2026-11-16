<?php
include("./includes/common.php");

$act = isset($_GET['act']) ? $_GET['act'] : null;

if($act == 'connect'){
    @header('Content-Type: application/json; charset=UTF-8');
    if(!$conf['userlogin'])exit('{"code":-1,"msg":"未开启登录"}');
    if($islogin2==1)exit('{"code":-1,"msg":"您已登录"}');
    $type = isset($_POST['type'])?$_POST['type']:exit('{"code":-1,"msg":"no type"}');
    if(!in_array($type, ['qq','wx']))exit('{"code":-1,"msg":"不支持的登录方式"}');
    if(!$conf['login_apiurl'] || !$conf['login_appid'] || !$conf['login_appkey'])exit('{"code":-1,"msg":"未配置好快捷登录接口信息"}');
    $Oauth = new \lib\Oauth($conf['login_apiurl'], $conf['login_appid'], $conf['login_appkey']);
    $res = $Oauth->login($type);
    if(isset($res['code']) && $res['code']==0){
        $result = ['code'=>0, 'url'=>$res['url']];
    }elseif(isset($res['code'])){
        $result = ['code'=>-1, 'msg'=>$res['msg']];
    }else{
        $result = ['code'=>-1, 'msg'=>'快捷登录接口请求失败'];
    }
    exit(json_encode($result));
}elseif($act == 'logout'){
    @header('Content-Type: application/json; charset=UTF-8');
    if($_SERVER['REQUEST_METHOD'] !== 'POST' || !checkRefererHost())exit('{"code":403,"msg":"非法请求"}');
    setcookie("user_token", "", time() - 1, '/', '', is_https(), true);
    exit('{"code":0,"msg":"您已成功注销本次登录！"}');
}elseif(isset($_GET['code']) && isset($_GET['type']) && isset($_GET['state'])){
    if(!$conf['userlogin'])sysmsg('未开启登录');
    if(empty($_SESSION['Oauth_state']) || !is_string($_GET['state']) || !hash_equals($_SESSION['Oauth_state'], $_GET['state'])){
        sysmsg("<h2>The state does not match. You may be a victim of CSRF.</h2>");
    }
    unset($_SESSION['Oauth_state']);
    $type = $_GET['type'];
    if(!in_array($type, ['qq','wx'], true))sysmsg('不支持的登录方式');
    $typename = $type=='wx'?'微信':'QQ';
    $Oauth = new \lib\Oauth($conf['login_apiurl'], $conf['login_appid'], $conf['login_appkey']);
    $arr = $Oauth->callback();
    if(isset($arr['code']) && $arr['code']==0){
        $openid=$arr['social_uid'];
        $access_token=$arr['access_token'];
        $nickname=trim($arr['nickname']);
        if(empty($nickname) || $nickname=='-') $nickname = $typename.'用户';
        $faceimg=$arr['faceimg'];
    }elseif(isset($arr['code'])){
        sysmsg('<h3>error:</h3>'.htmlspecialchars((string)$arr['errcode']).'<h3>msg  :</h3>'.htmlspecialchars((string)$arr['msg']));
    }else{
        sysmsg('获取登录数据失败');
    }

    $userrow=$DB->find('user','*',['type'=>$type, 'openid'=>$openid], null, '1');
    if(!$userrow){
        if(!$DB->insert('user', [
            'type' => $type,
            'openid' => $openid,
            'nickname' => $nickname,
            'faceimg' => $faceimg,
            'enable' => 1,
            'regip' => $clientip,
            'loginip' => $clientip,
            'addtime' => 'NOW()',
            'lasttime' => 'NOW()',
        ]))sysmsg('用户注册失败 '.$DB->error());
        $uid = $DB->lastInsertId();
    }else{
        if($userrow['enable']==0){
            $_SESSION['user_block'] = true;
            sysmsg('当前用户已被禁止登录');
        }
        $uid = $userrow['uid'];
        $DB->update('user', ['loginip' => $clientip, 'lasttime'=>'NOW()'], ['uid'=>$uid]);
    }
    if($_SESSION['user_block']){
        $DB->update('user', ['enable' => 0], ['uid'=>$uid]);
        sysmsg('当前用户已被禁止登录');
    }
    if(isset($_SESSION['fileids']) && count($_SESSION['fileids'])>0){
        $ids = array_reverse($_SESSION['fileids']);
        if(count($ids) > 60){
            $ids = array_splice($ids, 0, 60);
        }
        $ids = implode(',', array_map('intval', $ids));
        $DB->exec("UPDATE pre_file SET uid='".intval($uid)."' WHERE id IN ({$ids}) AND uid=0");
    }
    $session=md5($type.$openid.$password_hash);
    $expiretime=time()+2592000;
    $token=authcode("{$uid}\t{$session}\t{$expiretime}", 'ENCODE', SYS_KEY);
    ob_clean();
    setcookie("user_token", $token, time() + 2592000, '/', '', is_https(), true);
    exit("<script language='javascript'>window.location.href='./#/';</script>");
}

@header('Location: ./#/login');
exit;
