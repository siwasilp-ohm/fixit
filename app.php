<?php
require_once __DIR__ . '/config/config.php';
if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$user = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="th" data-theme="blue">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="FixIt">
<meta name="theme-color" content="<?= htmlspecialchars($user['theme_color'] ?? '#2196F3') ?>" id="meta-theme">
<title>FixIt — ระบบแจ้งซ่อม</title>
<link rel="manifest" href="manifest.json">
<link rel="apple-touch-icon" href="assets/icons/icon-192.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<script>
window.CURRENT_USER = <?= json_encode($user, JSON_UNESCAPED_UNICODE) ?>;
window.APP_BASE = '<?= rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') ?>/';
</script>
</head>
<body>

<!-- Loading Overlay -->
<div id="loading-overlay" class="loading-overlay">
  <div class="loader-wrap">
    <div class="loader-ring"><div></div><div></div><div></div><div></div></div>
    <p class="loader-text">กำลังโหลด...</p>
  </div>
</div>

<!-- App Shell -->
<div id="app" class="app-container">

  <!-- Sidebar -->
  <aside id="sidebar" class="sidebar">
    <div class="sidebar-brand">
      <div class="brand-logo"><i class="fa-solid fa-screwdriver-wrench"></i></div>
      <span class="brand-name">FixIt</span>
      <button id="sidebar-close" class="sidebar-close-btn" title="ปิดเมนู">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <div class="sidebar-user">
      <div class="user-avatar-wrap">
        <div class="user-avatar" id="sb-avatar">?</div>
        <div class="user-status-dot"></div>
      </div>
      <div class="user-info">
        <div class="user-name" id="sb-name">Loading...</div>
        <div class="user-role" id="sb-role"></div>
      </div>
    </div>

    <nav class="sidebar-nav" id="sidebar-nav"></nav>

    <div class="sidebar-footer">
      <button class="sidebar-footer-btn" onclick="App.logout()">
        <i class="fa-solid fa-right-from-bracket"></i>
        <span>ออกจากระบบ</span>
      </button>
    </div>
  </aside>

  <!-- Sidebar Overlay (mobile) -->
  <div id="sidebar-overlay" class="sidebar-overlay"></div>

  <!-- Main -->
  <main class="main-content" id="main-content">

    <!-- Topbar -->
    <header class="topbar">
      <button class="topbar-menu-btn" id="menu-toggle" title="เมนู">
        <i class="fa-solid fa-bars"></i>
      </button>
      <div class="topbar-breadcrumb">
        <span class="topbar-title" id="page-title">Dashboard</span>
      </div>
      <div class="topbar-actions">
        <button class="topbar-action-btn" id="topbar-theme-btn" title="เปลี่ยนธีม">
          <i class="fa-solid fa-palette"></i>
        </button>
        <div class="topbar-avatar" id="topbar-avatar" title="โปรไฟล์"></div>
      </div>
    </header>

    <!-- Theme Picker Popover -->
    <div id="theme-popover" class="theme-popover" style="display:none"></div>

    <!-- Page Content -->
    <div id="page-content" class="page-content"></div>

  </main>
</div>

<!-- Universal Modal -->
<div id="modal-overlay" class="modal-overlay" onclick="UI.modal.hide(event)">
  <div class="modal" id="main-modal">
    <div class="modal-header">
      <h3 class="modal-title" id="modal-title"></h3>
      <button class="modal-close-btn" onclick="UI.modal.hide()">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <div class="modal-body" id="modal-body"></div>
    <div class="modal-footer" id="modal-footer"></div>
  </div>
</div>

<!-- Image Lightbox -->
<div id="lightbox" class="lightbox" onclick="this.style.display='none'">
  <button class="lightbox-close" onclick="document.getElementById('lightbox').style.display='none'">
    <i class="fa-solid fa-xmark"></i>
  </button>
  <img id="lightbox-img" src="" alt="">
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
