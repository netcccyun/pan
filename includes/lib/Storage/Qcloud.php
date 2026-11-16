<?php
namespace lib\Storage;
use \lib\IStorage;

class Qcloud implements IStorage {
	private $config;
	private $bucket;
	private $cosClient;
	private $errmsg;
	private $filepath = 'file/';
	
	public static function info() {
		return [
			'name' => '腾讯云COS',
			'icon' => 'assets/img/storage/tencent.png',
			'url' => 'https://cloud.tencent.com/act/cps/redirect?redirect=10042&cps_key=11eaac2f518cd09a6288f4b1912228b8',
			'cloud' => true,
			'range' => true,
			'sort' => 11,
			'fields' => array_merge([
				['key' => 'secretId', 'label' => 'SecretId', 'required' => true],
				['key' => 'secretKey', 'label' => 'SecretKey', 'required' => true],
				['key' => 'region', 'label' => 'COS存储桶地域', 'placeholder' => '填写英文名称，例如：ap-shanghai', 'required' => true],
				['key' => 'bucket', 'label' => 'COS存储桶名称', 'placeholder' => '格式：BucketName-APPID', 'required' => true],
			], \lib\StorHelper::cloudFields()),
		];
	}

	public function __construct($config) {
		$this->bucket = $config['bucket'];
		$this->config = $config;
		$this->cosClient = new \Qcloud\Cos\Client(
			array(
				'region' => $config['region'],
				'schema' => 'http',
				'verify' => false,
				'credentials'=> array(
					'secretId'  => $config['secretId'] ,
					'secretKey' => $config['secretKey'])));
	}

	public function getClient(){
		return $this->cosClient;
	}

	public function errmsg(){
		return $this->errmsg;
	}

	public function exists($name) {
		try {
			$result = $this->cosClient->headObject(['Bucket'=>$this->bucket, 'Key'=>$this->filepath.$name]);
			return true;
        } catch(\Qcloud\Cos\Exception\ServiceResponseException $e) {
			return false;
		}
	}

	public function get($name) {
		try {
			$content = $this->cosClient->getObject(['Bucket'=>$this->bucket, 'Key'=>$this->filepath.$name]);
			return $content['Body'];
        } catch(\Qcloud\Cos\Exception\ServiceResponseException $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function downfile($name, $range = false) {
		$options = [];
		if($range){
			$options['Range'] = 'bytes='.$range[0].'-'.$range[1];
		}
		try {
			$content = $this->cosClient->getObject(['Bucket'=>$this->bucket, 'Key'=>$this->filepath.$name] + $options);
			echo $content['Body'];
			return true;
        } catch(\Qcloud\Cos\Exception\ServiceResponseException $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function upload($name, $tmpfile, $content_type = null) {
        try {
			$this->cosClient->upload($this->bucket, $this->filepath.$name, fopen($tmpfile, 'rb'));
			return true;
        } catch(\Qcloud\Cos\Exception\ServiceResponseException $e) {
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
			$objectMeta = $this->cosClient->headObject(['Bucket'=>$this->bucket, 'Key'=>$this->filepath.$name]);
			$result = ['length'=>$objectMeta['ContentLength'], 'content_type'=>$objectMeta['ContentType']];
			return $result;
        } catch(\Qcloud\Cos\Exception\ServiceResponseException $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function delete($name) {
		try {
			$this->cosClient->deleteObject(['Bucket'=>$this->bucket, 'Key'=>$this->filepath.$name]);
			return true;
        } catch(\Qcloud\Cos\Exception\ServiceResponseException $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function getUploadParam($name, $filename, $max_file_size = 0){
		$url = 'https://'.$this->bucket.'.cos.'.$this->config['region'].'.myqcloud.com/';
		$key = $this->filepath.$name;
		$expire = 3600;
		$expiration = date("Y-m-d\TH:i:s.000\Z", time() + $expire);
		$keyTime = time().';'.(time()+$expire);
		$conditions = [];
		$conditions[] = ['bucket' => $this->bucket];
        $conditions[] = ['eq', '$key', $key];
        if($max_file_size > 0){
            $conditions[] = ['content-length-range', 1, $max_file_size];
        }
		$conditions[] = ['q-sign-algorithm' => 'sha1'];
		$conditions[] = ['q-ak' => $this->config['secretId']];
		$conditions[] = ['q-sign-time' => $keyTime];
        $policy_param = [
            'expiration' => $expiration,
            'conditions' => $conditions
        ];
        $policy = json_encode($policy_param, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		$signKey = hash_hmac('sha1', $keyTime, $this->config['secretKey']);
		$stringToSign = hash('sha1', $policy);
		$signature = hash_hmac('sha1', $stringToSign, $signKey);
		$param = [
			'Cache-Control' => 'max-age=2592000',
			'Content-Disposition' => 'attachment; filename='.$filename,
			'key' => $key,
			'success_action_status' => '200',
            'policy' => base64_encode($policy),
			'q-sign-algorithm' => 'sha1',
			'q-ak' => $this->config['secretId'],
			'q-key-time' => $keyTime,
            'q-signature' => $signature,
        ];
		return ['url'=>$url, 'post'=>$param];
	}

	public function getDownUrl($name, $filename, $content_type = null){
		$conf = $this->config;
		$filename = '"'.$filename.'"; filename*=utf-8\'\''.rawurlencode($filename);
		if(!$content_type){
			$options = [
				'ResponseContentType' => 'application/force-download',
				'ResponseContentDisposition' => 'attachment; filename='.$filename,
			];
		}else{
			$options = [
				'ResponseContentType' => $content_type,
				'ResponseContentDisposition' => 'inline; filename='.$filename,
			];
		}
		try {
			$url = $this->cosClient->getObjectUrl($this->bucket, $this->filepath.$name, '+10 years', $options);
			if(!empty($conf['downfile_domain'])){
				$url_arr = parse_url($url);
				$url = str_replace($url_arr['scheme'].'://'.$url_arr['host'], ($conf['downfile_protocol']==1?'https://':'http://').$conf['downfile_domain'], $url);
			}
			return $url;
        } catch(\Qcloud\Cos\Exception\ServiceResponseException $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}
}