<?php
namespace lib\Storage;
use \lib\IStorage;

class Local implements IStorage {
	private $path = null;
	private $errmsg;

	public function __construct($filepath) {
		if($filepath && is_dir($filepath)){
			$this->path = $filepath;
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
		return file_get_contents($this->path.$name);
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

	public function upload($name,$tmpfile) {
		return move_uploaded_file($tmpfile,$this->path.$name);
	}

	public function savefile($name,$tmpfile) {
		return rename($tmpfile,$this->path.$name);
	}

	public function getinfo($name) {
		$result['length'] = filesize($this->path.$name);
		if(function_exists("finfo_open")){
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$type = finfo_file($finfo, $this->path.$name);
			finfo_close($finfo);
			$result['content-length'] = $type;
		}else{
			$result['content-length'] = null;
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