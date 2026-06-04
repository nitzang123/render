<?php
// הגדר לאן להעביר את הבקשה
$real_api_url = "https://love-marriage.co.il/meet/pdf/invoice_collect_api.php"; 
// למקרה של ה-WIDGET שנה ל: 
// $real_api_url = "https://love-marriage.co.il/meet/pdf/widget_api.php";

$inputJSON = file_get_contents('php://input');

// שליפת ה-Headers מהפייתון/ווידג'ט
$headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
$client_timestamp = $headers['X-Timestamp'] ?? $_SERVER['HTTP_X_TIMESTAMP'] ?? '';
$client_signature = $headers['X-Signature'] ?? $_SERVER['HTTP_X_SIGNATURE'] ?? '';

$ch = curl_init($real_api_url);

$client_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';

// הכנת הכותרות להעברה לשרת הראשי
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
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // חשוב למקרה של הפניות (Redirects)

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
