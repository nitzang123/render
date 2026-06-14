<?php
$config=include 'hagdarot_gateway.php';
if(!isset($config['gateway_token'])||!isset($config['main_server_token'])){
    http_response_code(500);
    exit;
}

$real_api_url="https://love-marriage.co.il/meet/pdf/turn_my_laptop_off_by_command_api.php";
$inputJSON=file_get_contents('php://input');
$headers=apache_request_headers();

$client_timestamp=$headers['X-Timestamp'] ?? $_SERVER['HTTP_X_TIMESTAMP'] ?? '';
$client_signature=$headers['X-Signature'] ?? $_SERVER['HTTP_X_SIGNATURE'] ?? '';

if(empty($client_timestamp)||empty($client_signature)){
    http_response_code(401);
    exit;
}

if(abs(time()-(int)$client_timestamp)>60){
    http_response_code(401);
    exit;
}

$expected_gateway_sig=hash_hmac('sha256',$client_timestamp,trim($config['gateway_token']));
if(!hash_equals($expected_gateway_sig,$client_signature)){
    http_response_code(403);
    exit;
}

// יצירת חתימה חדשה מבוססת זמן מול השרת הראשי
$new_timestamp=time();
$new_signature=hash_hmac('sha256',$new_timestamp,trim($config['main_server_token']));

$ch=curl_init($real_api_url);
$forward_headers=[
    "Content-Type: application/json",
    "X-Timestamp: ".$new_timestamp,
    "X-Signature: ".$new_signature,
    "X-Forwarded-For: ".$_SERVER['REMOTE_ADDR']
];

curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
curl_setopt($ch,CURLOPT_POST,true);
curl_setopt($ch,CURLOPT_POSTFIELDS,$inputJSON);
curl_setopt($ch,CURLOPT_HTTPHEADER,$forward_headers);

$response=curl_exec($ch);
$http_code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($http_code);
header('Content-Type: application/json; charset=utf-8');
echo $response;
?>
