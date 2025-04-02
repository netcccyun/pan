<?php
namespace lib\Storage;
use \lib\IStorage;

class Oss implements IStorage {
	private $config;
	private $bucket;
	private $ossClient;
	private $errmsg;
	private $filepath = 'file/';

	public function __construct($config) {
		$this->bucket = $config['bucket'];
		$this->config = $config;
		$this->ossClient = new \OSS\OssClient($config['accessKeyId'], $config['accessKeySecret'], $config['endpoint']);
	}

	public function getClient(){
		return $this->ossClient;
	}

	public function errmsg(){
		return $this->errmsg;
	}

	public function exists($name) {
		try {
			return $this->ossClient->doesObjectExist($this->bucket, $this->filepath.$name);
        } catch(\OSS\Core\OssException $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function get($name) {
		try {
			return $this->ossClient->getObject($this->bucket, $this->filepath.$name);
        } catch(\OSS\Core\OssException $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function downfile($name, $range = false) {
		if($range){
			$options['range'] = $range[0].'-'.$range[1];
		}
		try {
			echo $this->ossClient->getObject($this->bucket, $this->filepath.$name, $options);
			return true;
        } catch(\OSS\Core\OssException $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function upload($name, $tmpfile, $content_type = null) {
        try {
			$this->ossClient->uploadFile($this->bucket, $this->filepath.$name, $tmpfile);
			return true;
        } catch(\OSS\Core\OssException $e) {
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
			$objectMeta = $this->ossClient->getObjectMeta($this->bucket, $this->filepath.$name);
			$result = ['length'=>$objectMeta['content-length'], 'content_type'=>$objectMeta['content-type']];
			return $result;
        } catch(\OSS\Core\OssException $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function delete($name) {
		try {
			$this->ossClient->deleteObject($this->bucket, $this->filepath.$name);
			return true;
        } catch(\OSS\Core\OssException $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function getUploadParam($name, $filename, $max_file_size = 0){
		$url = 'https://'.$this->bucket.'.'.$this->config['endpoint'].'/';
		$key = $this->filepath.$name;
		$expire = 3600;
		$expiration = date("Y-m-d\TH:i:s.000\Z", time() + $expire);
		$conditions = [];
		$conditions[] = ['bucket' => $this->bucket];
        $conditions[] = ['eq', '$key', $key];
        if($max_file_size > 0){
            $conditions[] = ['content-length-range', 1, $max_file_size];
        }
        $policy_param = [
            'expiration' => $expiration,
            'conditions' => $conditions
        ];
        $policy = base64_encode(json_encode($policy_param, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
		$signature = base64_encode(hash_hmac('sha1', $policy, $this->config['accessKeySecret'], true));
		$param = [
			'Cache-Control' => 'max-age=2592000',
			'Content-Disposition' => 'attachment; filename='.$filename,
            'OSSAccessKeyId' => $this->config['accessKeyId'],
            'policy' => $policy,
            'Signature' => $signature,
            'key' => $key,
            'success_action_status' => '200',
        ];
		return ['url'=>$url, 'post'=>$param];
	}
	
	public function getDownUrl($name, $filename, $content_type = null){
		global $conf;
		$timeout = 315360000;
		$filename = '"'.$filename.'"; filename*=utf-8\'\''.rawurlencode($filename);
		if(!$content_type){
			$options = [
				//'response-content-type' => 'application/force-download',
				'response-content-disposition' => 'attachment; filename='.$filename,
			];
		}else{
			$options = [
				//'response-content-type' => $content_type,
				'response-content-disposition' => 'inline; filename='.$filename,
			];
		}
		try {
			$url = $this->ossClient->signUrl($this->bucket, $this->filepath.$name, $timeout, 'GET', $options);
			if(!empty($conf['downfile_domain'])){
				$url_arr = parse_url($url);
				$url = str_replace($url_arr['scheme'].'://'.$url_arr['host'], ($conf['downfile_protocol']==1?'https://':'http://').$conf['downfile_domain'], $url);
			}else{
				$url = str_replace('http://', 'https://', $url);
			}
			return $url;
        } catch(\OSS\Core\OssException $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}
}