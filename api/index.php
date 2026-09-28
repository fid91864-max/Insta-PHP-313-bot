<?php

$botToken = getenv('BOT_TOKEN') ?: '8909542012:AAE2CAnw-v-ee8-9d7znU96GjcCyOIi2CNE';

$update = json_decode(file_get_contents('php://input'), true);

if (!$update) {
    http_response_code(200);
    echo "Bot is active";
    exit;
}

$message = $update['message'] ?? null;
if (!$message) {
    exit;
}

$chatId = $message['chat']['id'];
$messageId = $message['message_id'];
$text = trim($message['text'] ?? '');

if ($text === '/start') {
    $welcomeText = "👋 <b>স্বাগতম!</b>\n\nAmake chobi pathan, ami Catbox direct link toiri kore dibo.";
    sendMessage($botToken, $chatId, $welcomeText, 'HTML', $messageId);
    exit;
}

$fileId = null;

if (!empty($message['photo'])) {
    $photo = end($message['photo']);
    $fileId = $photo['file_id'];
} elseif (!empty($message['document']) && str_starts_with($message['document']['mime_type'] ?? '', 'image/')) {
    $fileId = $message['document']['file_id'];
}

if ($fileId) {
    $fileInfoUrl = "https://api.telegram.org/bot{$botToken}/getFile?file_id={$fileId}";
    $fileInfo = json_decode(file_get_contents($fileInfoUrl), true);

    if (!empty($fileInfo['result']['file_path'])) {
        $filePath = $fileInfo['result']['file_path'];
        $downloadUrl = "https://api.telegram.org/file/bot{$botToken}/{$filePath}";

        $imageContent = file_get_contents($downloadUrl);

        if ($imageContent !== false) {
            $ext = pathinfo($filePath, PATHINFO_EXTENSION) ?: 'jpg';
            $tempFile = tempnam(sys_get_temp_dir(), 'catbox_') . ".{$ext}";
            file_put_contents($tempFile, $imageContent);

            $cfile = new CURLFile($tempFile, 'image/' . $ext, basename($tempFile));

            $postData = [
                'reqtype' => 'fileupload',
                'fileToUpload' => $cfile
            ];

            $ch = curl_init("https://catbox.moe/user/api.php");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $catboxUrl = trim(curl_exec($ch));
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if (file_exists($tempFile)) {
                unlink($tempFile);
            }

            if ($httpCode === 200 && str_starts_with($catboxUrl, 'http')) {
                $responseMsg = "🚀 <b>Image Uploaded!</b>\n\n🔗 <b>Direct Link:</b>\n<code>{$catboxUrl}</code>";
                $inlineKeyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '🌐 Open Image', 'url' => $catboxUrl]
                        ]
                    ]
                ];
                sendMessage($botToken, $chatId, $responseMsg, 'HTML', $messageId, $inlineKeyboard);
            } else {
                sendMessage($botToken, $chatId, "⚠️ Upload failed! Response: " . substr($catboxUrl, 0, 50), '', $messageId);
            }
        }
    }
    exit;
}

function sendMessage($token, $chatId, $text, $parseMode = '', $replyTo = null, $replyMarkup = null) {
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $postData = [
        'chat_id' => $chatId,
        'text' => $text,
    ];

    if ($parseMode) {
        $postData['parse_mode'] = $parseMode;
    }
    if ($replyTo) {
        $postData['reply_to_message_id'] = $replyTo;
    }
    if ($replyMarkup) {
        $postData['reply_markup'] = json_encode($replyMarkup);
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_exec($ch);
    curl_close($ch);
}
