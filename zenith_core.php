<?php
/*
* @brief hotevent 를 대체하기 위해 새롭게 개발된 신규 랜딩
* @author Jaybe
* @see 
* 	event_information - 랜딩 테이블
* 	event_advertiser - 광고주 테이블
* 	event_media - 매체 테이블
* 	랜딩테이블 기준으로 광고주, 매체 테이블이 인덱싱 구조로 구성되어있음
* 	URL Structure : https://event.hotblood.co.kr/랜딩번호/메소드
*/
include __DIR__ . "/zenith_db.php";
include __DIR__ . "/zenith_ip.php";
include __DIR__ . "/zenith_encryption.php";
include __DIR__ . "/zenith_cookie.php";
include __DIR__ . "/zenith_check_proc.php";

class HotbloodEventZenith
{ //Event 클래스
    private $rwdb, $rodb, $db;
    private $ip;
    private $paths, $real_paths, $landing;
    private $remote_addr, $visitor;
    private $our_ip = ['59.9.155.0/24', '127.0.0.1']; //사무실IP, 로컬호스트
    private $is_our = false;
    private $comments = [];
    private $counts = 0;
    private $check;
    private $encryption;
    public $no, $hash_no, $app_name, $method = 'view', $params;

    public function __construct($paths = null)
    {
        $this->real_paths = $paths;
        if(is_null($paths)) {
            $this->db = new ZenithDB();
            $this->rwdb = $this->db->getRWConnection();
            $this->rodb = $this->db->getROConnection();
            return;
        }
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '.' . $_SERVER['HTTP_HOST'],
            'secure' => true,
            'httponly' => true,
            'samesite' => 'None'
        ]);
        session_start();
        header('P3P: CP="NOI CURa ADMa DEVa TAIa OUR DELa BUS IND PHY ONL UNI COM NAV INT DEM PRE"');
        header('P3P: CP="ALL CURa ADMa DEVa TAIa OUR BUS IND PHY ONL UNI PUR FIN COM NAV INT DEM CNT STA POL HEA PRE LOC OTC"');
        header('P3P: CP="ALL ADM DEV PSAi COM OUR OTRo STP IND ONL"');
        header('P3P: CP="CAO PSA OUR"');
        set_exception_handler(array($this, 'exception_handler'));
        
        if (__DEV__) { //개발모드 일 경우 error_reporting 작동
            error_reporting(E_ALL);// & ~E_NOTICE
            define("EVENT_URL", "");
        } else {
            error_reporting(0);
            define("EVENT_URL", "//{$_SERVER['HTTP_HOST']}");
        }
        ini_set('display_errors', 'On');
        define("ROOT_PATH", ".");
        
        $this->hash_no = $paths[1];
        if(preg_match('/[A-Z]{2}[0-9]+/', strtoupper($paths[1]))) {
            if($this->chkHash($paths[1])) {
                $this->hash_no = $paths[1];
                $paths[1] = substr($paths[1], 2);
            }
        } else {
            $this->hash_no = $this->makeHash($paths[1]);
        }
        $this->paths = $paths;
        $this->no = $paths[1] ?? ''; //랜딩번호
        
        if (isset($paths[2])){
            $this->method = $paths[2]; //method 저장
        }

        if(isset($paths[1])){
            if(!preg_match('/^([A-Z]{2})?[0-9]+$/', strtoupper($paths[1])))
                $this->method = $paths[1];
        }
        
        if (!$this->no){ //랜딩번호가 없으면 exception 처리
            throw new Exception("잘못된 접근입니다.");
        }
        
        //DB연결
        $this->db = new ZenithDB();
        $this->rwdb = $this->db->getRWConnection();
        $this->rodb = $this->db->getROConnection();

        $this->ip = new ZenithIP();
        $this->remote_addr =  $this->ip->getRemoteAddr(); //REMOTE_ADDR 세팅
        
        if (!isset($_SESSION['browser'])){ //Browser 정보 세션으로 저장
            $_SESSION['browser'] = get_browser();
        }

        if (preg_match('/[0-9]+/', $this->no)) {
            $result = $this->db->getLandingBySeq($this->no); //랜딩 정보 호출
            if (!$result->db->num_rows) {
                throw new Exception("존재하지 않는 랜딩입니다."); //랜딩번호가 존재하지 않을경우 Exception 처리
            }
            $this->landing = $result->db->fetch_assoc();
            $_SESSION['no'] = $this->no;
            if (isset($_GET['site'])) $_SESSION['site'] = $_GET['site'];
            if (isset($_GET['code'])) $_SESSION['code'] = $_GET['code'];
        }

        if(!$this->landing['no_hash'] && $this->real_paths[1] == $this->no && $this->method == 'view') //일반 주소도 같이 사용에 체크가 되어있는지 확인
            throw new Exception("접근할 수 없는 이벤트입니다.");
        
        //메가더포르테 도메인 전용
        if (preg_match('/^megatheforte\.co\.kr/', $_SERVER['HTTP_HOST']) && $this->landing['name'] != '메가더포르테') {
            throw new Exception("존재하지 않는 이벤트입니다.");
        }

        //미담치과의원 도메인 전용
        //개발요청번호:22481 / 20220824 김하정 요청
        //        if (preg_match('/^careevt\.co\.kr/', $_SERVER['HTTP_HOST']) && $this->landing['name'] != '미담치과의원') {
        //            throw new Exception("존재하지 않는 이벤트입니다.");
        //        }

        //스탠다드치과의원 도메인 전용
        if (preg_match('/^heybt\.co\.kr/', $_SERVER['HTTP_HOST']) && $this->landing['name'] != '스탠다드치과의원') {
            throw new Exception("존재하지 않는 이벤트입니다.");
        }

        //서울권치과의원 도메인 전용
        if (preg_match('/^cutbut\.co\.kr/', $_SERVER['HTTP_HOST']) && $this->landing['name'] != '서울권치과의원') {
            throw new Exception("존재하지 않는 이벤트입니다.");
        }

        //그라클레스 도메인 전용
        if (preg_match('/^gragracules\.co\.kr/', $_SERVER['HTTP_HOST']) && $this->landing['name'] != '그라클레스') {
            throw new Exception("존재하지 않는 이벤트입니다.");
        }
        if (preg_match('/^smrstoremall\.shop/', $_SERVER['HTTP_HOST']) && $this->landing['name'] != '그라클레스') {
            throw new Exception("존재하지 않는 이벤트입니다.");
        }
        if (preg_match('/^smrstoremall\.co\.kr/', $_SERVER['HTTP_HOST']) && $this->landing['name'] != '그라클레스') {
            throw new Exception("존재하지 않는 이벤트입니다.");
        }

        //닥터크리미의원 도메인 전용 - 찬영님 요청 > 220705/하정님 요청으로 해제
        // if (preg_match('/^vviibbee\.co\.kr/', $_SERVER['HTTP_HOST']) && $this->landing['name'] != '닥터크리미의원') {
        //     throw new Exception("존재하지 않는 이벤트입니다.");
        // }
        
        $this->is_our = $this->ip->chk_ip($this->remote_addr, $this->our_ip); //내부 사용자인지 체크
        $this->landing['is_our'] = $this->is_our;
        
        $this->visitor = ZenithCookie::get_cookie(md5('chainsaw'));
        $this->encryption = new ZenithEncryption();
        if (!$this->visitor) {
            $this->visitor = $this->encryption->encrypt($this->remote_addr . '/' . microtime(true) . '/' . $this->no); //사용자 고유코드 생성
            ZenithCookie::set_cookie(md5('chainsaw'), $this->visitor, 2592000); //30일 Cookie Set
        }

        if (isset($this->landing['lead']) && $this->landing['lead'] != 3){
            //외부연동일 때 외부에서 대량으로 POST를 보내야할 수도 있어서 IP Blocker를 실행하지 않음
            $this->ip->ipBlocker($this->remote_addr, $this->our_ip);
        }
    }

    //잘못된 메소드 호출은 예외처리
    public function __call($method, $params)
    { 
        throw new Exception("잘못된 호출입니다.");
    }

    public function copy()
    {
        $old_seq = $this->paths[2] ?? '';
        $new_seq = $this->paths[3] ?? '';
        $res = $this->rwdb->query("SELECT MAX(seq) AS seq FROM event_advertiser LIMIT 1");
        $row = $res->fetch_assoc();
        if ($row['seq'] != $new_seq) {
            exit('The event does not exist in database.');
        }
        if ($old_seq && $new_seq) {
            $res = $this->db->getAdvertiserBySeq($old_seq);
            $row = $res->db->fetch_assoc();
            copy(__DIR__ . "/data/{$row['name']}/v_{$old_seq}.php", __DIR__ . "/data/{$row['name']}/v_{$new_seq}.php");
        }
    }

    //랜딩 페이지 호출
    public function view()
    { 
        ob_start();
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET');
        ob_end_flush();
        $appendHtml = [];
        $_GET['site'] = $_GET['site'] ?? '';
        $_GET['event_id'] = $_GET['event_id'] ?? '';
        $_GET['funnel'] = $_GET['funnel'] ?? '';
        if ($this->landing['is_stop'] || $this->landing['adv_stop']) {
            $txt = '';
            if ($this->landing['is_stop']){
                $txt = '차단 중인 이벤트입니다.';
            }
            if ($this->landing['adv_stop']){
                $txt = '사용 중지된 광고주입니다.';
            }
            if ($this->is_our){
                $appendHtml['header'] = '<div class="block_msg animate__animated animate__flash animate__slow animate__repeat-2 animate__delay-1s" onclick="$(this).addClass(\'hide\');">' . $txt . '</div>';
            }else{
                throw new Exception("사용할 수 없는 이벤트입니다."); //사용중지 상태일 경우 exception 처리
            }
        }
        
        $file = "./data/{$this->landing['name']}/v_{$this->no}.php";
        if (!file_exists($file)){
            throw new Exception("이벤트 파일이 존재하지 않습니다."); //랜딩파일이 없을 경우 exception 처리
        }
        if (isset($_SESSION['media'])){
            $this->landing['media'] = $_SESSION['media'];
        }
        unset($_SESSION['params']);
        if(count($_GET) > 0){
            //랜딩 접근시 parameter가 있을 시 추후 활용을 위해 세션에 저장
            $nonEmptyParams = [];
            foreach ($_GET as $key => $value) {
                if ($value !== '') {
                    $nonEmptyParams[$key] = $value;
                }
            }
            if (!empty($nonEmptyParams)) {
                $_SESSION['params'] = $nonEmptyParams;
            }
        }
        // event_leads 기반 댓글
        $result = $this->db->getEventLeadByEventSeq($this->no);
        if ($result->db->num_rows) {
            while ($row = $result->db->fetch_assoc()) {
                $row['name'] = mb_substr($row['name'], 0, 1, "UTF-8") . "**";
                $row['reg_date'] = date('m-d H:i', strtotime($row['reg_date']));
                //$row['phone'] = $this->encryption->decrypt($row['phone']);
                
                $row['phone'] = '010-****-**' . substr($row['phone'], -2); //이름 마스크 처리 
                
                $this->comments[] = $row;
            }
        }
        
        // event_leads 기반 db 갯수
        $result = $this->db->getEventLeadCountByEventSeq($this->no);
        if ($result->db->num_rows) {
            while ($row = $result->db->fetch_assoc()) {
                $this->counts = $row['count'];
            }
        }
        
        $this->makePage($file, NULL, $appendHtml);
        $_SESSION['REQUEST_URI'] = $_SERVER['REQUEST_URI'];
        if ($this->landing['pixel_id']) {
            $this->FBPixel();
        }
    }

    public function act()
    {
        $file = "./data/{$this->landing['name']}/v_{$this->no}_act.php";
        if (!file_exists($file)){
            die(json_encode(['result' => false, 'msg' => '파일이 존재하지 않습니다.']));
        }

        include $file;
    }

    private function FBPixel($event = 'ViewContent', $param = null)
    {
        if (!$this->landing['pixel_id'] || !$this->landing['access_token']){
            return false;
        }
        $url = "https://graph.facebook.com/v10.0/{$this->landing['pixel_id']}/events?access_token={$this->landing['access_token']}";
        $data = [
            'event_name' => $event, 
            'event_id' => time() . $this->visitor, 
            'event_time' => time(), 
            'event_source_url' => "{$_SERVER['REQUEST_SCHEME']}://{$_SERVER['HTTP_HOST']}" . urlencode($_SERVER['REQUEST_URI']), 
            'action_source' => "website", 
            'user_data' => [
                'client_ip_address' => $this->remote_addr, 
                'client_user_agent' => $_SERVER['HTTP_USER_AGENT']
            ]
        ];
        if ($event == 'CompleteRegistration' && isset($param)) {
            $data['user_data']['fn'] = hash("sha256", $param['name']);
            $data['user_data']['ph'] = hash("sha256", $param['phone']);
            $data['custom_data']['currency'] = "KRW";
        }

        $data = "data=[" . json_encode($data) . "]";
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data
        ));
        $result = curl_exec($ch);
        $info = curl_getinfo($ch);
        $result = json_decode($result, true);
        // echo json_encode($data);
        // echo '<pre>'.print_r($headers,1).'</pre>';
        // echo '<pre>'.print_r($data,1).'</pre>';
        // echo '<pre>'.print_r($info,1).'</pre>';
        // echo '<pre>'.print_r($result,1).'</pre>';
        curl_close($ch);
        if (isset($result['events_received'])) {
            echo "<!-- {$this->landing['pixel_name']}({$this->landing['pixel_id']}) / {$event} -->";
            return true;
        } else
            echo "<!-- ".json_encode($result)." -->";
            return false;
    }

    public function send()
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: *");
        $this->writeLog();
        $_POST = json_decode(file_get_contents('php://input'), true);
        
        if(isset($_POST['no'])){ //no 변수값이 넘어오면 랜딩번호를 교체
            if(preg_match('/[0-9]+/', $_POST['no'])){
                $result = $this->db->getLandingBySeq($_POST['no']);
                if (!$result->db->num_rows) {
                    throw new Exception("존재하지 않는 랜딩입니다."); //랜딩번호가 존재하지 않을경우 Exception 처리
                }
                $this->landing = $result->db->fetch_assoc();
            }
        }
        if (!isset($_POST['partner_name']) || !$_POST['partner_name']){
            exit(json_encode(['result' => false, 'msg' => '전송 업체명은 필수값입니다.'], JSON_UNESCAPED_UNICODE));
        }
        if (!$this->landing['seq']){
            exit(json_encode(['result' => false, 'msg' => '존재하지 않는 이벤트입니다.'], JSON_UNESCAPED_UNICODE));
        }
        if ($this->landing['lead'] != 3){
            exit(json_encode(['result' => false, 'msg' => '외부에서 전송 할 수 없는 이벤트입니다.'], JSON_UNESCAPED_UNICODE));
        }
        if ($this->landing['is_stop']){
            exit(json_encode(['result' => false, 'msg' => '사용할 수 없는 이벤트입니다.'], JSON_UNESCAPED_UNICODE));
        }
        if ($this->landing['adv_stop']){
            exit(json_encode(['result' => false, 'msg' => '사용할 수 없는 이벤트입니다.'], JSON_UNESCAPED_UNICODE));
        }
        if (!is_array($_POST)){
            exit(json_encode(['result' => false, 'msg' => 'JSON 형태로 전송해주십시오. ex) {"name":"홍길동","phone":"01012345678"...}'], JSON_UNESCAPED_UNICODE));
        }
        if (!isset($_POST['remote_addr']) || !$_POST['remote_addr']){
            exit(json_encode(['result' => false, 'msg' => '사용자IP는 필수값입니다.'], JSON_UNESCAPED_UNICODE));
        }

        $this->remote_addr = $_POST['remote_addr'];
        $check = new CheckProc($this->no, $_POST, $this->landing, $this->remote_addr, $this->is_our);
        $result = $check->check_proc();
        // $result = array_merge($result, $_SERVER);
        
        echo json_encode($result);
        exit;
    }

    //데이터 저장
    public function proc()
    { 
        $is_ajax = $_POST['ajax']; //ajax 형태 인지
        $check = new CheckProc($this->no, $_POST, $this->landing, $this->remote_addr, $this->is_our);
        $result = $check->check_proc();
        
        if ($is_ajax) {
            echo json_encode($result);
            exit;
        } else {
            if ($result['result'] === true) {
                session_write_close();
                header("Location: " . ROOT_PATH . "/{$this->hash_no}/thanks/{$result['data']}");
                exit; //저장이 정상적으로 처리 됐을 경우 result 페이지로 이동
            } else {
                if ($result['data'] == 'thanks') {
                    session_write_close();
                    header("Location: " . ROOT_PATH . "/{$this->hash_no}/thanks");
                    exit;
                } else if ($result['msg']) {
                    $this->alert($result['msg']);
                }
            }
        }
    }

    //ajax 처리용
    public function ajaxProc()
    { 
        $mode = $_POST['mode'] ?? '';
        if ($mode == "getComment") { //코멘트 가져오기
            $limit = $_POST['limit'] ? $_POST['limit'] : 10;
            $query = "";
            $data = ['result' => false, 'more' => false, 'data' => null];
            if ($_POST['lastmsg']) {
                $lastmsg = $_POST['lastmsg'];
                $query = " AND seq < '$lastmsg'";
            }

            $sql = "SELECT seq, name, phone, age, add1, reg_date FROM event_leads WHERE event_seq = '{$this->no}' AND is_deleted = 0";
            $cnt_sql = $this->db->query($sql);
            $total = $cnt_sql->db->num_rows;

            $sql .= "{$query} ORDER BY seq DESC LIMIT $limit"; //seq 작은 값 20개 출력 
            $result = $this->db->query($sql);
            if ($result->db->num_rows) {
                $data['result'] = true;
                while ($row = $result->db->fetch_assoc()) {
                    $row['phone'] = $this->encryption->decrypt($row['phone']);
                    $row['seq'] = $row['seq'];
                    $row['name'] = mb_substr($row['name'], '0', 1) . "**"; //이름 마스크 처리 
                    $row['phone'] = '010-****-**' . substr($row['phone'], -2); //전화번호 마스크 처리 
                    $row['age'] = substr($row['age'], 0, 1) . '0대'; //전화번호 마스크 처리 
                    $row['reg_date'] = date('m-d H:i', strtotime($row['reg_date']));
                    $row['msg'] = '신청했습니다~!';
                    $data['data'][] = $row;
                }
                if ($result->db->num_rows == $limit){
                    $data['more'] = true;
                }
            }
            echo json_encode($data);
            exit;
        }
    }

    //완료페이지
    public function thanks()
    { 
        $seq = $this->encryption->decrypt($this->paths[3]);
        $result = $this->db->getEventLeadBySeq($seq);
        $user = [];
        if ($result->db->num_rows) {
            $user = $result->db->fetch_assoc();
            $user['dec_phone'] = $this->encryption->decrypt($user['phone']);
            $user['international_phone'] = str_replace('010', '+8210', $user['dec_phone']);
        }

        $file = "./data/{$this->landing['name']}/v_{$this->no}_thanks.php";
        if (!file_exists($file)){
            $file = "./inc/thanks.php";
        }

        $this->makePage($file, $user);
        if ($this->landing['pixel_id']) {
            $this->FBPixel('CompleteRegistration', $user);
        }
    }

    public function result()
    {
        $path = '../' . strtoupper($this->hash_no);
        if ($_SESSION['params']) { //세션에 저장 된 parameter URL에 세팅
            $params = http_build_query($_SESSION['params']);
            $path .= '?' . $params;
        }

        session_write_close();
        header("Location: {$path}");
        exit; //view 페이지를 방문한 적이 없다면 돌려보냄
    }

    //HTML 출력
    private function html($page)
    { 
        if (is_array($page)) { //page 변수가 배열일 경우
            echo $page['header'];
            echo $page['content'];
            echo $page['footer'];
        } else { //page변수가 페이지 전체일 경우
            echo $page;
        }
    }

    //Page 생성
    private function makePage($file, $data = "", $appendHtml = [])
    { 
        $GLOBALS['data'] = $data;
        $header = $this->setHeader($appendHtml['header'] ?? '');
        $content = $this->setContent($file, $data);
        $header = preg_replace_callback('/\{\{([^\}]+)\}\}/m', function ($matches) {
            return $GLOBALS["data"][$matches[1]];
        }, $header);
        $content = preg_replace_callback('/\{\{([^\}]+)\}\}/m', function ($matches) {
            return $GLOBALS["data"][$matches[1]];
        }, $content);
        $page = $this->addStyle($header, $content);
        $page['footer'] = $this->getFooter(); //Footer를 buffer로 저장
        $this->html($page); //html 출력
    }

    //Header 호출
    private function getHeader()
    { 
        ob_start();
        include "./inc/head.php";
        $header = ob_get_contents();
        ob_end_clean();
        return $header;
    }

    //Footer 호출
    private function getFooter()
    { 
        ob_start();
        include "./inc/tail.php";
        $footer = ob_get_contents();
        ob_end_clean();

        return $footer;
    }

    private function setContent($file, $data = [])
    {
        if (!file_exists($file)){
            return NULL;
        }
        ob_start();
        include $file;
        $content = ob_get_contents(); //Contents를 buffer로 저장
        ob_end_clean();

        return $content;
    }

    //Header에 스타일시트 추가
    private function addStyle($header, $content)
    { 
        $content = $this->splitStyle($content);
        $page['header'] = preg_replace('#(</head>[^<]*<body[^>]*>)#', "{$content['style']}\n$1", $header); //Header에 스타일시트 추가
        $page['content'] = preg_replace('#(<style[^>]*>[^<]*<\/style>)#m', "", $content['content']); //스타일시트를 제외한 컨텐츠 재정의

        return $page;
    }

    //Header 제작
    private function setHeader($add_header = "")
    { 
        $title = $this->landing['title'] ?: $this->landing['name']; //타이틀 없을 경우 광고주명으로 대체
        $header = $this->getHeader(); //Header를 buffer로 저장
        $script = "";
        switch ($this->method) {
            case 'view':
                $script = stripslashes($this->landing['view_script']);
                break;
            case 'thanks':
                $script = stripslashes($this->landing['done_script']);
                break;
            case 'result':
                $title = "이벤트";
                break;
        }
        $header = preg_replace('#(<title>)([^<]*)(<\/title>)#', "$1 {$title}$3", $header); //저장된 buffer에 title 추가
        $header = preg_replace('#(</head>[^<]*<body[^>]*>)#', "{$script}\n$1", $header); //저장된 buffer에 header 스크립트 추가
        if ($add_header){
            $header = preg_replace('#(</head>[^<]*<body[^>]*>)#', "$1\n{$add_header}\n", $header); //저장된 buffer에 header 스크립트 추가   
        }

        return $header;
    }

    //스타일 분리
    private function splitStyle($content)
    {
        preg_match_all('#(<style>(.*?)</style>)?(.*)#is', $content, $matches, PREG_SET_ORDER); //content 파일내 스타일시트 분리
        $result['style'] = $matches[0][1];
        $result['content'] = $matches[0][3];

        return $result;
    }

    private function chkHash($uid) {
        $is_chk = false;
        $ab = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $uid = strtoupper($uid);
        $r_hash = substr($uid, 0, 2);
        $uid = substr($uid, 2);
        if(!preg_match('/[A-Z]{2}/', $r_hash)) return false;
        if(!preg_match('/[0-9]+/', $uid)) return false;
        $s_id = str_split($uid);
        $make_hash = [0,0];
        for($i=0; $i<count($s_id); $i++) $make_hash[0] += $s_id[$i]*($i+$s_id[count($s_id)-1]);
	    for($i=0; $i<count($s_id); $i++) $make_hash[1] += $s_id[$i]*($i+$s_id[0]);
        $make_hash = array_map(function($v) use($ab) { $chksum = ($v % 26); return $ab[$chksum]; }, $make_hash);
        $hash = implode("", $make_hash);
        if($hash.$uid == $r_hash.$uid) $is_chk = true;
        return $is_chk;
    }

    private function makeHash($uid) {
        $ab = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $s_id = str_split($uid);
        $make_hash = [0,0];
        for($i=0; $i<count($s_id); $i++) $make_hash[0] += $s_id[$i]*($i+$s_id[count($s_id)-1]);
        for($i=0; $i<count($s_id); $i++) $make_hash[1] += $s_id[$i]*($i+$s_id[0]);
        $make_hash = array_map(function($v) use($ab){$chksum = ($v % 26); return $ab[$chksum];}, $make_hash);
        $hash = implode("", $make_hash);
        $result = $hash.$uid;
        return $result;
    }

    //event 파일 목록 가져오기
    public function getFiles()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET');
        $files = [];
        $dirs = glob('data/*', GLOB_ONLYDIR);
        if (count($dirs) > 0) {
            foreach ($dirs as $d) {
                if ($file_list = array_map('basename', glob($d . '/v_*.php')))
                    $files = array_merge($files, $file_list);
            }
        }
        echo json_encode($files);
    }

    //파일체크
    public function filecheck()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET');
        $seq = $this->paths[2];
        $this->no = $seq;
        $result = $this->getLandingBySeq($this->no);
        if (!$result->db->num_rows) {
            throw new Exception("존재하지 않는 랜딩입니다."); //랜딩번호가 존재하지 않을경우 Exception 처리
        }
        $this->landing = $result->db->fetch_assoc();
        $file = "./data/{$this->landing['name']}/v_{$this->no}.php";
        echo file_exists($file);
    }

    // 신청자수 계산 함수
    public function applicant_num($input, $min = null, $max = null, $digit = 3)
    {
        $tmp = 0;
        if ($min < 0 || !$min) $min = 0;
        if ($max < 0 || !$max) $max = pow(10, $digit) - 1;

        $rst = $min + ($input % ($max - $min));

        return $rst;
    }

    //예외처리
    public static function exception_handler($e)
    {
        //var_dump($e);
        $error = ['title' => "PAGE NOT FOUND", 'subtitle' => "죄송합니다. 요청하신 페이지를 찾을 수 없습니다.", 'description' => "<!--{$e->getMessage()}-->", 'error' => $e->getFile()." ".$e->getLine()];
        include "error/404/index.html";
    }

    //경고처리
    public function alert($msg)
    {
        include "./inc/head.php";
        include "./inc/alert.php";
        include "./inc/tail.php";
        exit;
    }

    private function writeLog()
    {
        $path = explode('?', $_SERVER['REQUEST_URI']);
        $php_self = array_shift($path);
        $DBdata = [
            'evt_seq' => $this->no, 'scheme' => $_SERVER['REQUEST_SCHEME'], 'host' =>  $_SERVER['HTTP_HOST'], 'php_self' => $php_self, 'query_string' => $_SERVER['QUERY_STRING'], 'data' => file_get_contents('php://input'), 'post_data' => http_build_query($_POST), 'remote_addr' => $this->remote_addr, 'server_addr' => $_SERVER['SERVER_ADDR'], 'content_type' => $_SERVER['CONTENT_TYPE'] ?? '', 'user_agent' => $_SERVER['HTTP_USER_AGENT'], 'datetime' => date('Y-m-d H:i:s')
        ];
        $field = array();
        foreach ($DBdata as $k => $v) $field[] = "`$k` = '{$v}'";
        $fields = implode(', ', $field);
        $sql = " INSERT INTO event_logs SET {$fields} ";
        $this->db->query($sql, true);
    }

    public function serverinfo()
    {
        if ($this->our_ip || $_GET['auth']) echo '<pre>' . print_r($_SERVER, 1) . '</pre>';
    }

    //배열 그리딩
    public function grid($data, $link = null)
    {
        if (empty($data)) {
            echo '<p>null data</p>';
            return;
        }
        $table = '';
        foreach ($data as $row) {
            if (is_array($row)) {
                $table .= '<tr>';
                foreach ($row as $key => $var) {
                    if ($link) {
                        foreach ($link as $k => $v) {
                            if ($k == $key) {
                                $var = str_replace('{' . $k . '}', $var, $v);
                            }
                        }
                    }
                    $table .= '<td>' . (is_object($var) ? $var->load() : $var) . '</td>';
                }
                $table .= '</tr>';
            }
        }
        if (isset($row) && is_array($row)) {
            $thead = '<thead><tr>';
            foreach ($row as $key => $tmp) {
                $thead .= '<th>' . $key . '</th>';
            }
            $thead .= '</tr></thead>';
        } else {
            $thead = '<thead><tr>';
            $table = '<tr>';
            foreach ($data as $k => $v) {
                $thead .= '<th>' . $k . '</th>';
                $table .= '<td>' . $v . '</td>';
            }
            $thead .= '</tr></thead>';
            $table .= '</tr>';
        }
        echo '<table class="_dev_util_grid" border="1">' . $thead . $table . '</table>';
    }
}
