<?php
require_once __DIR__ . '/../includes/helpers.php';
require_auth();

$pdo  = get_pdo();
$uid  = $_SESSION['user_id'];
$role = $_SESSION['user']['role'];
$isManager = in_array($role, ['admin', 'officer', 'director']);

// ── Status counts ────────────────────────────────────────────────────────
$where = $isManager ? '1=1' : 'reporter_id = ' . (int)$uid;

$statusRows = $pdo->query(
    "SELECT status, COUNT(*) as cnt FROM repairs WHERE {$where} GROUP BY status"
)->fetchAll();

$counts = [
    'total'            => 0,
    'pending'          => 0,
    'estimating'       => 0,
    'waiting_approval' => 0,
    'approved'         => 0,
    'in_progress'      => 0,
    'completed'        => 0,
    'cancelled'        => 0,
];

foreach ($statusRows as $row) {
    $counts[$row['status']] = (int)$row['cnt'];
    $counts['total'] += (int)$row['cnt'];
}

// ── Category breakdown (doughnut) ───────────────────────────────────────
$catRows = $pdo->query(
    "SELECT c.name, c.color, COUNT(r.id) as cnt
     FROM repairs r
     LEFT JOIN categories c ON r.category_id = c.id
     WHERE {$where}
     GROUP BY r.category_id, c.name, c.color
     ORDER BY cnt DESC"
)->fetchAll();

// ── Monthly trend (bar, last 6 months) ──────────────────────────────────
$monthly = [];
for ($i = 5; $i >= 0; $i--) {
    $ts    = strtotime("-{$i} months");
    $ym    = date('Y-m', $ts);
    $label = date('M y', $ts);
    $stmt  = $pdo->prepare(
        "SELECT COUNT(*) FROM repairs WHERE DATE_FORMAT(created_at,'%Y-%m') = ? AND {$where}"
    );
    $stmt->execute([$ym]);
    $monthly[] = ['label' => $label, 'count' => (int)$stmt->fetchColumn()];
}

// ── Recent repairs (last 5) ──────────────────────────────────────────────
$recentSql = "SELECT r.*, u.full_name as reporter_name, c.name as category_name, c.color as category_color
              FROM repairs r
              LEFT JOIN users u ON r.reporter_id = u.id
              LEFT JOIN categories c ON r.category_id = c.id
              WHERE {$where}
              ORDER BY r.created_at DESC LIMIT 5";
$recent = $pdo->query($recentSql)->fetchAll();

json_response([
    'success' => true,
    'counts'  => $counts,
    'category_chart' => $catRows,
    'monthly_chart'  => $monthly,
    'recent_repairs' => $recent,
]);
