<?php
namespace lib\Storage;
use \lib\IStorage;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\Utils;

/**
 * Azure Blob
 * 网站中转使用共享密钥（Shared Key）签名，直接上传和下载使用服务 SAS 签名链接
 */
class Azure implements IStorage {
	const VERSION = '2021-08-06';
	//超过该大小的文件分块上传（Put Block + Put Block List）
	const SINGLE_MAX = 268435456;
	const BLOCK_SIZE = 33554432;

	private $config;
	private $account;
	private $key;
	private $container;
	private $scheme;
	private $host;
	private $basePath;
	private $client;
	private $errmsg;
	private $filepath = 'file/';

	public static function info() {
		return [
			'name' => 'Azure Blob',
			'icon' => 'assets/img/storage/azure.png',
			'url' => 'https://azure.microsoft.com/products/storage/blobs/',
			'cloud' => true,
			'range' => true,
			'sort' => 21,
			'fields' => array_merge([
				['key' => 'account', 'label' => '存储账户名称', 'placeholder' => '例如：mystorageaccount', 'help' => '在 Azure 门户的“存储账户”列表中查看', 'required' => true],
				['key' => 'accountKey', 'label' => '访问密钥', 'help' => '存储账户 → 安全性和网络 → 访问密钥，填写 key1 或 key2', 'required' => true],
				['key' => 'container', 'label' => '容器名称', 'placeholder' => '例如：files', 'help' => '需要先在存储账户中创建容器，访问级别保持“专用”即可', 'required' => true],
				['key' => 'cloud', 'label' => '服务区域', 'type' => 'select', 'options' => [
					['label' => 'Azure 全球版（core.windows.net）', 'value' => 'global'],
					['label' => 'Azure 中国（世纪互联，core.chinacloudapi.cn）', 'value' => 'china'],
					['label' => '自定义 Blob 服务地址', 'value' => 'custom'],
				]],
				['key' => 'endpoint', 'label' => 'Blob 服务地址', 'placeholder' => '例如：https://mystorageaccount.blob.core.windows.net', 'required' => true, 'show' => ['key' => 'cloud', 'value' => 'custom']],
			], \lib\StorHelper::cloudFields([
				'uploadfile_type' => ['help' => '直接链接上传需要在存储账户的“资源共享(CORS)”中为 Blob 服务添加规则：允许的来源填写本站域名，允许的方法勾选 PUT，允许的标头和公开的标头填写 *'],
				'downfile_domain' => ['placeholder' => '留空则使用 Blob 服务地址', 'help' => '填写绑定到存储账户的自定义域名，或 Azure CDN / Front Door 域名'],
			])),
		];
	}

	//保存配置时校验
	public static function check($config) {
		if(!preg_match('/^[a-z0-9]{3,24}$/', $config['account'])) return '存储账户名称只能包含小写字母和数字，长度3-24位';
		$key = base64_decode($config['accountKey'], true);
		if($key === false || strlen($key) < 16) return '访问密钥格式不正确，请复制门户中显示的完整密钥';
		if(!preg_match('/^[a-z0-9](?!.*--)[a-z0-9-]{1,61}[a-z0-9]$/', $config['container'])) return '容器名称只能包含小写字母、数字和连字符，长度3-63位';
		if(($config['cloud'] ?? '') == 'custom' && !preg_match('/^https?:\/\/[^\/\s]+/i', $config['endpoint'])) return 'Blob 服务地址需要以http://或https://开头';
		return true;
	}

