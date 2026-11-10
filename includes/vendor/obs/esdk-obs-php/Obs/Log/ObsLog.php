<?php

/**
 * Copyright 2019 Huawei Technologies Co.,Ltd.
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not use
 * this file except in compliance with the License.  You may obtain a copy of the
 * License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software distributed
 * under the License is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR
 * CONDITIONS OF ANY KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations under the License.
 *
 */

namespace Obs\Log;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

class ObsLog extends Logger
{
    public static $log = null;
    protected $logPath = './';
    protected $logName = null;
    protected $logLevel = Logger::DEBUG;
    protected $logMaxFiles = 0;

    private $formatter = null;
    private $filepath = '';

    public static function initLog($logConfig = [])
    {
        $s3log = new ObsLog('');
        $s3log->setConfig($logConfig);
        $s3log->cheakDir();
        $s3log->setFilePath();
        $s3log->setFormat();
        $s3log->setHande();
    }

    private function setFormat()
    {
        $output = "[%datetime%][%level_name%]%message%\n";
        $this->formatter = new LineFormatter($output);
    }

    private function setHande()
    {
        static::$log = new Logger('obs_logger');
        $rotating = new RotatingFileHandler($this->filepath, $this->logMaxFiles, $this->logLevel);
        $rotating->setFormatter($this->formatter);
        static::$log->pushHandler($rotating);
    }

    private function setConfig($logConfig = [])
    {
        $arr = empty($logConfig) ? ObsConfig::LOG_FILE_CONFIG : $logConfig;
        $this->logPath = iconv('UTF-8', 'GBK', $arr['FilePath']);
        $this->logName = iconv('UTF-8', 'GBK', $arr['FileName']);
        $this->logMaxFiles = is_numeric($arr['MaxFiles']) ? 0 : intval($arr['MaxFiles']);
        $this->logLevel = $arr['Level'];
    }

    private function cheakDir()
    {
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }

    private function setFilePath()
    {
        $this->filepath = $this->logPath . '/' . $this->logName;
    }

    private static function writeLog($level, $msg)
    {
        switch ($level) {
            case DEBUG:
                static::$log->debug($msg);
                break;
            case INFO:
                static::$log->info($msg);
                break;
            case NOTICE:
                static::$log->notice($msg);
                break;
            case WARNING:
                static::$log->warning($msg);
                break;
            case ERROR:
                static::$log->error($msg);
                break;
            case CRITICAL:
                static::$log->critical($msg);
                break;
            case ALERT:
                static::$log->alert($msg);
                break;
            case EMERGENCY:
                static::$log->emergency($msg);
                break;
            default:
                break;
        }
    }

    public static function commonLog($level, $format, $args1 = null, $arg2 = null)
    {
        if (ObsLog::$log) {
            if ($args1 === null && $arg2 === null) {
                $msg = urldecode($format);
            } else {
                $msg = sprintf($format, $args1, $arg2);
            }
            $back = debug_backtrace();
            $line = $back[0]['line'];
            $filename = basename($back[0]['file']);
            $message = '[' . $filename . ':' . $line . ']: ' . $msg;
            ObsLog::writeLog($level, $message);
        }
    }
}
