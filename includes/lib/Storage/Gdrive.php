<?php
namespace lib\Storage;
use \lib\IStorage;
use GuzzleHttp\Client;

/**
 * Google Drive 存储（Drive API v3）
 * 使用 drive.file 权限，只能访问本程序创建的文件夹和文件
 * 文件保存在指定的文件夹中（不存在时自动创建），经网站中转上传和下载
 */
class Gdrive implements IStorage {
	private $config;
	private $storageId;
	private $endpoint;
	private $client;
	private $errmsg;
	private $folderId = null;
	//文件名对应的文件信息缓存
	private $items = [];

	//分块上传的分块大小，必须是 256KiB 的整数倍
	const CHUNK_SIZE = 10485760;
	const FOLDER_MIME = 'application/vnd.google-apps.folder';

	public static function info() {
		return [
			'name' => 'Google Drive',
			'icon' => 'assets/img/storage/gdrive.png',
			'url' => 'https://www.google.com/drive/',
			'cloud' => false,
			'range' => true,
			'sort' => 26,
			'oauth' => [
				'require' => ['client_id', 'client_secret'],
				'steps' => [
					'在 Google Cloud 控制台（console.cloud.google.com）新建项目，在“API 和服务”中启用 Google Drive API',
					'配置 OAuth 同意屏幕（Google Auth Platform），用户类型选择“外部”，并将发布状态改为“正式版”，否则刷新令牌 7 天后就会失效',
					'在“凭据”（客户端）中创建 OAuth 客户端 ID，应用类型选择“Web 应用”，已获授权的重定向 URI 填写下方的回调地址',
					'把客户端 ID 和客户端密钥填入上方，点击“授权登录”，登录要使用的 Google 账号并同意授权，最后保存',
				],
			],
			'fields' => [
				['key' => 'client_id', 'label' => '客户端 ID（Client ID）', 'placeholder' => '以 .apps.googleusercontent.com 结尾', 'required' => true],
				['key' => 'client_secret', 'label' => '客户端密钥（Client Secret）', 'required' => true],
				['key' => 'refresh_token', 'label' => '刷新令牌（Refresh Token）', 'type' => 'textarea', 'placeholder' => '点击下方“授权登录”后自动填写', 'required' => true],
				['key' => 'folder', 'label' => '存储文件夹', 'default' => 'pan', 'help' => '文件保存在 Google Drive 中的该文件夹下，不存在时自动创建，多级文件夹用 / 分隔', 'required' => true],
			],
		];
	}

	private static function endpoints(){
		return [
			'auth' => 'https://accounts.google.com/o/oauth2/v2/auth',
			'token' => 'https://oauth2.googleapis.com/token',
			'api' => 'https://www.googleapis.com/drive/v3',
			'upload' => 'https://www.googleapis.com/upload/drive/v3',
			'scope' => 'https://www.googleapis.com/auth/drive.file',
		];
	}

