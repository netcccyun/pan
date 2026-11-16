<?php

namespace lib;

interface IStorage
{
    /**
     * 存储类型信息，后台据此生成配置表单
     * [
     *   'name'      => 显示名称,
     *   'url'       => 开通地址（可选）,
     *   'icon'      => 图标（可选），i-lucide-xxx 图标名，或相对网站根目录的正方形图片地址,
     *   'cloud'     => 是否支持直接上传/下载链接,
     *   'range'     => 是否支持断点续传,
     *   'available' => 当前环境是否可用（可选，默认true）,
     *   'fields'    => 配置参数列表，每项：key,label,type(text|password|select|switch|textarea),options,placeholder,help,required,default,show
     * ]
     */
    static function info();

    function getClient();

    function errmsg();

    function exists($name);

    function get($name);

    function downfile($name, $range = false);

    function upload($name, $tmpfile, $content_type = null);

    function savefile($name, $tmpfile, $content_type = null);

    function getinfo($name);

    function delete($name);

    function getUploadParam($name, $filename, $max_file_size = 0);

    function getDownUrl($name, $filename, $content_type = null);
}
