<?php
namespace lib\Storage;
use \lib\IStorage;

class Local implements IStorage {
	private $path = null;
	private $errmsg;

	public static function info() {
		return [
			'name' => '本地存储',
			'icon' => 'i-lucide-hard-drive',
			'cloud' => false,
			'range' => true,
			'sort' => 1,
			'fields' => [
				['key' => 'filepath', 'label' => '本地存储路径', 'placeholder' => '默认存储在网站file目录', 'help' => '不填写则默认存储在网站file目录下，如需填写，只能填写以服务器根目录/开始的绝对路径。'],
			],
		];
	}

	//保存配置时校验
	public static function check($config) {
		if(!empty($config['filepath'])){
			if(!is_dir($config['filepath'])) return '本地存储路径不存在';
			if(!is_writable($config['filepath'])) return '本地存储路径没有写入权限';
		}
		return true;
	}

	public function __construct($config) {
		$filepath = is_array($config) ? ($config['filepath'] ?? '') : $config;
		if($filepath && is_dir($filepath)){
			$this->path = rtrim($filepath, '/\\').'/';
		}else{
			$this->path = ROOT.'file/';
			if(!is_dir($this->path)) mkdir($this->path);
		}
		return true;
	}

	public function getClient(){
		return null;
	}
	
	public function errmsg(){
		return $this->errmsg;
	}

	public function exists($name) {
		return file_exists($this->path.$name);
	}

	public function get($name) {
		return @file_get_contents($this->path.$name);
	}

	public function downfile($name, $range = false) {
		$start = $range[0];
		$end = $range[1];
		$read_buffer = 1024 * 200;
		$handle = fopen($this->path.$name, 'rb');
		if($start > 0){
			fseek($handle, $start, 0);
		}
		$cur = $start;
		while(!feof($handle) && $cur<=$end) {
			echo fread($handle, min($read_buffer, ($end - $cur) + 1));
			$cur += $read_buffer;
			flush();
		}
		fclose($handle);
		return true;
	}

	public function upload($name, $tmpfile, $content_type = null) {
		return move_uploaded_file($tmpfile, $this->path.$name);
	}

	public function savefile($name, $tmpfile, $content_type = null) {
		return rename($tmpfile, $this->path.$name);
	}

	public function getinfo($name) {
		$result['length'] = filesize($this->path.$name);
		if(function_exists("finfo_open")){
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$type = finfo_file($finfo, $this->path.$name);
			finfo_close($finfo);
			$result['content_type'] = $type;
		}else{
			$result['content_type'] = null;
		}
		return $result;
	}

	public function delete($name) {
		return unlink($this->path.$name);
	}

	public function getUploadParam($name, $filename, $max_file_size = 0){
		return false;
	}

	public function getDownUrl($name, $filename, $content_type = null){
		return false;
	}
}