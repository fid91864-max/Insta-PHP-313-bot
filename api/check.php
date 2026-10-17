<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed"]);
    exit;
}

// ইনপুট থেকে IMEI পড়া
$data = json_decode(file_get_contents('php://input'), true);
$imei = isset($data['imei']) ? trim($data['imei']) : '';

// IMEI ভ্যালিডেশন (অবশ্যই ১৫ ডিজিটের সংখ্যা হতে হবে)
if (!preg_match('/^[0-9]{15}$/', $imei)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "সঠিক ১৫ ডিজিটের IMEI নাম্বার দিন।"]);
    exit;
}

// -------------------------------------------------------------
// এখানে যেকোনো অফিসিয়াল বা অনুমোদিত IMEI API সার্ভিস সংযুক্ত করবেন
// উদাহরণস্বরূপ: RapidAPI / IMEI Check Provider
// -------------------------------------------------------------
/*
$apiKey = "YOUR_OFFICIAL_API_KEY";
$apiEndpoint = "https://api-provider.com/check?imei=" . $imei;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiEndpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $apiKey"]);
$apiResponse = curl_exec($ch);
curl_close($ch);
$deviceData = json_decode($apiResponse, true);
*/

// ডেমো রেসপন্স (লগে পাওয়া ডেটা স্ট্রাকচার অনুযায়ী)
$sampleData = [
    "imei" => $imei,
    "brand" => "REALME",
    "model" => "Narzo 60 Pro",
    "code_name" => "RMX3771",
    "release_date" => "2023",
    "operating_systems" => "Android 13",
    "chipset" => "Mediatek MT6877V Dimensity 7050",
    "ram" => "8 GB",
    "storage" => "128 GB / 256 GB / 1 TB",
    "display" => "6.7\" AMOLED, 120Hz, 1080x2412 px",
    "camera" => "100 MP (Main) + 16 MP (Front)",
    "battery" => "5000 mAh (67W Fast Charging)"
];

echo json_encode([
    "status" => "success",
    "data" => $sampleData
]);
