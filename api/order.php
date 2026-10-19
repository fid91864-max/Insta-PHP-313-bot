<?php
error_reporting(0);
ini_set('display_errors', 0);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Only POST allowed!"]);
    exit;
}

$action = trim($_POST['action'] ?? 'order');
$link = trim($_POST['link'] ?? '');
$service = trim($_POST['service'] ?? '232'); // 232 = TikTok Likes
$cfToken = trim($_POST['cf_token'] ?? '');
$uuid = trim($_POST['uuid'] ?? '');

if (empty($link)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Video link is required!"]);
    exit;
}

$commonHeaders = [
    "Host: api.zefame.com",
    "User-Agent: Mozilla/5.0 (Linux; Android 14; 2409BRN2CY Build/UP1A.231005.007) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.8059.43 Mobile Safari/537.36",
    "Accept: application/json, text/javascript, */*; q=0.01",
    "Content-Type: application/x-www-form-urlencoded; charset=UTF-8",
    "Origin: https://zefame.com",
    "Referer: https://zefame.com/",
    "Accept-Language: en-US,en;q=0.9"
];

// ধাপ ১: Video ID সংগ্রহ করা
if ($action === 'checkVideoId') {
    $ch = curl_init("https://api.zefame.com/api_free.php?lang=en");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            "action" => "checkVideoId",
            "link"   => $link
        ]),
        CURLOPT_HTTPHEADER     => $commonHeaders,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($res, true);
    if ($httpCode === 200 && !empty($json)) {
        echo json_encode($json);
    } else {
        http_response_code(502);
        echo json_encode(["success" => false, "message" => "Failed to extract Video ID."]);
    }
    exit;
}

// ধাপ ২: অর্ডার প্লেস করা
if ($action === 'order') {
    $videoId = trim($_POST['videoId'] ?? '');

    if (empty($videoId)) {
        // যদি ফ্রন্টএন্ড থেকে না আসে তবে সরাসরি এক্সট্র্যাক্ট করা
        $chCheck = curl_init("https://api.zefame.com/api_free.php?lang=en");
        curl_setopt_array($chCheck, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                "action" => "checkVideoId",
                "link"   => $link
            ]),
            CURLOPT_HTTPHEADER     => $commonHeaders,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $checkRes = curl_exec($chCheck);
        curl_close($chCheck);

        $parsed = json_decode($checkRes, true);
        $videoId = $parsed['data']['videoId'] ?? '';
    }

    if (empty($videoId)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid TikTok video ID."]);
        exit;
    }

    if (empty($uuid)) {
        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    $orderPayload = [
        "service"  => $service,
        "link"     => $link,
        "uuid"     => $uuid,
        "videoId"  => $videoId,
        "cf_token" => $cfToken
    ];

    $chOrder = curl_init("https://api.zefame.com/api_free.php?action=order&lang=en");
    curl_setopt_array($chOrder, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($orderPayload),
        CURLOPT_HTTPHEADER     => $commonHeaders,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $orderRes = curl_exec($chOrder);
    $orderHttpCode = curl_getinfo($chOrder, CURLINFO_HTTP_CODE);
    curl_close($chOrder);

    $result = json_decode($orderRes, true);
    if ($orderHttpCode === 200 && !empty($result)) {
        echo json_encode([
            "success"   => $result['success'] ?? false,
            "developer" => "@abdullah_al_hasib_313",
            "message"   => $result['message'] ?? 'Order process complete',
            "data"      => $result['data'] ?? null
        ]);
    } else {
        http_response_code(502);
        echo json_encode([
            "success" => false,
            "developer" => "@abdullah_al_hasib_313",
            "message" => "Zefame server response error"
        ]);
    }
    exit;
}
