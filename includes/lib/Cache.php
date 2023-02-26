<?php
namespace lib;

class Cache {
	public function get($key) {
		global $_CACHE;
		return $_CACHE[$key];
	}
	public function read() {
		global $DB;
		$value = $DB->getColumn("SELECT v FROM pre_config WHERE k='cache' LIMIT 1");
		return $value;
	}
	public function save($value) {
		if (is_array($value)) $value = serialize($value);
		global $DB;
		return $DB->exec("REPLACE INTO pre_config VALUES ('cache', :value)", [':value'=>$value]);
	}
	public function pre_fetch(){
		global $_CACHE;
		$_CACHE=array();
		$cache = $this->read();
		$_CACHE = @unserialize($cache);
		if(empty($_CACHE['version']))$_CACHE = $this->update();
		return $_CACHE;
	}
	public function update() {
		global $DB;
		$cache = array();
		$result = $DB->getAll("SELECT * FROM pre_config");
		foreach($result as $row){
			if($row['k']=='cache') continue;
			$cache[ $row['k'] ] = $row['v'];
		}
		$this->save($cache);
		return $cache;
	}
	public function clear() {
		global $DB;
		return $DB->exec("UPDATE pre_config SET v='' WHERE k='cache'");
	}
}
