<?php
namespace lib\Storage;
use \lib\IStorage;

/**
 * SFTP 存储（基于 SSH 的文件传输），需要 PHP 的 ssh2 扩展
 * 支持密码和私钥登录，文件经网站中转上传和下载
 */
class Sftp implements IStorage {
	private $config;
	private $conn = null;
	private $sftp = null;
	private $errmsg;
	private $dir = null;
	private $dirReady = false;

	public static function info() {
		return [
			'name' => 'SFTP',
			'icon' => 'i-lucide-terminal',
			'cloud' => false,
			'range' => true,
			'available' => extension_loaded('ssh2'),
			'sort' => 32,
			'fields' => [
				['key' => 'host', 'label' => '服务器地址', 'placeholder' => '例如：sftp.example.com 或 IP 地址', 'required' => true],
				['key' => 'port', 'label' => '端口', 'default' => '22', 'required' => true],
				['key' => 'username', 'label' => '用户名', 'required' => true],
				['key' => 'authtype', 'label' => '登录方式', 'type' => 'select', 'options' => [['label' => '密码', 'value' => 'password'], ['label' => '私钥', 'value' => 'key']]],
				['key' => 'password', 'label' => '密码', 'show' => ['key' => 'authtype', 'value' => 'password']],
				['key' => 'private_key', 'label' => '私钥', 'type' => 'textarea', 'placeholder' => '-----BEGIN OPENSSH PRIVATE KEY-----', 'help' => '支持 OpenSSH 和 PEM 格式', 'required' => true, 'show' => ['key' => 'authtype', 'value' => 'key']],
				['key' => 'passphrase', 'label' => '私钥密码', 'placeholder' => '私钥没有设置密码则留空', 'show' => ['key' => 'authtype', 'value' => 'key']],
				['key' => 'path', 'label' => '存储目录', 'placeholder' => '例如：/home/www/pan', 'help' => '文件保存在该目录下的file文件夹中，留空为登录用户的主目录'],
			],
		];
	}

	//保存配置时校验
	public static function check($config) {
		$port = intval($config['port']);
		if($port < 1 || $port > 65535 || !preg_match('/^\d+$/', $config['port'])) return '端口填写错误';
		if(preg_match('/^\w+:\/\//', $config['host'])) return '服务器地址不需要带 sftp:// 前缀';
		if(($config['authtype'] ?? '') == 'key'){
			$key = trim($config['private_key']);
			if(strpos($key, 'PuTTY-User-Key-File') === 0) return '不支持 PuTTY 的 ppk 格式私钥，请用 PuTTYgen 导出为 OpenSSH 格式';
			if(!preg_match('/^-----BEGIN [A-Z0-9 ]*PRIVATE KEY-----.+-----END [A-Z0-9 ]*PRIVATE KEY-----$/s', $key)) return '私钥格式错误';
			//PEM 格式的私钥可以提前校验私钥密码，OpenSSH 格式在登录时校验
			if(strpos($key, 'OPENSSH PRIVATE KEY') === false && function_exists('openssl_pkey_get_private')){
				if(!@openssl_pkey_get_private($key, $config['passphrase'] !== '' ? $config['passphrase'] : null)) return '私钥格式错误或私钥密码不正确';
			}
			if(!function_exists('ssh2_auth_pubkey')) return '当前ssh2扩展版本过低，不支持私钥登录，请升级到1.3以上版本或使用密码登录';
		}
		return true;
	}

	public function __construct($config) {
		$this->config = $config;
	}

	public function __destruct() {
		if($this->conn && function_exists('ssh2_disconnect')) @ssh2_disconnect($this->conn);
		$this->sftp = null;
		$this->conn = null;
	}

	public function getClient(){
		return $this->sftp;
	}

	public function errmsg(){
		return $this->errmsg;
	}

	//最近一次PHP警告中的错误信息
	private static function lastError($default){
		$error = error_get_last();
		if($error && preg_match('/^(?:ssh2_\w+|fopen|file_get_contents)\(.*?\): (.+)$/s', $error['message'], $m)) return trim($m[1]);
		return $default;
	}

	private function connect(){
		if($this->sftp) return $this->sftp;
		$host = trim((string)$this->config['host']);
		$port = intval($this->config['port']) ?: 22;
		//连接超时时间
		$timeout = ini_get('default_socket_timeout');
		ini_set('default_socket_timeout', '15');
		error_clear_last();
		$conn = @ssh2_connect($host, $port);
		ini_set('default_socket_timeout', $timeout);
		if(!$conn) throw new \Exception('连接SFTP服务器失败：'.self::lastError('无法连接到'.$host.':'.$port));
		$username = (string)$this->config['username'];
		if(($this->config['authtype'] ?? '') == 'key'){
			if(!function_exists('ssh2_auth_pubkey')) throw new \Exception('当前ssh2扩展版本过低，不支持私钥登录');
			$passphrase = (string)($this->config['passphrase'] ?? '');
			//公钥留空，由 libssh2 从私钥中计算
			$logined = @ssh2_auth_pubkey($conn, $username, '', trim((string)$this->config['private_key'])."\n", $passphrase);
		}else{
			$logined = @ssh2_auth_password($conn, $username, (string)($this->config['password'] ?? ''));
		}
		if(!$logined){
			$error = error_get_last();
			if(function_exists('ssh2_disconnect')) @ssh2_disconnect($conn);
			//私钥无法解析（格式不支持或私钥密码错误）
			if($error && strpos($error['message'], 'Unable to extract public key') !== false) throw new \Exception('SFTP登录失败，私钥格式不支持或私钥密码不正确');
			throw new \Exception('SFTP登录失败，请检查用户名和'.(($this->config['authtype'] ?? '') == 'key' ? '私钥' : '密码'));
		}
		$sftp = @ssh2_sftp($conn);
		if(!$sftp) throw new \Exception('启动SFTP会话失败，服务器可能没有开启SFTP');
		$this->conn = $conn;
		$this->sftp = $sftp;
		//存储目录：相对路径以登录用户的主目录为基准
		$path = rtrim(str_replace('\\', '/', trim((string)($this->config['path'] ?? ''))), '/');
		if($path === '' || $path[0] != '/'){
			$home = @ssh2_sftp_realpath($sftp, '.');
			$path = rtrim($home ? $home : '', '/').($path !== '' ? '/'.$path : '');
		}
		$this->dir = $path.'/file';
		return $sftp;
	}

