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

// 1. Initial Page Load
$ch = curl_init($baseUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Linux; Android 14; Mobile) AppleWebKit/537.36 Chrome/120.0.0.0 Mobile Safari/537.36");
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$html = curl_exec($ch);
curl_close($ch);

// CSRF Token extract
$csrfToken = null;
if (preg_match('/<meta\s+name=["\']csrf-token["\']\s+content=["\']([^"\']+)["\']/i', $html, $m)) {
    $csrfToken = $m[1];
} elseif (preg_match('/csrfToken["\']?\s*:\s*["\']([^"\']+)["\']/i', $html, $m)) {
    $csrfToken = $m[1];
}

// Livewire Component Snapshot extract
$snapshot = null;
if (preg_match('/wire:name=["\']themes\.components\.change-account["\'][^>]*wire:snapshot=["\']([^"\']+)["\']/i', $html, $m)) {
    $snapshot = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
}

if (!$snapshot || !$csrfToken) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to extract session metadata."]);
    exit();
}

// Cookies read kora
$cookieData = file_get_contents($cookieJar);
$xsrfToken = null;
if (preg_match('/XSRF-TOKEN\s+([^\s\r\n]+)/', $cookieData, $m)) {
    $xsrfToken = urldecode($m[1]);
}

// 2. Change Email Address call
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

$headers = [
    "Content-Type: application/json",
    "Origin: {$baseUrl}",
    "Referer: {$baseUrl}/",
    "X-Livewire: 1",
    "X-Requested-With: XMLHttpRequest",
    "User-Agent: Mozilla/5.0 (Linux; Android 14; Mobile) AppleWebKit/537.36 Chrome/120.0.0.0 Mobile Safari/537.36"
];

if ($xsrfToken) {
    $headers[] = "X-XSRF-TOKEN: {$xsrfToken}";
}
if ($csrfToken) {
    $headers[] = "X-CSRF-TOKEN: {$csrfToken}";
}

$ch = curl_init("{$baseUrl}/livewire/update");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
$updateResp = curl_exec($ch);
curl_close($ch);

$finalCookies = file_get_contents($cookieJar);
@unlink($cookieJar);

// User-er session state encrypt/base64 kora jate serverless-e maintain hoy
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
