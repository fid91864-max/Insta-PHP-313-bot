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

$profileUrl = 'https://www.instagram.com/' . rawurlencode($username) . '/';

$data = [
    'username' => $username,
    'full_name' => $username,
    'biography' => 'No public bio broadcasted.',
    'profile_pic_url' => '',
    'public_url' => $profileUrl,
    'is_verified' => false,
    'is_private' => false,
    'posts' => 'Unknown',
    'followers' => 'Unknown',
    'following' => 'Unknown',
    'category' => 'Public Profile'
];

$found = false;

// মেথড ১: DuckDuckGo মেটা-সার্চ রিলে (Vercel IP ব্লক হয় না)
$ddgUrl = 'https://html.duckduckgo.com/html/?q=site:instagram.com/' . urlencode($username);
$ch = curl_init($ddgUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 6,
    CURLOPT_HTTPHEADER => [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Accept-Language: en-US,en;q=0.9'
    ],
    CURLOPT_SSL_VERIFYPEER => false
]);
$ddgRes = curl_exec($ch);
curl_close($ch);

if (!empty($ddgRes)) {
    // "XX Followers, YY Following, ZZ Posts" এক্সট্র্যাক্ট করা
    if (preg_match('/([0-9.,KkMmBb]+)\s*Followers,\s*([0-9.,KkMmBb]+)\s*Following,\s*([0-9.,KkMmBb]+)\s*Posts/is', $ddgRes, $m)) {
        $data['followers'] = $m[1];
        $data['following'] = $m[2];
        $data['posts'] = $m[3];
        $found = true;
    }
    
    // বায়ো এবং নাম সংগ্রহ
    if (preg_match('/Instagram photos and videos from ([^(]+)\s*\(@' . preg_quote($username, '/') . '\)[^"]*?"(.*?)"/is', $ddgRes, $m2)) {
        $data['full_name'] = trim($m2[1]);
        $data['biography'] = trim($m2[2]);
        $found = true;
    }
}

// মেথড ২: অফিশিয়াল Instagram oEmbed API (ক্লাউড আইপিতেও কাজ করে)
$oembedUrl = 'https://api.instagram.com/oembed/?url=' . urlencode($profileUrl);
$ch2 = curl_init($oembedUrl);
curl_setopt_array($ch2, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 5,
    CURLOPT_HTTPHEADER => [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ],
    CURLOPT_SSL_VERIFYPEER => false
]);
$oembedRes = curl_exec($ch2);
curl_close($ch2);

if (!empty($oembedRes)) {
    $oeData = json_decode($oembedRes, true);
    if (!empty($oeData['author_name'])) {
        $data['full_name'] = $oeData['author_name'];
        $found = true;
    }
    if (!empty($oeData['thumbnail_url'])) {
        $data['profile_pic_url'] = $oeData['thumbnail_url'];
        $found = true;
    }
}

// মেথড ৩: সরাসরি Instagram মেটা-ট্যাগ ট্রাই (ফলব্যাক)
if (!$found) {
    $ch3 = curl_init($profileUrl);
    curl_setopt_array($ch3, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36',
            'Accept-Language: en-US,en;q=0.9'
        ],
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $igRes = curl_exec($ch3);
    curl_close($ch3);

    if (!empty($igRes)) {
        if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\']([^"\']+)["\']/i', $igRes, $mPic)) {
            $data['profile_pic_url'] = html_entity_decode($mPic[1]);
            $found = true;
        }
        if (preg_match('/<meta\s+property=["\']og:description["\']\s+content=["\']([^"\']+)["\']/i', $igRes, $mDesc)) {
            $desc = html_entity_decode($mDesc[1]);
            if (preg_match('/([0-9.,KkMmBb]+)\s*Followers.*?([0-9.,KkMmBb]+)\s*Following.*?([0-9.,KkMmBb]+)\s*Posts/is', $desc, $mStats)) {
                $data['followers'] = $mStats[1];
                $data['following'] = $mStats[2];
                $data['posts'] = $mStats[3];
                $found = true;
            }
        }
    }
}

// যদি প্রোফাইল পিকচার না পাওয়া যায় তবে লাইট আনব্লকড অ্যাভাটার
if (empty($data['profile_pic_url'])) {
    $data['profile_pic_url'] = 'https://unavatar.io/instagram/' . urlencode($username);
}

http_response_code(200);
echo json_encode([
    'status' => 'success',
    'developer' => 'Abdullah Al Hasib 313',
    'telegram' => '@abdullah_al_hasib_313',
    'data' => [
        'username' => $data['username'],
        'full_name' => $data['full_name'],
        'biography' => $data['biography'],
        'profile_pic_url' => $data['profile_pic_url'],
        'public_url' => $data['public_url'],
        'is_verified' => $data['is_verified'],
        'is_private' => $data['is_private'],
        'metrics' => [
            'posts' => $data['posts'],
            'followers' => $data['followers'],
            'following' => $data['following']
        ],
        'category' => $data['category']
    ]
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
