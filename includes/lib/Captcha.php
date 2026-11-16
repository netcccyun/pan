<?php
namespace lib;

/**
 * 图形验证码（GD 库 + FreeType，字体文件 assets/fonts/elephant.ttf）
 * 验证码保存在 session 中，校验一次后立即失效，有效期 5 分钟，不区分大小写
 */
class Captcha
{
    //随机因子，去掉了容易混淆的字符
    const CHARS = 'abcdefghkmnprstuvwxyzABCDEFGHKMNPRSTUVWXYZ23456789';
    const LENGTH = 4;
    const EXPIRE = 300;
    const WIDTH = 130;
    const HEIGHT = 50;
    const FONT_SIZE = 20;

    private static function font()
    {
        return ROOT.'assets/fonts/elephant.ttf';
    }

    /**
     * 是否可以生成验证码：需要 GD 库、FreeType 支持和字体文件
     */
    public static function available()
    {
        return extension_loaded('gd') && function_exists('imagettftext') && is_file(self::font());
    }

    /**
     * 生成验证码并保存到 session，返回 PNG 图片的 data URI
     */
    public static function create($name)
    {
        $code = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::CHARS[random_int(0, strlen(self::CHARS) - 1)];
        }
        $_SESSION['captcha_'.$name] = ['code' => strtolower($code), 'time' => time()];
        return 'data:image/png;base64,'.base64_encode(self::render($code));
    }

    /**
     * 校验验证码，无论对错都会作废，需要重新获取
     * @return string|true 校验失败时返回错误提示
     */
    public static function check($name, $input)
    {
        $key = 'captcha_'.$name;
        $data = isset($_SESSION[$key]) ? $_SESSION[$key] : null;
        unset($_SESSION[$key]);
        $input = strtolower(trim((string)$input));
        if ($input === '') return '请输入验证码';
        if (!$data || time() - $data['time'] > self::EXPIRE) return '验证码已过期，请重新输入';
        if (!hash_equals($data['code'], $input)) return '验证码错误，请重新输入';
        return true;
    }

    private static function render($code)
    {
        $w = self::WIDTH;
        $h = self::HEIGHT;

        //背景
        $img = imagecreatetruecolor($w, $h);
        $bg = imagecolorallocate($img, random_int(157, 255), random_int(157, 255), random_int(157, 255));
        imagefilledrectangle($img, 0, $h, $w, 0, $bg);

        //线条
        for ($i = 0; $i < 6; $i++) {
            $color = imagecolorallocate($img, random_int(0, 156), random_int(0, 156), random_int(0, 156));
            imageline($img, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), $color);
        }
        //雪花
        for ($i = 0; $i < 100; $i++) {
            $color = imagecolorallocate($img, random_int(200, 255), random_int(200, 255), random_int(200, 255));
            imagestring($img, random_int(1, 5), random_int(0, $w), random_int(0, $h), '*', $color);
        }

        //文字
        $font = self::font();
        $slot = $w / self::LENGTH;
        for ($i = 0; $i < self::LENGTH; $i++) {
            $color = imagecolorallocate($img, random_int(0, 156), random_int(0, 156), random_int(0, 156));
            imagettftext($img, self::FONT_SIZE, random_int(-30, 30), (int)($slot * $i + random_int(1, 5)), (int)($h / 1.4), $color, $font, $code[$i]);
        }

        ob_start();
        imagepng($img);
        imagedestroy($img);
        return ob_get_clean();
    }
}