	private function remote($name){
		return $this->dir.'/'.$name;
	}

	//文件流地址
	private function url($name){
		return 'ssh2.sftp://'.intval($this->sftp).$this->remote($name);
	}

	public function exists($name) {
		try {
			$sftp = $this->connect();
			return @ssh2_sftp_stat($sftp, $this->remote($name)) !== false;
		} catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			return false;
		}
	}

	public function get($name) {
		try {
			$this->connect();
			error_clear_last();
			$content = @file_get_contents($this->url($name));
			if($content === false){
				$this->errmsg = __FUNCTION__ . ": " . self::lastError('读取文件失败');
				return false;
			}
			return $content;
		} catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function downfile($name, $range = false) {
		$fp = null;
		try {
			$this->connect();
			error_clear_last();
			$fp = @fopen($this->url($name), 'rb');
			if(!$fp){
				$this->errmsg = __FUNCTION__ . ": " . self::lastError('读取文件失败');
				return false;
			}
			$remain = -1;
			if($range){
				if(fseek($fp, intval($range[0])) !== 0){
					$this->errmsg = __FUNCTION__ . ": 定位文件失败";
					return false;
				}
				$remain = intval($range[1]) - intval($range[0]) + 1;
			}
			//边读取边输出，不把整个文件读入内存
			while(!feof($fp) && $remain != 0){
				$data = fread($fp, $remain > 0 ? min($remain, 1048576) : 1048576);
				if($data === false || $data === '') break;
				if($remain > 0) $remain -= strlen($data);
				echo $data;
				flush();
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

	public function upload($name, $tmpfile, $content_type = null) {
		$in = null;
		$out = null;
		try {
			$sftp = $this->connect();
			if(!$this->dirReady){
				if(@ssh2_sftp_stat($sftp, $this->dir) === false) @ssh2_sftp_mkdir($sftp, $this->dir, 0755, true);
				$this->dirReady = true;
			}
			$in = fopen($tmpfile, 'rb');
			if(!$in) throw new \Exception('读取临时文件失败');
			$out = @fopen($this->url($name), 'wb');
			if(!$out){
				$this->errmsg = __FUNCTION__ . ": 无法写入文件，请检查存储目录是否有写入权限";
				return false;
			}
			//每次写入较大的数据块，减少网络往返
			$written = 0;
			while(!feof($in)){
				$data = fread($in, 1048576);
				if($data === false) throw new \Exception('读取临时文件失败');
				if($data === '') break;
				for($offset = 0, $length = strlen($data); $offset < $length; $offset += $n){
					$n = @fwrite($out, $offset ? substr($data, $offset) : $data);
					if(!$n) throw new \Exception('写入文件失败，可能是存储空间不足');
				}
				$written += $length;
			}
			fclose($out);
			$out = null;
			if($written != filesize($tmpfile)) throw new \Exception('上传的文件不完整');
			return true;
		} catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		} finally {
			if(is_resource($in)) fclose($in);
			if(is_resource($out)) fclose($out);
		}
	}

	public function savefile($name, $tmpfile, $content_type = null) {
		$result = $this->upload($name, $tmpfile, $content_type);
		if($result) @unlink($tmpfile);
		return $result;
	}

	public function getinfo($name) {
		try {
			$sftp = $this->connect();
			error_clear_last();
			$stat = @ssh2_sftp_stat($sftp, $this->remote($name));
			if($stat === false){
				$this->errmsg = __FUNCTION__ . ": " . self::lastError('文件不存在');
				return false;
			}
			return ['length' => intval($stat['size'] ?? 0), 'content_type' => 'application/octet-stream'];
		} catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function delete($name) {
		try {
			$sftp = $this->connect();
			error_clear_last();
			if(@ssh2_sftp_unlink($sftp, $this->remote($name))) return true;
			//文件本来就不存在
			if(@ssh2_sftp_stat($sftp, $this->remote($name)) === false) return true;
			$this->errmsg = __FUNCTION__ . ": " . self::lastError('删除文件失败');
			return false;
		} catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function getUploadParam($name, $filename, $max_file_size = 0){
		$this->errmsg = 'SFTP不支持直接上传';
		return false;
	}

	public function getDownUrl($name, $filename, $content_type = null){
		$this->errmsg = 'SFTP不支持直接下载';
		return false;
	}
}
