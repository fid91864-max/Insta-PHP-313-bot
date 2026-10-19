<?php
error_reporting(0);
ini_set('display_errors', 0);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Only POST method is allowed!"]);
    exit;
}

$tempFile = null;
$fileName = 'scan_image.jpg';
$imageUrl = trim($_POST['url'] ?? '');

// ছবি প্রসেসিং (ফাইল আপলোড অথবা URL)
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $tempFile = $_FILES['image']['tmp_name'];
    $fileName = $_FILES['image']['name'];
} elseif (!empty($imageUrl)) {
    if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "ভুল ইমেজ URL দেওয়া হয়েছে!"]);
        exit;
    }

    $tempFile = tempnam(sys_get_temp_dir(), 'ai_detect_');
    $imgData = @file_get_contents($imageUrl);
    if (!$imgData) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "ইমেজ ডাউনলোড করা সম্ভব হয়নি!"]);
        exit;
    }
    file_put_contents($tempFile, $imgData);
    $fileName = basename(parse_url($imageUrl, PHP_URL_PATH)) ?: 'image.jpg';
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "কোনো ছবি বা লিঙ্ক পাওয়া যায়নি!"]);
    exit;
}

// ধাপ ১: DeepAI আপলোড
$cFile = new CURLFile($tempFile, mime_content_type($tempFile) ?: 'image/jpeg', $fileName);
$chUpload = curl_init("https://api.deepai.org/ai-image-detector-upload");
curl_setopt_array($chUpload, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => ["image" => $cFile],
    CURLOPT_HTTPHEADER     => [
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36",
        "Origin: https://deepai.org",
        "Referer: https://deepai.org/ai-image-detector"
    ],
    CURLOPT_TIMEOUT        => 25,
    CURLOPT_SSL_VERIFYPEER => false
]);

$uploadRes = curl_exec($chUpload);
$uploadCode = curl_getinfo($chUpload, CURLINFO_HTTP_CODE);
curl_close($chUpload);

// টেম্পোরারি ফাইল ডিলিট (যদি URL থেকে ডাউনলোড করা হয়ে থাকে)
if ($tempFile && file_exists($tempFile) && !isset($_FILES['image'])) {
    @unlink($tempFile);
}

$uploadData = json_decode($uploadRes, true);
$taskId = $uploadData['id'] ?? null;

if ($uploadCode !== 200 || empty($taskId)) {
    http_response_code(502);
    echo json_encode(["status" => "error", "message" => "DeepAI সার্ভারের সাথে সংযোগ বিচ্ছিন্ন!"]);
    exit;
}

// ধাপ ২: স্ক্যান রেজাল্ট ফেচ করা (Query loop)
$maxAttempts = 12;
$finalData = null;

for ($i = 0; $i < $maxAttempts; $i++) {
    usleep(700000); // ০.৭ সেকেন্ড বিরতি
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
        CURLOPT_SSL_VERIFYPEER => false
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
    echo json_encode(["status" => "error", "message" => "স্ক্যান সম্পন্ন হতে বেশি সময় নিচ্ছে, আবার চেষ্টা করুন!"]);
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
