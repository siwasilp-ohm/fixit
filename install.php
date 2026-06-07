<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>FixIt - ติดตั้งระบบ</title>
<style>
body { font-family: 'Segoe UI', sans-serif; max-width: 700px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
.card { background: #fff; border-radius: 12px; padding: 30px; box-shadow: 0 2px 12px rgba(0,0,0,.1); }
h1 { color: #2196F3; margin: 0 0 20px; }
.step { padding: 10px 14px; margin: 6px 0; border-radius: 8px; font-size: 14px; }
.ok   { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #4CAF50; }
.err  { background: #ffebee; color: #c62828; border-left: 4px solid #f44336; }
.info { background: #e3f2fd; color: #1565c0; border-left: 4px solid #2196F3; }
.warn { background: #fff8e1; color: #f57f17; border-left: 4px solid #FFC107; }
.btn  { display: inline-block; margin-top: 20px; padding: 12px 28px; background: #2196F3; color: #fff;
        text-decoration: none; border-radius: 8px; font-size: 15px; font-weight: 600; }
pre   { background: #f5f5f5; padding: 12px; border-radius: 6px; font-size: 13px; overflow-x: auto; }
</style>
</head>
<body>
<div class="card">
<h1><span style="font-size:1.3em">🔧</span> FixIt — ติดตั้งระบบ</h1>
<?php

// ─── DB Credentials ────────────────────────────────────────────────────────
$host    = 'localhost';
$dbname  = 'fixit_db';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

function step(string $label, bool $ok, string $detail = ''): void {
    $cls  = $ok ? 'ok'  : 'err';
    $icon = $ok ? '✅' : '❌';
    echo "<div class='step {$cls}'>{$icon} {$label}" . ($detail ? " — <em>{$detail}</em>" : '') . "</div>\n";
}

// ─── Connect ───────────────────────────────────────────────────────────────
try {
    $pdo = new PDO("mysql:host={$host};charset={$charset}", $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    step('เชื่อมต่อ MySQL', true, $host);
} catch (Exception $e) {
    step('เชื่อมต่อ MySQL', false, $e->getMessage());
    echo "<p class='step warn'>กรุณาตั้งค่า credential ใน install.php และ config/config.php ให้ตรงกัน</p>";
    exit;
}

// ─── Create DB ─────────────────────────────────────────────────────────────
$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `{$dbname}`");
step('สร้างฐานข้อมูล', true, $dbname);

// ─── Tables ────────────────────────────────────────────────────────────────
$tables = [];

$tables['users'] = "CREATE TABLE IF NOT EXISTS `users` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `username`    VARCHAR(50) UNIQUE NOT NULL,
    `password`    VARCHAR(255) NOT NULL,
    `full_name`   VARCHAR(100) NOT NULL,
    `role`        ENUM('admin','reporter','officer','technician','director') NOT NULL DEFAULT 'reporter',
    `department`  VARCHAR(100),
    `email`       VARCHAR(100),
    `phone`       VARCHAR(20),
    `theme_color` VARCHAR(20) DEFAULT '#2196F3',
    `active`      TINYINT(1) DEFAULT 1,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tables['categories'] = "CREATE TABLE IF NOT EXISTS `categories` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `name`       VARCHAR(100) NOT NULL,
    `color`      VARCHAR(20) DEFAULT '#2196F3',
    `icon`       VARCHAR(60) DEFAULT 'fa-wrench',
    `active`     TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tables['asset_locations'] = "CREATE TABLE IF NOT EXISTS `asset_locations` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `building`    VARCHAR(100),
    `floor`       VARCHAR(50),
    `room`        VARCHAR(100),
    `description` TEXT,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tables['assets'] = "CREATE TABLE IF NOT EXISTS `assets` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `asset_code`      VARCHAR(50) UNIQUE NOT NULL,
    `name`            VARCHAR(200) NOT NULL,
    `description`     TEXT,
    `category_id`     INT,
    `location_id`     INT,
    `brand`           VARCHAR(100),
    `model`           VARCHAR(100),
    `serial_number`   VARCHAR(100),
    `purchase_date`   DATE,
    `warranty_expire` DATE,
    `status`          ENUM('active','maintenance','retired') DEFAULT 'active',
    `created_by`      INT,
    `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`location_id`) REFERENCES `asset_locations`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tables['repairs'] = "CREATE TABLE IF NOT EXISTS `repairs` (
    `id`               INT AUTO_INCREMENT PRIMARY KEY,
    `repair_number`    VARCHAR(20) UNIQUE NOT NULL,
    `reporter_id`      INT NOT NULL,
    `category_id`      INT,
    `asset_id`         INT,
    `subject`          VARCHAR(255) NOT NULL,
    `description`      TEXT,
    `location`         VARCHAR(255),
    `building`         VARCHAR(100),
    `room`             VARCHAR(100),
    `status`           ENUM('pending','estimating','waiting_approval','approved','in_progress','completed','cancelled') DEFAULT 'pending',
    `priority`         ENUM('low','normal','high','urgent') DEFAULT 'normal',
    `estimated_cost`   DECIMAL(10,2),
    `actual_cost`      DECIMAL(10,2),
    `assigned_to`      INT,
    `approved_by`      INT,
    `officer_id`       INT,
    `notes`            TEXT,
    `completed_at`     TIMESTAMP NULL,
    `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`reporter_id`)  REFERENCES `users`(`id`),
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`asset_id`)    REFERENCES `assets`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`officer_id`)  REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tables['repair_images'] = "CREATE TABLE IF NOT EXISTS `repair_images` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `repair_id`     INT NOT NULL,
    `filename`      VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(255),
    `type`          ENUM('before','after','other') DEFAULT 'before',
    `uploaded_by`   INT,
    `uploaded_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`repair_id`)   REFERENCES `repairs`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tables['repair_history'] = "CREATE TABLE IF NOT EXISTS `repair_history` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `repair_id`  INT NOT NULL,
    `user_id`    INT,
    `action`     VARCHAR(200) NOT NULL,
    `old_status` VARCHAR(50),
    `new_status` VARCHAR(50),
    `comment`    TEXT,
    `cost`       DECIMAL(10,2),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`repair_id`) REFERENCES `repairs`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tables['activity_logs'] = "CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT,
    `username`    VARCHAR(50),
    `role`        VARCHAR(20),
    `action`      VARCHAR(200) NOT NULL,
    `module`      VARCHAR(50)  DEFAULT '',
    `target_id`   INT,
    `target_name` VARCHAR(200),
    `ip_address`  VARCHAR(45),
    `status`      ENUM('success','warning','error','info') DEFAULT 'success',
    `details`     TEXT,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_module`  (`module`),
    INDEX `idx_user`    (`user_id`),
    INDEX `idx_status`  (`status`),
    INDEX `idx_created` (`created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tables['backups'] = "CREATE TABLE IF NOT EXISTS `backups` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `filename`     VARCHAR(255) NOT NULL,
    `filesize`     BIGINT DEFAULT 0,
    `tables_count` INT DEFAULT 0,
    `rows_count`   INT DEFAULT 0,
    `type`         ENUM('manual','scheduled') DEFAULT 'manual',
    `note`         TEXT,
    `created_by`   INT,
    `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

foreach ($tables as $name => $sql) {
    try {
        $pdo->exec($sql);
        step("สร้างตาราง `{$name}`", true);
    } catch (Exception $e) {
        step("สร้างตาราง `{$name}`", false, $e->getMessage());
    }
}

// ─── Seed Data ─────────────────────────────────────────────────────────────
echo "<div class='step info'>📦 ใส่ข้อมูลเริ่มต้น (seed data)</div>";

// Default admin
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE username='admin'");
if ((int)$stmt->fetchColumn() === 0) {
    $hash = password_hash('admin1234', PASSWORD_BCRYPT);
    $pdo->prepare("INSERT INTO users (username,password,full_name,role,department,theme_color) VALUES (?,?,?,?,?,?)")
        ->execute(['admin', $hash, 'ผู้ดูแลระบบ', 'admin', 'IT', '#2196F3']);

    $pdo->prepare("INSERT INTO users (username,password,full_name,role,department,theme_color) VALUES (?,?,?,?,?,?)")
        ->execute(['officer1', password_hash('officer1234', PASSWORD_BCRYPT), 'นายสมศักดิ์ ดีใจ', 'officer', 'ฝ่ายซ่อมบำรุง', '#009688']);

    $pdo->prepare("INSERT INTO users (username,password,full_name,role,department,theme_color) VALUES (?,?,?,?,?,?)")
        ->execute(['user1', password_hash('user1234', PASSWORD_BCRYPT), 'นางสาวสมใจ รักดี', 'reporter', 'ฝ่ายบริหาร', '#9C27B0']);

    $pdo->prepare("INSERT INTO users (username,password,full_name,role,department,theme_color) VALUES (?,?,?,?,?,?)")
        ->execute(['tech1', password_hash('tech1234', PASSWORD_BCRYPT), 'นายช่างสมหมาย ขยัน', 'technician', 'ฝ่ายซ่อมบำรุง', '#FF9800']);

    $pdo->prepare("INSERT INTO users (username,password,full_name,role,department,theme_color) VALUES (?,?,?,?,?,?)")
        ->execute(['director', password_hash('director1234', PASSWORD_BCRYPT), 'นายผู้อำนวยการ ใหญ่มาก', 'director', 'ผู้บริหาร', '#F44336']);

    step('สร้างผู้ใช้งานเริ่มต้น', true, 'admin / officer1 / user1 / tech1 / director');
} else {
    step('ผู้ใช้งานเริ่มต้น', true, 'มีอยู่แล้ว');
}

// Default categories
$stmt = $pdo->query("SELECT COUNT(*) FROM categories");
if ((int)$stmt->fetchColumn() === 0) {
    $cats = [
        ['ระบบไฟฟ้า',     '#F44336', 'fa-bolt'],
        ['ระบบประปา',      '#2196F3', 'fa-droplet'],
        ['คอมพิวเตอร์/IT',  '#9C27B0', 'fa-computer'],
        ['เครื่องปรับอากาศ','#00BCD4', 'fa-snowflake'],
        ['อาคาร/สิ่งก่อสร้าง','#795548','fa-building'],
        ['ยานพาหนะ',       '#FF9800', 'fa-car'],
        ['เครื่องจักร',     '#607D8B', 'fa-gears'],
        ['อื่นๆ',           '#9E9E9E', 'fa-circle-question'],
    ];
    $ins = $pdo->prepare("INSERT INTO categories (name,color,icon) VALUES (?,?,?)");
    foreach ($cats as $c) { $ins->execute($c); }
    step('สร้างหมวดหมู่เริ่มต้น', true, count($cats) . ' หมวดหมู่');
}

// Default locations
$stmt = $pdo->query("SELECT COUNT(*) FROM asset_locations");
if ((int)$stmt->fetchColumn() === 0) {
    $locs = [
        ['อาคาร A', '1', 'A101', 'ห้องประชุม'],
        ['อาคาร A', '2', 'A201', 'สำนักงาน'],
        ['อาคาร B', '1', 'B101', 'ห้องคอมพิวเตอร์'],
        ['อาคาร B', '2', 'B201', 'ห้องเซิร์ฟเวอร์'],
    ];
    $ins = $pdo->prepare("INSERT INTO asset_locations (building,floor,room,description) VALUES (?,?,?,?)");
    foreach ($locs as $l) { $ins->execute($l); }
    step('สร้างสถานที่เริ่มต้น', true);
}

// ─── Uploads dirs ──────────────────────────────────────────────────────────
$dirs = [__DIR__.'/uploads', __DIR__.'/uploads/repairs', __DIR__.'/uploads/chunks', __DIR__.'/backups'];
foreach ($dirs as $d) {
    if (!is_dir($d)) @mkdir($d, 0755, true);
    step("โฟลเดอร์ {$d}", is_writable($d), is_writable($d) ? 'เขียนได้' : 'ไม่มีสิทธิ์เขียน');
}

?>
<br>
<div class="step ok" style="font-size:16px;font-weight:600">🎉 ติดตั้งระบบเสร็จสมบูรณ์!</div>
<br>
<div class="step info"><strong>บัญชีผู้ใช้เริ่มต้น:</strong><br>
<pre>admin     / admin1234    (ผู้ดูแลระบบ)
officer1  / officer1234  (เจ้าหน้าที่)
user1     / user1234     (ผู้แจ้งซ่อม)
tech1     / tech1234     (ช่างซ่อม)
director  / director1234 (ผู้อำนวยการ)</pre>
</div>
<a href="index.php" class="btn">🚀 เข้าสู่ระบบ</a>
<p style="margin-top:20px;color:#999;font-size:13px">⚠️ กรุณาลบหรือปิดใช้งานไฟล์ install.php หลังติดตั้งเสร็จแล้ว</p>
</div>
</body>
</html>
