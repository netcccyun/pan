<?php
namespace lib\Storage;
use \lib\IStorage;
use GuzzleHttp\Client;

/**
 * OneDrive 存储（Microsoft Graph API），支持国际版和世纪互联版
 * 需要在 Azure 注册应用并授权，访问令牌过期后用刷新令牌自动续期
 * 文件经网站中转上传和下载
 */
class Onedrive implements IStorage {
	private $config;
	private $storageId;
	private $endpoint;
	private $client;
	private $errmsg;
	private $filepath = 'file/';

	//小于此大小的文件直接上传，否则使用上传会话分块上传
	const SIMPLE_UPLOAD_MAX = 4194304;
	//分块大小，必须是 320KiB 的整数倍
	const CHUNK_SIZE = 10485760;

	public static function info() {
		return [
			'name' => 'OneDrive',
			'icon' => 'assets/img/storage/onedrive.png',
			'url' => 'https://www.microsoft.com/microsoft-365/onedrive/online-cloud-storage',
			'cloud' => false,
			'range' => true,
			'sort' => 25,
			'oauth' => [
				'require' => ['client_id', 'client_secret'],
				'steps' => [
					'在 Azure 门户（国际版 portal.azure.com，世纪互联 portal.azure.cn）的“应用注册”中新建应用，受支持的帐户类型选择“任何组织目录中的帐户和个人 Microsoft 帐户”（世纪互联选择“任何组织目录中的帐户”）',
					'在“身份验证”中添加 Web 平台，重定向 URI 填写下方的回调地址',
					'在“证书和密码”中新建客户端密码，复制“值”填入上方的客户端密码',
					'在“API 权限”中添加 Microsoft Graph 的委托权限 Files.ReadWrite.All 和 offline_access',
					'填好应用ID和客户端密码后点击“授权登录”，登录要使用的 OneDrive 账号并同意授权，最后保存',
				],
			],
			'fields' => [
				['key' => 'region', 'label' => '版本', 'type' => 'select', 'options' => [['label' => '国际版（含个人版、商业版）', 'value' => 'global'], ['label' => '世纪互联版', 'value' => 'cn']]],
				['key' => 'client_id', 'label' => '应用ID（Client ID）', 'placeholder' => '应用注册中的“应用程序(客户端) ID”', 'required' => true],
				['key' => 'client_secret', 'label' => '客户端密码（Client Secret）', 'required' => true],
				['key' => 'tenant', 'label' => '租户', 'default' => 'common', 'help' => '一般填写 common；如果应用只允许本组织账号登录，填写目录(租户) ID', 'required' => true],
				['key' => 'refresh_token', 'label' => '刷新令牌（Refresh Token）', 'type' => 'textarea', 'placeholder' => '点击下方“授权登录”后自动填写', 'required' => true],
				['key' => 'folder', 'label' => '存储目录', 'placeholder' => '例如：pan', 'help' => '文件保存在 OneDrive 中该目录下的 file 文件夹中，留空则为根目录'],
			],
		];
	}

	private static function endpoints($region){
		if($region == 'cn'){
			return ['login' => 'https://login.chinacloudapi.cn', 'graph' => 'https://microsoftgraph.chinacloudapi.cn/v1.0', 'scope' => 'offline_access https://microsoftgraph.chinacloudapi.cn/Files.ReadWrite.All'];
		}
		return ['login' => 'https://login.microsoftonline.com', 'graph' => 'https://graph.microsoft.com/v1.0', 'scope' => 'offline_access https://graph.microsoft.com/Files.ReadWrite.All'];
	}

	private static function tenant($config){
		$tenant = trim((string)($config['tenant'] ?? ''));
		return preg_match('/^[\w.-]+$/', $tenant) ? $tenant : 'common';
	}

	//授权页面地址
	public static function oauthUrl($config, $redirect_uri, $state){
		$ep = self::endpoints($config['region'] ?? '');
		return $ep['login'].'/'.self::tenant($config).'/oauth2/v2.0/authorize?'.http_build_query([
			'client_id' => $config['client_id'],
			'response_type' => 'code',
			'redirect_uri' => $redirect_uri,
			'response_mode' => 'query',
			'scope' => $ep['scope'],
			'state' => $state,
			'prompt' => 'select_account',
		]);
	}

