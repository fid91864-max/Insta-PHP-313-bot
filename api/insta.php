<?php
declare(strict_types=1);

error_reporting(0);
ini_set('display_errors', '0');

// Strict CORS and Security Headers
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'code' => 405,
        'developer' => 'Abdullah Al Hasib 313',
        'telegram' => '@abdullah_al_hasib_313',
        'message' => 'Method Not Allowed. Only GET queries are supported.'
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

$rawUsername = $_GET['username'] ?? '';
$username = trim((string)$rawUsername);
$username = ltrim($username, '@');

// RFC-compliant Instagram username validation (Letters, numbers, periods, underscores, max 30 chars)
if ($username === '' || !preg_match('/^[a-zA-Z0-9._]{1,30}$/', $username)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'code' => 400,
        'developer' => 'Abdullah Al Hasib 313',
        'telegram' => '@abdullah_al_hasib_313',
        'message' => 'Invalid handle identifier. Username must be 1-30 alphanumeric characters, dots, or underscores.'
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

// SSRF & Reserved Keyword Guard
$blacklist = ['login', 'accounts', 'explore', 'direct', 'stories', 'reels', 'about', 'developer', 'legal'];
if (in_array(strtolower($username), $blacklist, true)) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'code' => 422,
        'developer' => 'Abdullah Al Hasib 313',
        'telegram' => '@abdullah_al_hasib_313',
        'message' => 'Query target is a platform keyword, not a valid user entity.'
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

$upstreamUrl = 'https://www.instagram.com/' . rawurlencode($username) . '/';

$headers = [
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
    'Accept-Language: en-US,en;q=0.9',
    'Cache-Control: no-cache',
    'Pragma: no-cache',
    'Sec-Fetch-Dest: document',
    'Sec-Fetch-Mode: navigate',
    'Sec-Fetch-Site: none',
    'Sec-Fetch-User: ?1',
    'Upgrade-Insecure-Requests: 1',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36'
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $upstreamUrl,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 3,
    CURLOPT_TIMEOUT => 9,
    CURLOPT_CONNECTTIMEOUT => 4,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_ENCODING => 'gzip, deflate'
]);

