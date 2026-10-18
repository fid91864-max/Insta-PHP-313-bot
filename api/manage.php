<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Admin-Token");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");

// OPTIONS প্রি-ফ্লাইট রিকোয়েস্ট হ্যান্ডেল করা
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$adminPassword = "9876543210";
$jsonPath = __DIR__ . '/../data/keys.json';

// ডাটা ফোল্ডার না থাকলে স্বয়ংক্রিয় তৈরি
if (!is_dir(dirname($jsonPath))) {
    mkdir(dirname($jsonPath), 0777, true);
}
if (!file_exists($jsonPath)) {
    file_put_contents($jsonPath, '[]');
}

// পাসওয়ার্ড রিড করা (সব রকম উপায়ে সাপোর্ট রাখা হয়েছে)
$receivedPass = '';

if (isset($_SERVER['HTTP_X_ADMIN_TOKEN'])) {
    $receivedPass = trim($_SERVER['HTTP_X_ADMIN_TOKEN']);
} elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $receivedPass = trim(str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']));
} elseif (function_exists('getallheaders')) {
    $headers = getallheaders();
    $receivedPass = $headers['X-Admin-Token'] ?? $headers['x-admin-token'] ?? str_replace('Bearer ', '', $headers['Authorization'] ?? $headers['authorization'] ?? '');
    $receivedPass = trim($receivedPass);
}

// পাসওয়ার্ড যাচাই
if ($receivedPass !== $adminPassword) {
    http_response_code(401);
    echo json_encode([
        "status" => "error", 
        "message" => "ভুল পাসওয়ার্ড!",
        "debug_received" => $receivedPass // খালি আসলে ধরা পড়বে
    ]);
    exit;
}

function loadKeys($path) {
    if (!file_exists($path)) return [];
    return json_decode(file_get_contents($path), true) ?: [];
}

function saveKeys($path, $data) {
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
}

$method = $_SERVER['REQUEST_METHOD'];
$keys = loadKeys($jsonPath);

// ১. তালিকা প্রদর্শন
if ($method === 'GET') {
    echo json_encode(array_values($keys));
    exit;
}

// ২. নতুন কি তৈরি
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $prefix = !empty($input['prefix']) ? strtoupper(trim($input['prefix'])) : 'VIP';
    $limit  = (int)($input['limit'] ?? 100);

    $newKey = $prefix . "_" . strtoupper(bin2hex(random_bytes(5)));
    $id = time();

    $keys[] = [
        "id" => $id,
        "api_key" => $newKey,
        "req_limit" => $limit,
        "req_used" => 0,
        "status" => "active"
    ];

    saveKeys($jsonPath, $keys);
    echo json_encode(["status" => "success", "key" => $newKey]);
    exit;
}

// ৩. ডিলিট করা
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if ($id) {
        $keys = array_filter($keys, fn($k) => $k['id'] != $id);
        saveKeys($jsonPath, array_values($keys));
        echo json_encode(["status" => "success"]);
    }
    exit;
}
