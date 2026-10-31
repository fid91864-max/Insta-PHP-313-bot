<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$devCredit = "@abdullah_al_hasib_313";

$prompt = $_GET['prompt'] ?? null;
$style  = $_GET['style']  ?? 'No style';
$ratio  = strtolower($_GET['ratio'] ?? 'square');
$model  = $_GET['model']  ?? 'flux';

// JSON ইনপুট সাপোর্ট
if (!$prompt) {
    $rawInput = file_get_contents('php://input');
    $body = json_decode($rawInput, true);
    if ($body) {
        $prompt = $body['prompt'] ?? null;
        $style  = $body['style']  ?? $style;
        $ratio  = strtolower($body['ratio'] ?? $ratio);
        $model  = $body['model']  ?? $model;
    }
}

if (!$prompt || trim($prompt) === '') {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => "Prompt parameter is required.",
        "developer" => $devCredit
    ], JSON_UNESCAPED_SLASHES);
    exit();
}

// ১. স্ক্রিনশট অনুযায়ী রেজোলিউশন হ্যান্ডলিং
$width = 768;
$height = 768;

if ($ratio === 'portrait') {
    $width = 576;
    $height = 1024;
} elseif ($ratio === 'landscape') {
    $width = 1024;
    $height = 576;
}

// ২. স্টাইল প্রম্পট মডিফায়ার
$finalPrompt = trim($prompt);
if (strcasecmp($style, "No style") !== 0) {
    $finalPrompt .= ", in the style of {$style}, professional aesthetic, high details";
}

$seed = rand(100000000, 999999999);
$encodedPrompt = urlencode($finalPrompt);

// ৩. ইমেজ ইউআরএল তৈরি
$imageUrl = "https://image.pollinations.ai/prompt/{$encodedPrompt}?width={$width}&height={$height}&seed={$seed}&model={$model}&nologo=true";

echo json_encode([
    "success" => true,
    "prompt" => $prompt,
    "applied_style" => $style,
    "ratio" => $ratio,
    "resolution" => "{$width}x{$height}",
    "seed" => $seed,
    "model" => $model,
    "image_url" => $imageUrl,
    "developer" => $devCredit
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
