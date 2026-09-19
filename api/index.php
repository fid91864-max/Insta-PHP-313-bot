<?php
/**
 * ============================================================
 *  Instagram Dual Engine Bot — Final Custom Edition
 *  Developer: 𝐀𝐁𝐃𝐔𝐋𝐋𝐀𝐇 𝐀𝐋 𝐇𝐀𝐒𝐈𝐁
 *  Version: 14.0 Dual Split Engine
 * ============================================================
 */

error_reporting(0);
ini_set('display_errors', '0');
ini_set('max_execution_time', '300');
date_default_timezone_set('Asia/Dhaka');

/* ============ CONFIGURATION ============ */
define('BOT_TOKEN', '8946304892:AAFjZVju2W8y-cV3saXxVs_tOr5CO-6CYiI');
define('OWNER_ID', '8973209963');
define('DEVELOPER_NAME', '𝐀𝐁𝐃𝐔𝐋𝐋𝐀𝐇 𝐀𝐋 𝐇𝐀𝐒𝐈𝐁');
define('DEVELOPER_USERNAME', '@abdullahalhasib313');
define('REQUIRED_REFERRALS', 20);
define('WELCOME_VIDEO_URL', 'https://files.catbox.moe/v30pbc.mp4');
define('NUMBER_API_BASE', 'https://abdullah38-lakh-bd2fbandinsta-313.vercel.app/search?username=');

/* ============ GITHUB CLOUD DATABASE ============ */
define('GITHUB_REPO', 'fid91864-max/Insta-PHP-313-bot');
define('GITHUB_TOKEN', getenv('GH_STORAGE_TOKEN') ?: '');
define('GITHUB_FILE_PATH', 'database.json');
define('DB_FILE', sys_get_temp_dir() . '/ig_bot_database.json');

function defaultDB(): array {
    return [
        'users' => [],
        'settings' => ['extraction_open' => false],
        'admin_state' => null,
        'stats' => ['total_extractions' => 0, 'started_at' => time()]
    ];
}

function githubApi(string $endpoint, string $method = 'GET', ?array $body = null): array {
    $token = GITHUB_TOKEN;
    if (empty($token)) return [];
    $url = "https://api.github.com/repos/" . GITHUB_REPO . "/" . $endpoint;
    $headers = [
        "User-Agent: PHP-Bot-Storage",
        "Authorization: token " . $token,
        "Accept: application/vnd.github.v3+json"
    ];
    $options = [
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers) . "\r\n",
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ];
    if ($body !== null) {
        $headers[] = "Content-Type: application/json";
        $options['http']['header'] = implode("\r\n", $headers) . "\r\n";
        $options['http']['content'] = json_encode($body, JSON_UNESCAPED_UNICODE);
    }
    $context = stream_context_create($options);
    $res = @file_get_contents($url, false, $context);
    return json_decode((string)$res, true) ?? [];
}

function loadDB(): array {
    $res = githubApi('contents/' . GITHUB_FILE_PATH);
    if (!empty($res['content'])) {
        $decoded = base64_decode($res['content']);
        $json = json_decode($decoded, true);
        if (is_array($json)) {
            $json['__sha'] = $res['sha'] ?? null;
            return $json;
        }
    }
    if (file_exists(DB_FILE)) {
        $json = json_decode((string)@file_get_contents(DB_FILE), true);
        if (is_array($json)) return $json;
    }
    return defaultDB();
}

function saveDB(array $data): void {
    @file_put_contents(DB_FILE, json_encode($data, JSON_UNESCAPED_UNICODE));
    $sha = $data['__sha'] ?? null;
    unset($data['__sha']);
    if (!$sha) {
        $chk = githubApi('contents/' . GITHUB_FILE_PATH);
        $sha = $chk['sha'] ?? null;
    }
    $payload = [
        'message' => 'db sync ' . time(),
        'content' => base64_encode(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
        'branch'  => 'main'
    ];
    if ($sha) $payload['sha'] = $sha;
    githubApi('contents/' . GITHUB_FILE_PATH, 'PUT', $payload);
}

/* ============ TELEGRAM API ============ */
function botApi(string $method, array $params = []): array {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode((string)$res, true) ?? ['ok' => false];
}

function sendMessage($chatId, string $text, ?array $keyboard = null): array {
    $params = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true];
    if ($keyboard !== null) $params['reply_markup'] = $keyboard;
    return botApi('sendMessage', $params);
}

function editMessage($chatId, $msgId, string $text, ?array $keyboard = null): array {
    $params = ['chat_id' => $chatId, 'message_id' => $msgId, 'text' => $text, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true];
    if ($keyboard !== null) $params['reply_markup'] = $keyboard;
    return botApi('editMessageText', $params);
}