	//用授权码换取刷新令牌
	public static function oauthToken($config, $code, $redirect_uri){
		$result = self::tokenRequest($config, [
			'grant_type' => 'authorization_code',
			'code' => $code,
			'redirect_uri' => $redirect_uri,
		]);
		if(!is_array($result)) return $result;
		if(empty($result['refresh_token'])) return '没有获取到刷新令牌，请确认已添加 offline_access 权限';
		return ['refresh_token' => $result['refresh_token']];
	}

	/**
	 * 请求令牌接口
	 * @return array|string 成功返回接口结果，失败返回错误信息
	 */
	private static function tokenRequest($config, $params, $ep = null){
		if(!$ep) $ep = self::endpoints($config['region'] ?? '');
		$client = new Client(['timeout' => 30, 'connect_timeout' => 10, 'http_errors' => false, 'verify' => false]);
		try {
			$response = $client->request('POST', $ep['login'].'/'.self::tenant($config).'/oauth2/v2.0/token', [
				'form_params' => $params + [
					'client_id' => $config['client_id'],
					'client_secret' => $config['client_secret'],
					'scope' => $ep['scope'],
				],
			]);
		} catch(\Exception $e) {
			return '请求令牌失败：'.$e->getMessage();
		}
		$data = json_decode((string)$response->getBody(), true);
		if($response->getStatusCode() == 200 && !empty($data['access_token'])) return $data;
		$message = isset($data['error_description']) ? $data['error_description'] : (isset($data['error']) ? $data['error'] : 'HTTP '.$response->getStatusCode());
		//去掉 Trace ID 等排查信息
		return '获取令牌失败：'.trim(preg_replace('/\s*Trace ID:.*$/s', '', trim(strtok($message, "\r\n"))));
	}

	public function __construct($config, $storageId = 0) {
		$this->config = $config;
		$this->storageId = intval($storageId);
		$this->endpoint = self::endpoints($config['region'] ?? '');
		$folder = trim(str_replace('\\', '/', (string)($config['folder'] ?? '')), '/');
		if($folder !== '') $this->filepath = $folder.'/file/';
		$this->client = new Client(['timeout' => 600, 'connect_timeout' => 10, 'http_errors' => false, 'verify' => false]);
	}

	public function getClient(){
		return $this->client;
	}

	public function errmsg(){
		return $this->errmsg;
	}

	/**
	 * 获取访问令牌，过期时用刷新令牌续期，并保存新的令牌
	 */
	private function accessToken($force = false){
		if(!$force && !empty($this->config['access_token']) && intval($this->config['access_expires'] ?? 0) > time() + 120){
			return $this->config['access_token'];
		}
		if(empty($this->config['refresh_token'])) throw new \Exception('OneDrive尚未授权，请在后台存储管理中授权登录');
		$result = self::tokenRequest($this->config, [
			'grant_type' => 'refresh_token',
			'refresh_token' => $this->config['refresh_token'],
		], $this->endpoint);
		if(!is_array($result)) throw new \Exception($result);
		$values = [
			'access_token' => $result['access_token'],
			'access_expires' => (string)(time() + intval($result['expires_in'] ?? 3600)),
		];
		//刷新令牌会轮换，保存新的刷新令牌
		if(!empty($result['refresh_token'])) $values['refresh_token'] = $result['refresh_token'];
		$this->config = array_merge($this->config, $values);
		if($this->storageId > 0) \lib\StorHelper::updateConfig($this->storageId, $values);
		return $this->config['access_token'];
	}

	//文件在 OneDrive 中的路径地址，每段分别编码
	private function itemPath($name){
		$path = implode('/', array_map('rawurlencode', explode('/', $this->filepath.$name)));
		return $this->endpoint['graph'].'/me/drive/root:/'.$path;
	}

	private function api($method, $url, $options = []){
		$options['headers'] = ($options['headers'] ?? []) + ['Authorization' => 'Bearer '.$this->accessToken()];
		$response = $this->client->request($method, $url, $options);
		//令牌失效时强制刷新后重试一次
		if($response->getStatusCode() == 401){
			$options['headers']['Authorization'] = 'Bearer '.$this->accessToken(true);
			$response = $this->client->request($method, $url, $options);
		}
		return $response;
	}

	private function setError($func, $response){
		$message = 'HTTP '.$response->getStatusCode();
		$data = json_decode((string)$response->getBody(), true);
		if(isset($data['error']['message'])) $message .= ' '.$data['error']['message'];
		elseif(isset($data['error']['code'])) $message .= ' '.$data['error']['code'];
		$this->errmsg = $func.': '.$message;
	}

