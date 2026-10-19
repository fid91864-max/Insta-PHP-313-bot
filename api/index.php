<?php
error_reporting(0);
ini_set('display_errors', 0);

// AJAX স্ক্যান রিকোয়েস্ট হ্যান্ডলার
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'scan') {
    header("Content-Type: application/json; charset=UTF-8");

    $tempFile = null;
    $fileName = 'scan.jpg';

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $tempFile = $_FILES['image']['tmp_name'];
        $fileName = $_FILES['image']['name'];
    } elseif (!empty($_POST['url'])) {
        $imgUrl = trim($_POST['url']);
        if (!filter_var($imgUrl, FILTER_VALIDATE_URL)) {
            echo json_encode(["status" => "error", "message" => "ভুল ইমেজ লিঙ্ক দিয়েছেন!"]);
            exit;
        }
        $imgData = @file_get_contents($imgUrl);
        if (!$imgData) {
            echo json_encode(["status" => "error", "message" => "ইমেজ ডাউনলোড করা যায়নি। লিঙ্ক চেক করুন।"]);
            exit;
        }
        $tempFile = tempnam(sys_get_temp_dir(), 'ai_detect_');
        file_put_contents($tempFile, $imgData);
        $fileName = basename(parse_url($imgUrl, PHP_URL_PATH)) ?: 'image.jpg';
    } else {
        echo json_encode(["status" => "error", "message" => "কোনো ছবি সিলেক্ট করেননি!"]);
        exit;
    }

    // Step 1: DeepAI এ আপলোড
    $cFile = new CURLFile($tempFile, mime_content_type($tempFile) ?: 'image/jpeg', $fileName);
    $ch = curl_init("https://api.deepai.org/ai-image-detector-upload");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => ["image" => $cFile],
        CURLOPT_HTTPHEADER     => [
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/155.0.0.0 Safari/537.36",
            "Origin: https://deepai.org",
            "Referer: https://deepai.org/ai-image-detector"
        ],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $uploadRes = curl_exec($ch);
    curl_close($ch);

    if (isset($_POST['url']) && file_exists($tempFile)) {
        @unlink($tempFile);
    }

    $uploadData = json_decode($uploadRes, true);
    $taskId = $uploadData['id'] ?? null;

    if (!$taskId) {
        echo json_encode(["status" => "error", "message" => "স্ক্যানার সার্ভারে কানেক্ট করা যায়নি। আবার চেষ্টা করুন।"]);
        exit;
    }

    // Step 2: রেজাল্ট পোলে ফেচ করা
    $finalData = null;
    for ($i = 0; $i < 10; $i++) {
        usleep(700000); // 0.7 সেকেন্ড পর পর চেক
        $qCh = curl_init("https://api.deepai.org/ai-image-detector-query");
        curl_setopt_array($qCh, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(["id" => $taskId]),
            CURLOPT_HTTPHEADER     => [
                "Content-Type: application/json",
                "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/155.0.0.0 Safari/537.36",
                "Origin: https://deepai.org",
                "Referer: https://deepai.org/ai-image-detector"
            ],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $queryRes = curl_exec($qCh);
        curl_close($qCh);

        $parsed = json_decode($queryRes, true);
        if ($parsed && ($parsed['status'] ?? '') === 'done') {
            $finalData = $parsed;
            break;
        }
    }

    if (!$finalData) {
        echo json_encode(["status" => "error", "message" => "স্ক্যান সম্পন্ন হতে দেরি হচ্ছে, আবার চেষ্টা করুন!"]);
        exit;
    }

    $aiScore = round(floatval($finalData['result'] ?? 0), 1);
    $details = $finalData['result_details'] ?? [];
    $isAi = ($details['final_result'] ?? '') === 'AI' || $aiScore > 50;
    $realScore = round(100 - $aiScore, 1);

    echo json_encode([
        "status"     => "success",
        "verdict"    => $isAi ? "AI Generated Image" : "Real Human Photograph",
        "is_ai"      => $isAi,
        "ai_score"   => $aiScore,
        "real_score" => $realScore,
        "step"       => $details['detection_step'] ?? 3
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI Image Detector - Cyber 313</title>
<style>
  :root {
    --bg: #070913;
    --card: #0f1424;
    --cyan: #00f0ff;
    --pink: #ff0055;
    --green: #00ff88;
    --text: #ffffff;
    --muted: #8b9bb4;
    --border: #1b243d;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
  body {
    background-color: var(--bg);
    background-image: 
      radial-gradient(circle at 15% 15%, #00f0ff18 0%, transparent 40%),
      radial-gradient(circle at 85% 85%, #ff005518 0%, transparent 40%);
    color: var(--text);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
  }
  .navbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 18px 24px;
    border-bottom: 1px solid var(--border);
    backdrop-filter: blur(10px);
  }
  .brand {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    color: #fff;
    font-weight: 800;
    font-size: 18px;
    letter-spacing: 0.5px;
  }
  .brand-logo {
    width: 38px;
    height: 38px;
    background: linear-gradient(135deg, var(--cyan), var(--pink));
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    box-shadow: 0 0 15px #00f0ff55;
  }
  .tg-link {
    background: #141c30;
    border: 1px solid var(--border);
    color: var(--cyan);
    text-decoration: none;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 700;
    transition: 0.2s;
  }
  .tg-link:hover {
    background: var(--cyan);
    color: #000;
    box-shadow: 0 0 15px var(--cyan);
  }
  .container {
    max-width: 900px;
    margin: 40px auto;
    padding: 0 20px;
    width: 100%;
    flex: 1;
  }
  .hero {
    text-align: center;
    margin-bottom: 35px;
  }
  .hero-tag {
    color: var(--cyan);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-bottom: 8px;
  }
  .hero h1 {
    font-size: clamp(28px, 4vw, 44px);
    font-weight: 900;
    letter-spacing: -0.5px;
    margin-bottom: 10px;
  }
  .gradient {
    background: linear-gradient(90deg, #fff, var(--cyan), var(--pink));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
  }
  .hero p {
    color: var(--muted);
    font-size: 15px;
  }
  .app-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 24px;
    padding: 30px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.5);
    position: relative;
    overflow: hidden;
  }
  .app-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; height: 2px;
    background: linear-gradient(90deg, transparent, var(--cyan), var(--pink), transparent);
  }
  .dropzone {
    border: 2px dashed #283756;
    border-radius: 18px;
    padding: 40px 20px;
    text-align: center;
    cursor: pointer;
    background: #090e1c;
    transition: 0.2s;
  }
  .dropzone:hover, .dropzone.active {
    border-color: var(--cyan);
    background: #00f0ff08;
  }
  .drop-icon { font-size: 42px; margin-bottom: 10px; }
  .drop-text { font-size: 14px; color: var(--muted); }
  .drop-text strong { color: #fff; }
  .or-divider {
    text-align: center;
    margin: 20px 0;
    position: relative;
    color: #43526e;
    font-size: 12px;
    font-weight: 700;
  }
  .or-divider::before, .or-divider::after {
    content: '';
    position: absolute;
    top: 50%;
    width: 44%;
    height: 1px;
    background: #19243d;
  }
  .or-divider::before { left: 0; }
  .or-divider::after { right: 0; }
  .url-input-row {
    display: flex;
    gap: 10px;
  }
  input[type="text"] {
    flex: 1;
    background: #090e1c;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px 16px;
    color: #fff;
    font-size: 14px;
    outline: none;
  }
  input[type="text"]:focus { border-color: var(--cyan); }
  .scan-btn {
    background: linear-gradient(135deg, var(--cyan), #7c3aed);
    color: #000;
    font-weight: 800;
    font-size: 14px;
    padding: 0 24px;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    transition: 0.2s;
    box-shadow: 0 0 15px #00f0ff44;
  }
  .scan-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 0 25px #00f0ff77;
  }
  /* প্রিভিউ বক্স */
  .preview-wrap {
    display: none;
    margin-top: 20px;
    background: #090e1c;
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 12px;
    align-items: center;
    gap: 15px;
  }
  .preview-wrap img {
    width: 65px;
    height: 65px;
    object-fit: cover;
    border-radius: 10px;
  }
  .preview-details {
    flex: 1;
    font-size: 13px;
    overflow: hidden;
  }
  .preview-details span {
    display: block;
    color: var(--muted);
    font-size: 11px;
    margin-top: 3px;
  }
  /* লোডার */
  .loader-area {
    display: none;
    text-align: center;
    padding: 40px 0;
  }
  .radar-spinner {
    width: 50px;
    height: 50px;
    border: 3px solid #1a2844;
    border-top-color: var(--cyan);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin: 0 auto 15px;
  }
  @keyframes spin { to { transform: rotate(360deg); } }
  /* রেজাল্ট কার্ড */
  .result-area {
    display: none;
    margin-top: 30px;
    border-top: 1px solid var(--border);
    padding-top: 25px;
  }
  .verdict-box {
    padding: 18px;
    border-radius: 14px;
    text-align: center;
    font-size: 22px;
    font-weight: 900;
    margin-bottom: 25px;
    letter-spacing: 0.5px;
  }
  .verdict-ai {
    background: #ff00551a;
    border: 1px solid var(--pink);
    color: var(--pink);
    box-shadow: 0 0 20px #ff005533;
  }
  .verdict-real {
    background: #00ff881a;
    border: 1px solid var(--green);
    color: var(--green);
    box-shadow: 0 0 20px #00ff8833;
  }
  .score-bars {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
  }
  .score-item {
    background: #090e1c;
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 15px;
  }
  .score-title {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    margin-bottom: 8px;
    font-weight: 700;
  }
  .progress-bg {
    height: 8px;
    background: #162035;
    border-radius: 6px;
    overflow: hidden;
  }
  .progress-fill {
    height: 100%;
    width: 0%;
    transition: width 0.8s cubic-bezier(0.1, 1, 0.1, 1);
  }
  .fill-ai { background: linear-gradient(90deg, #7c3aed, var(--pink)); }
  .fill-real { background: linear-gradient(90deg, var(--cyan), var(--green)); }
  footer {
    text-align: center;
    padding: 24px;
    color: #4a5c7a;
    font-size: 13px;
    border-top: 1px solid var(--border);
  }
  footer a { color: var(--cyan); text-decoration: none; font-weight: 700; }
  @media(max-width: 600px) {
    .score-bars { grid-template-columns: 1fr; }
    .url-input-row { flex-direction: column; }
    .scan-btn { padding: 14px; }
  }
</style>
</head>
<body>

<nav class="navbar">
  <a href="#" class="brand">
    <div class="brand-logo">👁️</div>
    <span>AI DETECTOR 313</span>
  </a>
  <a href="https://t.me/abdullah_al_hasib_313" target="_blank" class="tg-link">
    ✈ @abdullah_al_hasib_313
  </a>
</nav>

<div class="container">
  <div class="hero">
    <div class="hero-tag">Neural Detection Scanner</div>
    <h1>ছবিটি কি আসল, নাকি <span class="gradient">AI দিয়ে বানানো?</span></h1>
    <p>যেকোনো ছবি আপলোড করুন অথবা লিংক পেস্ট করে মুহূর্তে সত্যতা যাচাই করুন।</p>
  </div>

  <div class="app-card">
    <div class="dropzone" id="dropArea">
      <div class="drop-icon">📤</div>
      <div class="drop-text"><strong>ছবি আপলোড করতে ট্যাপ করুন</strong> অথবা ড্র্যাগ করুন</div>
      <input type="file" id="filePicker" accept="image/*" style="display:none">
    </div>

    <div class="preview-wrap" id="previewBox">
      <img id="thumbImg" src="" alt="preview">
      <div class="preview-details">
        <strong id="thumbName">image.jpg</strong>
        <span>রেডি ফর স্ক্যান</span>
      </div>
    </div>

    <div class="or-divider">অথবা ছবির লিংক দিন</div>

    <div class="url-input-row">
      <input type="text" id="linkInput" placeholder="https://example.com/photo.jpg">
      <button class="scan-btn" id="startScanBtn">স্ক্যান করুন</button>
    </div>

    <div class="loader-area" id="loaderBox">
      <div class="radar-spinner"></div>
      <strong style="color:var(--cyan)">ছবিটির নিউরাল প্যাটার্ন চেক করা হচ্ছে...</strong>
    </div>

    <div class="result-area" id="resultBox">
      <div class="verdict-box" id="verdict">REAL</div>
      
      <div class="score-bars">
        <div class="score-item">
          <div class="score-title">
            <span style="color:var(--pink)">AI হওয়ার সম্ভাবনা</span>
            <span id="aiPercent" style="color:var(--pink)">0%</span>
          </div>
          <div class="progress-bg">
            <div class="progress-fill fill-ai" id="aiBar"></div>
          </div>
        </div>

        <div class="score-item">
          <div class="score-title">
            <span style="color:var(--green)">আসল হওয়ার সম্ভাবনা</span>
            <span id="realPercent" style="color:var(--green)">0%</span>
          </div>
          <div class="progress-bg">
            <div class="progress-fill fill-real" id="realBar"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<footer>
  তৈরি করেছেন: <a href="https://t.me/abdullah_al_hasib_313" target="_blank">@abdullah_al_hasib_313</a>
</footer>

<script>
  const dropArea = document.getElementById('dropArea');
  const filePicker = document.getElementById('filePicker');
  const previewBox = document.getElementById('previewBox');
  const thumbImg = document.getElementById('thumbImg');
  const thumbName = document.getElementById('thumbName');
  const linkInput = document.getElementById('linkInput');
  const startScanBtn = document.getElementById('startScanBtn');
  const loaderBox = document.getElementById('loaderBox');
  const resultBox = document.getElementById('resultBox');

  const verdict = document.getElementById('verdict');
  const aiPercent = document.getElementById('aiPercent');
  const realPercent = document.getElementById('realPercent');
  const aiBar = document.getElementById('aiBar');
  const realBar = document.getElementById('realBar');

  let chosenFile = null;

  dropArea.onclick = () => filePicker.click();
  filePicker.onchange = (e) => {
    if (e.target.files.length) handleFile(e.target.files[0]);
  };

  dropArea.ondragover = (e) => { e.preventDefault(); dropArea.classList.add('active'); };
  dropArea.ondragleave = () => dropArea.classList.remove('active');
  dropArea.ondrop = (e) => {
    e.preventDefault();
    dropArea.classList.remove('active');
    if (e.dataTransfer.files.length) handleFile(e.dataTransfer.files[0]);
  };

  function handleFile(file) {
    chosenFile = file;
    linkInput.value = '';
    thumbName.textContent = file.name;
    const r = new FileReader();
    r.onload = (ev) => {
      thumbImg.src = ev.target.result;
      previewBox.style.display = 'flex';
    };
    r.readAsDataURL(file);
  }

  startScanBtn.onclick = async () => {
    const url = linkInput.value.trim();
    if (!chosenFile && !url) {
      alert("দয়া করে একটি ছবি বেছে নিন অথবা ছবির URL দিন!");
      return;
    }

    resultBox.style.display = 'none';
    loaderBox.style.display = 'block';

    const fd = new FormData();
    fd.append('action', 'scan');
    if (chosenFile) fd.append('image', chosenFile);
    else fd.append('url', url);

    try {
      const res = await fetch('index.php', { method: 'POST', body: fd });
      const data = await res.json();
      loaderBox.style.display = 'none';

      if (data.status === 'success') {
        resultBox.style.display = 'block';
        verdict.textContent = data.verdict;
        verdict.className = 'verdict-box ' + (data.is_ai ? 'verdict-ai' : 'verdict-real');

        aiPercent.textContent = data.ai_score + '%';
        realPercent.textContent = data.real_score + '%';
        aiBar.style.width = data.ai_score + '%';
        realBar.style.width = data.real_score + '%';
      } else {
        alert(data.message || 'স্ক্যান ব্যর্থ হয়েছে!');
      }
    } catch (err) {
      loaderBox.style.display = 'none';
      alert('রিকোয়েস্ট ফেইল করেছে: ' + err.message);
    }
  };
</script>
</body>
</html>
