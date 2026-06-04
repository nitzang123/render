<?php
$real_api_url = "https://love-marriage.co.il/meet/pdf/invoice_collect_api.php";
$inputJSON = file_get_contents('php://input');

$client_timestamp = $_SERVER['HTTP_X_TIMESTAMP'] ?? '';
$client_signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
$gateway_auth = $_SERVER['HTTP_X_GATEWAY_AUTH'] ?? '';
$client_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';

$ch = curl_init($real_api_url);

$forward_headers = [
    "Content-Type: application/json",
    "X-Timestamp: " . $client_timestamp,
    "X-Signature: " . $client_signature,
    "X-Forwarded-For: " . $client_ip,
    "X-Gateway-Auth: " . $gateway_auth
];

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $inputJSON);
curl_setopt($ch, CURLOPT_HTTPHEADER, $forward_headers);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

http_response_code($http_code);
header('Content-Type: application/json; charset=utf-8');

if ($response === false) {
    echo json_encode(["status" => "error", "message" => "Gateway Error: " . $curl_error]);
} else {
    echo $response;
}
?>