	/**
	 * 获取文件信息
	 * @return array|false|null 成功返回信息，文件不存在返回null，出错返回false
	 */
	private function item($name){
		$response = $this->api('GET', $this->itemPath($name));
		$status = $response->getStatusCode();
		if($status == 404) return null;
		if($status != 200){
			$this->setError('item', $response);
			return false;
		}
		return json_decode((string)$response->getBody(), true);
	}

	//文件的临时下载地址（有效期约1小时，无需授权头）
	private function downloadUrl($name){
		$item = $this->item($name);
		if($item === null) $this->errmsg = 'downloadUrl: 文件不存在';
		if(!$item) return false;
		if(empty($item['@microsoft.graph.downloadUrl'])){
			$this->errmsg = 'downloadUrl: 没有获取到下载地址';
			return false;
		}
		return $item['@microsoft.graph.downloadUrl'];
	}

	public function exists($name) {
		try {
			return is_array($this->item($name));
		} catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			return false;
		}
	}

	public function get($name) {
		try {
			$url = $this->downloadUrl($name);
			if(!$url) return false;
			$response = $this->client->request('GET', $url);
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
			$url = $this->downloadUrl($name);
			if(!$url) return false;
			$response = $this->client->request('GET', $url, ['headers' => $headers, 'stream' => true]);
			$status = $response->getStatusCode();
			if($status != 200 && $status != 206){
				$this->setError(__FUNCTION__, $response);
				return false;
			}
			$body = $response->getBody();
			//服务器忽略Range时返回整个文件，跳过不需要的部分
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

	public function upload($name, $tmpfile, $content_type = null) {
		$fp = null;
		$uploadUrl = null;
		try {
			$size = filesize($tmpfile);
			$fp = fopen($tmpfile, 'rb');
			if(!$fp) throw new \Exception('读取临时文件失败');
			if($size <= self::SIMPLE_UPLOAD_MAX){
				$response = $this->api('PUT', $this->itemPath($name).':/content?@microsoft.graph.conflictBehavior=replace', [
					'headers' => ['Content-Type' => 'application/octet-stream', 'Content-Length' => $size],
					'body' => $fp,
				]);
				if(in_array($response->getStatusCode(), [200, 201])) return true;
				$this->setError(__FUNCTION__, $response);
				return false;
			}
			//大文件：创建上传会话后分块上传
			$response = $this->api('POST', $this->itemPath($name).':/createUploadSession', [
				'json' => ['item' => ['@microsoft.graph.conflictBehavior' => 'replace']],
			]);
			$session = json_decode((string)$response->getBody(), true);
			if($response->getStatusCode() != 200 || empty($session['uploadUrl'])){
				$this->setError(__FUNCTION__, $response);
				return false;
			}
			$uploadUrl = $session['uploadUrl'];
			$offset = 0;
			while($offset < $size){
				$chunk = fread($fp, min(self::CHUNK_SIZE, $size - $offset));
				if($chunk === false || $chunk === '') throw new \Exception('读取临时文件失败');
				$length = strlen($chunk);
				//上传地址自带授权，不能再加 Authorization 头
				$response = $this->client->request('PUT', $uploadUrl, [
					'headers' => ['Content-Length' => $length, 'Content-Range' => 'bytes '.$offset.'-'.($offset + $length - 1).'/'.$size],
					'body' => $chunk,
				]);
				$status = $response->getStatusCode();
				if(!in_array($status, [200, 201, 202])){
					$this->setError(__FUNCTION__, $response);
					$this->client->request('DELETE', $uploadUrl);
					return false;
				}
				$offset += $length;
			}
			return true;
		} catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		} finally {
			if(is_resource($fp)) fclose($fp);
		}
	}

	public function savefile($name, $tmpfile, $content_type = null) {
		$result = $this->upload($name, $tmpfile, $content_type);
		if($result) @unlink($tmpfile);
		return $result;
	}

	public function getinfo($name) {
		try {
			$item = $this->item($name);
			if($item === null) $this->errmsg = __FUNCTION__.': 文件不存在';
			if(!$item) return false;
			return ['length' => intval($item['size'] ?? 0), 'content_type' => $item['file']['mimeType'] ?? 'application/octet-stream'];
		} catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function delete($name) {
		try {
			$response = $this->api('DELETE', $this->itemPath($name));
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
		$this->errmsg = 'OneDrive不支持直接上传';
		return false;
	}

	public function getDownUrl($name, $filename, $content_type = null){
		$this->errmsg = 'OneDrive不支持直接下载';
		return false;
	}
}
