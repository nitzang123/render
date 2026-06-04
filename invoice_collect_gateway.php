<?php
// כתובת ה-API מהקוד הישן
$real_api_url = "https://love-marriage.co.il/meet/pdf/invoice_collect_api.php";
$inputJSON = file_get_contents('php://input');

// משיכת ההדרים כפי שמתבצע בקוד החדש (ללא בדיקה מקומית)
$client_timestamp = $_SERVER['HTTP_X_TIMESTAMP'] ?? '';
$client_signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';

$ch = curl_init($real_api_url);

// משיכת כתובת ה-IP כפי שמתבצע בקוד החדש
$client_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';

$forward_headers = [
    "Content-Type: application/json",
    "X-Timestamp: " . $client_timestamp,
    "X-Signature: " . $client_signature,
    "X-Forwarded-For: " . $client_ip
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

// טיפול בשגיאות כפי שמתבצע בקוד החדש
if ($response === false) {
    echo json_encode(["status" => "error", "message" => "Gateway Error: " . $curl_error]);
} else {
    echo $response;
}
?>