	public function __construct($config) {
		$this->config = $config;
		$this->account = trim((string)$config['account']);
		$this->key = base64_decode(trim((string)$config['accountKey']));
		$this->container = trim((string)$config['container']);
		$cloud = $config['cloud'] ?? 'global';
		if($cloud == 'custom'){
			$parts = parse_url(rtrim(trim((string)$config['endpoint']), '/'));
			$this->scheme = strtolower($parts['scheme'] ?? 'https');
			$this->host = ($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');
			$this->basePath = $parts['path'] ?? '';
		}else{
			$this->scheme = 'https';
			$this->host = $this->account.'.blob.'.($cloud == 'china' ? 'core.chinacloudapi.cn' : 'core.windows.net');
			$this->basePath = '';
		}
		$this->client = new Client(['timeout' => 600, 'connect_timeout' => 10, 'http_errors' => false, 'verify' => false]);
	}

	public function getClient(){
		return $this->client;
	}

	public function errmsg(){
		return $this->errmsg;
	}

	//blob 在容器中的名称
	private function blobName($name){
		return $this->filepath.$name;
	}

	//请求路径（已编码）
	private function blobPath($name){
		$path = implode('/', array_map('rawurlencode', explode('/', $this->blobName($name))));
		return $this->basePath.'/'.$this->container.'/'.$path;
	}

	private function hmac($data){
		return base64_encode(hash_hmac('sha256', $data, $this->key, true));
	}

	/**
	 * 发送共享密钥签名的请求
	 * @param array $query 查询参数
	 * @param array $headers 请求头，Content-Type、Content-Length 及 x-ms-* 参与签名
	 */
	private function request($method, $name, $query = [], $headers = [], $body = null, $stream = false){
		$path = $this->blobPath($name);
		$headers['x-ms-date'] = gmdate('D, d M Y H:i:s').' GMT';
		$headers['x-ms-version'] = self::VERSION;
		$lower = array_change_key_case($headers, CASE_LOWER);

		$msHeaders = [];
		foreach($lower as $k => $v){
			if(strpos($k, 'x-ms-') === 0) $msHeaders[$k] = trim((string)$v);
		}
		ksort($msHeaders, SORT_STRING);
		$canonicalHeaders = '';
		foreach($msHeaders as $k => $v){
			$canonicalHeaders .= $k.':'.$v."\n";
		}
		$canonicalResource = '/'.$this->account.$path;
		$params = array_change_key_case($query, CASE_LOWER);
		ksort($params, SORT_STRING);
		foreach($params as $k => $v){
			$canonicalResource .= "\n".$k.':'.$v;
		}
		$length = isset($lower['content-length']) && (string)$lower['content-length'] !== '0' ? (string)$lower['content-length'] : '';
		$stringToSign = implode("\n", [
			$method,
			$lower['content-encoding'] ?? '',
			$lower['content-language'] ?? '',
			$length,
			$lower['content-md5'] ?? '',
			$lower['content-type'] ?? '',
			'', //Date，已使用 x-ms-date
			$lower['if-modified-since'] ?? '',
			$lower['if-match'] ?? '',
			$lower['if-none-match'] ?? '',
			$lower['if-unmodified-since'] ?? '',
			$lower['range'] ?? '',
		])."\n".$canonicalHeaders.$canonicalResource;
		$headers['Authorization'] = 'SharedKey '.$this->account.':'.$this->hmac($stringToSign);

		$url = $this->scheme.'://'.$this->host.$path;
		if($query) $url .= '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
		$options = ['headers' => $headers, 'stream' => $stream];
		if($body !== null) $options['body'] = $body;
		return $this->client->request($method, $url, $options);
	}

	private function setError($func, $response){
		$status = $response->getStatusCode();
		$message = 'HTTP '.$status;
		$body = (string)$response->getBody();
		$code = $response->getHeaderLine('x-ms-error-code');
		if($body && preg_match('/<Code>(.*?)<\/Code>/s', $body, $m)) $code = $m[1];
		if($code) $message .= ' '.$code;
		if($body && preg_match('/<Message>(.*?)(?:\r?\n|<\/Message>)/s', $body, $m)) $message .= ': '.$m[1];
		elseif($code == 'AuthenticationFailed' || $status == 403) $message .= ' 访问密钥错误或没有访问权限';
		elseif($code == 'ContainerNotFound') $message .= ' 容器不存在';
		$this->errmsg = $func.': '.$message;
	}

	public function exists($name) {
		try {
			$response = $this->request('HEAD', $name);
			if($response->getStatusCode() == 200) return true;
			$this->setError(__FUNCTION__, $response);
			return false;
		} catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			return false;
		}
	}

	public function get($name) {
		try {
			$response = $this->request('GET', $name);
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
			$headers['x-ms-range'] = 'bytes='.$range[0].'-'.$range[1];
		}
		try {
			$response = $this->request('GET', $name, [], $headers, null, true);
			$status = $response->getStatusCode();
			if($status != 200 && $status != 206){
				$this->setError(__FUNCTION__, $response);
				return false;
			}
			$body = $response->getBody();
			while(!$body->eof()){
				echo $body->read(102400);
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
			$fp = fopen($tmpfile, 'rb');
			if(!$fp){
				$this->errmsg = __FUNCTION__ . ': 读取临时文件失败';
				return false;
			}
			$size = filesize($tmpfile);
			$content_type = $content_type ?: 'application/octet-stream';
			if($size <= static::SINGLE_MAX){
				$headers = ['Content-Length' => $size, 'Content-Type' => $content_type, 'x-ms-blob-type' => 'BlockBlob'];
				$response = $this->request('PUT', $name, [], $headers, $fp);
				if($response->getStatusCode() == 201) return true;
				$this->setError(__FUNCTION__, $response);
				return false;
			}
			return $this->uploadBlocks($name, $fp, $size, $content_type);
		} catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		} finally {
			if(is_resource($fp)) fclose($fp);
		}
	}

