<?php
require_once __DIR__ . '/../includes/helpers.php';
require_auth();

$pdo    = get_pdo();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = $_GET['action'] ?? '';
$uid    = (int)$_SESSION['user_id'];
$role   = $_SESSION['user']['role'];
$isManager = in_array($role, ['admin', 'officer', 'director', 'technician']);

// ── GET list / single ────────────────────────────────────────────────────
if ($method === 'GET' && !$action) {
    if ($id) {
        $stmt = $pdo->prepare(
            "SELECT r.*, u.full_name as reporter_name, u.department as reporter_dept,
                    c.name as category_name, c.color as category_color, c.icon as category_icon,
                    t.full_name as technician_name, a.full_name as approver_name,
                    al.name as asset_name, al.asset_code
             FROM repairs r
             LEFT JOIN users u  ON r.reporter_id = u.id
             LEFT JOIN categories c ON r.category_id = c.id
             LEFT JOIN users t  ON r.assigned_to = t.id
             LEFT JOIN users a  ON r.approved_by = a.id
             LEFT JOIN assets al ON r.asset_id = al.id
             WHERE r.id = ?"
        );
        $stmt->execute([$id]);
        $repair = $stmt->fetch();
        if (!$repair) json_response(['success'=>false,'message'=>'ไม่พบรายการ'], 404);

        // Access control for reporters
        if ($role === 'reporter' && (int)$repair['reporter_id'] !== $uid) {
            json_response(['success'=>false,'message'=>'Forbidden'], 403);
        }

        // Images
        $imgStmt = $pdo->prepare("SELECT * FROM repair_images WHERE repair_id=? ORDER BY type, uploaded_at");
        $imgStmt->execute([$id]);
        $repair['images'] = $imgStmt->fetchAll();

        // History / Timeline
        $histStmt = $pdo->prepare(
            "SELECT h.*, u.full_name as user_name, u.role as user_role
             FROM repair_history h LEFT JOIN users u ON h.user_id = u.id
             WHERE h.repair_id=? ORDER BY h.created_at ASC"
        );
        $histStmt->execute([$id]);
        $repair['history'] = $histStmt->fetchAll();

        json_response(['success'=>true,'data'=>$repair]);
    }

    // List
    $where  = [];
    $params = [];

    if ($role === 'reporter') {
        $where[] = 'r.reporter_id = ?'; $params[] = $uid;
    } elseif ($role === 'technician') {
        $where[] = '(r.assigned_to = ? OR r.reporter_id = ?)'; $params[] = $uid; $params[] = $uid;
    }

    if ($_GET['status'] ?? '') { $where[] = 'r.status = ?'; $params[] = $_GET['status']; }
    if ($_GET['category_id'] ?? '') { $where[] = 'r.category_id = ?'; $params[] = (int)$_GET['category_id']; }
    if ($_GET['search'] ?? '') {
        $s = '%' . $_GET['search'] . '%';
        $where[] = '(r.repair_number LIKE ? OR r.subject LIKE ?)';
        $params[] = $s; $params[] = $s;
    }
    if ($_GET['date_from'] ?? '') { $where[] = 'DATE(r.created_at) >= ?'; $params[] = $_GET['date_from']; }
    if ($_GET['date_to']   ?? '') { $where[] = 'DATE(r.created_at) <= ?'; $params[] = $_GET['date_to']; }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = ($page - 1) * $limit;

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM repairs r {$whereSql}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $sql = "SELECT r.*, u.full_name as reporter_name, c.name as category_name, c.color as category_color, c.icon as category_icon,
                   t.full_name as technician_name
            FROM repairs r
            LEFT JOIN users u ON r.reporter_id = u.id
            LEFT JOIN categories c ON r.category_id = c.id
            LEFT JOIN users t ON r.assigned_to = t.id
            {$whereSql} ORDER BY r.created_at DESC LIMIT {$limit} OFFSET {$offset}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    json_response(['success'=>true,'data'=>$stmt->fetchAll(),'total'=>$total,'page'=>$page,'limit'=>$limit]);
}

// ── POST create ─────────────────────────────────────────────────────────
if ($method === 'POST' && !$action) {
    $data = get_input();
    if (empty($data['subject'])) json_response(['success'=>false,'message'=>'กรุณากรอกหัวข้อ/อาการ'], 400);

    $repair_number = generate_repair_number($pdo);

    $pdo->prepare(
        "INSERT INTO repairs (repair_number,reporter_id,category_id,asset_id,subject,description,location,building,room,priority)
         VALUES (?,?,?,?,?,?,?,?,?,?)"
    )->execute([
        $repair_number, $uid,
        $data['category_id'] ?: null,
        $data['asset_id'] ?: null,
        clean($data['subject']),
        clean($data['description'] ?? ''),
        clean($data['location'] ?? ''),
        clean($data['building'] ?? ''),
        clean($data['room'] ?? ''),
        $data['priority'] ?? 'normal',
    ]);
    $newId = (int)$pdo->lastInsertId();
    log_repair_history($pdo, $newId, null, 'pending', 'แจ้งซ่อมใหม่', 'รับเรื่องแจ้งซ่อม: ' . clean($data['subject']));

    json_response(['success'=>true,'message'=>'แจ้งซ่อมสำเร็จ','id'=>$newId,'repair_number'=>$repair_number]);
}

