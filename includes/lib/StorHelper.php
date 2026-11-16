<?php

namespace lib;

/**
 * 存储管理
 * 存储类型：includes/lib/Storage 目录下实现了 IStorage 接口的类，新增存储类型只需添加对应的类文件
 * 存储配置：pre_storage 表，每个文件通过 pre_file.storage_id 关联所在的存储
 */
class StorHelper
{
    private static $types = null;
    private static $storages = [];
    private static $models = [];
    private static $defaultId = null;
    private static $errmsg = null;

    public static function errmsg(){
        return self::$errmsg;
    }

    /**
     * 获取所有存储类型
     * @param bool $onlyAvailable 只返回当前环境可用的
     */
    public static function getTypes($onlyAvailable = false){
        if(self::$types === null){
            self::$types = [];
            foreach(glob(__DIR__.'/Storage/*.php') as $file){
                $name = basename($file, '.php');
                $class = '\\lib\\Storage\\'.$name;
                if(!class_exists($class) || !in_array('lib\\IStorage', class_implements($class))) continue;
                $info = $class::info();
                if(!is_array($info)) continue;
                $type = strtolower($name);
                $fields = [];
                foreach($info['fields'] ?? [] as $field){
                    $fields[] = self::normalizeField($field);
                }
                self::$types[$type] = [
                    'type' => $type,
                    'class' => $class,
                    'name' => $info['name'] ?? $name,
                    'url' => $info['url'] ?? null,
                    //图标：i-lucide-xxx 为图标名，否则为相对网站根目录的图片地址
                    'icon' => $info['icon'] ?? 'i-lucide-cloud',
                    'cloud' => !empty($info['cloud']),
                    'range' => !empty($info['range']),
                    'available' => !isset($info['available']) || $info['available'],
                    //需要账号授权（OAuth）的存储，类中需实现 oauthUrl 和 oauthToken 静态方法
                    'oauth' => !empty($info['oauth']) ? array_merge(['steps' => []], is_array($info['oauth']) ? $info['oauth'] : []) : null,
                    'sort' => intval($info['sort'] ?? 99),
                    'fields' => $fields,
                ];
            }
            uasort(self::$types, function($a, $b){ return $a['sort'] - $b['sort']; });
        }
        if($onlyAvailable){
            return array_filter(self::$types, function($t){ return $t['available']; });
        }
        return self::$types;
    }

    public static function getType($type){
        $types = self::getTypes();
        return isset($types[$type]) ? $types[$type] : null;
    }

    private static function normalizeField($field){
        $field['type'] = $field['type'] ?? 'text';
        $field['label'] = $field['label'] ?? $field['key'];
        $field['required'] = !empty($field['required']);
        if(isset($field['options'])){
            foreach($field['options'] as &$option){
                $option['value'] = (string)$option['value'];
            }
            unset($option);
        }
        if(!isset($field['default'])){
            if($field['type'] == 'switch') $field['default'] = '0';
            elseif($field['type'] == 'select' && !empty($field['options'])) $field['default'] = $field['options'][0]['value'];
            else $field['default'] = '';
        }
        $field['default'] = (string)$field['default'];
        return $field;
    }

    /**
     * 云存储通用的上传下载方式参数
     * @param array $overrides 按key覆盖默认字段属性
     */
    public static function cloudFields($overrides = []){
        $fields = [
            ['key' => 'uploadfile_type', 'label' => '文件上传方式', 'type' => 'select', 'options' => [['label' => '网站中转', 'value' => '0'], ['label' => '直接链接', 'value' => '1']]],
            ['key' => 'downfile_type', 'label' => '文件下载方式', 'type' => 'select', 'options' => [['label' => '网站中转', 'value' => '0'], ['label' => '直接链接', 'value' => '1']]],
            ['key' => 'downfile_protocol', 'label' => '文件下载域名协议', 'type' => 'select', 'options' => [['label' => 'https://', 'value' => '1'], ['label' => 'http://', 'value' => '0']], 'show' => ['key' => 'downfile_type', 'value' => '1']],
            ['key' => 'downfile_domain', 'label' => '文件下载域名', 'placeholder' => '留空则使用云存储默认域名', 'help' => '填写Bucket绑定的域名，也可使用CDN域名', 'format' => 'domain', 'show' => ['key' => 'downfile_type', 'value' => '1']],
        ];
        foreach($fields as &$field){
            if(isset($overrides[$field['key']])) $field = array_merge($field, $overrides[$field['key']]);
        }
        unset($field);
        return $fields;
    }

    /**
     * 判断字段在当前参数下是否显示
     */
    public static function isFieldVisible($field, $values){
        if(empty($field['show'])) return true;
        $show = $field['show'];
        $value = isset($values[$show['key']]) ? (string)$values[$show['key']] : '';
        $expect = is_array($show['value']) ? array_map('strval', $show['value']) : [(string)$show['value']];
        return in_array($value, $expect, true);
    }

