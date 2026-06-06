<?php
require_once __DIR__ . '/../includes/helpers.php';
require_role('admin', 'officer', 'director');

$pdo    = get_pdo();
$type   = $_GET['type'] ?? 'summary';
$from   = $_GET['date_from'] ?? date('Y-m-01');
$to     = $_GET['date_to']   ?? date('Y-m-t');

$baseWhere = "WHERE DATE(r.created_at) BETWEEN ? AND ?";
$baseParams = [$from, $to];

switch ($type) {
    // ── Summary ────────────────────────────────────────────────────────────
    case 'summary':
        $statusRows = $pdo->prepare(
            "SELECT r.status, COUNT(*) as cnt, SUM(r.actual_cost) as total_cost
             FROM repairs r {$baseWhere} GROUP BY r.status"
        );
        $statusRows->execute($baseParams);

        $catRows = $pdo->prepare(
            "SELECT c.name, c.color, COUNT(r.id) as cnt
             FROM repairs r
             LEFT JOIN categories c ON r.category_id = c.id
             {$baseWhere}
             GROUP BY r.category_id ORDER BY cnt DESC"
        );
        $catRows->execute($baseParams);

        $techRows = $pdo->prepare(
            "SELECT u.full_name, COUNT(r.id) as cnt, SUM(r.actual_cost) as total_cost,
                    SUM(CASE WHEN r.status='completed' THEN 1 ELSE 0 END) as completed
             FROM repairs r
             LEFT JOIN users u ON r.assigned_to = u.id
             {$baseWhere} AND r.assigned_to IS NOT NULL
             GROUP BY r.assigned_to ORDER BY cnt DESC"
        );
        $techRows->execute($baseParams);

        json_response([
            'success' => true,
            'period'  => ['from' => $from, 'to' => $to],
            'by_status'     => $statusRows->fetchAll(),
            'by_category'   => $catRows->fetchAll(),
            'by_technician' => $techRows->fetchAll(),
        ]);
        break;

    // ── Repairs list (for print) ───────────────────────────────────────────
    case 'repairs':
        $where  = [$baseWhere];
        $params = $baseParams;
        if ($_GET['status']      ?? '') { $where[] = 'AND r.status = ?';      $params[] = $_GET['status']; }
        if ($_GET['category_id'] ?? '') { $where[] = 'AND r.category_id = ?'; $params[] = (int)$_GET['category_id']; }

        $sql = "SELECT r.*, u.full_name as reporter_name, u.department,
                       c.name as category_name, t.full_name as technician_name
                FROM repairs r
                LEFT JOIN users u ON r.reporter_id = u.id
                LEFT JOIN categories c ON r.category_id = c.id
                LEFT JOIN users t ON r.assigned_to = t.id
                " . implode(' ', $where) . "
                ORDER BY r.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        json_response(['success'=>true,'data'=>$stmt->fetchAll(),'period'=>['from'=>$from,'to'=>$to]]);
        break;

    // ── Asset inventory ───────────────────────────────────────────────────
    case 'assets':
        $stmt = $pdo->query(
            "SELECT a.*, c.name as category_name, l.building, l.floor, l.room,
                    (SELECT COUNT(*) FROM repairs WHERE asset_id = a.id) as repair_count,
                    (SELECT COUNT(*) FROM repairs WHERE asset_id = a.id AND status != 'completed') as open_repairs
             FROM assets a
             LEFT JOIN categories c ON a.category_id = c.id
             LEFT JOIN asset_locations l ON a.location_id = l.id
             ORDER BY a.name ASC"
        );
        json_response(['success'=>true,'data'=>$stmt->fetchAll()]);
        break;

    // ── Monthly trend ─────────────────────────────────────────────────────
    case 'monthly':
        $months = (int)($_GET['months'] ?? 12);
        $data   = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $ts  = strtotime("-{$i} months");
            $ym  = date('Y-m', $ts);
            $lbl = date('M Y', $ts);
            $stmt = $pdo->prepare("SELECT COUNT(*) as cnt, SUM(actual_cost) as cost FROM repairs WHERE DATE_FORMAT(created_at,'%Y-%m') = ?");
            $stmt->execute([$ym]);
            $row = $stmt->fetch();
            $data[] = ['month' => $lbl, 'count' => (int)$row['cnt'], 'cost' => (float)$row['cost']];
        }
        json_response(['success'=>true,'data'=>$data]);
        break;

    // ── Cost summary ──────────────────────────────────────────────────────
    case 'cost':
        $stmt = $pdo->prepare(
            "SELECT SUM(estimated_cost) as total_estimated, SUM(actual_cost) as total_actual,
                    AVG(actual_cost) as avg_cost, COUNT(*) as total_completed
             FROM repairs {$baseWhere} AND status='completed'"
        );
        $stmt->execute($baseParams);
        json_response(['success'=>true,'data'=>$stmt->fetch(),'period'=>['from'=>$from,'to'=>$to]]);
        break;

    default:
        json_response(['success'=>false,'message'=>'Unknown report type'], 400);
}
