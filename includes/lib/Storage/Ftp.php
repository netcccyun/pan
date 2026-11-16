<?php
namespace lib\Storage;
use \lib\IStorage;

/**
 * FTP 存储，支持 FTP 和显式 FTPS（FTP over TLS）
 * 需要 PHP 的 ftp 扩展，文件经网站中转上传和下载
 */
class Ftp implements IStorage {
	private $config;
	private $conn = null;
	private $errmsg;
	private $dir;
	private $dirReady = false;

	public static function info() {
		return [
			'name' => 'FTP',
			'icon' => 'i-lucide-folder-up',
			'cloud' => false,
			'range' => true,
			'available' => extension_loaded('ftp'),
			'sort' => 31,
			'fields' => [
				['key' => 'host', 'label' => '服务器地址', 'placeholder' => '例如：ftp.example.com 或 IP 地址', 'required' => true],
				['key' => 'port', 'label' => '端口', 'default' => '21', 'required' => true],
				['key' => 'username', 'label' => '用户名', 'placeholder' => '留空则匿名登录'],
				['key' => 'password', 'label' => '密码'],
				['key' => 'path', 'label' => '存储目录', 'placeholder' => '例如：/pan', 'help' => '文件保存在该目录下的file文件夹中，留空为登录后的根目录'],
				['key' => 'ssl', 'label' => '使用FTPS加密', 'type' => 'switch', 'help' => '服务器支持 FTP over TLS（显式 FTPS）时可以开启'],
				['key' => 'passive', 'label' => '被动模式', 'type' => 'switch', 'default' => '1', 'help' => '一般需要开启，服务器在防火墙或NAT后面时必须开启'],
			],
		];
	}

	//保存配置时校验
	public static function check($config) {
		$port = intval($config['port']);
		if($port < 1 || $port > 65535 || !preg_match('/^\d+$/', $config['port'])) return '端口填写错误';
		if(preg_match('/^\w+:\/\//', $config['host'])) return '服务器地址不需要带 ftp:// 前缀';
		if(!empty($config['ssl']) && !function_exists('ftp_ssl_connect')) return '当前PHP环境不支持FTPS';
		return true;
	}

	public function __construct($config) {
		$this->config = $config;
		$path = trim(str_replace('\\', '/', (string)($config['path'] ?? '')), '/');
		$this->dir = ($path !== '' ? '/'.$path : '').'/file';
		//未填写存储目录时使用相对路径（登录后的目录）
		if($path === '') $this->dir = 'file';
	}

	public function __destruct() {
		if($this->conn){
			@ftp_close($this->conn);
			$this->conn = null;
		}
	}

	public function getClient(){
		return $this->conn;
	}

	public function errmsg(){
		return $this->errmsg;
	}

	//最近一次PHP警告中的服务器返回信息
	private static function lastError($default){
		$error = error_get_last();
		if($error && preg_match('/^ftp_\w+\(\): (.+)$/s', $error['message'], $m)) return trim($m[1]);
		return $default;
	}

	private function connect(){
		if($this->conn) return $this->conn;
		$host = trim((string)$this->config['host']);
		$port = intval($this->config['port']) ?: 21;
		error_clear_last();
		$conn = !empty($this->config['ssl']) ? @ftp_ssl_connect($host, $port, 15) : @ftp_connect($host, $port, 15);
		if(!$conn) throw new \Exception('连接FTP服务器失败');
		$username = (string)($this->config['username'] ?? '');
		if(!@ftp_login($conn, $username !== '' ? $username : 'anonymous', $username !== '' ? (string)($this->config['password'] ?? '') : 'anonymous@')){
			$message = self::lastError('');
			@ftp_close($conn);
			throw new \Exception('FTP登录失败'.($message ? '：'.$message : '，请检查用户名和密码'));
		}
		if(($this->config['passive'] ?? '1') == '1'){
			//服务器在NAT后面时返回的被动模式地址可能是内网IP，使用连接的服务器地址
			if(defined('FTP_USEPASVADDRESS')) @ftp_set_option($conn, FTP_USEPASVADDRESS, false);
			@ftp_pasv($conn, true);
		}
		return $this->conn = $conn;
	}

