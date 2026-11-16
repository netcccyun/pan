<?php
namespace lib\Storage;
use \lib\IStorage;
use GuzzleHttp\Client;

/**
 * WebDAV 存储，兼容坚果云、Nextcloud、Alist 等支持 WebDAV 协议的服务
 * 文件经网站中转上传和下载
 */
class Webdav implements IStorage {
	private $config;
	private $baseUrl;
	private $client;
	private $errmsg;
	private $filepath = 'file/';
	private $dirReady = false;

	public static function info() {
		return [
			'name' => 'WebDAV',
			'icon' => 'i-lucide-server',
			'cloud' => false,
			'range' => true,
			'sort' => 30,
			'fields' => [
				['key' => 'url', 'label' => 'WebDAV地址', 'placeholder' => '例如：https://dav.jianguoyun.com/dav/', 'help' => '可以包含子目录，文件保存在该目录下的file文件夹中', 'required' => true],
				['key' => 'username', 'label' => '用户名'],
				['key' => 'password', 'label' => '密码', 'help' => '坚果云等服务需要填写第三方应用密码，而不是登录密码'],
				['key' => 'authtype', 'label' => '认证方式', 'type' => 'select', 'options' => [['label' => 'Basic', 'value' => 'basic'], ['label' => 'Digest', 'value' => 'digest']]],
			],
		];
	}

	//保存配置时校验
	public static function check($config) {
		if(!preg_match('/^https?:\/\/[^\/\s]+/i', $config['url'])) return 'WebDAV地址需要以http://或https://开头';
		return true;
	}

	public function __construct($config) {
		$this->config = $config;
		$this->baseUrl = rtrim(trim((string)$config['url']), '/').'/';
		$options = ['timeout' => 600, 'connect_timeout' => 10, 'http_errors' => false, 'verify' => false];
		if(isset($config['username']) && $config['username'] !== ''){
			$options['auth'] = [$config['username'], (string)($config['password'] ?? ''), ($config['authtype'] ?? '') == 'digest' ? 'digest' : 'basic'];
		}
		$this->client = new Client($options);
	}

	public function getClient(){
		return $this->client;
	}

	public function errmsg(){
		return $this->errmsg;
	}

	private function url($name = ''){
		return $this->baseUrl.$this->filepath.rawurlencode($name);
	}

	private function setError($func, $response){
		$status = $response->getStatusCode();
		$message = 'HTTP '.$status;
		if($status == 401) $message .= ' 用户名或密码错误';
		elseif($status == 403) $message .= ' 没有访问权限';
		elseif($status == 404) $message .= ' 文件不存在';
		elseif($status == 507) $message .= ' 存储空间不足';
		$this->errmsg = $func.': '.$message;
	}

	/**
	 * 查询文件属性
	 * @return array|false|null 成功返回属性，文件不存在返回null，出错返回false
	 */
	private function propfind($name){
		$body = '<?xml version="1.0" encoding="utf-8"?><d:propfind xmlns:d="DAV:"><d:prop><d:getcontentlength/><d:getcontenttype/><d:resourcetype/></d:prop></d:propfind>';
		$response = $this->client->request('PROPFIND', $this->url($name), [
			'headers' => ['Depth' => '0', 'Content-Type' => 'application/xml; charset=utf-8'],
			'body' => $body,
		]);
		$status = $response->getStatusCode();
		if($status == 404) return null;
		if($status != 207 && $status != 200){
			$this->setError('propfind', $response);
			return false;
		}
		$xml = (string)$response->getBody();
		$length = preg_match('/<(?:[\w-]+:)?getcontentlength[^>]*>\s*(\d+)\s*</i', $xml, $m) ? intval($m[1]) : 0;
		$type = preg_match('/<(?:[\w-]+:)?getcontenttype[^>]*>\s*([^<\s]+)\s*</i', $xml, $m) ? $m[1] : 'application/octet-stream';
		return ['length' => $length, 'content_type' => $type];
	}

	public function exists($name) {
		try {
			return is_array($this->propfind($name));
		} catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			return false;
		}
	}

	public function get($name) {
		try {
			$response = $this->client->request('GET', $this->url($name));
			if($response->getStatusCode() == 200) return (string)$response->getBody();
			$this->setError(__FUNCTION__, $response);
			return false;
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
			$response = $this->client->request('GET', $this->url($name), ['headers' => $headers, 'stream' => true]);
			$status = $response->getStatusCode();
			if($status != 200 && $status != 206){
				$this->setError(__FUNCTION__, $response);
				return false;
			}
			$body = $response->getBody();
			//服务器不支持Range时返回整个文件，跳过不需要的部分
			$skip = $range && $status == 200 ? intval($range[0]) : 0;
			$remain = $range && $status == 200 ? intval($range[1]) - intval($range[0]) + 1 : -1;
			while($skip > 0 && !$body->eof()){
				$skip -= strlen($body->read(min($skip, 102400)));
			}
			while(!$body->eof() && $remain != 0){
				$data = $body->read($remain > 0 ? min($remain, 102400) : 102400);
				if($remain > 0) $remain -= strlen($data);
				echo $data;
				flush();
			}
			return true;
		} catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	//创建file目录（WebDAV上传前父目录必须存在）
	private function ensureDir(){
		if($this->dirReady) return;
		$response = $this->client->request('MKCOL', rtrim($this->url(), '/').'/');
		$status = $response->getStatusCode();
		//405 表示目录已存在
		if(in_array($status, [200, 201, 405])) $this->dirReady = true;
	}

	private function put($name, $tmpfile, $content_type){
		$fp = fopen($tmpfile, 'rb');
		if(!$fp) throw new \Exception('读取临时文件失败');
		try {
			return $this->client->request('PUT', $this->url($name), [
				'headers' => ['Content-Length' => filesize($tmpfile), 'Content-Type' => $content_type ?: 'application/octet-stream'],
				'body' => $fp,
			]);
		} finally {
			if(is_resource($fp)) fclose($fp);
		}
	}

	public function upload($name, $tmpfile, $content_type = null) {
		try {
			$response = $this->put($name, $tmpfile, $content_type);
			//父目录不存在时（409/404）创建后重试
			if(in_array($response->getStatusCode(), [404, 409])){
				$this->ensureDir();
				$response = $this->put($name, $tmpfile, $content_type);
			}
			if(in_array($response->getStatusCode(), [200, 201, 204])) return true;
			$this->setError(__FUNCTION__, $response);
			return false;
		} catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function savefile($name, $tmpfile, $content_type = null) {
		$result = $this->upload($name, $tmpfile, $content_type);
		if($result) @unlink($tmpfile);
		return $result;
	}

	public function getinfo($name) {
		try {
			$info = $this->propfind($name);
			if(is_array($info)) return $info;
			if($info === null) $this->errmsg = __FUNCTION__.': 文件不存在';
			return false;
		} catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function delete($name) {
		try {
			$response = $this->client->request('DELETE', $this->url($name));
			if(in_array($response->getStatusCode(), [200, 204, 404])) return true;
			$this->setError(__FUNCTION__, $response);
			return false;
		} catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function getUploadParam($name, $filename, $max_file_size = 0){
		$this->errmsg = 'WebDAV不支持直接上传';
		return false;
	}

	public function getDownUrl($name, $filename, $content_type = null){
		$this->errmsg = 'WebDAV不支持直接下载';
		return false;
	}
}
