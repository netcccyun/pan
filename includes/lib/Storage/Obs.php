<?php
namespace lib\Storage;
use \lib\IStorage;

class Obs implements IStorage {
	private $config;
	private $bucket;
	private $obsClient;
	private $errmsg;
	private $filepath = 'file/';

	public static function info() {
		return [
			'name' => '华为云OBS',
			'icon' => 'assets/img/storage/huawei.png',
			'url' => 'https://www.huaweicloud.com/product/obs.html?fromacct=b70162c8-fbde-42ca-9f3d-5d99dc1951ba&utm_source=bmV0MjAy=&utm_medium=cps&utm_campaign=201905',
			'cloud' => true,
			'range' => true,
			'sort' => 12,
			'fields' => array_merge([
				['key' => 'accessKey', 'label' => 'AccessKeyId', 'required' => true],
				['key' => 'secretKey', 'label' => 'SecretAccessKey', 'required' => true],
				['key' => 'endpoint', 'label' => 'OBS EndPoint', 'placeholder' => '例如：obs.cn-north-4.myhuaweicloud.com', 'format' => 'domain', 'required' => true],
				['key' => 'bucket', 'label' => 'OBS桶名称', 'required' => true],
			], \lib\StorHelper::cloudFields()),
		];
	}

	public function __construct($config) {
		$this->bucket = $config['bucket'];
		$this->config = $config;
		$this->obsClient = new \Obs\ObsClient([
			'key' => $config['accessKey'],
			'secret' => $config['secretKey'],
			'endpoint' => $config['endpoint'],
			'ssl_verify' => false,
		]);
	}

	public function getClient(){
		return $this->obsClient;
	}
	
	public function errmsg(){
		return $this->errmsg;
	}

	public function exists($name) {
		try {
			$this->obsClient->getObjectMetadata([
				'Bucket' => $this->bucket,
				'Key' => $this->filepath.$name
			]);
			return true;
        } catch(\Obs\ObsException $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->__toString();
			return false;
		}
	}

	public function get($name) {
		try {
			$resp = $this->obsClient->getObject([
				'Bucket' => $this->bucket,
				'Key' => $this->filepath.$name
			]);
			return $resp['Body'];
        } catch(\Obs\ObsException $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->__toString();
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
			$resp = $this->obsClient->getObject([
				'Bucket' => $this->bucket,
				'Key' => $this->filepath.$name,
				'SaveAsStream' => true
			] + $options);
			while(!$resp['Body'] -> eof()){
				echo $resp['Body'] -> read(102400);
			}
			return true;
        } catch(\Obs\ObsException $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->__toString();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function upload($name, $tmpfile, $content_type = null) {
        try {
			$this->obsClient->putObject([
				'Bucket' => $this->bucket,
				'Key' => $this->filepath.$name,
				'SourceFile' => $tmpfile
			]);
			return true;
        } catch(\Obs\ObsException $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->__toString();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function savefile($name, $tmpfile, $content_type = null) {
		return $this->upload($name, $tmpfile);
	}

	public function getinfo($name) {
		try {
			$objectMeta = $this->obsClient->getObjectMetadata([
				'Bucket' => $this->bucket,
				'Key' => $this->filepath.$name
			]);
			$result = ['length'=>$objectMeta['ContentLength'], 'content_type'=>$objectMeta['ContentType']];
			return $result;
        } catch(\Obs\ObsException $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->__toString();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function delete($name) {
		try {
			$this->obsClient->deleteObject([
				'Bucket' => $this->bucket,
				'Key' => $this->filepath.$name
			]);
			return true;
        } catch(\Obs\ObsException $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->__toString();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function getUploadParam($name, $filename, $max_file_size = 0){
		$key = $this->filepath.$name;
		$url = 'https://'.$this->bucket.'.'.$this->config['endpoint'].'/';
		$formParams = [
			'Cache-Control' => 'max-age=2592000',
			'Content-Disposition' => 'attachment; filename='.$filename,
			'success_action_status' => '200'
		];
		try {
			$resp = $this->obsClient->createPostSignature([
				'Bucket' => $this->bucket,
				'Key' => $key,
				'Expires' => 3600,
				'FormParams' => $formParams,
			]);
			$param = $formParams + [
				'key' => $key,
				'AccessKeyId' => $this->config['accessKey'],
				'policy' => $resp['Policy'],
				'signature' => $resp['Signature'],
			];
			return ['url'=>$url, 'post'=>$param];
        } catch(\Obs\ObsException $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->__toString();
			trigger_error($this->errmsg);
			return false;
		}
	}
	
	public function getDownUrl($name, $filename, $content_type = null){
		$conf = $this->config;
		$expires = 315360000;
		$filename = '"'.$filename.'"; filename*=utf-8\'\''.rawurlencode($filename);
		if(!$content_type){
			$options = [
				'response-content-type' => 'application/force-download',
				'response-content-disposition' => 'attachment; filename='.$filename,
			];
		}else{
			$options = [
				'response-content-type' => $content_type,
				'response-content-disposition' => 'inline; filename='.$filename,
			];
		}
		try {
			$resp = $this->obsClient->createSignedUrl([
				'Method' => 'GET',
				'Bucket' => $this->bucket,
				'Key' => $this->filepath.$name,
				'Expires' => $expires,
				'QueryParams' => $options
			]);
			$url = $resp['SignedUrl'];
			if(!empty($conf['downfile_domain'])){
				$url_arr = parse_url($url);
				$url = str_replace($url_arr['scheme'].'://'.$url_arr['host'], ($conf['downfile_protocol']==1?'https://':'http://').$conf['downfile_domain'], $url);
			}
			return $url;
        } catch(\Obs\ObsException $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->__toString();
			trigger_error($this->errmsg);
			return false;
		}
	}
}