function sendPhoto($chatId, string $photoUrl, string $caption = '', ?array $keyboard = null): array {
    $params = ['chat_id' => $chatId, 'photo' => $photoUrl, 'caption' => $caption, 'parse_mode' => 'HTML'];
    if ($keyboard !== null) $params['reply_markup'] = $keyboard;
    return botApi('sendPhoto', $params);
}

function sendMediaGroup($chatId, array $media): array {
    return botApi('sendMediaGroup', ['chat_id' => $chatId, 'media' => $media]);
}

function sendVideo($chatId, string $videoUrl, string $caption = '', ?array $keyboard = null): array {
    $params = ['chat_id' => $chatId, 'video' => $videoUrl, 'caption' => $caption, 'parse_mode' => 'HTML'];
    if ($keyboard !== null) $params['reply_markup'] = $keyboard;
    return botApi('sendVideo', $params);
}

function deleteMessage($chatId, $msgId): array {
    return botApi('deleteMessage', ['chat_id' => $chatId, 'message_id' => $msgId]);
}

/* ============ INSTAGRAM SCRAPER ============ */
function extractHighestResolutionUrls($obj, array &$urls = [], ?string $postId = null): array {
    if (is_array($obj)) {
        if (isset($obj['pk']) && is_string($obj['pk'])) $postId = $obj['pk'];
        if (isset($obj['id']) && is_string($obj['id']) && !$postId) $postId = $obj['id'];

        if (isset($obj['image_versions2']['candidates']) && is_array($obj['image_versions2']['candidates'])) {
            $candidates = $obj['image_versions2']['candidates'];
            if (!empty($candidates)) {
                $highest = null;
                $maxArea = 0;
                foreach ($candidates as $c) {
                    $area = (int)($c['width'] ?? 0) * (int)($c['height'] ?? 0);
                    if ($area > $maxArea) {
                        $maxArea = $area;
                        $highest = $c;
                    }
                }
                if ($highest && !empty($highest['url']) && $postId && !isset($urls[$postId])) {
                    $urls[$postId] = str_replace(['\\u0026', '\\/'], ['&', '/'], $highest['url']);
                }
            }
        }

        if (isset($obj['display_url']) && !empty($obj['display_url']) && $postId && !isset($urls[$postId])) {
            $urls[$postId] = $obj['display_url'];
        }

        foreach ($obj as $v) {
            if (is_array($v)) extractHighestResolutionUrls($v, $urls, $postId);
        }
    }
    return $urls;
}

function getInstagramDataLive(string $username): ?array {
    $username = trim(str_replace(['@', ' '], '', $username));
    $endpoints = [
        "https://i.instagram.com/api/v1/users/web_profile_info/?username={$username}",
        "https://www.instagram.com/api/v1/users/web_profile_info/?username={$username}"
    ];
    $headers = [
        "User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1",
        "X-IG-App-ID: 936619743392459",
        "Accept: */*",
        "Accept-Language: en-US,en;q=0.9"
    ];
    foreach ($endpoints as $apiUrl) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code === 200 && !empty($res)) {
            $json = json_decode($res, true);
            if (!empty($json['data']['user'])) {
                $user = $json['data']['user'];
                $images = [];
                extractHighestResolutionUrls($user, $images);
                return [
                    'username'    => $user['username'] ?? $username,
                    'full_name'   => $user['full_name'] ?? 'N/A',
                    'bio'         => $user['biography'] ?? 'N/A',
                    'followers'   => (int)($user['edge_followed_by']['count'] ?? 0),
                    'following'   => (int)($user['edge_follow']['count'] ?? 0),
                    'posts'       => (int)($user['edge_owner_to_timeline_media']['count'] ?? 0),
                    'is_verified' => !empty($user['is_verified']),
                    'is_private'  => !empty($user['is_private']),
                    'profile_pic' => $user['profile_pic_url_hd'] ?? ($user['profile_pic_url'] ?? ''),
                    'images'      => $images
                ];
            }
        }
    }
    return null;
}

/* ============ KEYBOARDS ============ */
function getUserKeyboard($isAdmin = false): array {
    $kb = [
        [
            ['text' => '📸 insta prvt post viwe', 'style' => 'danger'],
            ['text' => '📞 insta number to info', 'style' => 'success']
        ],
        [
            ['text' => '💎 My Balance', 'style' => 'primary'],
            ['text' => '👤 My Profile', 'style' => 'primary']
        ],
        [
            ['text' => '🎁 Refer & Earn', 'style' => 'primary'],
            ['text' => 'ℹ️ About', 'style' => 'primary']
        ]
    ];
    if ($isAdmin) {
        $kb[] = [['text' => '👑 ADMIN PANEL 👑', 'style' => 'danger']];
    }
    return ['keyboard' => $kb, 'resize_keyboard' => true, 'is_persistent' => true];
}