    /**
     * 按存储类型的参数定义过滤并校验提交的配置
     * @return array|string 成功返回配置数组，失败返回错误信息
     */
    public static function filterConfig($type, $input){
        $typeInfo = self::getType($type);
        if(!$typeInfo) return '存储类型不存在';
        if(!is_array($input)) $input = [];
        $config = [];
        foreach($typeInfo['fields'] as $field){
            $key = $field['key'];
            $value = isset($input[$key]) && is_scalar($input[$key]) ? trim((string)$input[$key]) : $field['default'];
            if($field['type'] == 'switch'){
                $value = $value == '1' ? '1' : '0';
            }elseif($field['type'] == 'select' && !empty($field['options'])){
                if(!in_array($value, array_column($field['options'], 'value'), true)) $value = $field['default'];
            }
            if(isset($field['format']) && $field['format'] == 'domain'){
                $value = rtrim(preg_replace('/^https?:\/\//i', '', str_replace([' ', '，'], ['', ','], $value)), '/');
            }
            $config[$key] = $value;
        }
        foreach($typeInfo['fields'] as $field){
            if($field['required'] && $config[$field['key']] === '' && self::isFieldVisible($field, $config)){
                return '请填写'.$field['label'];
            }
        }
        if(method_exists($typeInfo['class'], 'check')){
            $result = call_user_func([$typeInfo['class'], 'check'], $config);
            if($result !== true) return $result ? $result : '存储配置校验失败';
        }
        return $config;
    }

    /**
     * 按类型和配置创建存储实例
     */
    public static function getModel($type, $config = [], $id = 0)
    {
        $typeInfo = self::getType($type);
        if(!$typeInfo){
            self::$errmsg = '存储类型'.$type.'不存在';
            return false;
        }
        if(!$typeInfo['available']){
            self::$errmsg = $typeInfo['name'].'在当前环境不可用';
            return false;
        }
        try{
            $class = $typeInfo['class'];
            //第二个参数为存储ID，需要回写配置（如刷新令牌）的存储可通过 updateConfig 保存
            return new $class(is_array($config) ? $config : [], intval($id));
        }catch(\Throwable $e){
            self::$errmsg = '初始化存储失败：'.$e->getMessage();
            return false;
        }
    }

    private static function parseRow($row){
        $row['id'] = intval($row['id']);
        $row['status'] = intval($row['status']);
        $row['isdefault'] = intval($row['isdefault']);
        $config = $row['config'] ? json_decode($row['config'], true) : [];
        $row['config'] = is_array($config) ? $config : [];
        return $row;
    }

    /**
     * 获取存储配置
     */
    public static function getStorage($id){
        global $DB;
        $id = intval($id);
        if($id <= 0) return false;
        if(!array_key_exists($id, self::$storages)){
            $row = $DB->getRow("SELECT * FROM pre_storage WHERE id=:id LIMIT 1", [':id'=>$id]);
            self::$storages[$id] = $row ? self::parseRow($row) : false;
        }
        return self::$storages[$id];
    }

    /**
     * 获取默认存储
     */
    public static function getDefault(){
        global $DB;
        if(self::$defaultId === null){
            $id = $DB->getColumn("SELECT id FROM pre_storage WHERE isdefault=1 ORDER BY id ASC LIMIT 1");
            if(!$id) $id = $DB->getColumn("SELECT id FROM pre_storage WHERE status=1 ORDER BY sort ASC,id ASC LIMIT 1");
            self::$defaultId = $id ? intval($id) : 0;
        }
        if(!self::$defaultId){
            self::$errmsg = '未配置默认存储，请先在后台存储管理中添加';
            return false;
        }
        return self::getStorage(self::$defaultId);
    }

    /**
     * 获取所有已开启的存储（前台上传选择）
     */
    public static function getEnabledList(){
        global $DB;
        $list = [];
        $types = self::getTypes(true);
        $rows = $DB->getAll("SELECT id,name,type,isdefault FROM pre_storage WHERE status=1 ORDER BY sort ASC,id ASC");
        foreach($rows as $row){
            if(!isset($types[$row['type']])) continue;
            $list[] = ['id'=>intval($row['id']), 'name'=>$row['name'], 'isdefault'=>intval($row['isdefault'])];
        }
        return $list;
    }

    /**
     * 获取上传使用的存储
     * @param int $storage_id 用户选择的存储，开启“允许选择存储类型”时有效
     */
    public static function getUploadStorage($storage_id = 0){
        global $conf;
        $storage_id = intval($storage_id);
        if($conf['upload_choose_storage'] == 1 && $storage_id > 0){
            $storage = self::getStorage($storage_id);
            if(!$storage || $storage['status'] != 1 || !self::getType($storage['type'])){
                self::$errmsg = '所选存储不存在或未开启';
                return false;
            }
            return $storage;
        }
        return self::getDefault();
    }

    /**
     * 获取文件所在的存储配置（旧数据未关联存储时使用默认存储）
     */
    public static function getFileStorage($file){
        $storage_id = isset($file['storage_id']) ? intval($file['storage_id']) : 0;
        if($storage_id > 0){
            $storage = self::getStorage($storage_id);
            if(!$storage) self::$errmsg = '文件所在的存储不存在';
            return $storage;
        }
        return self::getDefault();
    }

