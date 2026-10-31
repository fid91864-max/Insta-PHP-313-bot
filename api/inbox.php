<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$token = $_GET['token'] ?? null;
if (!$token) {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    $token = $body['token'] ?? null;
}

if (!$token) {
    http_response_code(400);
    echo json_encode(["error" => "Missing 'token' parameter."]);
    exit();
}

$decodedData = json_decode(base64_decode($token), true);
if (!$decodedData || !isset($decodedData['cookies'])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid session token."]);
    exit();
}

$baseUrl = "https://priyo.email";
$cookieJar = tempnam(sys_get_temp_dir(), 'cookie_inbox_');
file_put_contents($cookieJar, $decodedData['cookies']);
$csrfToken = $decodedData['csrf_token'] ?? null;

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
    "Upgrade-Insecure-Requests: 1"
];

// ১. পেজ লোড করে লাইভওয়্যার ইনবক্স স্ন্যাপশট সংগ্রহ করা
$ch = curl_init($baseUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
curl_setopt($ch, CURLOPT_HTTPHEADER, $browserHeaders);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
$html = curl_exec($ch);
curl_close($ch);

$inboxSnapshot = null;

// ফ্লেক্সিবল Regex ম্যাচিং (আগে বা পরে wire:snapshot যেখানেই থাকুক)
if (preg_match('/themes\.components\.inbox-message[^>]*wire:snapshot=["\']([^"\']+)["\']/i', $html, $m)) {
    $inboxSnapshot = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
} elseif (preg_match('/wire:snapshot=["\']([^"\']+)["\'][^>]*themes\.components\.inbox-message/i', $html, $m)) {
    $inboxSnapshot = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
}

if (!$inboxSnapshot) {
    @unlink($cookieJar);
    http_response_code(500);
    echo json_encode([
        "error" => "Failed to load inbox component.",
        "page_title" => (preg_match('/<title>(.*?)<\/title>/is', $html, $tm)) ? trim($tm[1]) : "No Title",
        "remote_preview" => substr(strip_tags((string)$html), 0, 200)
    ]);
    exit();
}

// কুকি থেকে XSRF-TOKEN পড়া
$cookieData = file_get_contents($cookieJar);
$xsrfToken = null;
if (preg_match('/XSRF-TOKEN\s+([^\s\r\n]+)/', $cookieData, $m)) {
    $xsrfToken = urldecode($m[1]);
}

if (!$csrfToken && $xsrfToken) {
    $csrfToken = $xsrfToken;
}

// ২. Messages ফেচ করা
$payload = [
    "_token" => $csrfToken,
    "components" => [
        [
            "snapshot" => $inboxSnapshot,
            "updates" => (object)[],
            "calls" => [
                [
                    "method" => "__dispatch",
                    "params" => ["fetchMessages", (object)[]],
                    "metadata" => []
                ]
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
$resp = curl_exec($ch);
curl_close($ch);
@unlink($cookieJar);

$messages = [];
$resJson = json_decode((string)$resp, true);

if (isset($resJson['components'][0]['snapshot'])) {
    $snapData = json_decode($resJson['components'][0]['snapshot'], true);
    $rawMsgs = $snapData['data']['messages'] ?? [];
    
    // Priyo Mail এর নেস্টেড অ্যারে থেকে মেসেজ পার্স করা
    if (!empty($rawMsgs) && is_array($rawMsgs) && isset($rawMsgs[0])) {
        $firstTier = $rawMsgs[0];
        if (is_array($firstTier) && isset($firstTier[0])) {
            foreach ($firstTier[0] as $item) {
                if (is_array($item) && isset($item['id'])) {
                    $messages[] = [
                        "id" => $item['id'],
                        "sender_name" => $item['sender_name'] ?? '',
                        "sender_email" => $item['sender_email'] ?? '',
                        "subject" => $item['subject'] ?? '',
                        "date" => $item['date'] ?? '',
                        "datediff" => $item['datediff'] ?? '',
                        "content" => $item['content'] ?? ''
                    ];
                }
            }
        }
    }
}

echo json_encode([
    "success" => true,
    "email" => $decodedData['email'] ?? '',
    "count" => count($messages),
    "messages" => $messages
], JSON_UNESCAPED_SLASHES);
