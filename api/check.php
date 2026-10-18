<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

$rawInput = $_GET['bin'] ?? $_GET['card'] ?? '';
$cleanInput = preg_replace('/\D/', '', $rawInput);

if (empty($cleanInput) || strlen($cleanInput) < 6) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Valid BIN or card number required! (e.g. ?bin=453201)"
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$bin = substr($cleanInput, 0, 6);

function validateLuhn($number) {
    $sum = 0;
    $length = strlen($number);
    $parity = $length % 2;
    for ($i = $length - 1; $i >= 0; $i--) {
        $digit = intval($number[$i]);
        if ($i % 2 === $parity) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        $sum += $digit;
    }
    return ($sum % 10 === 0);
}

$luhnStatus = (strlen($cleanInput) >= 13) 
              ? (validateLuhn($cleanInput) ? "Valid" : "Invalid") 
              : "BIN Check Only";

$ch = curl_init("https://data.handyapi.com/bin/" . $bin);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 8);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true) ?: [];

echo json_encode([
    "status" => "success",
    "bin" => $bin,
    "luhn_status" => $luhnStatus,
    "scheme" => $data['Scheme'] ?? "Unknown",
    "type" => $data['Type'] ?? "Unknown",
    "tier" => $data['CardTier'] ?? "Unknown",
    "bank" => $data['Issuer'] ?? "Unknown",
    "country" => $data['Country']['Name'] ?? "Unknown",
    "country_code" => $data['Country']['A2'] ?? ""
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