    /**
     * 获取存储实例
     * @param array|int $storage 存储配置或存储ID
     */
    public static function getModelByStorage($storage){
        if(!is_array($storage)) $storage = self::getStorage($storage);
        if(!$storage) return false;
        $id = $storage['id'];
        if(!isset(self::$models[$id])){
            self::$models[$id] = self::getModel($storage['type'], $storage['config'], $id);
        }
        return self::$models[$id];
    }

    /**
     * 获取文件所在存储的实例
     */
    public static function getFileStor($file){
        $storage = self::getFileStorage($file);
        if(!$storage) return false;
        return self::getModelByStorage($storage);
    }

    //判断是否可以直接链接
    public static function is_cloud($storage){
        $typeInfo = $storage ? self::getType($storage['type']) : null;
        return $typeInfo ? $typeInfo['cloud'] : false;
    }

    //判断是否可以断点续传
    public static function is_range($storage){
        $typeInfo = $storage ? self::getType($storage['type']) : null;
        return $typeInfo ? $typeInfo['range'] : false;
    }

    //是否直接上传到云存储
    public static function is_direct_upload($storage){
        return self::is_cloud($storage) && ($storage['config']['uploadfile_type'] ?? '0') == '1';
    }

    //是否直接从云存储下载
    public static function is_direct_down($storage){
        return self::is_cloud($storage) && ($storage['config']['downfile_type'] ?? '0') == '1';
    }

    /**
     * 更新存储的部分配置（存储类内部使用，如保存刷新后的访问令牌）
     */
    public static function updateConfig($id, $values){
        global $DB;
        $id = intval($id);
        if($id <= 0 || !is_array($values)) return false;
        $row = $DB->getRow("SELECT config FROM pre_storage WHERE id=:id LIMIT 1", [':id'=>$id]);
        if(!$row) return false;
        $config = $row['config'] ? json_decode($row['config'], true) : [];
        if(!is_array($config)) $config = [];
        $config = array_merge($config, $values);
        $DB->exec("UPDATE pre_storage SET config=:config WHERE id=:id", [':config'=>json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ':id'=>$id]);
        if(!empty(self::$storages[$id])) self::$storages[$id]['config'] = $config;
        return true;
    }

    /**
     * 开始账号授权：生成授权页面地址，授权参数暂存在会话中
     * @param string $type 存储类型
     * @param array $input 表单中填写的参数（尚未保存）
     * @param string $redirect_uri 回调地址
     * @return array [url=>授权页面地址, state=>授权标识]，失败返回false
     */
    public static function oauthStart($type, $input, $redirect_uri){
        $typeInfo = self::getType($type);
        if(!$typeInfo || !$typeInfo['oauth'] || !method_exists($typeInfo['class'], 'oauthUrl')){
            self::$errmsg = '该存储类型不需要授权';
            return false;
        }
        if(!is_array($input)) $input = [];
        $config = [];
        foreach($typeInfo['fields'] as $field){
            $config[$field['key']] = isset($input[$field['key']]) && is_scalar($input[$field['key']]) ? trim((string)$input[$field['key']]) : $field['default'];
        }
        foreach($typeInfo['oauth']['require'] ?? [] as $key){
            if($config[$key] === ''){
                $label = $key;
                foreach($typeInfo['fields'] as $field){ if($field['key'] == $key) $label = $field['label']; }
                self::$errmsg = '请先填写'.$label;
                return false;
            }
        }
        $state = bin2hex(random_bytes(16));
        $_SESSION['storage_oauth'] = ['state' => $state, 'type' => $type, 'config' => $config, 'redirect_uri' => $redirect_uri, 'time' => time()];
        $url = call_user_func([$typeInfo['class'], 'oauthUrl'], $config, $redirect_uri, $state);
        return ['url' => $url, 'state' => $state];
    }

    /**
     * 完成账号授权：用授权码换取令牌
     * @return array 需要填入表单的参数，失败返回false
     */
    public static function oauthFinish($code, $state){
        $session = $_SESSION['storage_oauth'] ?? null;
        if(!$session || !$state || !hash_equals($session['state'], (string)$state)){
            self::$errmsg = '授权已失效，请重新点击授权登录';
            return false;
        }
        if(time() - $session['time'] > 1800){
            self::$errmsg = '授权已超时，请重新点击授权登录';
            return false;
        }
        if(!$code){
            self::$errmsg = '没有获取到授权码';
            return false;
        }
        $typeInfo = self::getType($session['type']);
        if(!$typeInfo) return false;
        $result = call_user_func([$typeInfo['class'], 'oauthToken'], $session['config'], $code, $session['redirect_uri']);
        if(!is_array($result)){
            self::$errmsg = $result ?: '获取令牌失败';
            return false;
        }
        unset($_SESSION['storage_oauth']);
        return $result;
    }

    //清除缓存（后台修改存储后调用）
    public static function clearCache(){
        self::$storages = [];
        self::$models = [];
        self::$defaultId = null;
    }
}
