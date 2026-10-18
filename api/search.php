<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

$supabaseUrl = "https://mvogzotkhobfuhzebyvk.supabase.co";
$supabaseKey = "sb_secret_gMS0t901S53a4cjgBNVa7w_D92xGjpj";

// ফোন নম্বর ভ্যারিয়েন্ট তৈরি ফাংশন (+972, 05, ইত্যাদি)
function generatePhoneVariants($phoneStr) {
    $digits = preg_replace('/\D/', '', $phoneStr);
    $variants = [];
    $variants[] = trim($phoneStr);

    if (!empty($digits)) {
        $variants[] = $digits;
        $variants[] = "+" . $digits;
        if (str_starts_with($digits, "972")) {
            $variants[] = "0" . substr($digits, 3);
        } elseif (str_starts_with($digits, "0")) {
            $variants[] = "972" . substr($digits, 1);
            $variants[] = "+972" . substr($digits, 1);
        }
    }
    return array_values(array_unique($variants));
}

// ইনপুট প্যারামিটার গ্রহণ
$q        = trim($_GET['q'] ?? '');
$phone    = trim($_GET['phone'] ?? '');
$username = trim($_GET['username'] ?? '');
$userId   = trim($_GET['user_id'] ?? '');
$name     = trim($_GET['name'] ?? '');
$email    = trim($_GET['email'] ?? '');
$platform = trim($_GET['platform'] ?? '');
$limit    = trim($_GET['limit'] ?? '50');

$params = [
    'select' => 'id,user_id,username,full_name,phone,email,city,platform',
    'limit'  => $limit
];

if (!empty($q)) {
    $variants = generatePhoneVariants($q);
    $conditions = [];
    foreach ($variants as $v) {
        $conditions[] = "phone.eq." . $v;
    }
    $conditions[] = "username.ilike.*" . $q . "*";
    $conditions[] = "full_name.ilike.*" . $q . "*";
    $conditions[] = "user_id.eq." . $q;
    $conditions[] = "email.ilike.*" . $q . "*";
    $params['or'] = '(' . implode(',', $conditions) . ')';
} elseif (!empty($phone)) {
    $variants = generatePhoneVariants($phone);
    $quoted = array_map(fn($v) => '"' . $v . '"', $variants);
    $params['phone'] = 'in.(' . implode(',', $quoted) . ')';
} elseif (!empty($username)) {
    $params['username'] = "ilike.*" . $username . "*";
} elseif (!empty($userId)) {
    $params['user_id'] = "eq." . $userId;
} elseif (!empty($name)) {
    $params['full_name'] = "ilike.*" . $name . "*";
} elseif (!empty($email)) {
    $params['email'] = "ilike.*" . $email . "*";
} else {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Provide at least one parameter: q, phone, username, user_id, name, or email"
    ]);
    exit;
}

if (!empty($platform)) {
    $params['platform'] = "eq." . $platform;
}

// Supabase REST API কল
$queryStr = http_build_query($params);
$ch = curl_init("{$supabaseUrl}/rest/v1/contacts?{$queryStr}");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "apikey: {$supabaseKey}",
    "Authorization: Bearer {$supabaseKey}",
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $results = json_decode($response, true);
    echo json_encode([
        "status" => "success",
        "total_found" => count($results),
        "results" => $results
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    http_response_code($httpCode);
    echo json_encode([
        "status" => "error",
        "http_code" => $httpCode,
        "message" => json_decode($response, true) ?: $response
    ]);
}
