<?php

/**
 *
 * 自动载入函数
 */
class Autoloader
{
    /**
     * 向PHP注册在自动载入函数
     */
    public static function register()
    {
        spl_autoload_register(array(new self, 'autoload'));
    }

    /**
     * 根据类名载入所在文件
     */
    public static function autoload($className)
    {

        // DIRECTORY_SEPARATOR：目录分隔符，linux上就是’/’    windows上是’\’
        $filePath = __DIR__ . DIRECTORY_SEPARATOR . $className;
        $filePath = str_replace('\\', DIRECTORY_SEPARATOR, $filePath) . '.php';
        if (file_exists($filePath)) {
            require_once $filePath;
            return true;
        } else {
            return false;
        }
    }
}