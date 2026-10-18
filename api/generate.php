<?php
header("Content-Type: text/plain; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

// Luhn Check Digit Generator
function generateLuhn($prefix, $length = 16) {
    $number = (string)$prefix;
    while (strlen($number) < ($length - 1)) {
        $number .= mt_rand(0, 9);
    }

    $reversed = strrev($number);
    $total = 0;

    for ($i = 0; $i < strlen($reversed); $i++) {
        $digit = intval($reversed[$i]);
        if ($i % 2 === 0) {
            $doubled = $digit * 2;
            $total += ($doubled > 9) ? ($doubled - 9) : $doubled;
        } else {
            $total += $digit;
        }
    }

    $checkDigit = (10 - ($total % 10)) % 10;
    return $number . $checkDigit;
}

// URL Parameters
$bin   = isset($_GET['bin']) ? preg_replace('/\D/', '', $_GET['bin']) : '434559';
$bin   = !empty($bin) ? $bin : '434559';
$month = isset($_GET['mm']) ? str_pad(preg_replace('/\D/', '', $_GET['mm']), 2, '0', STR_PAD_LEFT) : '';
$year  = isset($_GET['yy']) ? preg_replace('/\D/', '', $_GET['yy']) : '';
$count = isset($_GET['count']) ? intval($_GET['count']) : 10;

// Count Limit (1 to 100)
$count = max(1, min(100, $count));
$currentYear = intval(date('Y'));

$outputLines = [];

for ($i = 0; $i < $count; $i++) {
    $cardNumber = generateLuhn($bin, 16);

    // Month Logic
    $mm = (!empty($month) && intval($month) >= 1 && intval($month) <= 12) 
          ? $month 
          : str_pad(mt_rand(1, 12), 2, '0', STR_PAD_LEFT);

    // Year Logic (Default range: current year to +5 years)
    if (!empty($year)) {
        $yy = (strlen($year) === 2) ? '20' . $year : $year;
    } else {
        $yy = (string)($currentYear + mt_rand(0, 5));
    }

    // 3-digit CVV
    $cvv = str_pad(mt_rand(10, 999), 3, '0', STR_PAD_LEFT);

    $outputLines[] = "{$cardNumber}|{$mm}|{$yy}|{$cvv}";
}

echo implode("\n", $outputLines);
