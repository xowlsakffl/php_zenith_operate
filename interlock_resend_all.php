<?php
include __DIR__."/../dbinfo.php";

$db = new mysqli(MYSQL_RW_HOST, MYSQL_USER_ID, MYSQL_USER_PW, MYSQL_DB_NAME);

$advertisers = ['더스퀘어치과의원', '메이드영성형외과의원', '모아만의원', '모우림의원', '열정치과의원' ,'우리들40플란트치과병원' , '하루플란트치과의원광고주', '한나이브성형외과의원', '강남애프터치과의원', '참좋은치과의원', '보가치과의원'];

//$eids = [6121036];

$start_date = "2023-12-01";
$end_date = "2023-12-31";

$query = "";
//if(count($eids)) $query .= " AND app.eid IN (".implode(",",$eids).")";
if($start_date) $query .= " AND date(app.reg_date) >= '{$start_date}'";
if($end_date) $query .= " AND date(app.reg_date) <= '{$end_date}'";

$sql = "SELECT app.*, dec_data(app.phone) as dec_phone, ei.seq as event_seq, ei.description, adv.name as advertiser_name, adv.agent as advertiser_agent, med.media as media_name, med.target as media_target
            FROM app_subscribe AS app 
                JOIN zenith.event_information AS ei ON app.event_seq = ei.seq
                JOIN zenith.event_advertiser AS adv ON ei.advertiser = adv.seq
                JOIN zenith.event_media AS med ON ei.media = med.seq
        WHERE adv.name IN ('".implode("','",$advertisers)."') {$query}";
$result = $db->query($sql);

if(!$result->num_rows) exit;
$resta_url = "https://event.resta.co.kr/eventleads";
while($row = $result->fetch_assoc()) {
    $resta_interlock_data = array(
        "event_seq" => $row['event_seq'],
        "advertiser_name" => $row['advertiser_name'],
        "advertiser_agent" => $row['advertiser_agent'],
        "media_name" => $row['media_name'],
        "media_target" => $row['media_target'],
        "description" => $row['description'] ?? '',
        "partner_name" => "케어랩스",
        "name" => $row['name'] ?? '',
        "age" => $row['age'] ?? '',
        "branch" => $row['branch'] ?? '',
        "email" => $row['email'] ?? '',
        "gender" => $row['gender'] ?? '',
        "phone" => $row['dec_phone'] ?? '',
        "add1" => $row['add1'] ?? '',
        "add2" => $row['add2'] ?? '',
        "add3" => $row['add3'] ?? '',
        "add4" => $row['add4'] ?? '',
        "add5" => $row['add5'] ?? '',
        "add6" => $row['add6'] ?? '',
        "add7" => $row['add7'] ?? '',
        "add8" => $row['add8'] ?? '',
        "site" => $row['site'] ?? '',
        "memo" => $row['memo'] ?? '',
        "memo3" => $row['memo3'] ?? '',
        "remote_addr" => $row['ip'] ?? '',
        "agBox" => $row['agree'],
        "reg_date" => $row['reg_date']
    );
    $data = json_encode($resta_interlock_data);
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $resta_url,
        CURLOPT_POSTFIELDS => $data
    ));

    $response =curl_exec($ch);
    curl_close($ch);
    $res = json_decode($response, true);
    
    if($res['result'] == true) {
        $sql = "UPDATE app_subscribe SET returnvalue = '$response', url='$resta_url', data='$data' WHERE eid = '{$row['eid']}'";
        $insert_result = $db->query($sql);
        if($insert_result){
            print_r($res);
        }
    }
}

?>