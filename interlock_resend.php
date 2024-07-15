<?php
include __DIR__."/../dbinfo.php";

$db = new mysqli(MYSQL_RW_HOST, ZENITH_DB_ID, ZENITH_DB_PW, ZENITH_DB_NAME);

$event_seqs = [];
$start_date = "2023-12-14";
$end_date = "2023-12-14";

$query = " AND `url` = 'https://event.resta.co.kr/eventleads'";
if(count($event_seqs)) $query .= " AND event_seq IN (".implode(",",$event_seqs).")";
if($start_date) $query .= " AND date(reg_date) >= '{$start_date}'";
if($end_date) $query .= " AND date(reg_date) <= '{$end_date}'";
$sql = "SELECT * FROM event_leads_interlock WHERE is_success = 0 {$query};";
echo $sql."<br>";
$result = $db->query($sql);
if(!$result->num_rows) exit;
while($row=$result->fetch_assoc()) {
    $url = $row['url'];
    $send_data = json_decode($row['send_data'], true);
    $send_data['reg_date'] = $row['reg_date'];
    $data = json_encode($send_data);
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $url,
        CURLOPT_POSTFIELDS => $data
    ));
    $response =curl_exec($ch);
    curl_close($ch);
    $res = json_decode($response, true);
    print_r($res);
    if($res['result'] == true) {
        $db->query("UPDATE event_leads_interlock SET is_success = 1, response_data = '{$response}' WHERE event_seq = {$row['event_seq']} AND leads_seq = {$row['leads_seq']} AND is_success = 0 AND `url` = '{$row['url']}'");
    }
}