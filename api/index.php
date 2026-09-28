<?php

$botToken = getenv('BOT_TOKEN') ?: '8909542012:AAE2CAnw-v-ee8-9d7znU96GjcCyOIi2CNE';

$update = json_decode(file_get_contents('php://input'), true);

if (!$update) {
    http_response_code(200);
    echo "Bot is running perfectly!";
    exit;
}

$message = $update['message'] ?? null;
if (!$message) {
    exit;
}

$chatId = $message['chat']['id'];
$messageId = $message['message_id'];
$text = trim($message['text'] ?? '');

// শুধু /start কমান্ড হ্যান্ডেল করা
if ($text === '/start') {
    $welcomeText = "👋 <b>স্বাগতম!</b>\n\nআমাকে এক বা একাধিক যেকোনো ছবি পাঠান। কোনো কমান্ডের প্রয়োজন নেই, প্রতিটা ছবির জন্য সরাসরি Catbox লিংক তৈরি হয়ে যাবে।";
    sendMessage($botToken, $chatId, $welcomeText, 'HTML', $messageId);
    exit;
}

// ছবি চেক করা (Regular Photo অথবা Document হিসেবে আসা Image)
$fileId = null;

if (!empty($message['photo'])) {
    // অ্যালবাম বা সিঙ্গেল ফটোর ক্ষেত্রে সর্বোচ্চ রেজোলিউশনের ফটো নেওয়া
    $photo = end($message['photo']);
    $fileId = $photo['file_id'];
} elseif (!empty($message['document']) && str_starts_with($message['document']['mime_type'] ?? '', 'image/')) {
    // কেউ আনকমপ্রেসড ফাইল/ডকুমেন্ট হিসেবে ছবি পাঠালে
    $fileId = $message['document']['file_id'];
}

if ($fileId) {
    // টেলিগ্রাম থেকে ছবির পাথ বের করা
    $fileInfoUrl = "https://api.telegram.org/bot{$botToken}/getFile?file_id={$fileId}";
    $fileInfo = json_decode(file_get_contents($fileInfoUrl), true);

    if (!empty($fileInfo['result']['file_path'])) {
        $filePath = $fileInfo['result']['file_path'];
        $downloadUrl = "https://api.telegram.org/file/bot{$botToken}/{$filePath}";

        // ছবি ডাউনলোড করা
        $imageContent = file_get_contents($downloadUrl);

        if ($imageContent !== false) {
            // Catbox.moe API-তে পাঠানো
            $boundary = "----WebKitFormBoundary" . md5(microtime());
            $ext = pathinfo($filePath, PATHINFO_EXTENSION) ?: 'jpg';
            $filename = "img_" . time() . "_" . bin2hex(random_bytes(4)) . ".{$ext}";

            $payload  = "--{$boundary}\r\n";
            $payload .= "Content-Disposition: form-data; name=\"reqtype\"\r\n\r\n";
            $payload .= "fileupload\r\n";
            $payload .= "--{$boundary}\r\n";
            $payload .= "Content-Disposition: form-data; name=\"fileToUpload\"; filename=\"{$filename}\"\r\n";
            $payload .= "Content-Type: image/{$ext}\r\n\r\n";
            $payload .= $imageContent . "\r\n";
            $payload .= "--{$boundary}--\r\n";

            $ch = curl_init("https://catbox.moe/user/api.php");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: multipart/form-data; boundary={$boundary}",
                "Content-Length: " . strlen($payload)
            ]);

            $catboxUrl = trim(curl_exec($ch));
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

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
                sendMessage($botToken, $chatId, "⚠️ আপলোড ব্যর্থ হয়েছে। আবার চেষ্টা করুন।", '', $messageId);
            }
        }
    }
    exit;
}

// Telegram API Helper ফাংশন
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
