<?php
// 1. הטוקן שהאפליקציה משתמשת בו (חייב להיות זהה ל- API_TOKEN בקובץ config.json)
$gateway_token = 'XXXXX'; 

// 2. הטוקן שהשרת שלך משתמש בו (חייב להיות זהה ל- api_token בקובץ hagdarot.php)
$main_server_token = 'XXXX'; 

$real_api_url = "https://love-marriage.co.il/meet/pdf/invoice_collect_api.php";

header('Content-Type: application/json; charset=utf-8');
$inputJSON = file_get_contents('php://input');

$client_timestamp = $_SERVER['HTTP_X_TIMESTAMP'] ?? '';
$client_signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';

// בדיקה 1: האם חסרים פרמטרים?
if (empty($client_timestamp) || empty($client_signature)) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Gateway Error: Missing security headers"]);
    exit;
}

// בדיקה 2: האם הבקשה ישנה מדי?
if (abs(time() - (int)$client_timestamp) > 60) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Gateway Error: Request expired"]);
    exit;
}

// בדיקה 3: האם החתימה של האפליקציה תואמת לטוקן של ה-Gateway?
$expected_gateway_sig = hash_hmac('sha256', $client_timestamp, trim($gateway_token));
if (!hash_equals($expected_gateway_sig, $client_signature)) {
    http_response_code(403);
    echo json_encode([
        "status" => "error", 
        "message" => "Gateway Error: Signature mismatch. Check that API_TOKEN in Python matches gateway_token."
    ]);
    exit;
}

// === מכאן הבקשה תקינה, ה-Gateway מייצר חתימה חדשה מול השרת הראשי ===

$new_timestamp = (string)time();
$new_signature = hash_hmac('sha256', $new_timestamp, trim($main_server_token));

$ch = curl_init($real_api_url);
$forward_headers = [
    "Content-Type: application/json",
    "X-Timestamp: " . $new_timestamp,
    "X-Signature: " . $new_signature,
    "X-Forwarded-For: " . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '')
];

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $inputJSON);
curl_setopt($ch, CURLOPT_HTTPHEADER, $forward_headers);
// אם יש בעיית SSL בשרת הראשי שמפילה את ה-cURL, שתי השורות האלו יעקפו אותה:
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// בדיקה 4: האם ה-cURL נכשל בתקשורת עם השרת הראשי שלך?
if ($response === false) {
    http_response_code(502);
    echo json_encode(["status" => "error", "message" => "Gateway Error: Cannot connect to Main Server. " . $curl_error]);
    exit;
}

// מעביר את התשובה מהשרת הראשי חזרה לאפליקציה
http_response_code($http_code);
echo $response;
?>
