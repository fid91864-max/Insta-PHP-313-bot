<?php
/**
 * ============================================================
 *  PROJECT: INSTA INFO 313 — BACKEND API
 *  Developer: Abdullah Al Hasib 313
 *  Telegram: @abdullah_al_hasib_313
 * ============================================================
 */

error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Input validation & sanitization
$username = isset($_GET['username']) ? trim($_GET['username']) : '';
$username = preg_replace('/[^a-zA-Z0-9._]/', '', $username);

if (empty($username)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'অ্যাকাউন্ট ইউজারনেম প্রদান করা হয়নি। অনুগ্রহ করে সঠিক ইউজারনেম দিন।'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strlen($username) > 30) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'ইউজারনেমটি অত্যন্ত বড়। দয়া করে সঠিক ইউজারনেম লিখুন।'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Authorized Upstream Public Profile Fetcher
$apiUrl = "https://i.instagram.com/api/v1/users/web_profile_info/?username=" . urlencode($username);
$headers = [
    "User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1",
    "X-IG-App-ID: 936619743392459",
    "Accept: */*",
    "Accept-Language: en-US,en;q=0.9"
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || empty($response)) {
    http_response_code(404);
    echo json_encode([
        'status' => 'error',
        'message' => 'প্রোফাইলটি খুঁজে পাওয়া যায়নি অথবা অ্যাকাউন্টটি প্রাইভেট/লিমিটেড।'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode($response, true);
if (!isset($data['data']['user']) || empty($data['data']['user'])) {
    http_response_code(404);
    echo json_encode([
        'status' => 'error',
        'message' => 'অ্যাকাউন্ট ডাটা সংগ্রহ করা সম্ভব হয়নি।'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$user = $data['data']['user'];

// Structured Public Profile Response
$profileData = [
    'status' => 'success',
    'profile' => [
        'username' => $user['username'] ?? $username,
        'full_name' => $user['full_name'] ?? 'N/A',
        'biography' => $user['biography'] ?? '',
        'profile_pic_url' => $user['hd_profile_pic_url_info']['url'] ?? ($user['profile_pic_url_hd'] ?? ($user['profile_pic_url'] ?? '')),
        'profile_url' => 'https://instagram.com/' . ($user['username'] ?? $username),
        'category' => $user['category_name'] ?? ($user['business_category_name'] ?? 'Public Figure / Personal'),
        'is_verified' => (bool)($user['is_verified'] ?? false),
        'is_private' => (bool)($user['is_private'] ?? false),
        'posts_count' => $user['edge_owner_to_timeline_media']['count'] ?? 0,
        'followers_count' => $user['edge_followed_by']['count'] ?? 0,
        'following_count' => $user['edge_followතික']['count'] ?? ($user['edge_follow']['count'] ?? 0),
        'lookup_timestamp' => date('Y-m-d H:i:s')
    ],
    'developer' => [
        'name' => 'Abdullah Al Hasib 313',
        'telegram' => '@abdullah_al_hasib_313'
    ]
];

echo json_encode($profileData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
