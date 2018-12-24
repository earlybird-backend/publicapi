<?php
if ( ! function_exists('getIp'))
{

    function getIp($format = 'string', $side = 'client') {
        if ($side === 'client') {
            static $_client_ip = NULL;
            if ($_client_ip === NULL) {
                // 获取客户端IP地址
                $ci = &get_instance ();
                $_client_ip = $ci->input->ip_address ();
            }
            $ip = $_client_ip;
        } else {
            static $_server_ip = NULL;
            if ($_server_ip === NULL) {
                // 获取服务器IP地址
                if (isset ( $_SERVER )) {
                    if ($_SERVER ['SERVER_ADDR']) {
                        $_server_ip = $_SERVER ['SERVER_ADDR'];
                    } else {
                        $_server_ip = $_SERVER ['LOCAL_ADDR'];
                    }
                } else {
                    $_server_ip = getenv ( 'SERVER_ADDR' );
                }
            }
            $ip = $_server_ip;
        }

        return $format === 'string' ? $ip : bindec ( decbin ( ip2long ( $ip ) ) );
    }
}