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

$rawUsername =$_GET['username'] ?? '';
$username = trim((string)$rawUsername);
$username = ltrim($username, '@');

if ($username === '' || !preg_match('/^[a-zA-Z0-9._]{1,30}$/',$username)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'developer' => 'Abdullah Al Hasib 313',
        'message' => 'Invalid username format.'
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

function extractHighestResolutionUrls($obj, array &$urls = [], ?string $postId = null): array {
    if (is_array($obj)) {
        if (isset($obj['pk']) && is_string($obj['pk'])) $postId =$obj['pk'];
        if (isset($obj['id']) && is_string($obj['id']) && !$postId) $postId =$obj['id'];

        if (isset($obj['image_versions2']['candidates']) && is_array($obj['image_versions2']['candidates'])) {
            $candidates =$obj['image_versions2']['candidates'];
            if (!empty($candidates)) {$highest = null;
                $maxArea = 0;
                foreach ($candidates as $c) {$area = (int)($c['width'] ?? 0) * (int)($c['height'] ?? 0);
                    if ($area >$maxArea) {
                        $maxArea =$area;
                        $highest =$c;
                    }
                }
                if ($highest && !empty($highest['url']) && $postId && !isset($urls[$postId])) {$urls[$postId] = str_replace(['\\u0026', '\\/'], ['&', '/'], $highest['url']);
                }
            }
        }

        if (isset($obj['display_url']) && !empty($obj['display_url']) && $postId && !isset($urls[$postId])) {$urls[$postId] =$obj['display_url'];
        }

        foreach ($obj as$v) {
            if (is_array($v)) extractHighestResolutionUrls($v, $urls,$postId);
        }
    }
    return $urls;
}

// আপনার বটের মেথড ১: X-IG-App-ID ইঞ্জিন
$endpoints = [
    "https://i.instagram.com/api/v1/users/web_profile_info/?username={$username}",
    "https://www.instagram.com/api/v1/users/web_profile_info/?username={$username}"
];

$headers = [
    "User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1",
    "X-IG-App-ID: 936619743392459",
    "Accept: */*",
    "Accept-Language: en-US,en;q=0.9",
    "Sec-Fetch-Mode: cors",
    "Sec-Fetch-Site: same-origin"
];

$userData = null;

foreach ($endpoints as $apiUrl) {$ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && !empty($res)) {
        $json = json_decode($res, true);
        if (!empty($json['data']['user'])) {$u = $json['data']['user'];$images = [];
            extractHighestResolutionUrls($u,$images);

            $userData = [
                'username'    => $u['username'] ?? $username,
                'full_name'   => $u['full_name'] ?? $username,
                'biography'   => $u['biography'] ?? 'No public bio broadcasted.',
                'public_url'  => 'https://www.instagram.com/' . rawurlencode($username) . '/',
                'is_verified' => !empty($u['is_verified']),
                'is_private'  => !empty($u['is_private']),
                'profile_pic' => $u['profile_pic_url_hd'] ?? ($u['profile_pic_url'] ?? ''),
                'metrics'     => [
                    'posts'     => number_format((float)($u['edge_owner_to_timeline_media']['count'] ?? 0)), 'followers' => number_format((float)($u['edge_followed_by']['count'] ?? 0)),
                    'following' => number_format((float)($u['edge_follow']['count'] ?? 0)) ], 'category'    =>$u['category_name'] ?? 'Public Entity',
                'images'      => array_values($images)
            ];
            break;
        }
    }
}

// আপনার বটের মেথড ২: Nexus 5 ফলব্যাক ইঞ্জিন
if (!$userData) {
    $ch = curl_init("https://www.instagram.com/{$username}/");
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'accept-language: en-GB,en;q=0.9',
            'sec-ch-ua: "Google Chrome";v="141", "Chromium";v="141"',
            'sec-ch-ua-mobile: ?1',
            'sec-ch-ua-platform: "Android"',
            'user-agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    $html = curl_exec($ch);
    curl_close($ch);

    if ($html) {
        preg_match_all('/<script[^>]*type="application\/json"[^>]*>(.*?)<\/script>/s', $html,$matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as$content) {
                if (strpos($content, 'image_versions2') !== false \vert{}\vert{} strpos($content, 'display_url') !== false) {
                    $decoded = json_decode($content, true);
                    if (is_array($decoded)) {$images = [];
                        extractHighestResolutionUrls($decoded,$images);
                        if (!empty($images)) {$userData = [
                                'username'    => $username,
                                'full_name'   => $username,
                                'biography'   => 'Extracted from live public node.',
                                'public_url'  => 'https://www.instagram.com/' . rawurlencode($username) . '/',
                                'is_verified' => false,
                                'is_private'  => false,
                                'profile_pic' => reset($images),
                                'metrics'     => [
                                    'posts'     => (string)count($images),
                                    'followers' => 'Public View',
                                    'following' => 'Public View'
                                ],
                                'category'    => 'Public Entity',
                                'images'      => array_values($images)
                            ];
                            break;
                        }
                    }
                }
            }
        }
    }
}

if (!$userData) {
    http_response_code(404);
    echo json_encode([
        'status' => 'error',
        'developer' => 'Abdullah Al Hasib 313',
        'message' => 'Profile is restricted, blocked, or unavailable on Instagram directory.'
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

http_response_code(200);
echo json_encode([
    'status'    => 'success',
    'developer' => 'Abdullah Al Hasib 313',
    'telegram'  => '@abdullah_al_hasib_313',
    'data'      => $userData
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
