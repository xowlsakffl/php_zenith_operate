<?php
include __DIR__ . "/zenith_ip.php";

$addr = ZenithIP::getRemoteAddr();
$jsonData = [
	'name' => $_POST['name'],
	'phone' => $_POST['phone'],
	'remote_addr' => $addr,
	'age' => $_POST['age'] ?? '',
	'branch' => $_POST['branch'] ?? '',
	'add1' => $_POST['add1'] ?? '',
	'add2' => $_POST['add2'] ?? '',
	'add3' => $_POST['add3'] ?? '',
	'add4' => $_POST['add4'] ?? '',
	'add5' => $_POST['add5'] ?? '',
	'add6' => $_POST['add6'] ?? '',
];

$url = 'http://local.event.hotblood.co.kr/zenith_index.php/1/send/';

$ch = curl_init();
curl_setopt_array($ch, array(
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_URL => $url,
	CURLOPT_HEADER => false,
	CURLOPT_HTTPHEADER => array(
		'Content-Type: application/json'
	),
	CURLOPT_POSTFIELDS => json_encode($jsonData),
	CURLOPT_AUTOREFERER    => true,  
	CURLOPT_SSL_VERIFYPEER => false,
	CURLOPT_VERBOSE => true,
	CURLOPT_FOLLOWLOCATION => true,
	CURLOPT_POST => true,
));

$response =curl_exec($ch);
curl_close($ch);
print_r(curl_getinfo($ch));
var_dump($response);
$result = json_decode($response);
if ($response !== null) {
    echo "결과값:\n";
    echo "result: " . ($result->result ? 'true' : 'false') . "\n";
    echo "data: " . $result->data . "\n";
} else {
    echo "JSON 파싱 오류 또는 빈 응답\n";
}
?>