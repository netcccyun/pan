<?php
namespace lib\Storage;
use \lib\IStorage;
use GuzzleHttp\Client;

/**
 * S3 协议存储，兼容 Amazon S3 以及 MinIO、Cloudflare R2 等 S3 兼容服务
 * 使用 AWS Signature Version 4 签名
 */
class S3 implements IStorage {
	private $config;
	private $bucket;
	private $region;
	private $scheme = 'https';
	private $endpoint;
	private $pathStyle;
	private $client;
	private $errmsg;
	private $filepath = 'file/';

	public static function info() {
		return [
			'name' => 'AWS S3',
			'icon' => 'assets/img/storage/aws.png',
			'url' => 'https://aws.amazon.com/s3/',
			'cloud' => true,
			'range' => true,
			'sort' => 20,
			'fields' => array_merge([
				['key' => 'endpoint', 'label' => 'Endpoint', 'placeholder' => '例如：s3.us-east-1.amazonaws.com', 'help' => '不包含存储桶名称，默认使用https，如需使用http请以http://开头。支持Amazon S3、MinIO、Cloudflare R2等S3兼容存储（R2文件上传方式只支持网站中转）', 'required' => true],
				['key' => 'region', 'label' => '区域（Region）', 'placeholder' => '例如：us-east-1', 'help' => 'Cloudflare R2 填写 auto，MinIO 一般填写 us-east-1', 'default' => 'us-east-1', 'required' => true],
				['key' => 'bucket', 'label' => '存储桶名称（Bucket）', 'required' => true],
				['key' => 'accessKey', 'label' => 'Access Key ID', 'required' => true],
				['key' => 'secretKey', 'label' => 'Secret Access Key', 'required' => true],
				['key' => 'pathstyle', 'label' => '路径风格访问', 'type' => 'switch', 'help' => '开启后使用 Endpoint/Bucket 的地址格式访问，MinIO 等自建服务一般需要开启'],
			], \lib\StorHelper::cloudFields([
				'downfile_domain' => ['placeholder' => '留空则使用Endpoint地址', 'help' => '填写绑定到存储桶的自定义域名，下载地址会使用该域名签名'],
			])),
		];
	}

	public function __construct($config) {
		$this->config = $config;
		$this->bucket = $config['bucket'];
		$this->region = !empty($config['region']) ? $config['region'] : 'us-east-1';
		$this->pathStyle = !empty($config['pathstyle']);
		$endpoint = trim((string)$config['endpoint']);
		if(preg_match('/^(https?):\/\//i', $endpoint, $match)){
			$this->scheme = strtolower($match[1]);
			$endpoint = substr($endpoint, strlen($match[0]));
		}
		$this->endpoint = rtrim($endpoint, '/');
		$this->client = new Client(['timeout' => 600, 'connect_timeout' => 10, 'http_errors' => false, 'verify' => false]);
	}

	public function getClient(){
		return $this->client;
	}

	public function errmsg(){
		return $this->errmsg;
	}

	/**
	 * 存储桶访问地址
	 * @return array [scheme, host, 存储桶路径前缀]
	 */
	private function bucketBase($domain = null){
		if($domain){
			return [$domain['scheme'], $domain['host'], ''];
		}
		if($this->pathStyle){
			return [$this->scheme, $this->endpoint, '/'.$this->bucket];
		}
		return [$this->scheme, $this->bucket.'.'.$this->endpoint, ''];
	}

	private static function encodePath($path){
		return implode('/', array_map('rawurlencode', explode('/', $path)));
	}

	private static function canonicalQuery($query){
		$params = [];
		foreach($query as $k => $v){
			$params[rawurlencode($k)] = rawurlencode((string)$v);
		}
		ksort($params, SORT_STRING);
		$result = [];
		foreach($params as $k => $v){
			$result[] = $k.'='.$v;
		}
		return implode('&', $result);
	}

	private function signingKey($date){
		$key = hash_hmac('sha256', $date, 'AWS4'.$this->config['secretKey'], true);
		$key = hash_hmac('sha256', $this->region, $key, true);
		$key = hash_hmac('sha256', 's3', $key, true);
		return hash_hmac('sha256', 'aws4_request', $key, true);
	}

	/**
	 * 计算 SigV4 签名
	 * @param array $headers 参与签名的请求头（小写名称 => 值）
	 * @return array [签名, SignedHeaders, Credential Scope]
	 */
	public function sign($method, $uri, $query, $headers, $payloadHash, $amzdate){
		$date = substr($amzdate, 0, 8);
		ksort($headers, SORT_STRING);
		$canonicalHeaders = '';
		foreach($headers as $k => $v){
			$canonicalHeaders .= strtolower($k).':'.trim((string)$v)."\n";
		}
		$signedHeaders = implode(';', array_keys($headers));
		$canonicalRequest = $method."\n".$uri."\n".self::canonicalQuery($query)."\n".$canonicalHeaders."\n".$signedHeaders."\n".$payloadHash;
		$scope = $date.'/'.$this->region.'/s3/aws4_request';
		$stringToSign = "AWS4-HMAC-SHA256\n".$amzdate."\n".$scope."\n".hash('sha256', $canonicalRequest);
		$signature = hash_hmac('sha256', $stringToSign, $this->signingKey($date));
		return [$signature, $signedHeaders, $scope];
	}

