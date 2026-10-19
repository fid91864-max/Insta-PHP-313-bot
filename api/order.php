<?php
error_reporting(0);
ini_set('display_errors', 0);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Only POST allowed!"]);
    exit;
}

$tempFilePath = null;
$fileName = 'image.jpg';
$mimeType = 'image/jpeg';

if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $originalName = $_FILES['image']['name'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $fileName = 'scan_' . time() . '.' . ($ext ?: 'jpg');
    
    // Mime type নির্ধারণ (mime_content_type ছাড়া নিরাপদ পদ্ধতি)
    $mimes = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp'
    ];
    $mimeType = $mimes[$ext] ?? ($_FILES['image']['type'] ?: 'image/jpeg');

    $tempFilePath = sys_get_temp_dir() . '/' . $fileName;
    if (!move_uploaded_file($_FILES['image']['tmp_name'], $tempFilePath)) {
        // move_uploaded_file ব্যর্থ হলে কপি চেষ্টা
        copy($_FILES['image']['tmp_name'], $tempFilePath);
    }
} else {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "ছবি আপলোড হয়নি বা ফাইলের সাইজ বেশি বড়!"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!file_exists($tempFilePath) || filesize($tempFilePath) === 0) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "টেম্পোরারি ফাইল তৈরি করা সম্ভব হয়নি!"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ধাপ ১: DeepAI-তে আপলোড
$cFile = new CURLFile($tempFilePath, $mimeType, $fileName);

$chUpload = curl_init("https://api.deepai.org/ai-image-detector-upload");
curl_setopt_array($chUpload, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => ["image" => $cFile],
    CURLOPT_HTTPHEADER     => [
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36",
        "Origin: https://deepai.org",
        "Referer: https://deepai.org/ai-image-detector",
        "Accept: */*",
        "Accept-Language: en-US,en;q=0.9"
    ],
    CURLOPT_TIMEOUT        => 25,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false
]);

$uploadRes = curl_exec($chUpload);
$uploadCode = curl_getinfo($chUpload, CURLINFO_HTTP_CODE);
$curlErr = curl_error($chUpload);
curl_close($chUpload);

// কাজ শেষে টেম্পোরারি ফাইল রিমুভ
if ($tempFilePath && file_exists($tempFilePath)) {
    @unlink($tempFilePath);
}

$uploadData = json_decode($uploadRes, true);
$taskId = $uploadData['id'] ?? null;

if ($uploadCode !== 200 || empty($taskId)) {
    http_response_code(502);
    echo json_encode([
        "status"    => "error",
        "code"      => $uploadCode,
        "curl_err"  => $curlErr,
        "message"   => "DeepAI সার্ভারের সাথে সংযোগ বিচ্ছিন্ন হয়েছে! (HTTP {$uploadCode})"
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ধাপ ২: স্ক্যান রেজাল্ট ফেচ করা (Query polling)
$maxAttempts = 12;
$finalData = null;

for ($i = 0; $i < $maxAttempts; $i++) {
    usleep(750000); // ০.৭৫ সেকেন্ড বিরতি
    $chQuery = curl_init("https://api.deepai.org/ai-image-detector-query");
    curl_setopt_array($chQuery, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(["id" => $taskId]),
        CURLOPT_HTTPHEADER     => [
            "Content-Type: application/json",
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36",
            "Origin: https://deepai.org",
            "Referer: https://deepai.org/ai-image-detector"
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $queryRes = curl_exec($chQuery);
    curl_close($chQuery);

    $parsed = json_decode($queryRes, true);
    if ($parsed && ($parsed['status'] ?? '') === 'done') {
        $finalData = $parsed;
        break;
    }
}

if (!$finalData) {
    http_response_code(504);
    echo json_encode([
        "status" => "error",
        "message" => "স্ক্যান সম্পন্ন হতে বেশি সময় নিচ্ছে, আবার চেষ্টা করুন!"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$details = $finalData['result_details'] ?? [];
$aiPercent = round(floatval($finalData['result'] ?? 0), 2);
$humanPercent = round(100 - $aiPercent, 2);
$isAi = (($details['final_result'] ?? '') === 'AI' || $aiPercent > 50);

echo json_encode([
    "status"           => "success",
    "developer"        => "@abdullah_al_hasib_313",
    "verdict"          => $isAi ? "AI Generated" : "Real Camera",
    "is_ai"            => $isAi,
    "ai_percentage"    => $aiPercent,
    "human_percentage" => $humanPercent,
    "task_id"          => $taskId,
    "meta_info"        => $details['metadata'][0] ?? "Analysis Done"
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
