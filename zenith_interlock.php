<?php

class ZenithInterlock
{
    private $db, $rwdb;
    public function __construct()
    {
        $this->db = new ZenithDB();
        $this->rwdb = $this->db->getRWConnection();
    }
    
    //외부연동 처리
    public function eventLeadsInterlock($data) { 
        if(!isset($data['interlock_result'])){
            return;
        }
        $interlock_failed = count($data['interlock_result']); //랜딩별 총 외부연동 갯수를 실패횟수로 정의
        foreach($data['interlock_result'] as $row) {
            //수신데이터 정리
            if (self::is_json($row['response_data'])) { //수신 데이터가 json 형태라면..
                $response = json_decode($row['response_data'], true); //json을 배열로 변형
                $row['response_data'] = json_encode($response, JSON_UNESCAPED_UNICODE); //DB에 저장하기 위해 배열로 변형한 데이터를 unicode 처리하지 않고 json으로 변환
            } else {
                $response = $row['response_data']; //수신데이터가 배열이면 그대로 지정
            }
            if(isset($response['state']))
                $response['result'] = $response['state']; //수신 데이터가 result가 아닌 state가 있어서 result로 변경
            if(isset($response['status']))
                $response['result'] = $response['status']; //수신 데이터가 result가 아닌 status가 있어서 result로 변경
            $is_success = 0;
            /*
            ! result 값에 따라 $is_success 를 처리할 수 있도록 모든 성공값을 처리
            ? 정의된 배열 이외 성공값이 있을 경우 수식을 수정하거나 배열에 추가하여야 함
            */
            if((isset($response['result']) //reponse가 result 배열이 존재 할 경우
                && (in_array(strtolower($response['result']), ["ok","200","y","success","01"]) //배열 내 result 값이 정의된 배열 안에 존재하거나
                || $response['result'] === true)) //배열 내 result 가 boolean 값 true로 넘어왔거나
                || in_array(strtolower((string)$response), ["ok","200","y","1","success"])) //response가 배열이 아닌 text값이 정의된 배열에 존재할 경우
            {
                $is_success = 1; //성공
            }
            //전송데이터 정리
            if(self::is_json($row['send_data'])) $row['send_data'] = json_decode($row['send_data'], true);
            //전송한 데이터의 문자가 변형 된 것을 UTF-8 형태로 치환
            $row['send_data'] = array_map(function($v){
                if(urlencode(urldecode($v)) === $v) $v = urldecode($v);
                return iconv(mb_detect_encoding($v, ['ASCII','UTF-8','EUC-KR']), 'UTF-8//TRANSLIT', urldecode($v));
            },$row['send_data']);
            //DB에 저장하기 위해 전송한 데이터를 unicode 처리하지 않고 json으로 변환
            $row['send_data'] = json_encode($row['send_data'], JSON_UNESCAPED_UNICODE);
            array_walk($row, function(&$string) { $string = $this->rwdb->real_escape_string($string); }); //DB저장을 위해 escape 처리
            //외부연동 내역
            $this->db->insertEventLeadInterlock($data, $row, $is_success);
            if($is_success === 1){
                $interlock_failed--; //성공할 때 마다 실패횟수 차감
            }else { //외부연동 실패 할 때 마다
                $memoData = [
                    'seq' => $data['seq']
                    ,'event_seq' => $data['event_seq']
                    ,'memo' => "외부연동을 실패하였습니다."
                ];
                if(isset($response['msg']) || isset($response['message'])) {
                    if(isset($response['msg']))
                        $msg = $response['msg'];
                    if(isset($response['message']))
                        $msg = $response['message'];
                    if(isset($response['code_message']))
                        $msg = $response['code_message'];
                    $memoData['memo'] .= "[메세지:{$msg}]";
                }
                $this->db->insertToMemo($memoData);
            }
        }

        if($interlock_failed === 0) { //외부연동 모두 성공 했을 때
            //외부연동 결과 처리
            $this->db->updateEventLeadInterlock($data['seq']);
        }
    }

    //외부연동
    public function zenithInterlock() {
        $sql = "SELECT ea.name AS advertiser_name, el.*, `zenith`.dec_data(el.phone) AS phone
        FROM `zenith`.`event_leads` AS el
            LEFT JOIN `zenith`.`event_information` AS ei ON el.event_seq = ei.seq
            LEFT JOIN `zenith`.`event_advertiser` AS ea ON ei.advertiser = ea.seq
        WHERE el.interlock_success = 0
            AND el.status = 1
            AND ei.interlock = 1
            AND ea.interlock_url <> ''
            AND el.reg_date >= DATE_SUB(NOW(), INTERVAL 20 MINUTE)";
        $result = $this->db->query($sql);
        while($row = $result->db->fetch_assoc()) {
            $this->db->getLandingBySeq($row['event_seq']);
            $data = array_map('addslashes', $row);
            $interlock_file = __DIR__."/data/{$row['advertiser_name']}/interlock.php";
            echo '<br>'.$row['advertiser_name'].'/';
            var_dump(file_exists($interlock_file));
            echo '<pre>'.print_r($data,1).'</pre>';
            continue;
            if(file_exists($interlock_file)) //외부연동 파일이 있으면 진행
                include $interlock_file;
            $this->eventLeadsInterlock($data);
        }
    }

    private function is_json($string = null)
    {
        $ret = true;
        if (!is_string($string) || null === json_decode($string)) {
            $ret = false;
        }
        
        return $ret;
    }
}
?>