	//大文件分块上传，全部分块上传完成后提交分块列表
	private function uploadBlocks($name, $fp, $size, $content_type){
		$stream = Utils::streamFor($fp);
		$ids = [];
		for($offset = 0, $i = 0; $offset < $size; $offset += static::BLOCK_SIZE, $i++){
			$length = min(static::BLOCK_SIZE, $size - $offset);
			//同一个 blob 的分块ID长度必须相同
			$id = base64_encode(sprintf('block-%06d', $i));
			$body = new LimitStream($stream, $length, $offset);
			$response = $this->request('PUT', $name, ['comp' => 'block', 'blockid' => $id], ['Content-Length' => $length], $body);
			if($response->getStatusCode() != 201){
				$this->setError('upload_block', $response);
				return false;
			}
			$ids[] = $id;
		}
		$xml = '<?xml version="1.0" encoding="utf-8"?><BlockList>';
		foreach($ids as $id) $xml .= '<Latest>'.$id.'</Latest>';
		$xml .= '</BlockList>';
		$headers = ['Content-Length' => strlen($xml), 'Content-Type' => 'application/xml', 'x-ms-blob-content-type' => $content_type];
		$response = $this->request('PUT', $name, ['comp' => 'blocklist'], $headers, $xml);
		if($response->getStatusCode() == 201) return true;
		$this->setError('upload_blocklist', $response);
		return false;
	}

	public function savefile($name, $tmpfile, $content_type = null) {
		$result = $this->upload($name, $tmpfile, $content_type);
		if($result) @unlink($tmpfile);
		return $result;
	}

	public function getinfo($name) {
		try {
			$response = $this->request('HEAD', $name);
			if($response->getStatusCode() == 200){
				return ['length' => intval($response->getHeaderLine('Content-Length')), 'content_type' => $response->getHeaderLine('Content-Type')];
			}
			$this->setError(__FUNCTION__, $response);
			return false;
		} catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function delete($name) {
		try {
			$response = $this->request('DELETE', $name);
			$status = $response->getStatusCode();
			if($status == 202 || $status == 200 || $status == 404) return true;
			$this->setError(__FUNCTION__, $response);
			return false;
		} catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	/**
	 * 生成 blob 的服务 SAS 签名参数
	 * @param string $permissions 权限，r 读取，c 创建，w 写入
	 * @param int $expire 有效期（秒）
	 * @param array $response 覆盖响应头 rscd（Content-Disposition）、rsct（Content-Type）
	 */
	private function sas($name, $permissions, $expire, $response = []){
		$expiry = gmdate('Y-m-d\TH:i:s\Z', time() + $expire);
		$resource = '/blob/'.$this->account.'/'.$this->container.'/'.$this->blobName($name);
		$stringToSign = implode("\n", [
			$permissions,
			'', //signedStart
			$expiry,
			$resource,
			'', //signedIdentifier
			'', //signedIP
			'', //signedProtocol
			self::VERSION,
			'b', //signedResource
			'', //signedSnapshotTime
			'', //signedEncryptionScope
			'', //rscc
			$response['rscd'] ?? '',
			'', //rsce
			'', //rscl
			$response['rsct'] ?? '',
		]);
		$query = ['sv' => self::VERSION, 'se' => $expiry, 'sr' => 'b', 'sp' => $permissions];
		foreach(['rscd', 'rsct'] as $k){
			if(isset($response[$k]) && $response[$k] !== '') $query[$k] = $response[$k];
		}
		$query['sig'] = $this->hmac($stringToSign);
		return http_build_query($query, '', '&', PHP_QUERY_RFC3986);
	}

	/**
	 * 浏览器直传参数：使用 SAS 链接 PUT 上传，需要在存储账户配置 CORS
	 */
	public function getUploadParam($name, $filename, $max_file_size = 0){
		$ext = strtolower(pathinfo((string)$filename, PATHINFO_EXTENSION));
		$content_type = function_exists('minetype') ? minetype($ext) : 'application/octet-stream';
		$url = $this->scheme.'://'.$this->host.$this->blobPath($name).'?'.$this->sas($name, 'cw', 3600);
		return [
			'url' => $url,
			'method' => 'PUT',
			'headers' => ['x-ms-blob-type' => 'BlockBlob', 'Content-Type' => $content_type ?: 'application/octet-stream'],
			'post' => [],
		];
	}

	/**
	 * SAS 签名下载地址，有效期7天
	 */
	public function getDownUrl($name, $filename, $content_type = null){
		$conf = $this->config;
		$response = [];
		if($filename){
			//响应头只能是 ASCII，中文等字符通过 filename* 传递，filename 仅作为旧浏览器的备用名称
			$fallback = preg_replace('/[^\x20-\x7e]|["\\\\]/u', '_', $filename) ?: 'download';
			$disposition = '"'.$fallback.'"; filename*=utf-8\'\''.rawurlencode($filename);
			$response['rscd'] = ($content_type ? 'inline' : 'attachment').'; filename='.$disposition;
		}
		if($content_type){
			$response['rsct'] = $content_type;
		}
		if(!empty($conf['downfile_domain'])){
			$base = (($conf['downfile_protocol'] ?? '0') == 1 ? 'https' : 'http').'://'.$conf['downfile_domain'].'/'.$this->container.'/'.implode('/', array_map('rawurlencode', explode('/', $this->blobName($name))));
		}else{
			$base = $this->scheme.'://'.$this->host.$this->blobPath($name);
		}
		return $base.'?'.$this->sas($name, 'r', 604800, $response);
	}
}
