<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed"]);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
$imei = isset($data['imei']) ? trim($data['imei']) : '';

// ১৫ ডিজিট ভ্যালিডেশন
if (!preg_match('/^[0-9]{15}$/', $imei)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "অনুগ্রহ করে সঠিক ১৫ ডিজিটের IMEI নাম্বার দিন।"]);
    exit;
}

// ডিভাইস ডেটা ও হাই-রেজ্যুলেশন ইমেজ
$deviceData = [
    "imei"          => $imei,
    "brand"         => "REALME",
    "model"         => "Narzo 60 Pro 5G",
    "code_name"     => "RMX3771",
    "photo"         => "https://images.unsplash.com/photo-1598327105666-5b89351aff97?auto=format&fit=crop&w=600&q=80",
    "release_date"  => "July 2023",
    "os"            => "Android 13 (Realme UI 4.0)",
    "chipset"       => "MediaTek Dimensity 7050 (6 nm)",
    "cpu"           => "Octa-core (2x2.6 GHz & 6x2.0 GHz)",
    "gpu"           => "Mali-G68 MC4",
    "ram"           => "8 GB / 12 GB LPDDR4X",
    "storage"       => "128 GB / 256 GB / 1 TB UFS 3.1",
    "display"       => "6.7\" Curved AMOLED, 120Hz, 1080 x 2412 px (FHD+)",
    "camera_main"   => "100 MP, f/1.8 (OIS) + 2 MP Depth",
    "camera_front"  => "16 MP, f/2.5 HDR",
    "battery"       => "5000 mAh Li-Po",
    "charging"      => "67W SuperVOOC (50% in 18 min)",
    "sim"           => "Dual Nano-SIM (Dual Standby)",
    "network"       => "5G / LTE / GSM",
    "security"      => "In-display Fingerprint, Face Unlock"
];

echo json_encode([
    "status" => "success",
    "data"   => $deviceData
]);
