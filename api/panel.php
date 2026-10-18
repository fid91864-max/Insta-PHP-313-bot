<?php
$adminPassword = "9876543210";
$jsonPath = __DIR__ . '/../data/keys.json';

if (!is_dir(dirname($jsonPath))) {
    mkdir(dirname($jsonPath), 0777, true);
}
if (!file_exists($jsonPath)) {
    file_put_contents($jsonPath, '[]');
}

function loadKeys($path) {
    if (!file_exists($path)) return [];
    return json_decode(file_get_contents($path), true) ?: [];
}

function saveKeys($path,$data) {
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
}

// ব্যাকএন্ড API অ্যাকশন হ্যান্ডলার
if (isset($_GET['action'])) {
    header("Content-Type: application/json; charset=UTF-8");
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $token =$_SERVER['HTTP_X_ADMIN_TOKEN'] ?? $headers['X-Admin-Token'] ?? $headers['x-admin-token'] ?? '';

    if ($token !==$adminPassword) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "ভুল পাসওয়ার্ড!"]);
        exit;
    }

    $keys = loadKeys($jsonPath);
    $action =$_GET['action'];

    if ($action === 'list') {
        echo json_encode(array_values($keys));
        exit;
    }

    if ($action === 'create' &&$_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);$prefix = !empty($input['prefix']) ? strtoupper(trim($input['prefix'])) : 'VIP';
        $limit  = (int)($input['limit'] ?? 100);

        $newKey =$prefix . "_" . strtoupper(bin2hex(random_bytes(5)));
        $keys[] = [
            "id" => time(),
            "api_key" => $newKey,
            "req_limit" => $limit,
            "req_used" => 0,
            "status" => "active"
        ];
        saveKeys($jsonPath,$keys);
        echo json_encode(["status" => "success", "key" => $newKey]);
        exit;
    }

    if ($action === 'delete') {
        $id =$_GET['id'] ?? null;
        if ($id) {$keys = array_filter($keys, fn($k) => $k['id'] !=$id);
            saveKeys($jsonPath, array_values($keys));
            echo json_encode(["status" => "success"]);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internal Console</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #0b0f19; color: #f8fafc; padding: 20px; }
        .box { max-width: 900px; margin: 0 auto; background: #131c31; border: 1px solid #1e293b; padding: 30px; border-radius: 14px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        h2 { color: #38bdf8; margin-bottom: 20px; }
        .login-box { max-width: 400px; margin: 80px auto; background: #131c31; border: 1px solid #1e293b; padding: 30px; border-radius: 14px; text-align: center; }
        input, button { padding: 12px 16px; border-radius: 8px; border: 1px solid #334155; font-size: 15px; }
        input { background: #0b0f19; color: #fff; outline: none; }
        button { background: #0284c7; color: #fff; border: none; font-weight: bold; cursor: pointer; }
        button:hover { background: #0369a1; }
        .form-row { display: flex; gap: 10px; margin-bottom: 25px; flex-wrap: wrap; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #1e293b; }
        th { background: #0b0f19; color: #94a3b8; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .badge-active { background: #065f46; color: #34d399; }
        .badge-limit { background: #991b1b; color: #f87171; }
        .btn-del { background: #ef4444; padding: 6px 12px; font-size: 13px; }
        code { background: #0b0f19; padding: 4px 8px; border-radius: 4px; color: #38bdf8; font-family: monospace; }
        .err-text { color: #f87171; font-size: 14px; margin-top: 10px; display: none; }
    </style>
</head>
<body>

<div id="loginSection" class="login-box">
    <h2>অ্যাডমিন লগইন</h2>
    <input type="password" id="adminPass" placeholder="পাসওয়ার্ড দিন..." style="width: 100%; margin-bottom: 15px;">
    <button onclick="login()" style="width: 100%;">লগইন করুন</button>
    <div id="loginError" class="err-text">ভুল পাসওয়ার্ড!</div>
</div>

<div id="dashboardSection" class="box" style="display: none;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h2>API Key & Limit Manager</h2>
        <button onclick="logout()" style="background: #334155;">লগআউট</button>
    </div>

    <div class="form-row">
        <input type="text" id="keyPrefix" placeholder="Prefix" value="VIP">
        <input type="number" id="keyLimit" placeholder="লিমিট" value="100">
        <button onclick="createKey()">+ নতুন Key</button>
    </div>

    <table>
        <thead>
            <tr>
                <th>API Key</th>
                <th>ব্যবহার / লিমিট</th>
                <th>স্ট্যাটাস</th>
                <th>অ্যাকশন</th>
            </tr>
        </thead>
        <tbody id="keysTable"></tbody>
    </table>
</div>

<script>
    let token = localStorage.getItem('admin_token') || '';
    if (token) checkAuth(token);

    async function login() {
        const pass = document.getElementById('adminPass').value.trim();
        if (!pass) return;
        document.getElementById('loginError').style.display = 'none';
        await checkAuth(pass, true);
    }

    function logout() {
        localStorage.removeItem('admin_token');
        token = '';
        document.getElementById('loginSection').style.display = 'block';
        document.getElementById('dashboardSection').style.display = 'none';
        document.getElementById('adminPass').value = '';
    }

    async function checkAuth(testPass, isManual = false) {
        try {
            const res = await fetch('/api/panel.php?action=list', {
                headers: { 'X-Admin-Token': testPass }
            });
            if (res.status === 200) {
                token = testPass;
                localStorage.setItem('admin_token', token);
                document.getElementById('loginSection').style.display = 'none';
                document.getElementById('dashboardSection').style.display = 'block';
                const data = await res.json();
                renderTable(data);
            } else {
                if (isManual) {
                    document.getElementById('loginError').style.display = 'block';
                } else {
                    logout();
                }
            }
        } catch (e) {
            alert("সার্ভার এরর!");
        }
    }

    function renderTable(data) {
        const tbody = document.getElementById('keysTable');
        tbody.innerHTML = '';
        if (!data || data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; color:#94a3b8;">কোনো Key নেই।</td></tr>';
            return;
        }
        data.forEach(item => {
            const isExhausted = item.req_used >= item.req_limit;
            tbody.innerHTML += `
                <tr>
                    <td><code>${item.api_key}</code></td>
                    <td><b>${item.req_used}</b> / ${item.req_limit}</td>
                    <td><span class="badge ${isExhausted ? 'badge-limit' : 'badge-active'}">${isExhausted ? 'Exhausted' : 'Active'}</span></td>
                    <td><button class="btn-del" onclick="deleteKey(${item.id})">Delete</button></td>
                </tr>
            `;
        });
    }

    async function createKey() {
        const prefix = document.getElementById('keyPrefix').value.trim();
        const limit = document.getElementById('keyLimit').value;
        await fetch('/api/panel.php?action=create', {
            method: 'POST',
            headers: { 'X-Admin-Token': token, 'Content-Type': 'application/json' },
            body: JSON.stringify({ prefix, limit })
        });
        checkAuth(token);
    }

    async function deleteKey(id) {
        if (!confirm("মুছে ফেলতে চান?")) return;
        await fetch('/api/panel.php?action=delete&id=' + id, {
            headers: { 'X-Admin-Token': token }
        });
        checkAuth(token);
    }
</script>

</body>
</html>
