<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// আপনার বট ক্রেডেনশিয়ালস
$botToken = "8712159172:AAHBS_yBuBCA3kzExWEmZRz9U_UK1G3FF-c";
$chatId   = "8621601432";

// ভিজিটরের তথ্য সংগ্রহ
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown IP';
$ip = explode(',',$ip)[0]; // একাধিক প্রক্সি আইপি থাকলে প্রথমটি নেওয়া
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Device';$time = date("Y-m-d H:i:s");

// টেলিগ্রামে পাঠানোর মেসেজ
$text = "🔔 *নতুন ভিজিটর নোটিফিকেশন!*\n\n";
$text .= "🌐 *IP Address:* `{$ip}`\n";
$text .= "📱 *Device/Browser:* {$userAgent}\n";
$text .= "⏰ *Time:* {$time}\n";

$url = "https://api.telegram.org/bot{$botToken}/sendMessage";

$postData = [
    'chat_id' => $chatId,
    'text' => $text,
    'parse_mode' => 'Markdown'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL,$url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS,$postData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
curl_close($ch);

echo json_encode(["status" => "success", "message" => "Notification triggered"]);
