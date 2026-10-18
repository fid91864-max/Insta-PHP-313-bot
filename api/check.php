<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed"]);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
$rawCard = trim($data['card'] ?? '');

// ১. ইনপুট স্যানিটাইজেশন (ফরম্যাট: NUMBER|MM|YY|CVV অথবা শুধু NUMBER)
$parts = explode('|', str_replace([' ', '/', ':'], '|', $rawCard));
$cardNumber = preg_replace('/\D/', '', $parts[0] ?? '');
$month = $parts[1] ?? '';
$year  = $parts[2] ?? '';
$cvv   = $parts[3] ?? '';

if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "সঠিক কার্ড নাম্বার দিন (১৩-১৯ ডিজিট)"]);
    exit;
}

// ২. Luhn Algorithm (MOD 10 ভ্যালিডেশন)
function validateLuhn($number) {
    $sum = 0;
    $length = strlen($number);
    $parity = $length % 2;
    for ($i = $length - 1; $i >= 0; $i--) {
        $digit = intval($number[$i]);
        if ($i % 2 == $parity) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        $sum += $digit;
    }
    return ($sum % 10 === 0);
}

$isLuhnValid = validateLuhn($cardNumber);

if (!$isLuhnValid) {
    echo json_encode([
        "status" => "invalid",
        "message" => "Luhn Check Failed (ভুল বা ফেক কার্ড নম্বর)",
        "card_number" => substr($cardNumber, 0, 6) . "******" . substr($cardNumber, -4)
    ]);
    exit;
}

// ৩. ওপেন BIN ডেটাবেজ লুকআপ (প্রথম ৬ ডিজিট)
$bin = substr($cardNumber, 0, 6);
$binApiUrl = "https://data.handyapi.com/bin/" . $bin;

$ch = curl_init($binApiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 6);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
$binResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$binData = json_decode($binResponse, true) ?: [];

echo json_encode([
    "status" => "success",
    "validation" => "Passed (Luhn Algorithm Valid)",
    "masked_card" => substr($cardNumber, 0, 6) . "******" . substr($cardNumber, -4),
    "bin_info" => [
        "bin" => $bin,
        "scheme" => $binData['Scheme'] ?? 'Unknown',
        "type" => $binData['Type'] ?? 'Credit/Debit',
        "brand" => $binData['CardTier'] ?? 'Standard',
        "issuer" => $binData['Issuer'] ?? 'Unknown Bank',
        "country" => $binData['Country']['Name'] ?? 'Unknown',
        "country_flag" => $binData['Country']['A2'] ?? ''
    ]
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
