<?php
declare(strict_types=1);

error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$rawUsername = $_GET['username'] ?? '';
$username = trim((string)$rawUsername);
$username = ltrim($username, '@');

if ($username === '' || !preg_match('/^[a-zA-Z0-9._]{1,30}$/', $username)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'developer' => 'Abdullah Al Hasib 313',
        'message' => 'Invalid username format.'
    ]);
    exit;
}

$upstreamUrl = 'https://www.instagram.com/' . rawurlencode($username) . '/';

// আপনার টেস্ট করা প্রমাণিত মোবাইল হেডার
$headers = [
    'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
    'accept-language: en-GB,en;q=0.9',
    'dpr: 1',
    'priority: u=0, i',
    'sec-ch-prefers-color-scheme: dark',
    'sec-ch-ua: "Google Chrome";v="141", "Not?A_Brand";v="8", "Chromium";v="141"',
    'sec-ch-ua-mobile: ?1',
    'sec-ch-ua-model: "Nexus 5"',
    'sec-ch-ua-platform: "Android"',
    'sec-ch-ua-platform-version: "6.0"',
    'sec-fetch-dest: document',
    'sec-fetch-mode: navigate',
    'sec-fetch-site: none',
    'sec-fetch-user: ?1',
    'upgrade-insecure-requests: 1',
    'user-agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36',
    'viewport-width: 1000'
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $upstreamUrl,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 3,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_ENCODING => ''
]);

$response = curl_exec($ch);
$httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpStatus !== 200 || empty($response)) {
    http_response_code(502);
    echo json_encode([
        'status' => 'error',
        'developer' => 'Abdullah Al Hasib 313',
        'message' => 'Failed to reach profile from node.'
    ]);
    exit;
}

$extracted = [
    'username' => $username,
    'full_name' => $username,
    'biography' => 'No public bio broadcasted.',
    'profile_pic_url' => '',
    'is_verified' => false,
    'is_private' => false,
    'posts_count' => '0',
    'followers_count' => '0',
    'following_count' => '0',
    'category_name' => 'Public Entity'
];

// ১. OpenGraph Description থেকে মেট্রিক্স পার্সিং
if (preg_match('/<meta\s+property=["\']og:description["\']\s+content=["\']([^"\']+)["\']/i', $response, $mDesc) ||
    preg_match('/<meta\s+name=["\']description["\']\s+content=["\']([^"\']+)["\']/i', $response, $mDesc)) {
    $desc = html_entity_decode($mDesc[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // "XX Followers, YY Following, ZZ Posts" ম্যাচিং
    if (preg_match('/([0-9.,KkMmBb]+)\s*Followers.*?([0-9.,KkMmBb]+)\s*Following.*?([0-9.,KkMmBb]+)\s*Posts/is', $desc, $mStats)) {
        $extracted['followers_count'] = $mStats[1];
        $extracted['following_count'] = $mStats[2];
        $extracted['posts_count'] = $mStats[3];
    }
}

// ২. OpenGraph Image থেকে আসল প্রোফাইল পিকচার পার্সিং
if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\']([^"\']+)["\']/i', $response, $mImg)) {
    $extracted['profile_pic_url'] = html_entity_decode($mImg[1]);
}

// ৩. OpenGraph Title থেকে আসল নাম পার্সিং
if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\']([^"\']+)["\']/i', $response, $mTitle)) {
    $title = html_entity_decode($mTitle[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (preg_match('/^(.*?)\s*\(@[a-zA-Z0-9._]+\)/u', $title, $mName)) {
        $extracted['full_name'] = trim($mName[1]);
    }
}

// ৪. JSON স্ক্রিপ্ট ট্যাগ থেকে সরাসরি বায়ো ও অন্যান্য ডেটা খোঁজা
if (preg_match_all('/<script\s+type="application\/json"[^>]*>(.*?)<\/script>/is', $response, $scripts)) {
    foreach ($scripts[1] as $content) {
        if (strpos($content, 'biography') !== false || strpos($content, 'user') !== false) {
            $parsed = json_decode($content, true);
            if (is_array($parsed)) {
                array_walk_recursive($parsed, function($val, $key) use (&$extracted) {
                    if ($key === 'biography' && is_string($val) && !empty($val) && $extracted['biography'] === 'No public bio broadcasted.') {
                        $extracted['biography'] = $val;
                    }
                    if ($key === 'full_name' && is_string($val) && !empty($val) && $extracted['full_name'] === $extracted['username']) {
                        $extracted['full_name'] = $val;
                    }
                    if (($key === 'profile_pic_url_hd' || $key === 'profile_pic_url') && empty($extracted['profile_pic_url']) && is_string($val)) {
                        $extracted['profile_pic_url'] = $val;
                    }
                    if ($key === 'is_verified' && is_bool($val)) {
                        $extracted['is_verified'] = $val;
                    }
                    if ($key === 'is_private' && is_bool($val)) {
                        $extracted['is_private'] = $val;
                    }
                    if ($key === 'edge_followed_by' && is_array($val) && isset($val['count'])) {
                        $extracted['followers_count'] = number_format((float)$val['count']);
                    }
                    if ($key === 'edge_follow' && is_array($val) && isset($val['count'])) {
                        $extracted['following_count'] = number_format((float)$val['count']);
                    }
                    if ($key === 'edge_owner_to_timeline_media' && is_array($val) && isset($val['count'])) {
                        $extracted['posts_count'] = number_format((float)$val['count']);
                    }
                });
            }
        }
    }
}

// বায়ো যদি এখনও ফাঁকা থাকে এবং মেটাতে থাকে
if ($extracted['biography'] === 'No public bio broadcasted.' && !empty($desc)) {
    if (preg_match('/Posts\s*-\s*See Instagram photos and videos from .*?:\s*"(.*?)"/is', $desc, $mBio)) {
        $extracted['biography'] = trim($mBio[1]);
    }
}

http_response_code(200);
echo json_encode([
    'status' => 'success',
    'developer' => 'Abdullah Al Hasib 313',
    'telegram' => '@abdullah_al_hasib_313',
    'data' => [
        'username' => $extracted['username'],
        'full_name' => $extracted['full_name'],
        'biography' => $extracted['biography'],
        'profile_pic_url' => $extracted['profile_pic_url'],
        'public_url' => 'https://www.instagram.com/' . rawurlencode($username) . '/',
        'is_verified' => (bool)$extracted['is_verified'],
        'is_private' => (bool)$extracted['is_private'],
        'metrics' => [
            'posts' => $extracted['posts_count'],
            'followers' => $extracted['followers_count'],
            'following' => $extracted['following_count']
        ],
        'category' => $extracted['category_name']
    ]
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
