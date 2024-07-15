<?php
include __DIR__ . "/zenith_interlock.php";

class CheckProc
{
    private $db, $rwdb;
    private $no, $app_name, $event_seq, $post, $landing, $remote_addr, $is_our, $encryption;

    public function __construct($event_seq, $post, $landing, $remote_addr, $is_our)
    {
        $this->no = $event_seq;
        $this->event_seq = $event_seq;
        $this->app_name = 'evt_'.$event_seq;
        $this->post = $post;
        $this->landing = $landing;
        $this->remote_addr = $remote_addr;
        $this->is_our = $is_our;
        $this->encryption = new ZenithEncryption();

        $this->db = new ZenithDB();
        $this->rwdb = $this->db->getRWConnection();
    }

    public function check_proc()
    {
        $return = $this->validator();

        if ($return['result']) {
            foreach ($this->post as $k => $v){
                if (is_array($v)){
                    $this->post[$k] = implode(',', $v);
                }
            }
            $age = isset($_POST['age']) ? intval($_POST['age']) : 0;
            //Injection 방지 및 따옴표 오류 방지를 위해 escape 처리
            $data = [
                'event_seq' => $this->event_seq,
                'site' => $this->rwdb->real_escape_string(trim($this->post['site'] ?? '')),
                'name' => $this->rwdb->real_escape_string(trim($this->post['name'] ?? '')),
                'phone' => $this->rwdb->real_escape_string(preg_replace("/[^0-9]/", "", $this->post["phone"] ?? '')),
                'gender' => $this->rwdb->real_escape_string(trim($this->post['gender'] ?? '')),
                'age' => $this->rwdb->real_escape_string(trim($age)),
                'branch' => $this->rwdb->real_escape_string(trim($this->post['branch'] ?? '')),
                'addr' => $this->rwdb->real_escape_string(trim($this->post['addr'] ?? '')),
                'email' => $this->rwdb->real_escape_string(trim($this->post['email'] ?? '')),
                'add1' => $this->rwdb->real_escape_string(trim($this->post['add1'] ?? '')),
                'add2' => $this->rwdb->real_escape_string(trim($this->post['add2'] ?? '')),
                'add3' => $this->rwdb->real_escape_string(trim($this->post['add3'] ?? '')),
                'add4' => $this->rwdb->real_escape_string(trim($this->post['add4'] ?? '')),
                'add5' => $this->rwdb->real_escape_string(trim($this->post['add5'] ?? '')),
                'add6' => $this->rwdb->real_escape_string(trim($this->post['add6'] ?? '')),
                'memo' => $this->rwdb->real_escape_string(trim($this->post['memo'] ?? '')),
                'memo3' => $this->rwdb->real_escape_string(trim($this->post['memo3'] ?? '')),
                'file_url' => $this->rwdb->real_escape_string(trim($this->post['file_url'] ?? '')),
                'reg_date' => $this->rwdb->real_escape_string(trim($this->post['reg_date'] ?? date('Y-m-d H:i:s'))),
            ];
            
            $agBox = $this->rwdb->real_escape_string(trim($this->post['agBox'] ?? ''));

            if ($this->landing['custom']) {
                $json = json_decode(str_replace('\\', '', $this->landing['custom']), true);
                foreach ($json as $item) {
                    // if(!$data[$json[$idx]['key']]) $data[$json[$idx]['key']] = $json[$idx]['val'];
                    if ($item['val'] && $item['val'] != "") {
                        $data[$item['key']] = $item['val'];
                    }
                }
            }

            $data['agree'] = $agBox ? 'Y' : 'N'; //agBox 값이 있으면 동의 체크된 값이므로 value에 상관없이 Y로 보정

            $result = $this->db->getBlackListByPhone($data['phone']);
            if ($result->db->num_rows) { //연락처가 블랙리스트에 있을 경우 저장하지않고 thanks로 바로 넘김
                $return = ['result' => false, 'data' => 'thanks'];
                return $return;
            }
            if ($data['name'] && ($data['phone'] || $data['email'])) {   //이름과 전화번호는 필수            
                $result = $this->db->insertEventLead($this->event_seq, $data, $this->remote_addr);
                if ($result->insert_id) { //저장 완료 시
                    foreach (['memo', 'memo3'] as $field) {
                        if (!empty($data[$field])) {
                            $memoData = [
                                'seq' => $result->insert_id,
                                'event_seq' => $this->event_seq,
                                'memo' => $data[$field]
                            ];
                            $this->db->insertToMemo($memoData);
                        }
                    }
                    
                    $data['seq'] = $result->insert_id;
                    $enc_param = $this->encryption->encrypt($data['seq']);
                    $check = $this->checkEventLeads($data); //인정기준 처리
                    $status = $check['status'];
                    $done_cookie = $this->encryption->encrypt("{$this->remote_addr}_".time());
                    ZenithCookie::set_cookie("Thanks_{$this->event_seq}", $done_cookie, 86400*$this->landing['duplicate_term']); //저장완료 시 고유 쿠키 생성
                    $thanks_cookie = ZenithCookie::get_cookie("Thanks_{$this->event_seq}");
                    if($this->landing['check_cookie'] && $thanks_cookie) { //제니스에서는 상태값 변경 메모가 저장되기 때문에 쿠키 중복을 확인할 수 있어서, return을 하지 않고 다음 체크로 넘김
                        $ck = $this->encryption->decrypt($thanks_cookie);
                        $ck = explode('_', $ck);
                        $status = 13;
                        $msg = "쿠키 중복-{$ck[0]}";
                        $returnData = ['status' => $status, 'status_memo' => $msg];
                        $this->db->updateStatusToEventLeads($returnData, $data);
                    }

                    //AppSubscribe 처리 시작
                    if(isset($this->post['local']) && $this->post['local']) $data['add9'] = $this->post['local'];
                    $data['zenith_lead_seq'] = $data['seq'];
                    $data['group_id'] = 'evt_'.$this->event_seq;
                    $data['add7']     = $this->rwdb->real_escape_string(trim($this->post['add7'] ?? ''));
                    $data['add8']     = $this->rwdb->real_escape_string(trim($this->post['add8'] ?? ''));
                    $data['add9']     = $this->rwdb->real_escape_string(trim($this->post['add9'] ?? ''));
                    $this->db->insertAppSubscribe($data, $this->remote_addr);
                    //AppSubscribe 처리 끝

                    $interlock_result = [];
                    if ($this->landing['interlock']) { //외부연동이 true 일 경우
                        sleep(1);	
                        $seq = $data['seq'];
                        $interlock_file = "./data/{$this->landing['name']}/interlock.php";
                        if (file_exists($interlock_file)){ //외부연동 파일이 있으면 진행
                            include $interlock_file;
                        }
                    }

                    $proc_file = "./data/{$this->landing['name']}/v_{$this->event_seq}_proc.php";
                    if (file_exists($proc_file)){
                        include $proc_file;
                    }

                    if(count($interlock_result)){ //proc 에서도 interlock 이 일어나는 경우가 있어서 직전에 정리
                        $data = array_merge($data, ['interlock_result' => $interlock_result]);
                        
                        $interlockOj = new ZenithInterlock();
                        $interlockOj->eventLeadsInterlock($data);
                    }

                    $return = ['result' => true, 'data' => $enc_param];
                } else {
                    $return = ['result' => false, 'msg' => "데이터 저장에 문제가 발생하였습니다.<br><br>문제가 계속 될 경우 jaybe@carelabs.co.kr 로 문의주세요."];
                }
            } else {
                if (preg_match('/instagram/i', $_SERVER['HTTP_USER_AGENT'])) {
                    $return = ['result' => false, 'msg' => "개인정보 암호화 성공!\n\n다시 한 번 신청 버튼을 눌러주세요."];
                } else {
                    $return = ['result' => false, 'msg' => "필수값이 누락되어 데이터를 저장할 수 없습니다."];
                }
            }
        }

        return $return;
    }

