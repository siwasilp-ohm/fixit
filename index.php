<?php
require_once __DIR__ . '/config/config.php';
if (!empty($_SESSION['user_id'])) {
    header('Location: app.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>FixIt — เข้าสู่ระบบ</title>
<link rel="manifest" href="manifest.json">
<link rel="apple-touch-icon" href="assets/icons/icon-192.png">
<meta name="theme-color" content="#2196F3">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root { --primary: #2196F3; --primary-dark: #1565C0; }

  body {
    min-height: 100vh; display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, #1565C0 0%, #42A5F5 50%, #00BCD4 100%);
    font-family: 'Segoe UI', 'Prompt', system-ui, sans-serif;
    overflow: hidden;
  }

  /* Animated background blobs */
  .bg-blob { position: fixed; border-radius: 50%; filter: blur(80px); opacity: .35; animation: blob 12s ease-in-out infinite; }
  .bg-blob:nth-child(1) { width: 500px; height: 500px; background: #2196F3; top: -150px; left: -100px; animation-delay: 0s; }
  .bg-blob:nth-child(2) { width: 400px; height: 400px; background: #00BCD4; bottom: -100px; right: -80px; animation-delay: 4s; }
  .bg-blob:nth-child(3) { width: 300px; height: 300px; background: #9C27B0; top: 50%; left: 60%; animation-delay: 8s; }
  @keyframes blob { 0%,100%{transform:translate(0,0) scale(1)} 33%{transform:translate(30px,-30px) scale(1.05)} 66%{transform:translate(-20px,20px) scale(.95)} }

  .login-card {
    position: relative; z-index: 10;
    background: rgba(255,255,255,.95);
    backdrop-filter: blur(20px);
    border-radius: 24px;
    padding: 48px 40px;
    width: 100%; max-width: 420px;
    box-shadow: 0 32px 80px rgba(0,0,0,.25);
    animation: slideUp .6s cubic-bezier(.22,.61,.36,1) both;
  }
  @keyframes slideUp { from{opacity:0;transform:translateY(40px)} to{opacity:1;transform:translateY(0)} }

  .brand { text-align: center; margin-bottom: 36px; }
  .brand-icon {
    display: inline-flex; align-items: center; justify-content: center;
    width: 72px; height: 72px; border-radius: 20px;
    background: linear-gradient(135deg, #2196F3, #00BCD4);
    font-size: 32px; color: #fff; margin-bottom: 14px;
    box-shadow: 0 8px 24px rgba(33,150,243,.4);
    animation: iconPop .8s cubic-bezier(.34,1.56,.64,1) .2s both;
  }
  @keyframes iconPop { from{transform:scale(0) rotate(-20deg)} to{transform:scale(1) rotate(0)} }
  .brand h1 { font-size: 28px; font-weight: 800; color: #1a1a2e; letter-spacing: -0.5px; }
  .brand p  { color: #666; font-size: 14px; margin-top: 4px; }

  .form-group { margin-bottom: 20px; }
  .form-label { display: block; font-size: 13px; font-weight: 600; color: #444; margin-bottom: 8px; }
  .input-wrap { position: relative; }
  .input-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #aaa; font-size: 15px; }
  .form-control {
    width: 100%; padding: 13px 14px 13px 42px;
    border: 2px solid #e8e8e8; border-radius: 12px;
    font-size: 15px; background: #fafafa; transition: .2s;
    outline: none; color: #1a1a2e;
  }
  .form-control:focus { border-color: var(--primary); background: #fff; box-shadow: 0 0 0 3px rgba(33,150,243,.12); }
  .toggle-pw {
    position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
    background: none; border: none; cursor: pointer; color: #aaa; font-size: 15px; padding: 4px;
  }
  .toggle-pw:hover { color: var(--primary); }

  .btn-login {
    width: 100%; padding: 14px;
    background: linear-gradient(135deg, #2196F3, #00BCD4);
    color: #fff; border: none; border-radius: 12px;
    font-size: 16px; font-weight: 700; cursor: pointer;
    transition: .2s; letter-spacing: .3px;
    box-shadow: 0 6px 20px rgba(33,150,243,.35);
  }
  .btn-login:hover  { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(33,150,243,.45); }
  .btn-login:active { transform: translateY(0); }
  .btn-login:disabled { opacity: .7; cursor: not-allowed; transform: none; }

  .alert-error {
    padding: 12px 16px; border-radius: 10px;
    background: #ffebee; color: #c62828;
    border-left: 4px solid #f44336;
    font-size: 14px; margin-bottom: 18px;
    display: none; animation: shake .4s ease;
  }
  .alert-error.show { display: block; }
  @keyframes shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-8px)} 75%{transform:translateX(8px)} }

  .demo-accounts {
    margin-top: 24px; padding: 16px; background: #f0f7ff;
    border-radius: 12px; border: 1px solid #bbdefb;
  }
  .demo-accounts h4 { font-size: 12px; color: #1565c0; font-weight: 700; margin-bottom: 10px; text-transform: uppercase; letter-spacing: .5px; }
  .demo-row {
    display: flex; justify-content: space-between; font-size: 12.5px; padding: 5px 0;
    border-bottom: 1px solid #e3f2fd; color: #444; cursor: pointer; border-radius: 4px; padding: 5px 8px;
    transition: .15s;
  }
  .demo-row:last-child { border-bottom: none; }
  .demo-row:hover { background: #bbdefb; }
  .demo-badge { font-size: 11px; padding: 2px 8px; border-radius: 20px; font-weight: 600; }
  .badge-admin { background: #e3f2fd; color: #1565c0; }
  .badge-officer { background: #e0f2f1; color: #00695c; }
  .badge-reporter { background: #f3e5f5; color: #6a1b9a; }
  .badge-technician { background: #fff3e0; color: #e65100; }
  .badge-director { background: #ffebee; color: #b71c1c; }

  .spinner { display: inline-block; width: 18px; height: 18px; border: 2px solid rgba(255,255,255,.4); border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; }
  @keyframes spin { to{transform:rotate(360deg)} }

  @media (max-width: 480px) { .login-card { padding: 32px 24px; margin: 16px; border-radius: 20px; } }
</style>
</head>
<body>
<div class="bg-blob"></div>
<div class="bg-blob"></div>
<div class="bg-blob"></div>

<div class="login-card">
  <div class="brand">
    <div class="brand-icon"><i class="fa-solid fa-screwdriver-wrench"></i></div>
    <h1>FixIt</h1>
    <p>ระบบแจ้งซ่อมออนไลน์</p>
  </div>

  <div class="alert-error" id="error-msg">
    <i class="fa-solid fa-circle-exclamation"></i> <span id="error-text"></span>
  </div>

  <form id="login-form" autocomplete="on">
    <div class="form-group">
      <label class="form-label">Username</label>
      <div class="input-wrap">
        <i class="fa-solid fa-user input-icon"></i>
        <input type="text" id="username" class="form-control" placeholder="กรอก Username" autocomplete="username" required>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Password</label>
      <div class="input-wrap">
        <i class="fa-solid fa-lock input-icon"></i>
        <input type="password" id="password" class="form-control" placeholder="กรอก Password" autocomplete="current-password" required>
        <button type="button" class="toggle-pw" id="toggle-pw" tabindex="-1">
          <i class="fa-solid fa-eye" id="pw-icon"></i>
        </button>
      </div>
    </div>
    <button type="submit" class="btn-login" id="btn-login">
      <i class="fa-solid fa-right-to-bracket"></i> เข้าสู่ระบบ
    </button>
  </form>

  <div class="demo-accounts">
    <h4><i class="fa-solid fa-circle-info"></i> บัญชีทดสอบ (คลิกเพื่อเติม)</h4>
    <div class="demo-row" data-u="admin" data-p="admin1234">
      <span><i class="fa-solid fa-user-shield"></i> admin</span>
      <span class="demo-badge badge-admin">Admin</span>
    </div>
    <div class="demo-row" data-u="officer1" data-p="officer1234">
      <span><i class="fa-solid fa-user-tie"></i> officer1</span>
      <span class="demo-badge badge-officer">Officer</span>
    </div>
    <div class="demo-row" data-u="user1" data-p="user1234">
      <span><i class="fa-solid fa-user"></i> user1</span>
      <span class="demo-badge badge-reporter">Reporter</span>
    </div>
    <div class="demo-row" data-u="tech1" data-p="tech1234">
      <span><i class="fa-solid fa-helmet-safety"></i> tech1</span>
      <span class="demo-badge badge-technician">Technician</span>
    </div>
    <div class="demo-row" data-u="director" data-p="director1234">
      <span><i class="fa-solid fa-user-check"></i> director</span>
      <span class="demo-badge badge-director">Director</span>
    </div>
  </div>
</div>

<script>
const form    = document.getElementById('login-form');
const errEl   = document.getElementById('error-msg');
const errText = document.getElementById('error-text');
const btnEl   = document.getElementById('btn-login');
const pwEl    = document.getElementById('password');
const pwIcon  = document.getElementById('pw-icon');

document.getElementById('toggle-pw').addEventListener('click', () => {
  const isText = pwEl.type === 'text';
  pwEl.type = isText ? 'password' : 'text';
  pwIcon.className = isText ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash';
});

document.querySelectorAll('.demo-row').forEach(row => {
  row.addEventListener('click', () => {
    document.getElementById('username').value = row.dataset.u;
    document.getElementById('password').value = row.dataset.p;
    errEl.classList.remove('show');
  });
});

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  errEl.classList.remove('show');
  btnEl.disabled = true;
  btnEl.innerHTML = '<span class="spinner"></span> กำลังเข้าสู่ระบบ...';

  try {
    const res  = await fetch('api/auth.php?action=login', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({
        username: document.getElementById('username').value.trim(),
        password: document.getElementById('password').value,
      })
    });
    const json = await res.json();
    if (json.success) {
      btnEl.innerHTML = '<i class="fa-solid fa-check"></i> สำเร็จ! กำลังโหลด...';
      window.location.href = 'app.php';
    } else {
      showError(json.message || 'เกิดข้อผิดพลาด');
    }
  } catch (err) {
    showError('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้');
  }
});

function showError(msg) {
  errText.textContent = msg;
  errEl.classList.add('show');
  btnEl.disabled = false;
  btnEl.innerHTML = '<i class="fa-solid fa-right-to-bracket"></i> เข้าสู่ระบบ';
}

// Register service worker
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('sw.js').catch(() => {});
}
</script>
</body>
</html>
