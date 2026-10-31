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
$cookieJar = tempnam(sys_get_temp_dir(), 'cookie_');
file_put_contents($cookieJar, $decodedData['cookies']);
$csrfToken = $decodedData['csrf_token'] ?? null;

// Page theke inbox-message component snapshot ana
$ch = curl_init($baseUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Linux; Android 14; Mobile) AppleWebKit/537.36 Chrome/120.0.0.0 Mobile Safari/537.36");
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$html = curl_exec($ch);
curl_close($ch);

$inboxSnapshot = null;
if (preg_match('/wire:name=["\']themes\.components\.inbox-message["\'][^>]*wire:snapshot=["\']([^"\']+)["\']/i', $html, $m)) {
    $inboxSnapshot = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
}

if (!$inboxSnapshot) {
    @unlink($cookieJar);
    http_response_code(500);
    echo json_encode(["error" => "Failed to load inbox component."]);
    exit();
}

$cookieData = file_get_contents($cookieJar);
$xsrfToken = null;
if (preg_match('/XSRF-TOKEN\s+([^\s\r\n]+)/', $cookieData, $m)) {
    $xsrfToken = urldecode($m[1]);
}

// Fetch Messages
$payload = [
    "_token" => $csrfToken,
    "components" => [
        [
            "snapshot" => $inboxSnapshot,
            "updates" => (object)[],
            "calls" => [
                ["method" => "__dispatch", "params" => ["fetchMessages", (object)[]], "metadata" => []]
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
$resp = curl_exec($ch);
curl_close($ch);
@unlink($cookieJar);

$messages = [];
$resJson = json_decode($resp, true);

if (isset($resJson['components'][0]['snapshot'])) {
    $snapData = json_decode($resJson['components'][0]['snapshot'], true);
    $rawMsgs = $snapData['data']['messages'] ?? [];
    if (!empty($rawMsgs) && isset($rawMsgs[0][0])) {
        foreach ($rawMsgs[0][0] as $item) {
            if (is_array($item) && isset($item['id'])) {
                $messages[] = [
                    "id" => $item['id'],
                    "sender_name" => $item['sender_name'] ?? '',
                    "sender_email" => $item['sender_email'] ?? '',
                    "subject" => $item['subject'] ?? '',
                    "date" => $item['date'] ?? '',
                    "content" => $item['content'] ?? ''
                ];
            }
        }
    }
}

echo json_encode([
    "email" => $decodedData['email'] ?? '',
    "count" => count($messages),
    "messages" => $messages
]);