    private function validator()
    {
        $age = $this->post['age'] ?? null;
        $checkAgeMin = $this->landing['check_age_min'];
        $checkAgeMax = $this->landing['check_age_max'];

        if ($age !== null && $checkAgeMin && $checkAgeMax) {
            if ($age < $checkAgeMin) {
                return ['result' => false, 'msg' => "이 이벤트는 {$checkAgeMin}세 이상만 신청 가능합니다."];
            } elseif ($age > $checkAgeMax) {
                return ['result' => false, 'msg' => "이 이벤트는 {$checkAgeMax}세 이하로 신청 가능합니다."];
            }
        }

        /* var align */
        $name = $this->post['name'];
        $phone = $this->post['phone'];
        $encPhone = $this->encryption->encrypt($phone, false);
        $advertiserNo = $this->landing['advertiser'] ?? '';
        $mediaNo = $this->landing['med_seq'] ?? '';
        $ip = $this->remote_addr;

        $duplicatePrecheck = $this->landing['duplicate_precheck'];
        $sql = "";

        switch ($duplicatePrecheck) {
            case '0':/** 사전 중복 체크 **/
                return ['result' => true];
            case '1':
                // 해당 랜딩 - 이름&연락처 중복
                $sql = "SELECT seq FROM event_leads WHERE is_deleted=0 AND name = '$name' AND (phone = '$phone' OR phone = '$encPhone') ";
                $sql .= "AND event_seq = '{$this->event_seq}'";
                break;
            case '2':
                // 해당 광고주 - 이름&연락처 중복
                $sql = "SELECT seq FROM event_leads WHERE is_deleted=0 AND name = '$name' AND (phone = '$phone' OR phone = '$encPhone') ";
                $sql .= "AND event_seq IN (SELECT seq FROM event_information WHERE advertiser = '$advertiserNo' )";
                break;
            case '3':
                // 해당 광고주&매체 - 이름&연락처 중복
                $sql = "SELECT seq FROM event_leads WHERE is_deleted=0 AND name = '$name' AND (phone = '$phone' OR phone = '$encPhone') ";
                $sql .= "AND event_seq IN (SELECT seq FROM event_information WHERE advertiser = '$advertiserNo' AND media = '$mediaNo' )";
                break;
            case '7':
                // 해당 랜딩 - 연락처 중복
                $sql = "SELECT seq FROM event_leads WHERE is_deleted=0 AND (phone = '$phone' OR phone = '$encPhone') ";
                $sql .= "AND event_seq = '{$this->event_seq}'";
                break;
            case '8':
                // 해당 광고주 - 연락처 중복
                $sql = "SELECT seq FROM event_leads WHERE is_deleted=0 AND (phone = '$phone' OR phone = '$encPhone') ";
                $sql .= "AND event_seq IN (SELECT seq FROM event_information WHERE advertiser = '$advertiserNo' )";
                break;
            case '9':
                // 해당 광고주&매체 - 연락처 중복
                $sql = "SELECT seq FROM event_leads WHERE is_deleted=0 AND (phone = '$phone' OR phone = '$encPhone') ";
                $sql .= "AND event_seq IN (SELECT seq FROM event_information WHERE advertiser = '$advertiserNo' AND media = '$mediaNo' )";
                break;
            default:
                $sql = "SELECT seq FROM event_leads WHERE is_deleted=0 AND name = '$name' AND (phone = '$phone' OR phone = '$encPhone') ";
                $sql .= "AND event_seq = '{$this->event_seq}'";
        }
    
        if (!$this->is_our && ($duplicatePrecheck == '4' || $duplicatePrecheck == '5' || $duplicatePrecheck == '6')) {
            switch ($duplicatePrecheck) {
                case '4':
                    // 해당 랜딩 - IP 중복
                    $sql = "SELECT seq FROM event_leads WHERE is_deleted=0 AND ip = '$ip'";
                    $sql .= "AND event_seq = '{$this->event_seq}'";
                    break;
                case '5':
                    // 해당 광고주 - IP 중복
                    $sql = "SELECT seq FROM event_leads WHERE is_deleted=0 AND ip = '$ip'";
                    $sql .= "AND event_seq IN (SELECT seq FROM event_information WHERE advertiser = '$advertiserNo' )";
                    break;
                case '6':
                    // 해당 광고주&매체 - IP 중복
                    $sql = "SELECT seq FROM event_leads WHERE is_deleted=0 AND ip = '$ip' AND (phone = '$phone' OR phone = '$encPhone') ";
                    $sql .= "AND event_seq IN (SELECT seq FROM event_information WHERE advertiser = '$advertiserNo' AND media = '$mediaNo' )";
                    break;
            }
        }

        $result = $this->db->query($sql);
        if ($result->db->num_rows) {
            return ['result' => false, 'msg' => "이미 참가하셨습니다!"];
        }

        return ['result' => true];
    }

