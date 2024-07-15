<?php

class ZenithCookie
{
    //쿠키변수 설정
    public static function set_cookie($cookie_name, $value, $expire)
    {
        if(is_null($value)) return;
        setcookie(md5($cookie_name), base64_encode($value), time() + $expire, '/', '.event.hotblood.co.kr');
    }

    // 쿠키변수값 얻음
    public static function get_cookie($cookie_name)
    {
        $cookie = md5($cookie_name);
        if (array_key_exists($cookie, $_COOKIE))
            return base64_decode($_COOKIE[$cookie]);
        else
            return "";
    }
}
?>