<?php

class ZenithIP
{
    private $db;
    public function __construct()
    {
        $this->db = new ZenithDB();
    }
    public function chk_ip($remote_addr, $list=[]) 
    {
        $is_ip_chk = false;
        foreach($list as &$ip) {
            $ip = trim($ip);
            
            if(strpos($ip, "/") !== false) {
                if($this->_ip_match($ip, $remote_addr) == true) {
                    $is_ip_chk = true;
                    break;
                }
            }
        }

        if(in_array($remote_addr, $list) === false && $is_ip_chk === false) {
            return false;
        }
        return true;
    }

    protected function _ip_match($network, $remote_addr) 
    {
        $ip_arr = explode("/", $network);
        
        $network_long = ip2long($ip_arr[0]);
        
        $mask_long = pow(2,32)-pow(2,(32-$ip_arr[1]));  
        $ip_long = ip2long($remote_addr);
        if(($ip_long & $mask_long) == $network_long) {
            return true;
        }
        return false;
    }

    public function ipBlocker($remote_addr, $is_our) 
    {
        /*
        $mobile_ip = [
            '203.226.0.0/16'     // SKT 3G
            ,'211.234.0.0/16'    // SKT 3G
            ,'223.32.0.0/11'     // SKT 4G, 5G
            //,'2001:2d8::/32'     // SKT 4G, 5G IPv6
            ,'39.7.0.0/24'       // KT 3G, 4G, 5G
            ,'110.70.0.0/16'     // KT 3G, 4G, 5G
            ,'175.223.0.0/16'    // KT 3G, 4G
            ,'211.246.0.0/16'    // KT 3G
            ,'118.235.0.0/16'    // KT 4G, 5G
            ,'211.246.0.0/16'    // KT 4G
            //,'2001:e60::/32'     // KT 4G, 5G IPv6
            ,'61.43.0.0/16'      // LG 3G
            ,'211.234.0.0/16'    // LG 3G
            ,'106.102.0.0/16'    // LG 4G
            ,'117.111.0.0/16'    // LG 4G
            ,'211.36.0.0/16'     // LG 4G
            ,'106.101.0.0/16'    // LG 5G
            //,'2001:4430::/32'    // LG 5G IPv6
        ];
        */

        $result = $this->db->getIpBlockerByIp($remote_addr);
        $blocked = $result->db->num_rows;
        if ($blocked) {
            $block = $result->db->fetch_assoc();
            if (strtotime($block['term']) >= time()) {
                throw new Exception("부정접속으로 확인되어 1시간동안 접속이 차단됩니다.");
            } elseif ($block['forever'] == 1) {
                throw new Exception("지속적인 부정접속으로 확인되어 사이트 접속이 차단됩니다.");
            }
        }
        
        //session 으로 분당 접속 카운트
        if(isset($_SESSION['visit'])){
            if ($_SESSION['visit']['datetime'] && strtotime($_SESSION['visit']['datetime'] . ' +1 minute') >= time()) {
                $_SESSION['visit']['count']++;
            } else {
                $_SESSION['visit']['datetime'] = date('Y-m-d H:i:s');
                $_SESSION['visit']['count'] = 1;
            }
            //카운트 분당 30회 이상일 경우 블럭처리
            if ($_SESSION['visit']['count'] >= 30 && !$is_our) {
                $this->db->setIpBlocker($remote_addr);
                unset($_SESSION['visit']);
            }
        }
    }

    //IP정보 가져오기
    public static function getRemoteAddr()
    { 
        $ipaddress = '';
        if (getenv('HTTP_CLIENT_IP'))
            $ipaddress = getenv('HTTP_CLIENT_IP');
        else if (getenv('HTTP_X_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
        else if (getenv('HTTP_X_FORWARDED'))
            $ipaddress = getenv('HTTP_X_FORWARDED');
        else if (getenv('HTTP_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_FORWARDED_FOR');
        else if (getenv('HTTP_FORWARDED'))
            $ipaddress = getenv('HTTP_FORWARDED');
        else if (getenv('REMOTE_ADDR'))
            $ipaddress = getenv('REMOTE_ADDR');
        else
            $ipaddress = 'UNKNOWN';

        return $ipaddress;
    }
}
?>