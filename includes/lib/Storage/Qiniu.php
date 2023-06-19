<?php
namespace lib\Storage;
use \lib\IStorage;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;

class Qiniu implements IStorage {
	private $config;
	private $bucket;
	private $auth;
	private $errmsg;
	private $filepath = 'file/';
	
	public function __construct($config) {
		$this->bucket = $config['bucket'];
		$this->config = $config;
		$this->auth = new \Qiniu\Auth($config['accessKey'], $config['secretKey']);
	}

	public function getClient(){
		return $this->auth;
	}

	public function errmsg(){
		return $this->errmsg;
	}

	public function exists($name) {
		try {
			$bucketMgr = new \Qiniu\Storage\BucketManager($this->auth);
			list($res, $err) = $bucketMgr->stat($this->bucket, $this->filepath.$name);
			return $res!==null;
        } catch(\Exception $e) {
			return false;
		}
	}

	public function get($name) {
		try {
			$url = $this->getDownUrl($name);
			if(!$url) return false;
			$client = new Client(['timeout' => 30]);
			$request = new Psr7\Request('GET', $url);
			$response = $client->send($request);
			$body = $response->getBody();
			return $body->getContents();
        } catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function downfile($name, $range = false) {
		$headers = [];
		if($range){
			$headers['Range'] = 'bytes='.$range[0].'-'.$range[1];
		}
		try {
			$url = $this->getDownUrl($name);
			if(!$url) return false;
			$client = new Client(['timeout' => 30]);
			$request = new Psr7\Request('GET', $url, $headers);
			$response = $client->send($request);
			$body = $response->getBody();
			while(!$body -> eof()){
				echo $body -> read(102400);
			}
			return true;
        } catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function upload($name, $tmpfile, $content_type = null) {
        try {
			$token = $this->auth->uploadToken($this->bucket);
			$uploadMgr = new \Qiniu\Storage\UploadManager();
			$uploadMgr->putFile($token, $this->filepath.$name, $tmpfile);
			return true;
        } catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function savefile($name, $tmpfile, $content_type = null) {
		return $this->upload($name, $tmpfile);
	}

	public function getinfo($name) {
		try {
			$bucketMgr = new \Qiniu\Storage\BucketManager($this->auth);
			list($res, $err) = $bucketMgr->stat($this->bucket, $this->filepath.$name);
			if($res!==null){
				$result = ['length'=>$res['fsize'], 'content_type'=>$res['fsize']];
				return $result;
			}else{
				$this->errmsg = $err ? $err->message() : 'Unknown Error';
			}
        } catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function delete($name) {
		try {
			$bucketMgr = new \Qiniu\Storage\BucketManager($this->auth);
			list($res, $err) = $bucketMgr->delete($this->bucket, $this->filepath.$name);
			if($err===null){
				return true;
			}else{
				$this->errmsg = $err->message();
			}
        } catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function getUploadParam($name, $filename, $max_file_size = 0){
		$config = new \Qiniu\Config();
		$config->useHTTPS = true;
		list($upHost, $err) = $config->getUpHostV2($this->config['accessKey'], $this->bucket);
        if ($err != null) {
            $this->errmsg = $err->message();
			return false;
        }
		$url = $upHost;
		$key = $this->filepath.$name;
		$policy = [];
		if($max_file_size > 0){
			$policy['fsizeLimit'] = $max_file_size;
		}
		$token = $this->auth->uploadToken($this->bucket, $key, 3600, $policy);
		$post = [
			'key' => $key,
			'token' => $token,
		];
		return ['url'=>$url, 'post'=>$post];
	}

	public function getDownUrl($name, $filename = null, $content_type = null){
		global $conf;
		$expires = 315360000;
		if(!$conf['downfile_type'] || empty($conf['downfile_domain'])) $conf['downfile_domain'] = $this->config['domain'];
		$url = ($conf['downfile_protocol']==1?'https://':'http://').$conf['downfile_domain'].'/'.$this->filepath.$name;
		if(!empty($filename) && $content_type==null){
			$url .= '?attname='.rawurlencode($filename);
		}
		$url = $this->auth->privateDownloadUrl($url, $expires);
		return $url;
	}
}