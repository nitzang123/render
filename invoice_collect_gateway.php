<?php
/**
 * GATEWAY API - משמש כתווך בין Python ל-Server הראשי
 * * זרימה:
 * 1. Python שולח בקשה עם X-Timestamp + X-Signature
 * 2. Gateway מוודא את החתימה עם GATEWAY_TOKEN מתוך משתני סביבה
 * 3. Gateway יוצר חתימה חדשה עם MAIN_SERVER_TOKEN מתוך משתני סביבה
 * 4. Gateway מעביר לשרת הראשי
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

// ============================================
// 1. טען את ההגדרות ממשתני סביבה (Environment Variables)
// ============================================
$gateway_token = getenv('GATEWAY_TOKEN') ?: $_ENV['GATEWAY_TOKEN'] ?? $_SERVER['GATEWAY_TOKEN'] ?? '';
$main_server_token = getenv('MAIN_SERVER_TOKEN') ?: $_ENV['MAIN_SERVER_TOKEN'] ?? $_SERVER['MAIN_SERVER_TOKEN'] ?? '';

if (!$gateway_token || !$main_server_token) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Gateway configuration error"]);
    exit;
}

// ============================================
// 2. קרא את ה-JSON מהבקשה
// ============================================
$inputJSON = file_get_contents('php://input');
$headers = apache_request_headers();

// ============================================
// 3. חלץ את ה-Timestamp וה-Signature מה-Headers
// ============================================
$client_timestamp = $headers['X-Timestamp'] ?? $_SERVER['HTTP_X_TIMESTAMP'] ?? '';
$client_signature = $headers['X-Signature'] ?? $_SERVER['HTTP_X_SIGNATURE'] ?? '';

if (empty($client_timestamp) || empty($client_signature)) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Missing security headers"]);
    exit;
}

// ============================================
// 4. בדוק שהזמן לא עבר (לא יותר מ-60 שניות)
// ============================================
if (abs(time() - (int)$client_timestamp) > 60) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Request expired"]);
    exit;
}

// ============================================
// 5. חשב מה צריכה להיות החתימה הנכונה
//    (בדיוק כמו שPython חישב)
// ============================================
$message = $client_timestamp . $inputJSON;
$expected_gateway_sig = hash_hmac(
    'sha256',
    $message,
    trim($gateway_token)
);

// ============================================
// 6. וודא שהחתימה תואמת
// ============================================
if (!hash_equals($expected_gateway_sig, $client_signature)) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Invalid signature from client"]);
    exit;
}

// ✅ החתימה מ-Python תקינה!

// ============================================
// 7. עכשיו צור חתימה חדשה לשרת הראשי
// ============================================
$new_timestamp = time();  // זמן חדש
$new_message = $new_timestamp . $inputJSON;  // timestamp חדש + JSON
$new_signature = hash_hmac(
    'sha256',
    $new_message,
    trim($main_server_token)
);

// ============================================
// 8. העבר את הבקשה לשרת הראשי
// ============================================
$real_api_url = "https://love-marriage.co.il/meet/pdf/invoice_collect_api.php";

$ch = curl_init($real_api_url);

$forward_headers = [
    "Content-Type: application/json",
    "X-Timestamp: " . $new_timestamp,
    "X-Signature: " . $new_signature,
    "X-Forwarded-For: " . $_SERVER['REMOTE_ADDR']
];

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $inputJSON);
curl_setopt($ch, CURLOPT_HTTPHEADER, $forward_headers);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// ============================================
// 9. החזר את התוצאה בחזרה ל-Python
// ============================================
http_response_code($http_code);
header('Content-Type: application/json; charset=utf-8');

if ($response === false) {
    echo json_encode([
        "status" => "error",
        "message" => "Gateway error: " . $curl_error
    ]);
} else {
    echo $response;
}
?>
