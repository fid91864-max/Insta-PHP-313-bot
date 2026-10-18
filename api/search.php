<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

// ১. ইনপুট নেওয়া ও স্যানিটাইজ করা
$rawPhone = $_GET['phone'] ?? $_GET['q'] ?? '';
$phone = trim(str_replace(' ', '+', $rawPhone));

if (empty($phone)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Phone number is required! Example: ?phone=0548824343"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ২. মূল API সিক্রেট এনকোডিং (কোড সরাসরি স্ক্যান করলেও লিংক ধরা পড়বে না)
// মূল লিংক: https://israel-number-to-info-313.vercel.app/api/search
$encodedHost = "aHR0cHM6Ly9pc3JhZWwtbnVtYmVyLXRvLWluZm8tMzEzLnZlcmNlbC5hcHA=";
$baseEndpoint = base64_decode($encodedHost) . "/api/search";

// ৩. রিকোয়েস্ট প্যারামিটার তৈরি
$queryUrl = $baseEndpoint . "?phone=" . urlencode($phone);

// ৪. ব্যাকএন্ড থেকে ডেটা ফেচ (cURL)
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $queryUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// মেমোরি থেকে সিক্রেট ভ্যারিয়েবল মুছে ফেলা
unset($encodedHost, $baseEndpoint, $queryUrl);

// ৫. আউটপুট রিটার্ন
if ($curlError) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Internal gateway timeout or connection error"
    ]);
    exit;
}

if ($httpCode === 200 && !empty($response)) {
    // সফল রেসপন্স সরাসরি ক্লিন JSON ফরম্যাটে পাঠানো
    echo $response;
} else {
    http_response_code($httpCode ?: 500);
    echo json_encode([
        "status" => "error",
        "http_code" => $httpCode,
        "message" => "Data not found or upstream error"
    ]);
}