	/**
	 * 发送签名请求
	 */
	private function request($method, $name, $headers = [], $body = null, $stream = false){
		list($scheme, $host, $prefix) = $this->bucketBase();
		$uri = $prefix.'/'.self::encodePath($this->filepath.$name);
		$amzdate = gmdate('Ymd\THis\Z');
		$payloadHash = 'UNSIGNED-PAYLOAD';
		$signHeaders = ['host' => $host, 'x-amz-content-sha256' => $payloadHash, 'x-amz-date' => $amzdate];
		list($signature, $signedHeaders, $scope) = $this->sign($method, $uri, [], $signHeaders, $payloadHash, $amzdate);
		$headers['x-amz-content-sha256'] = $payloadHash;
		$headers['x-amz-date'] = $amzdate;
		$headers['Authorization'] = 'AWS4-HMAC-SHA256 Credential='.$this->config['accessKey'].'/'.$scope.', SignedHeaders='.$signedHeaders.', Signature='.$signature;
		$options = ['headers' => $headers, 'stream' => $stream];
		if($body !== null) $options['body'] = $body;
		return $this->client->request($method, $scheme.'://'.$host.$uri, $options);
	}

	private function setError($func, $response){
		$message = 'HTTP '.$response->getStatusCode();
		$body = (string)$response->getBody();
		if($body && preg_match('/<Code>(.*?)<\/Code>/s', $body, $code)){
			$message .= ' '.$code[1];
			if(preg_match('/<Message>(.*?)<\/Message>/s', $body, $msg)) $message .= ': '.$msg[1];
		}
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
			$headers['Range'] = 'bytes='.$range[0].'-'.$range[1];
		}
		try {
			$response = $this->request('GET', $name, $headers, null, true);
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
			$headers = ['Content-Length' => filesize($tmpfile), 'Content-Type' => $content_type ?: 'application/octet-stream'];
			$response = $this->request('PUT', $name, $headers, $fp);
			if($response->getStatusCode() == 200) return true;
			$this->setError(__FUNCTION__, $response);
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
			if($status == 204 || $status == 200) return true;
			$this->setError(__FUNCTION__, $response);
			return false;
		} catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	/**
	 * 浏览器表单直传参数（POST Policy）
	 */
	public function getUploadParam($name, $filename, $max_file_size = 0){
		list($scheme, $host, $prefix) = $this->bucketBase();
		$key = $this->filepath.$name;
		$amzdate = gmdate('Ymd\THis\Z');
		$date = substr($amzdate, 0, 8);
		$credential = $this->config['accessKey'].'/'.$date.'/'.$this->region.'/s3/aws4_request';
		$conditions = [];
		$conditions[] = ['bucket' => $this->bucket];
		$conditions[] = ['eq', '$key', $key];
		$conditions[] = ['success_action_status' => '200'];
		$conditions[] = ['x-amz-algorithm' => 'AWS4-HMAC-SHA256'];
		$conditions[] = ['x-amz-credential' => $credential];
		$conditions[] = ['x-amz-date' => $amzdate];
		if($max_file_size > 0){
			$conditions[] = ['content-length-range', 1, $max_file_size];
		}
		$policy = base64_encode(json_encode([
			'expiration' => gmdate('Y-m-d\TH:i:s.000\Z', time() + 3600),
			'conditions' => $conditions,
		], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
		$signature = hash_hmac('sha256', $policy, $this->signingKey($date));
		$post = [
			'key' => $key,
			'success_action_status' => '200',
			'x-amz-algorithm' => 'AWS4-HMAC-SHA256',
			'x-amz-credential' => $credential,
			'x-amz-date' => $amzdate,
			'policy' => $policy,
			'x-amz-signature' => $signature,
		];
		return ['url' => $scheme.'://'.$host.$prefix.'/', 'post' => $post];
	}

	/**
	 * 预签名下载地址，SigV4 最长有效期为7天
	 */
	public function getDownUrl($name, $filename, $content_type = null){
		$conf = $this->config;
		$domain = null;
		if(!empty($conf['downfile_domain'])){
			$domain = ['scheme' => ($conf['downfile_protocol'] ?? '0') == 1 ? 'https' : 'http', 'host' => $conf['downfile_domain']];
		}
		list($scheme, $host, $prefix) = $this->bucketBase($domain);
		$uri = $prefix.'/'.self::encodePath($this->filepath.$name);
		$amzdate = gmdate('Ymd\THis\Z');
		$date = substr($amzdate, 0, 8);
		$query = [
			'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
			'X-Amz-Credential' => $conf['accessKey'].'/'.$date.'/'.$this->region.'/s3/aws4_request',
			'X-Amz-Date' => $amzdate,
			'X-Amz-Expires' => '604800',
			'X-Amz-SignedHeaders' => 'host',
		];
		if($filename){
			$disposition = '"'.$filename.'"; filename*=utf-8\'\''.rawurlencode($filename);
			$query['response-content-disposition'] = ($content_type ? 'inline' : 'attachment').'; filename='.$disposition;
		}
		if($content_type){
			$query['response-content-type'] = $content_type;
		}
		list($signature) = $this->sign('GET', $uri, $query, ['host' => $host], 'UNSIGNED-PAYLOAD', $amzdate);
		$query['X-Amz-Signature'] = $signature;
		return $scheme.'://'.$host.$uri.'?'.self::canonicalQuery($query);
	}
}