	//授权页面地址
	public static function oauthUrl($config, $redirect_uri, $state){
		$ep = self::endpoints();
		return $ep['auth'].'?'.http_build_query([
			'client_id' => $config['client_id'],
			'redirect_uri' => $redirect_uri,
			'response_type' => 'code',
			'scope' => $ep['scope'],
			//离线访问才会返回刷新令牌，consent 保证每次授权都返回刷新令牌
			'access_type' => 'offline',
			'prompt' => 'consent',
			'state' => $state,
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
		if(empty($result['refresh_token'])) return '没有获取到刷新令牌，请重新授权';
		return ['refresh_token' => $result['refresh_token']];
	}

	/**
	 * 请求令牌接口
	 * @return array|string 成功返回接口结果，失败返回错误信息
	 */
	private static function tokenRequest($config, $params, $ep = null){
		if(!$ep) $ep = self::endpoints();
		$client = new Client(['timeout' => 30, 'connect_timeout' => 10, 'http_errors' => false, 'verify' => false]);
		try {
			$response = $client->request('POST', $ep['token'], [
				'form_params' => $params + [
					'client_id' => $config['client_id'],
					'client_secret' => $config['client_secret'],
				],
			]);
		} catch(\Exception $e) {
			return '请求令牌失败：'.$e->getMessage();
		}
		$data = json_decode((string)$response->getBody(), true);
		if($response->getStatusCode() == 200 && !empty($data['access_token'])) return $data;
		$message = isset($data['error_description']) ? $data['error_description'] : (isset($data['error']) ? $data['error'] : 'HTTP '.$response->getStatusCode());
		//刷新令牌失效（被撤销或测试状态的应用已过期）
		if(isset($data['error']) && $data['error'] == 'invalid_grant') $message .= '，请重新授权';
		return '获取令牌失败：'.$message;
	}

	public function __construct($config, $storageId = 0) {
		$this->config = $config;
		$this->storageId = intval($storageId);
		$this->endpoint = self::endpoints();
		$this->client = new Client(['timeout' => 600, 'connect_timeout' => 10, 'http_errors' => false, 'verify' => false]);
	}

	public function getClient(){
		return $this->client;
	}

	public function errmsg(){
		return $this->errmsg;
	}

	//保存配置中的部分参数（令牌、文件夹ID）
	private function saveConfig($values){
		$this->config = array_merge($this->config, $values);
		if($this->storageId > 0) \lib\StorHelper::updateConfig($this->storageId, $values);
	}

	/**
	 * 获取访问令牌，过期时用刷新令牌续期
	 */
	private function accessToken($force = false){
		if(!$force && !empty($this->config['access_token']) && intval($this->config['access_expires'] ?? 0) > time() + 120){
			return $this->config['access_token'];
		}
		if(empty($this->config['refresh_token'])) throw new \Exception('Google Drive尚未授权，请在后台存储管理中授权登录');
		$result = self::tokenRequest($this->config, [
			'grant_type' => 'refresh_token',
			'refresh_token' => $this->config['refresh_token'],
		], $this->endpoint);
		if(!is_array($result)) throw new \Exception($result);
		$values = [
			'access_token' => $result['access_token'],
			'access_expires' => (string)(time() + intval($result['expires_in'] ?? 3600)),
		];
		if(!empty($result['refresh_token'])) $values['refresh_token'] = $result['refresh_token'];
		$this->saveConfig($values);
		return $this->config['access_token'];
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
		$this->errmsg = $func.': '.$message;
	}

	//查询语句中的字符串
	private static function quote($value){
		return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
	}

	/**
	 * 在父文件夹中按名称查找
	 * @return array|null|false 找到返回文件信息，没有返回null，出错返回false
	 */
	private function find($name, $parent, $isFolder = false){
		$q = 'name = '.self::quote($name).' and '.self::quote($parent).' in parents and trashed = false';
		$q .= $isFolder ? " and mimeType = '".self::FOLDER_MIME."'" : " and mimeType != '".self::FOLDER_MIME."'";
		$response = $this->api('GET', $this->endpoint['api'].'/files?'.http_build_query([
			'q' => $q,
			'fields' => 'files(id,name,size,mimeType)',
			'pageSize' => 1,
			'spaces' => 'drive',
		]));
		if($response->getStatusCode() != 200){
			$this->setError('find', $response);
			return false;
		}
		$data = json_decode((string)$response->getBody(), true);
		return !empty($data['files'][0]) ? $data['files'][0] : null;
	}

	/**
	 * 存储文件夹的ID
	 * @param bool $create 不存在时是否创建（上传时创建，读取时不创建）
	 * @return string|null|false 文件夹ID，不存在返回null，出错返回false
	 */
	private function folderId($create = false){
		if($this->folderId) return $this->folderId;
		$path = trim(str_replace('\\', '/', (string)($this->config['folder'] ?? '')), '/');
		if($path === '') $path = 'pan';
		//使用保存的文件夹ID；上传前确认文件夹还在，避免上传到已删除的文件夹
		if(!empty($this->config['folder_id']) && ($this->config['folder_path'] ?? '') === $path){
			if(!$create) return $this->folderId = $this->config['folder_id'];
			$response = $this->api('GET', $this->endpoint['api'].'/files/'.rawurlencode($this->config['folder_id']).'?fields=id,trashed');
			$data = json_decode((string)$response->getBody(), true);
			if($response->getStatusCode() == 200 && empty($data['trashed'])) return $this->folderId = $this->config['folder_id'];
		}
		$parent = 'root';
		foreach(array_filter(explode('/', $path), 'strlen') as $segment){
			$folder = $this->find($segment, $parent, true);
			if($folder === false) return false;
			if($folder === null){
				if(!$create) return null;
				$response = $this->api('POST', $this->endpoint['api'].'/files?fields=id', [
					'json' => ['name' => $segment, 'mimeType' => self::FOLDER_MIME, 'parents' => [$parent]],
				]);
				$folder = json_decode((string)$response->getBody(), true);
				if($response->getStatusCode() != 200 || empty($folder['id'])){
					$this->setError('createFolder', $response);
					return false;
				}
			}
			$parent = $folder['id'];
		}
		$this->saveConfig(['folder_id' => $parent, 'folder_path' => $path]);
		return $this->folderId = $parent;
	}

	/**
	 * 获取文件信息
	 * @return array|null|false 成功返回信息，文件不存在返回null，出错返回false
	 */
	private function item($name){
		if(array_key_exists($name, $this->items)) return $this->items[$name];
		$folder = $this->folderId();
		if($folder === false) return false;
		$item = $folder === null ? null : $this->find($name, $folder);
		if($item !== false) $this->items[$name] = $item;
		return $item;
	}

	private function mediaUrl($item){
		return $this->endpoint['api'].'/files/'.rawurlencode($item['id']).'?alt=media';
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
			$item = $this->item($name);
			if(!$item){
				if($item === null) $this->errmsg = __FUNCTION__.': 文件不存在';
				return false;
			}
			$response = $this->api('GET', $this->mediaUrl($item));
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
			$item = $this->item($name);
			if(!$item){
				if($item === null) $this->errmsg = __FUNCTION__.': 文件不存在';
				return false;
			}
			$response = $this->api('GET', $this->mediaUrl($item), ['headers' => $headers, 'stream' => true]);
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
		try {
			$size = filesize($tmpfile);
			$content_type = $content_type ?: 'application/octet-stream';
			$folder = $this->folderId(true);
			if(!$folder) return false;
			$this->folderId = $folder;
			unset($this->items[$name]);
			$existing = $this->item($name);
			if($existing === false) return false;
			//创建可续传上传会话：同名文件已存在时覆盖其内容
			$headers = ['X-Upload-Content-Type' => $content_type, 'X-Upload-Content-Length' => $size];
			//响应的 Location 头是上传地址，不能当作跳转处理
			if($existing){
				$response = $this->api('PATCH', $this->endpoint['upload'].'/files/'.rawurlencode($existing['id']).'?uploadType=resumable', ['headers' => $headers, 'json' => new \stdClass(), 'allow_redirects' => false]);
			}else{
				$response = $this->api('POST', $this->endpoint['upload'].'/files?uploadType=resumable', ['headers' => $headers, 'json' => ['name' => $name, 'parents' => [$folder]], 'allow_redirects' => false]);
			}
			$sessionUrl = $response->getHeaderLine('Location');
			if($response->getStatusCode() != 200 || !$sessionUrl){
				$this->setError(__FUNCTION__, $response);
				return false;
			}
			$fp = fopen($tmpfile, 'rb');
			if(!$fp) throw new \Exception('读取临时文件失败');
			$offset = 0;
			do {
				$chunk = $size > 0 ? fread($fp, min(self::CHUNK_SIZE, $size - $offset)) : '';
				if($chunk === false || ($size > 0 && $chunk === '')) throw new \Exception('读取临时文件失败');
				$length = strlen($chunk);
				$range = $size > 0 ? 'bytes '.$offset.'-'.($offset + $length - 1).'/'.$size : 'bytes */0';
				$response = $this->api('PUT', $sessionUrl, [
					'headers' => ['Content-Length' => $length, 'Content-Range' => $range],
					'body' => $chunk,
					'allow_redirects' => false,
				]);
				$status = $response->getStatusCode();
				$offset += $length;
				//308 表示分块已接收，继续上传下一块
				if($status == 308) continue;
				if($status == 200 || $status == 201){
					unset($this->items[$name]);
					return true;
				}
				$this->setError(__FUNCTION__, $response);
				return false;
			} while($offset < $size);
			$this->errmsg = __FUNCTION__.': 上传未完成';
			return false;
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
			return ['length' => intval($item['size'] ?? 0), 'content_type' => $item['mimeType'] ?? 'application/octet-stream'];
		} catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function delete($name) {
		try {
			$item = $this->item($name);
			if($item === false) return false;
			if($item === null) return true;
			$response = $this->api('DELETE', $this->endpoint['api'].'/files/'.rawurlencode($item['id']));
			unset($this->items[$name]);
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
		$this->errmsg = 'Google Drive不支持直接上传';
		return false;
	}

	public function getDownUrl($name, $filename, $content_type = null){
		$this->errmsg = 'Google Drive不支持直接下载';
		return false;
	}
}
