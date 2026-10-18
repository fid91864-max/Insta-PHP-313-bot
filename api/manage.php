<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, DELETE");

$adminPassword = "9876543210";
$jsonPath = __DIR__ . '/../data/keys.json';

// অথেন্টিকেশন চেক
$headers = getallheaders();
$auth = $headers['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $auth);

if ($token !== $adminPassword) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "ভুল পাসওয়ার্ড!"]);
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

// ১. তালিকা প্রদর্শন (GET)
if ($method === 'GET') {
    echo json_encode(array_values($keys));
    exit;
}

// ২. নতুন কি তৈরি (POST)
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $prefix = !empty($input['prefix']) ? strtoupper(trim($input['prefix'])) : 'KEY';
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

// ৩. কি ডিলেট করা (DELETE)
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if ($id) {
        $keys = array_filter($keys, fn($k) => $k['id'] != $id);
        saveKeys($jsonPath, array_values($keys));
        echo json_encode(["status" => "success"]);
    }
    exit;
}