	private function remote($name){
		return $this->dir.'/'.$name;
	}

	//创建存储目录（逐级创建，已存在的忽略）
	private function ensureDir(){
		if($this->dirReady) return;
		$conn = $this->connect();
		$current = $this->dir[0] == '/' ? '' : null;
		foreach(explode('/', trim($this->dir, '/')) as $part){
			$current = $current === null ? $part : $current.'/'.$part;
			@ftp_mkdir($conn, $current);
		}
		$this->dirReady = true;
	}

	public function exists($name) {
		try {
			return @ftp_size($this->connect(), $this->remote($name)) >= 0;
		} catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			return false;
		}
	}

	public function get($name) {
		try {
			$conn = $this->connect();
			$fp = fopen('php://temp', 'w+b');
			error_clear_last();
			if(!@ftp_fget($conn, $fp, $this->remote($name), FTP_BINARY)){
				fclose($fp);
				$this->errmsg = __FUNCTION__ . ": " . self::lastError('读取文件失败');
				return false;
			}
			rewind($fp);
			$content = stream_get_contents($fp);
			fclose($fp);
			return $content;
		} catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function downfile($name, $range = false) {
		try {
			$conn = $this->connect();
			$start = $range ? intval($range[0]) : 0;
			$remain = $range ? intval($range[1]) - $start + 1 : -1;
			//边下载边输出：数据先写入临时流，每次取出后输出，达到需要的长度就停止
			$buffer = fopen('php://temp/maxmemory:4194304', 'w+b');
			error_clear_last();
			$ret = @ftp_nb_fget($conn, $buffer, $this->remote($name), FTP_BINARY, $start);
			while(true){
				if($ret == FTP_FAILED){
					fclose($buffer);
					$this->errmsg = __FUNCTION__ . ": " . self::lastError('读取文件失败');
					return false;
				}
				rewind($buffer);
				$data = stream_get_contents($buffer);
				ftruncate($buffer, 0);
				rewind($buffer);
				if($data !== ''){
					if($remain >= 0){
						$data = substr($data, 0, $remain);
						$remain -= strlen($data);
					}
					echo $data;
					flush();
				}
				if($remain == 0 || $ret != FTP_MOREDATA) break;
				$ret = @ftp_nb_continue($conn);
			}
			fclose($buffer);
			//提前结束的传输会使连接处于不可用状态，关闭后下次重新连接
			if($ret == FTP_MOREDATA){
				@ftp_close($this->conn);
				$this->conn = null;
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
			$conn = $this->connect();
			$fp = fopen($tmpfile, 'rb');
			if(!$fp) throw new \Exception('读取临时文件失败');
			error_clear_last();
			if(!@ftp_fput($conn, $this->remote($name), $fp, FTP_BINARY)){
				//目录不存在时创建后重试
				$this->ensureDir();
				rewind($fp);
				error_clear_last();
				if(!@ftp_fput($conn, $this->remote($name), $fp, FTP_BINARY)){
					$this->errmsg = __FUNCTION__ . ": " . self::lastError('上传文件失败');
					return false;
				}
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
			error_clear_last();
			$size = @ftp_size($this->connect(), $this->remote($name));
			if($size < 0){
				$this->errmsg = __FUNCTION__ . ": " . self::lastError('文件不存在');
				return false;
			}
			return ['length' => $size, 'content_type' => 'application/octet-stream'];
		} catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function delete($name) {
		try {
			$conn = $this->connect();
			error_clear_last();
			if(@ftp_delete($conn, $this->remote($name))) return true;
			//文件本来就不存在
			if(@ftp_size($conn, $this->remote($name)) < 0) return true;
			$this->errmsg = __FUNCTION__ . ": " . self::lastError('删除文件失败');
			return false;
		} catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function getUploadParam($name, $filename, $max_file_size = 0){
		$this->errmsg = 'FTP不支持直接上传';
		return false;
	}

	public function getDownUrl($name, $filename, $content_type = null){
		$this->errmsg = 'FTP不支持直接下载';
		return false;
	}
}
