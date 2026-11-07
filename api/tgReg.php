<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

// OPTIONS রিকোয়েস্ট হ্যান্ডেল করা
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// userid / chat_id / id প্যারামিটার গ্রহণ করা
$userId = $_GET['userid'] ?? $_GET['chat_id'] ?? $_GET['id'] ?? '';

// POST মেথডে JSON বা ফর্ম ডাটা আসলে
if (empty($userId) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput, true);
    $userId = $jsonData['userid'] ?? $jsonData['chat_id'] ?? $jsonData['id'] ?? $_POST['userid'] ?? $_POST['chat_id'] ?? '';
}

$userId = trim($userId);

// ভ্যালিডেশন
if (empty($userId) || !ctype_digit($userId)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'Valid numeric user ID / chat ID is required'
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

// সোর্স API থেকে ডেটা ফেচ করা
$targetUrl = 'https://anon-tg-info.vercel.app/tgReg_beta?userid=' . urlencode($userId);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false || !empty($curlError)) {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'error'   => 'Failed to connect to source API: ' . $curlError
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

$data = json_decode($response, true);

if (!is_array($data)) {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'error'   => 'Invalid JSON received from source API'
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

// Developer নাম সম্পূর্ণ বাদ দেওয়া
unset($data['developer']);

// ক্লিন রেসপন্স প্রিন্ট
http_response_code($httpCode >= 200 && $httpCode < 300 ? 200 : $httpCode);
echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