// ── PUT update ───────────────────────────────────────────────────────────
if ($method === 'PUT' && !$action) {
    if (!$id) json_response(['success'=>false,'message'=>'ระบุ ID ด้วย'], 400);
    if (!$isManager) json_response(['success'=>false,'message'=>'ไม่มีสิทธิ์'], 403);
    $data = get_input();
    $pdo->prepare(
        "UPDATE repairs SET category_id=?,subject=?,description=?,location=?,building=?,room=?,priority=?,notes=?,assigned_to=?,officer_id=? WHERE id=?"
    )->execute([
        $data['category_id'] ?: null, clean($data['subject']), clean($data['description']??''),
        clean($data['location']??''), clean($data['building']??''), clean($data['room']??''),
        $data['priority']??'normal', clean($data['notes']??''),
        $data['assigned_to'] ?: null, $data['officer_id'] ?: null, $id
    ]);
    json_response(['success'=>true,'message'=>'แก้ไขข้อมูลสำเร็จ']);
}

// ── PUT status change ────────────────────────────────────────────────────
if ($method === 'PUT' && $action === 'status') {
    if (!$id) json_response(['success'=>false,'message'=>'ระบุ ID ด้วย'], 400);
    $data = get_input();
    $newStatus = $data['status'] ?? '';
    $comment   = clean($data['comment'] ?? '');
    $cost      = isset($data['cost']) && $data['cost'] !== '' ? (float)$data['cost'] : null;

    $allowed = ['pending','estimating','waiting_approval','approved','in_progress','completed','cancelled'];
    if (!in_array($newStatus, $allowed)) json_response(['success'=>false,'message'=>'สถานะไม่ถูกต้อง'], 400);

    // Get current repair
    $stmt = $pdo->prepare("SELECT * FROM repairs WHERE id=?");
    $stmt->execute([$id]);
    $repair = $stmt->fetch();
    if (!$repair) json_response(['success'=>false,'message'=>'ไม่พบรายการ'], 404);

    // Reporter can only cancel their own
    if ($role === 'reporter') {
        if ((int)$repair['reporter_id'] !== $uid) json_response(['success'=>false,'message'=>'Forbidden'], 403);
        if ($newStatus !== 'cancelled') json_response(['success'=>false,'message'=>'ผู้แจ้งซ่อมยกเลิกได้เท่านั้น'], 403);
        if (!in_array($repair['status'], ['pending'])) json_response(['success'=>false,'message'=>'ไม่สามารถยกเลิกได้ในสถานะนี้'], 400);
    }

    $updates = ['status=?'];
    $params  = [$newStatus];

    if ($cost !== null) { $updates[] = 'estimated_cost=?'; $params[] = $cost; }
    if ($newStatus === 'completed') {
        $updates[] = 'completed_at=NOW()';
        if ($cost !== null) { $updates[] = 'actual_cost=?'; $params[] = $cost; }
    }
    if ($newStatus === 'approved' || $newStatus === 'in_progress') {
        $updates[] = 'approved_by=?'; $params[] = $uid;
        if (!empty($data['assigned_to'])) { $updates[] = 'assigned_to=?'; $params[] = (int)$data['assigned_to']; }
    }

    $params[] = $id;
    $pdo->prepare("UPDATE repairs SET " . implode(',', $updates) . " WHERE id=?")->execute($params);

    $statusLabels = [
        'pending'=>'รอประเมิน','estimating'=>'กำลังประเมินราคา','waiting_approval'=>'รออนุมัติ',
        'approved'=>'อนุมัติแล้ว','in_progress'=>'กำลังซ่อม','completed'=>'เสร็จสิ้น','cancelled'=>'ยกเลิก'
    ];
    $actionLabel = 'เปลี่ยนสถานะ: ' . ($statusLabels[$newStatus] ?? $newStatus);
    log_repair_history($pdo, $id, $repair['status'], $newStatus, $actionLabel, $comment, $cost);

    json_response(['success'=>true,'message'=>'เปลี่ยนสถานะสำเร็จ']);
}

// ── DELETE ───────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    if (!$isManager) json_response(['success'=>false,'message'=>'ไม่มีสิทธิ์'], 403);
    if (!$id) json_response(['success'=>false,'message'=>'ระบุ ID ด้วย'], 400);
    $pdo->prepare("DELETE FROM repairs WHERE id=?")->execute([$id]);
    json_response(['success'=>true,'message'=>'ลบรายการสำเร็จ']);
}

json_response(['success'=>false,'message'=>'Bad request'], 400);
