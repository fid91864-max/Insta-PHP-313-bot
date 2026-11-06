<?php
// Telegram Bot Token
$botToken = "9098236929:AAEnHEfMew9Vs7hQAd20_deag6HrrM-mJfQ";
$telegramApi = "https://api.telegram.org/bot" . $botToken;

// Telegram Webhook Update Read Karna
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
    http_response_code(200);
    echo json_encode(["status" => "online", "developer" => "@abdullah_al_hasib_313"]);
    exit();
}

$message = $update["message"] ?? null;
if (!$message || !isset($message["text"])) {
    exit();
}

$chatId = $message["chat"]["id"];
$userText = trim($message["text"]);

// /start Command Handler
if ($userText === "/start") {
    $welcomeText = "Assalamu Alaikum! Main ek AI Bot hoon.\n\nMujhe koi bhi sawaal puchiye ya code likhne ko kahiye, main turant jawab dunga!";
    sendTelegramMessage($telegramApi, $chatId, $welcomeText);
    exit();
}

// Telegram me "typing..." action dikhana
sendChatAction($telegramApi, $chatId, "typing");

// Pollinations AI Call Karna
$aiReply = getAiResponse($userText);

// User ko jawab bhejna
sendTelegramMessage($telegramApi, $chatId, $aiReply);

// ----------------- Helper Functions ----------------- //

function getAiResponse($prompt) {
    $payload = [
        "messages" => [
            [
                "role" => "system",
                "content" => "You are an intelligent, helpful AI assistant and expert coder. Answer clearly and provide clean code whenever requested."
            ],
            [
                "role" => "user",
                "content" => $prompt
            ]
        ],
        "model" => "openai",
        "seed" => 42
    ];

    $ch = curl_init("https://text.pollinations.ai/openai");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_TIMEOUT => 45,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $res) {
        $data = json_decode($res, true);
        if (isset($data["choices"][0]["message"]["content"])) {
            return $data["choices"][0]["message"]["content"];
        }
    }

    // Fallback GET Request
    $fallbackUrl = "https://text.pollinations.ai/" . rawurlencode($prompt);
    $fallbackRes = @file_get_contents($fallbackUrl);
    if ($fallbackRes !== false) {
        return $fallbackRes;
    }

    return "Server busy hai, kripya thodi der baad dobara koshish karein.";
}

function sendTelegramMessage($api, $chatId, $text) {
    $url = $api . "/sendMessage";
    $postData = [
        "chat_id" => $chatId,
        "text" => $text
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function sendChatAction($api, $chatId, $action) {
    $url = $api . "/sendChatAction";
    $postData = [
        "chat_id" => $chatId,
        "action" => $action
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_TIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    curl_exec($ch);
    curl_close($ch);
}
