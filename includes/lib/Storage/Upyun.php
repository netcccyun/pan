<?php
namespace lib\Storage;
use \lib\IStorage;

class Upyun implements IStorage {
	private $config;
	private $client;
	private $errmsg;
	private $filepath = 'file/';

	public function __construct($config) {
		$this->config = $config;
		$serviceConfig = new \Upyun\Config($config['serviceName'], $config['operatorName'], $config['operatorPwd']);
		$this->client = new \Upyun\Upyun($serviceConfig);
	}
	
	public function getClient(){
		return $this->client;
	}

	public function errmsg(){
		return $this->errmsg;
	}

	public function exists($name) {
		try {
			return $this->client->has($this->filepath.$name);
        } catch(\Exception $e) {
			return false;
		}
	}

	public function get($name) {
		try {
			return $this->client->read($this->filepath.$name);
        } catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function downfile($name, $range = false) {
		try {
			$body = $this->client->read($this->filepath.$name, true);
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
		$params['Content-Type'] = $content_type;
        try {
			$this->client->write($this->filepath.$name, fopen($tmpfile, 'rb'), $params);
			return true;
        } catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}
	
	public function savefile($name, $tmpfile, $content_type = null) {
		return $this->upload($name, $tmpfile, $content_type);
	}

	public function getinfo($name) {
		try {
			$info = $this->client->info($this->filepath.$name);
			$minetype = $this->client->getMimetype($this->filepath.$name);
			$result = ['length'=>$info['x-upyun-file-size'], 'content_type'=>$minetype];
			return $result;
        } catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function delete($name) {
		try {
			return $this->client->delete($this->filepath.$name);
        } catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function getUploadParam($name, $filename, $max_file_size = 0){
		$params = [];
		if($max_file_size > 0){
			$params['content-length-range'] = '1,'.$max_file_size;
		}
		$result = $this->client->getUploadParam($this->filepath.$name, $params);
		$post = [
			'policy' => $result['policy'],
			'authorization' => $result['authorization'],
		];
		return ['url'=>$result['url'], 'post'=>$post];
	}

	public function getDownUrl($name, $filename, $content_type = null){
		global $conf;
		if(empty($conf['downfile_domain'])){
			$this->errmsg = '文件下载域名不能为空';
			return false;
		}
		$url = ($conf['downfile_protocol']==1?'https://':'http://').$conf['downfile_domain'].'/'.$this->filepath.$name;
		return $url;
	}
}