<?php
namespace lib\Storage;
use \lib\IStorage;

class Sae implements IStorage { //SaeStorage
	private $Storage = null;
	private $errmsg;
	private $domain;
	private $path = 'file/';

	public function __construct($Storage) {
		$this->Storage = new \SaeStorage();
		$this->domain = $Storage;
		return true;
	}

	public function getClient(){
		return $this->Storage;
	}

	public function errmsg(){
		return $this->errmsg;
	}

	public function exists($name) {
		return $this->Storage->fileExists($this->domain, $this->path.$name);
	}

	public function get($name) {
		return $this->Storage->read($this->domain, $this->path.$name);
	}

	public function downfile($name, $start = 0, $end = 0) {
		echo $this->Storage->read($this->domain, $this->path.$name);
		return true;
	}

	public function upload($name, $tmpfile, $content_type = null) {
		return $this->Storage->upload($this->domain,$this->path.$name, $tmpfile);
	}

	public function savefile($name, $tmpfile, $content_type = null) {
		return $this->upload($name, $tmpfile);
	}

	public function getinfo($name) {
		$res = $this->Storage->getAttr($this->domain, $this->path.$name);
		$result = ['length'=>$res['length'], 'content_type'=>$res['content_type']];
		return $result;
	}
	
	public function delete($name) {
		return $this->Storage->delete($this->domain, $this->path.$name);
	}

	public function getUploadParam($name, $filename, $max_file_size = 0){
		return false;
	}

	public function getDownUrl($name, $filename, $content_type = null){
		return false;
	}
}