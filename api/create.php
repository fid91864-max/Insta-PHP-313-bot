<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$baseUrl = "https://priyo.email";
$username = $_GET['username'] ?? null;
$domain = $_GET['domain'] ?? 'priyomail.site';

if (!$username) {
    $rawInput = file_get_contents('php://input');
    $body = json_decode($rawInput, true);
    $username = $body['username'] ?? null;
    $domain = $body['domain'] ?? $domain;
}

if (!$username) {
    http_response_code(400);
    echo json_encode(["error" => "Missing 'username' parameter."]);
    exit();
}

$cookieJar = tempnam(sys_get_temp_dir(), 'cookie_');

$browserHeaders = [
    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36",
    "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
    "Accept-Language: en-US,en;q=0.9",
    "Sec-Ch-Ua: \"Chromium\";v=\"124\", \"Google Chrome\";v=\"124\", \"Not-A.Brand\";v=\"99\"",
    "Sec-Ch-Ua-Mobile: ?0",
    "Sec-Ch-Ua-Platform: \"Windows\"",
    "Sec-Fetch-Dest: document",
    "Sec-Fetch-Mode: navigate",
    "Sec-Fetch-Site: none",
    "Sec-Fetch-User: ?1",
    "Upgrade-Insecure-Requests: 1"
];

// ১. প্রাথমিক পেজ লোড
$ch = curl_init($baseUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
curl_setopt($ch, CURLOPT_HTTPHEADER, $browserHeaders);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
$html = curl_exec($ch);
curl_close($ch);

// কুকি ডেটা পড়া
$cookieData = file_get_contents($cookieJar);
$xsrfToken = null;
if (preg_match('/XSRF-TOKEN\s+([^\s\r\n]+)/', $cookieData, $m)) {
    $xsrfToken = urldecode($m[1]);
}

// CSRF Token খোঁজার মাল্টিপল মেথড
$csrfToken = null;

// মেথড ক: <meta name="csrf-token" content="...">
if (preg_match('/name=["\']csrf-token["\']\s+content=["\']([^"\']+)["\']/i', $html, $m)) {
    $csrfToken = $m[1];
} 
// মেথড খ: <input type="hidden" name="_token" value="...">
elseif (preg_match('/name=["\']_token["\']\s+value=["\']([^"\']+)["\']/i', $html, $m)) {
    $csrfToken = $m[1];
}
// মেথড গ: JS কনফিগে "csrf": "..." বা "token": "..."
elseif (preg_match('/(?:csrfToken|_token|csrf)["\']?\s*[:=]\s*["\']([a-zA-Z0-9]{40,})["\']/i', $html, $m)) {
    $csrfToken = $m[1];
}
// মেথড ঘ: যদি HTML-এ না থাকে, XSRF-TOKEN কুকি নিজেই টোকেন হিসেবে ব্যবহৃত হয়
elseif ($xsrfToken) {
    $csrfToken = $xsrfToken;
}

// Livewire Component Snapshot সংগ্রহ
$snapshot = null;
if (preg_match('/themes\.components\.change-account[^>]*wire:snapshot=["\']([^"\']+)["\']/i', $html, $m)) {
    $snapshot = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
} elseif (preg_match('/wire:snapshot=["\']([^"\']+)["\'][^>]*themes\.components\.change-account/i', $html, $m)) {
    $snapshot = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
}

if (!$snapshot || !$csrfToken) {
    http_response_code(500);
    echo json_encode([
        "error" => "Failed to extract session metadata.",
        "found_csrf" => (bool)$csrfToken,
        "found_snapshot" => (bool)$snapshot
    ]);
    exit();
}

// ২. Change Email Address কল করা
$payload = [
    "_token" => $csrfToken,
    "components" => [
        [
            "snapshot" => $snapshot,
            "updates" => [
                "username" => $username,
                "domain" => $domain
            ],
            "calls" => [
                ["method" => "changeEmailAddress", "params" => [], "metadata" => []]
            ]
        ]
    ]
];

$postHeaders = [
    "Content-Type: application/json",
    "Origin: {$baseUrl}",
    "Referer: {$baseUrl}/",
    "X-Livewire: 1",
    "X-Requested-With: XMLHttpRequest",
    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36"
];

if ($xsrfToken) {
    $postHeaders[] = "X-XSRF-TOKEN: {$xsrfToken}";
}
if ($csrfToken) {
    $postHeaders[] = "X-CSRF-TOKEN: {$csrfToken}";
}

$ch = curl_init("{$baseUrl}/livewire/update");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, $postHeaders);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
$updateResp = curl_exec($ch);
curl_close($ch);

$finalCookies = file_get_contents($cookieJar);
@unlink($cookieJar);

// স্টেট সংরক্ষণ প্যাকেজ (Base64 Token)
$sessionPackage = [
    "cookies" => $finalCookies,
    "csrf_token" => $csrfToken,
    "email" => "{$username}@{$domain}"
];
$userToken = base64_encode(json_encode($sessionPackage));

echo json_encode([
    "success" => true,
    "email" => "{$username}@{$domain}",
    "token" => $userToken
]);