/* ============ MAIN ENTRY ============ */
$input = file_get_contents('php://input');
$update = json_decode($input ?: '', true);

if (!is_array($update)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'status' => 'online', 'core' => 'ABDULLAH DUAL ENGINE']);
    exit;
}

$db = loadDB();

if (isset($update['message'])) {
    $msg = $update['message'];
    $chatId = (string)($msg['chat']['id'] ?? '');
    $text = trim((string)($msg['text'] ?? ''));
    $from = $msg['from'] ?? [];
    $userId = (string)($from['id'] ?? '');
    $isAdmin = ($userId === (string)OWNER_ID);

    if (!isset($db['users'][$chatId])) {
        $refBy = null;
        if (strpos($text, '/start ') === 0) {
            $cand = trim(substr($text, 7));
            if ($cand !== $chatId && isset($db['users'][$cand])) $refBy = $cand;
        }
        $db['users'][$chatId] = [
            'first_name' => $from['first_name'] ?? 'User',
            'username'   => $from['username'] ?? '',
            'referrals'  => 0,
            'state'      => null
        ];
        if ($refBy) {
            $db['users'][$refBy]['referrals'] = ($db['users'][$refBy]['referrals'] ?? 0) + 1;
        }
        saveDB($db);
    }

    $uData = &$db['users'][$chatId];

    if ($text === '/cancel') {
        $uData['state'] = null; saveDB($db);
        sendMessage($chatId, "❌ Cancelled.", getUserKeyboard($isAdmin));
        exit;
    }

    if (strpos($text, '/start') === 0) {
        $uData['state'] = null; saveDB($db);
        $caption = "╔══════════════════════════════╗\n"
                 . "       ⚡ <b>𝐖𝐄𝐋𝐂𝐎𝐌𝐄</b> ⚡\n"
                 . "╚══════════════════════════════╝\n\n"
                 . "🔥 <b>𝐀𝐁𝐃𝐔𝐋𝐋𝐀𝐇 𝐂𝐎𝐑𝐄 𝐎𝐍𝐋𝐈𝐍𝐄</b> 🔥\n\n"
                 . "👑 <b>Developer:</b> " . DEVELOPER_NAME . "\n"
                 . "🎁 <b>Requirement:</b> " . REQUIRED_REFERRALS . " Referrals to Unlock any Search!\n\n"
                 . "👇 Nicher duita button theke apnar option select korun:";
        sendVideo($chatId, WELCOME_VIDEO_URL, $caption, getUserKeyboard($isAdmin));
        exit;
    }

    if ($text === '📸 insta prvt post viwe') {
        $refs = $uData['referrals'] ?? 0;
        if ($refs < REQUIRED_REFERRALS && !$isAdmin) {
            sendMessage($chatId, "🔒 <b>LOCKED FEATURE</b>\n\n'insta prvt post viwe' use korte <b>" . REQUIRED_REFERRALS . " ti refer</b> lagbe!\nApnar refer ache: <b>{$refs}/" . REQUIRED_REFERRALS . "</b>\n\n🎁 Refer korte 'Refer & Earn' use korun.", getUserKeyboard($isAdmin));
            exit;
        }
        $uData['state'] = 'awaiting_insta_target'; saveDB($db);
        sendMessage($chatId, "🎯 <b>Instagram Username pathan (Prvt Post View):</b>\n<i>Example: <code>cristiano</code></i>", getUserKeyboard($isAdmin));
        exit;
    }

    if ($text === '📞 insta number to info') {
        $refs = $uData['referrals'] ?? 0;
        if ($refs < REQUIRED_REFERRALS && !$isAdmin) {
            sendMessage($chatId, "🔒 <b>LOCKED FEATURE</b>\n\n'insta number to info' use korte <b>" . REQUIRED_REFERRALS . " ti refer</b> lagbe!\nApnar refer ache: <b>{$refs}/" . REQUIRED_REFERRALS . "</b>\n\n🎁 Refer korte 'Refer & Earn' use korun.", getUserKeyboard($isAdmin));
            exit;
        }
        $uData['state'] = 'awaiting_number_target'; saveDB($db);
        sendMessage($chatId, "🔍 <b>Instagram Username pathan (Number to Info Lookup):</b>\n<i>Example: <code>shafaqat_alam</code></i>", getUserKeyboard($isAdmin));
        exit;
    }

    if ($text === '💎 My Balance') {
        $refs = $uData['referrals'] ?? 0;
        $status = ($refs >= REQUIRED_REFERRALS || $isAdmin) ? "🟢 UNLOCKED" : "🔒 LOCKED";
        sendMessage($chatId, "💎 <b>Balance Status:</b>\n━━━━━━━━━━━━━━━━━━━━\n\n👥 Referrals: <code>{$refs}/" . REQUIRED_REFERRALS . "</code>\n🔓 Status: <b>{$status}</b>", getUserKeyboard($isAdmin));
        exit;
    }

    if ($text === '👤 My Profile') {
        sendMessage($chatId, "👤 <b>User:</b> " . htmlspecialchars($from['first_name'] ?? 'User') . "\n🆔 ID: <code>{$chatId}</code>\n👥 Referrals: <code>" . ($uData['referrals'] ?? 0) . "</code>", getUserKeyboard($isAdmin));
        exit;
    }

    if ($text === '🎁 Refer & Earn') {
        $me = botApi('getMe');
        $botUser = $me['result']['username'] ?? 'bot';
        sendMessage($chatId, "🔗 <b>Invite Link:</b>\n<code>https://t.me/{$botUser}?start={$chatId}</code>\n\n🎁 " . REQUIRED_REFERRALS . " Referrals = <b>Unlock Both Features!</b>", getUserKeyboard($isAdmin));
        exit;
    }

    if ($text === 'ℹ️ About') {
        sendMessage($chatId, "ℹ️ <b>Instagram Dual Engine Bot v14.0</b>\nDeveloper: " . DEVELOPER_USERNAME, getUserKeyboard($isAdmin));
        exit;
    }

    // STATE 1: INSTA PRIVATE POST VIEW
    if ($uData['state'] === 'awaiting_insta_target') {
        $target = trim(str_replace(['https://www.instagram.com/', 'https://instagram.com/', '/', '@'], '', $text));
        $uData['state'] = null; saveDB($db);

        $proc = sendMessage($chatId, "⚡ <b>Fetching Instagram Media for @{$target}...</b>");
        $procId = $proc['result']['message_id'] ?? null;

        $data = getInstagramDataLive($target);
        if ($procId) deleteMessage($chatId, $procId);

        if (!$data || empty($data['images'])) {
            sendMessage($chatId, "⚠️ <b>Profile ba media khuje paowa jayni!</b>", getUserKeyboard($isAdmin));
            exit;
        }

        $images = array_values($data['images']);
        $total = count($images);
        
        $rep = "📸 <b>INSTAGRAM INTEL REPORT:</b> @" . htmlspecialchars($data['username']) . "\n📦 Total Media: {$total} images";
        if (!empty($data['profile_pic'])) {
            sendPhoto($chatId, $data['profile_pic'], $rep);
        } else {
            sendMessage($chatId, $rep);
        }

        $chunks = array_chunk(array_slice($images, 0, 20), 5);
        foreach ($chunks as $chunk) {
            $group = [];
            foreach ($chunk as $imgUrl) {
                $group[] = ['type' => 'photo', 'media' => $imgUrl];
            }
            sendMediaGroup($chatId, $group);
        }
        exit;
    }

    // STATE 2: INSTA NUMBER TO INFO LOOKUP
    if ($uData['state'] === 'awaiting_number_target') {
        $target = trim(str_replace(['https://www.instagram.com/', 'https://instagram.com/', '/', '@'], '', $text));
        $uData['state'] = null; saveDB($db);

        $proc = sendMessage($chatId, "🔍 <b>Searching Database for @{$target}...</b>");
        $procId = $proc['result']['message_id'] ?? null;

        $apiUrl = NUMBER_API_BASE . urlencode($target);
        $res = @file_get_contents($apiUrl);
        if ($procId) deleteMessage($chatId, $procId);

        $json = json_decode($res, true);
        if (!empty($json['status']) && $json['status'] === 'success' && !empty($json['results'])) {
            foreach ($json['results'] as $item) {
                $d = $item['data'] ?? [];
                $out = "╔════════════════════════╗\n"
                     . "   📞 <b>NUMBER TO INFO REPORT</b>\n"
                     . "╚════════════════════════╝\n│\n"
                     . "├ 👤 <b>Name:</b> " . ($d['name'] ?? 'N/A') . "\n"
                     . "├ 📧 <b>Email:</b> " . ($d['email'] ?? 'N/A') . "\n"
                     . "├ 📞 <b>Phone:</b> <code>" . ($d['phone'] ?? 'N/A') . "</code>\n"
                     . "├ 🏠 <b>Location:</b> " . ($d['location'] ?? 'N/A') . "\n"
                     . "├ 🔗 <b>Username:</b> @" . ($d['username'] ?? $target) . "\n│\n"
                     . "╘═══ 👑 " . DEVELOPER_USERNAME;
                sendMessage($chatId, $out, getUserKeyboard($isAdmin));
            }
        } else {
            sendMessage($chatId, "⚠️ <b>Database-e @{$target} er kono info paowa jayni!</b>", getUserKeyboard($isAdmin));
        }
        exit;
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true]);