$responseBody = curl_exec($ch);
$httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($responseBody === false || !empty($curlError)) {
    http_response_code(504);
    echo json_encode([
        'status' => 'error',
        'code' => 504,
        'developer' => 'Abdullah Al Hasib 313',
        'telegram' => '@abdullah_al_hasib_313',
        'message' => 'Quantum probe timed out connecting to neural relays.'
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

if ($httpStatus === 404) {
    http_response_code(404);
    echo json_encode([
        'status' => 'error',
        'code' => 404,
        'developer' => 'Abdullah Al Hasib 313',
        'telegram' => '@abdullah_al_hasib_313',
        'message' => 'Node does not exist in the public directory registry.'
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

$extracted = [
    'username' => $username,
    'full_name' => null,
    'biography' => null,
    'profile_pic_url' => null,
    'is_verified' => false,
    'is_private' => false,
    'posts_count' => null,
    'followers_count' => null,
    'following_count' => null,
    'external_url' => null,
    'category_name' => null
];

// Parser Step 1: OpenGraph and Twitter Meta Tags
$dom = new DOMDocument();
@$dom->loadHTML('<?xml encoding="utf-8" ?>' . $responseBody, LIBXML_NOERROR | LIBXML_NOWARNING);
$xpath = new DOMXPath($dom);

$metaNodes = $xpath->query('//meta[@property or @name]');
$metaTags = [];
if ($metaNodes) {
    foreach ($metaNodes as $meta) {
        $key = $meta->getAttribute('property') ?: $meta->getAttribute('name');
        $val = $meta->getAttribute('content');
        if ($key && $val) {
            $metaTags[$key] = $val;
        }
    }
}

// Parse OpenGraph Description (e.g., "12M Followers, 500 Following, 1,234 Posts - See Instagram photos...")
if (!empty($metaTags['og:description'])) {
    $desc = $metaTags['og:description'];
    if (preg_match('/([0-9.,KkMmBb]+)\s*Followers,\s*([0-9.,KkMmBb]+)\s*Following,\s*([0-9.,KkMmBb]+)\s*Posts/i', $desc, $matches)) {
        $extracted['followers_count'] = $matches[1];
        $extracted['following_count'] = $matches[2];
        $extracted['posts_count'] = $matches[3];
    }
}

// Parse Title (e.g., "Cristiano Ronaldo (@cristiano) • Instagram photos and videos")
if (!empty($metaTags['og:title'])) {
    $title = $metaTags['og:title'];
    if (preg_match('/^(.*?)\s*\(@[a-zA-Z0-9._]+\)/u', $title, $mTitle)) {
        $extracted['full_name'] = trim($mTitle[1]);
    }
}

if (!empty($metaTags['og:image'])) {
    $extracted['profile_pic_url'] = $metaTags['og:image'];
}

// Parser Step 2: Embedded JSON-LD schema
$scriptNodes = $xpath->query('//script[@type="application/ld+json"]');
if ($scriptNodes) {
    foreach ($scriptNodes as $scriptNode) {
        $jsonContent = trim($scriptNode->nodeValue);
        $decoded = json_decode($jsonContent, true);
        if (is_array($decoded)) {
            if (isset($decoded['name']) && !$extracted['full_name']) {
                $extracted['full_name'] = (string)$decoded['name'];
            }
            if (isset($decoded['description']) && !$extracted['biography']) {
                $extracted['biography'] = (string)$decoded['description'];
            }
            if (isset($decoded['image']) && !$extracted['profile_pic_url']) {
                $extracted['profile_pic_url'] = (string)$decoded['image'];
            }
            if (isset($decoded['interactionStatistic']) && is_array($decoded['interactionStatistic'])) {
                foreach ($decoded['interactionStatistic'] as $stat) {
                    $type = $stat['interactionType'] ?? '';
                    if (str_contains($type, 'WriteAction') && isset($stat['userInteractionCount'])) {
                        $extracted['posts_count'] = (string)$stat['userInteractionCount'];
                    }
                }
            }
        }
    }
}

// Parser Step 3: Application Shared Data Extraction
if (preg_match('/<script\s+type="application\/json"\s+data-sjs>(\{.*?})<\/script>/is', $responseBody, $matchData)) {
    $sjs = json_decode($matchData[1], true);
    if (is_array($sjs)) {
        array_walk_recursive($sjs, function($val, $key) use (&$extracted) {
            if ($key === 'is_verified' && is_bool($val)) {
                $extracted['is_verified'] = $val;
            }
            if ($key === 'is_private' && is_bool($val)) {
                $extracted['is_private'] = $val;
            }
            if ($key === 'biography' && is_string($val) && !$extracted['biography']) {
                $extracted['biography'] = $val;
            }
            if ($key === 'category_name' && is_string($val)) {
                $extracted['category_name'] = $val;
            }
        });
    }
}

// Determine if profile was retrieved
$hasData = ($extracted['full_name'] !== null || $extracted['profile_pic_url'] !== null || $extracted['followers_count'] !== null);

if (!$hasData) {
    // If Instagram rate-limited the anonymous request or locked behind dynamic authentication
    http_response_code(403);
    echo json_encode([
        'status' => 'restricted',
        'code' => 403,
        'developer' => 'Abdullah Al Hasib 313',
        'telegram' => '@abdullah_al_hasib_313',
        'message' => 'Instagram access lock detected. Profile requires active session or is shielded by login gate.',
        'target_url' => 'https://www.instagram.com/' . rawurlencode($username) . '/'
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

http_response_code(200);
echo json_encode([
    'status' => 'success',
    'code' => 200,
    'developer' => 'Abdullah Al Hasib 313',
    'telegram' => '@abdullah_al_hasib_313',
    'node' => 'ORBIT-2150-CORE',
    'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
    'data' => [
        'username' => $extracted['username'],
        'full_name' => $extracted['full_name'] ?? $extracted['username'],
        'biography' => $extracted['biography'] ?? 'No public quantum transmission available.',
        'profile_pic_url' => $extracted['profile_pic_url'] ?? '',
        'public_url' => 'https://www.instagram.com/' . rawurlencode($username) . '/',
        'is_verified' => (bool)$extracted['is_verified'],
        'is_private' => (bool)$extracted['is_private'],
        'metrics' => [
            'posts' => $extracted['posts_count'] ?? 'Unknown',
            'followers' => $extracted['followers_count'] ?? 'Encrypted',
            'following' => $extracted['following_count'] ?? 'Encrypted'
        ],
        'category' => $extracted['category_name'] ?? 'Public Intelligence Node'
    ]
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