    //불량DB 처리
    private function checkEventLeads($data) { 
        $status = 1;
        $msg = '';
        if ($data['gender'] && $this->landing['check_gender']) { //성별 체크
            if ($this->landing['check_gender'] == 'm' && !preg_match('/(남|m)/i', $data['gender'])) {
                $status = 3;
                $msg = '남자체크';
            } elseif ($this->landing['check_gender'] == 'f' && !preg_match('/(여|f)/i', $data['gender'])) {
                $status = 3;
                $msg = '여자체크';
            }
            if ($status != 1) {
                $return = ['status' => $status, 'status_memo' => $msg];
                $this->db->updateStatusToEventLeads($return, $data);
                return $return;
            }
        }
        if ($data['age']) {
            if ($this->landing['check_age_min'] && $data['age'] < $this->landing['check_age_min']) {
                $return = ['status' => 4, 'status_memo' => '나이조건 이하'];
                $this->db->updateStatusToEventLeads($return, $data);
                return $return;
            }
    
            if ($this->landing['check_age_max'] && $data['age'] > $this->landing['check_age_max']) {
                $return = ['status' => 4, 'status_memo' => '나이조건 이상'];
                $this->db->updateStatusToEventLeads($return, $data);
                return $return;
            }
        }
        
        if ($data['phone'] && $this->landing['check_phone']) { //전화번호 불량,중복 체크
            $number = substr($data['phone'], 3, 8);
            if (!preg_match('/^[\d]{11}$/', $data['phone'])) {
                $status = 6;
                $msg = '전화번호 길이 불량';
            } else if (in_array($number, ['00000000', '11111111', '22222222', '33333333', '44444444', '55555555', '66666666', '77777777', '88888888', '99999999'])) {
                $status = 6;
                $msg = '8자리 같은번호';
            }
            $phone = $this->encryption->encrypt($data['phone'], false);
            $sql = "SELECT seq FROM `zenith`.`event_leads` WHERE `event_seq` = '{$data['event_seq']}' AND `phone` = '{$phone}' AND `seq` <> '{$data['seq']}' AND `is_deleted` = 0 AND `status` <> 0";
            if ($this->landing['duplicate_term']) {
                $sql .= " AND `reg_date` >= DATE_SUB(NOW(), INTERVAL {$this->landing['duplicate_term']} DAY)";
            }
            $result = $this->db->query($sql);
            if ($result->db->num_rows) {
                $status = 2;
                $msg = '전화번호 중복';
            }

            if ($status != 1) {
                $return = ['status' => $status, 'status_memo' => $msg];
                $this->db->updateStatusToEventLeads($return, $data);
                return $return;
            }
        }
        
        if ($data['name']) { //이름 불량,중복 체크
            if ($this->landing['check_name']) {
                $sql = "SELECT seq FROM `zenith`.`event_leads` WHERE `event_seq` = '{$data['event_seq']}' AND `name` = '{$data['name']}' AND `seq` <> '{$data['seq']}' AND `is_deleted` = 0 AND `status` <> 0
                ";
                if ($this->landing['duplicate_term']) {
                    $sql .= " AND `reg_date` >= DATE_SUB(NOW(), INTERVAL {$this->landing['duplicate_term']} DAY)";
                }
                $result = $this->db->query($sql);
                if ($result->db->num_rows) {
                    $status = 2;
                    $msg = '이름 중복';
                }
            }
            
            if (preg_match('/(테스트|테스트|test)/i', $data['name'])) { //테스트 체크
                $status = 7;
                $msg = '테스트';
            }
            
            if ($status != 1) {
                $return = ['status' => $status, 'status_memo' => $msg];
                $this->db->updateStatusToEventLeads($return, $data);
                return $return;
            }
        }
        
        $return = ['status' => $status, 'status_memo' => $msg];
        $this->db->updateStatusToEventLeads($return, $data);
        
        return $return;
    }
}
?>