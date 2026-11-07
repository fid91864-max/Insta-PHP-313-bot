<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$url = $_GET['url'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput, true);
    if (!empty($jsonData['url'])) {
        $url = $jsonData['url'];
    } elseif (!empty($_POST['url'])) {
        $url = $_POST['url'];
    }
}

$url = trim($url);

if (empty($url)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'URL parameter is missing'
    ]);
    exit;
}

function fetchCurl($endpoint, $postData = null, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $defaultHeaders = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept: application/json, text/plain, */*'
    ];

    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($defaultHeaders, $headers));

    if ($postData !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($postData) ? http_build_query($postData) : $postData);
    }

    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

// ১. TikTok Handler
if (strpos($url, 'tiktok.com') !== false) {
    $tikUrl = 'https://www.tikwm.com/api/?url=' . urlencode($url);
    $res = json_decode(fetchCurl($tikUrl), true);

    if (!empty($res['data']['play'])) {
        echo json_encode([
            'success' => true,
            'platform' => 'TikTok',
            'title' => $res['data']['title'] ?? 'TikTok Video',
            'duration' => $res['data']['duration'] ?? 0,
            'thumbnail' => $res['data']['cover'] ?? null,
            'video_url' => $res['data']['play'],
            'audio_url' => $res['data']['music'] ?? null
        ]);
        exit;
    }
}

// ২. Instagram Handler
if (strpos($url, 'instagram.com') !== false) {
    $instaApi = 'https://downloaderfree.com/api/instagram.php?url=' . urlencode($url);
    $headers = [
        'Referer: https://downloaderfree.com/',
        'X-Requested-With: mark.via.gp'
    ];
    $res = json_decode(fetchCurl($instaApi, null, $headers), true);

    if (!empty($res['success']) && (!empty($res['video_url']) || !empty($res['url']))) {
        echo json_encode([
            'success' => true,
            'platform' => 'Instagram',
            'title' => $res['title'] ?? 'Instagram Reel',
            'thumbnail' => $res['thumbnail'] ?? null,
            'video_url' => $res['video_url'] ?? $res['url'],
            'audio_url' => null
        ]);
        exit;
    }
}

// ৩. YouTube Handler (Invidious / Piped ক্লাস্টার ব্যবহার করে Vercel IP Block বাইপাস)
if (strpos($url, 'youtu.be') !== false || strpos($url, 'youtube.com') !== false) {
    preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/\s]{11})%i', $url, $match);
    $videoId = $match[1] ?? null;

    if ($videoId) {
        $invidiousInstances = [
            "https://inv.nadeko.net/api/v1/videos/{$videoId}",
            "https://invidious.nerdvpn.de/api/v1/videos/{$videoId}",
            "https://invidious.jing.rocks/api/v1/videos/{$videoId}",
            "https://pipedapi.kavin.rocks/streams/{$videoId}"
        ];

        foreach ($invidiousInstances as $instanceUrl) {
            $raw = fetchCurl($instanceUrl);
            $ytData = json_decode($raw, true);

            if (!empty($ytData['formatStreams'])) { // Invidious Response
                $videoStream = null;
                foreach ($ytData['formatStreams'] as $s) {
                    if (!empty($s['url'])) {
                        $videoStream = $s['url'];
                        break;
                    }
                }

                if ($videoStream) {
                    echo json_encode([
                        'success' => true,
                        'platform' => 'YouTube',
                        'title' => $ytData['title'] ?? 'YouTube Video',
                        'duration' => (int)($ytData['lengthSeconds'] ?? 0),
                        'thumbnail' => "https://i.ytimg.com/vi/{$videoId}/hqdefault.jpg",
                        'video_url' => $videoStream,
                        'audio_url' => null
                    ]);
                    exit;
                }
            } elseif (!empty($ytData['videoStreams'])) { // Piped Response
                $videoStream = null;
                foreach ($ytData['videoStreams'] as $s) {
                    if (!empty($s['url']) && !empty($s['videoOnly']) === false) {
                        $videoStream = $s['url'];
                        break;
                    }
                }

                if ($videoStream) {
                    echo json_encode([
                        'success' => true,
                        'platform' => 'YouTube',
                        'title' => $ytData['title'] ?? 'YouTube Video',
                        'duration' => (int)($ytData['duration'] ?? 0),
                        'thumbnail' => "https://i.ytimg.com/vi/{$videoId}/hqdefault.jpg",
                        'video_url' => $videoStream,
                        'audio_url' => null
                    ]);
                    exit;
                }
            }
        }
    }
}

// ৪. সার্বজনীন ডাউনলোডার ইঞ্জিন (Facebook, Twitter ও অন্যান্য প্ল্যাটফর্ম)
$cobaltInstances = [
    'https://api.cobalt.tools/api/json',
    'https://co.wuk.sh/api/json'
];

foreach ($cobaltInstances as $cUrl) {
    $payload = json_encode([
        'url' => $url,
        'vQuality' => '720'
    ]);
    
    $res = json_decode(fetchCurl($cUrl, $payload, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]), true);

    if (!empty($res['url'])) {
        echo json_encode([
            'success' => true,
            'platform' => 'Universal',
            'title' => 'Downloaded Media',
            'video_url' => $res['url'],
            'audio_url' => null
        ]);
        exit;
    }
}

http_response_code(422);
echo json_encode([
    'success' => false,
    'error' => 'Unable to fetch downloadable stream for this URL.'
]);
