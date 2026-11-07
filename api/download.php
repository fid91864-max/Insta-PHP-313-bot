<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

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
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $defaultHeaders = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept: */*'
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

// ৩. YouTube Handler
if (strpos($url, 'youtu.be') !== false || strpos($url, 'youtube.com') !== false) {
    preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/\s]{11})%i', $url, $match);
    $videoId = $match[1] ?? null;

    if ($videoId) {
        $postData = json_encode([
            'videoId' => $videoId,
            'context' => [
                'client' => [
                    'clientName' => 'ANDROID',
                    'clientVersion' => '19.09.37'
                ]
            ]
        ]);

        $res = fetchCurl('https://www.youtube.com/youtubei/v1/player', $postData, ['Content-Type: application/json']);
        $data = json_decode($res, true);

        $formats = array_merge(
            $data['streamingData']['formats'] ?? [],
            $data['streamingData']['adaptiveFormats'] ?? []
        );

        $videoUrl = null;
        $audioUrl = null;

        // অডিওসহ কম্বাইন্ড ভিডিও ফরম্যাট খোঁজা
        foreach ($formats as $f) {
            if (!empty($f['url']) && strpos($f['mimeType'] ?? '', 'video/mp4') !== false && !empty($f['audioQuality'])) {
                $videoUrl = $f['url'];
                break;
            }
        }

        // আলাদা অডিও ট্র্যাক
        foreach ($formats as $f) {
            if (!empty($f['url']) && strpos($f['mimeType'] ?? '', 'audio') !== false) {
                $audioUrl = $f['url'];
                break;
            }
        }

        if ($videoUrl) {
            echo json_encode([
                'success' => true,
                'platform' => 'YouTube',
                'title' => $data['videoDetails']['title'] ?? 'YouTube Video',
                'duration' => (int)($data['videoDetails']['lengthSeconds'] ?? 0),
                'thumbnail' => "https://i.ytimg.com/vi/{$videoId}/hqdefault.jpg",
                'video_url' => $videoUrl,
                'audio_url' => $audioUrl
            ]);
            exit;
        }
    }
}

// ৪. সার্বজনীন ফলব্যাক ইঞ্জিন (Facebook ও অন্যান্য সাইটের জন্য)
$cobaltUrl = 'https://co.wuk.sh/api/json';
$cobaltData = json_encode(['url' => $url]);
$res = json_decode(fetchCurl($cobaltUrl, $cobaltData, ['Content-Type: application/json', 'Accept: application/json']), true);

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

http_response_code(422);
echo json_encode([
    'success' => false,
    'error' => 'Unable to fetch downloadable stream for this URL.'
]);
