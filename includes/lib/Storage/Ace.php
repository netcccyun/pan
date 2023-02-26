<?php
namespace lib\Storage;
use \lib\IStorage;

class Ace implements IStorage { //AceStorage
	private $Storage = null;
	private $errmsg;

	public function __construct($Storage) {
		$this->Storage = \Alibaba::Storage($Storage);
		return true;
	}
	
	public function getClient(){
		return $this->Storage;
	}

	public function errmsg(){
		return $this->errmsg;
	}

	public function exists($name) {
		return $this->Storage->fileExists($name);
	}

	public function get($name) {
		return $this->Storage->get($name);
	}

	public function downfile($name, $start = 0, $end = 0) {
		echo $this->Storage->get($name);
		return true;
	}

	public function upload($name,$tmpfile) {
		return $this->Storage->saveFile($name, $tmpfile);
	}

	public function savefile($name,$tmpfile) {
		return $this->upload($name,$tmpfile);
	}

	public function getinfo($name) {
		$res = $this->Storage->getMeta($name);
		$result = ['length'=>$res['content-length'], 'content_type'=>$res['content-type']];
		return $result;
	}

	public function delete($name) {
		return $this->Storage->delete($name);
	}

	public function getUploadParam($name, $filename, $max_file_size = 0){
		return false;
	}

	public function getDownUrl($name, $filename, $content_type = null){
		return false;
	}
}
