<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$code = trim($_GET['code'] ?? '');
$asset = null;

if ($code) {
    $pdo = get_pdo();
    $stmt = $pdo->prepare(
        "SELECT a.*, c.name as category_name, c.color as category_color,
                l.building, l.floor, l.room
         FROM assets a
         LEFT JOIN categories c ON a.category_id = c.id
         LEFT JOIN asset_locations l ON a.location_id = l.id
         WHERE a.asset_code = ? AND a.status != 'retired'"
    );
    $stmt->execute([$code]);
    $asset = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FixIt — สแกน QR <?= htmlspecialchars($code) ?></title>
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#2196F3">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', system-ui, sans-serif; background: #F0F4F8; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
  .card { background: #fff; border-radius: 20px; padding: 32px; max-width: 440px; width: 100%; box-shadow: 0 8px 32px rgba(0,0,0,.12); }
  .brand { display: flex; align-items: center; gap: 10px; margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid #eee; }
  .brand-icon { width: 42px; height: 42px; border-radius: 10px; background: linear-gradient(135deg, #2196F3, #00BCD4); display: flex; align-items: center; justify-content: center; font-size: 18px; color: #fff; }
  .brand-name { font-size: 18px; font-weight: 800; color: #1a1a2e; }
  .asset-icon { width: 72px; height: 72px; border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 32px; margin: 0 auto 16px; }
  h2 { font-size: 20px; font-weight: 700; text-align: center; color: #1a1a2e; margin-bottom: 6px; }
  .asset-code { text-align: center; font-family: monospace; color: #666; font-size: 13px; margin-bottom: 20px; }
  .info-row { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
  .info-row:last-child { border-bottom: none; }
  .info-row i { width: 20px; color: #2196F3; text-align: center; }
  .info-label { color: #999; font-size: 12px; min-width: 80px; }
  .btn { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 14px; border-radius: 12px; font-size: 15px; font-weight: 700; border: none; cursor: pointer; margin-top: 10px; text-decoration: none; transition: .2s; }
  .btn-primary { background: linear-gradient(135deg, #2196F3, #00BCD4); color: #fff; box-shadow: 0 4px 16px rgba(33,150,243,.35); }
  .btn-primary:hover { transform: translateY(-2px); }
  .btn-ghost { background: #f5f5f5; color: #444; }
  .btn-ghost:hover { background: #e8e8e8; }
  .not-found { text-align: center; padding: 20px 0; }
  .not-found i { font-size: 48px; color: #f44336; margin-bottom: 14px; }
  .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
  .status-active { background: #E8F5E9; color: #1B5E20; }
  .status-maintenance { background: #FFF3E0; color: #E65100; }
</style>
</head>
<body>
<div class="card">
  <div class="brand">
    <div class="brand-icon"><i class="fa-solid fa-screwdriver-wrench"></i></div>
    <div><div class="brand-name">FixIt</div><div style="font-size:12px;color:#999">ระบบแจ้งซ่อมออนไลน์</div></div>
  </div>

  <?php if ($asset): ?>
    <div class="asset-icon" style="background:<?= htmlspecialchars($asset['category_color']??'#E3F2FD') ?>22;color:<?= htmlspecialchars($asset['category_color']??'#2196F3') ?>">
      <i class="fa-solid fa-cube"></i>
    </div>
    <h2><?= htmlspecialchars($asset['name']) ?></h2>
    <div class="asset-code"><?= htmlspecialchars($asset['asset_code']) ?></div>

    <div>
      <?php if ($asset['category_name']): ?><div class="info-row"><i class="fa-solid fa-tag"></i><span class="info-label">หมวดหมู่</span><span><?= htmlspecialchars($asset['category_name']) ?></span></div><?php endif; ?>
      <?php if ($asset['building']): ?><div class="info-row"><i class="fa-solid fa-location-dot"></i><span class="info-label">ตำแหน่ง</span><span><?= htmlspecialchars($asset['building'] . ' ' . ($asset['floor']??'') . ' ' . ($asset['room']??'')) ?></span></div><?php endif; ?>
      <?php if ($asset['brand'] || $asset['model']): ?><div class="info-row"><i class="fa-solid fa-info-circle"></i><span class="info-label">รุ่น</span><span><?= htmlspecialchars(trim($asset['brand'].' '.$asset['model'])) ?></span></div><?php endif; ?>
      <div class="info-row"><i class="fa-solid fa-circle-dot"></i><span class="info-label">สถานะ</span>
        <span class="status-badge <?= $asset['status']==='active'?'status-active':'status-maintenance' ?>">
          <?= $asset['status']==='active'?'พร้อมใช้งาน':'อยู่ระหว่างซ่อม' ?>
        </span>
      </div>
    </div>

    <?php if (!empty($_SESSION['user_id'])): ?>
      <a href="app.php#new-repair" class="btn btn-primary" style="margin-top:24px">
        <i class="fa-solid fa-plus"></i> แจ้งซ่อมอุปกรณ์นี้
      </a>
      <a href="app.php#asset-detail/<?= $asset['id'] ?>" class="btn btn-ghost">
        <i class="fa-solid fa-clock-rotate-left"></i> ดูประวัติการซ่อม
      </a>
    <?php else: ?>
      <a href="index.php?redirect=scan&code=<?= urlencode($code) ?>" class="btn btn-primary" style="margin-top:24px">
        <i class="fa-solid fa-right-to-bracket"></i> เข้าสู่ระบบเพื่อแจ้งซ่อม
      </a>
    <?php endif; ?>

  <?php elseif ($code): ?>
    <div class="not-found">
      <i class="fa-solid fa-circle-exclamation"></i>
      <h2 style="margin:0 0 8px">ไม่พบอุปกรณ์</h2>
      <p style="color:#666;font-size:14px">รหัส <code><?= htmlspecialchars($code) ?></code><br>ไม่พบในระบบ หรืออุปกรณ์ถูกปลดระวางแล้ว</p>
    </div>
    <a href="index.php" class="btn btn-ghost" style="margin-top:24px"><i class="fa-solid fa-house"></i> กลับหน้าหลัก</a>

  <?php else: ?>
    <div class="not-found">
      <i class="fa-solid fa-qrcode"></i>
      <h2 style="margin:0 0 8px">สแกน QR Code</h2>
      <p style="color:#666;font-size:14px">กรุณาสแกน QR Code จากอุปกรณ์<br>เพื่อเข้าสู่หน้าแจ้งซ่อม</p>
    </div>
    <a href="index.php" class="btn btn-primary" style="margin-top:24px"><i class="fa-solid fa-house"></i> ไปหน้าหลัก</a>
  <?php endif; ?>
</div>
</body>
</html>
