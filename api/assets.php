<?php
require_once __DIR__ . '/../includes/helpers.php';
require_auth();

$pdo    = get_pdo();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = $_GET['action'] ?? '';
$role   = $_SESSION['user']['role'];
$canEdit = in_array($role, ['admin', 'officer']);

// ── GET locations ─────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'locations') {
    $rows = $pdo->query("SELECT * FROM asset_locations ORDER BY building,floor,room")->fetchAll();
    json_response(['success'=>true,'data'=>$rows]);
}

// ── GET list / single ────────────────────────────────────────────────────
if ($method === 'GET' && !$action) {
    if ($id) {
        $stmt = $pdo->prepare(
            "SELECT a.*, c.name as category_name, c.color as category_color,
                    l.building, l.floor, l.room, l.description as location_desc,
                    u.full_name as created_by_name
             FROM assets a
             LEFT JOIN categories c ON a.category_id = c.id
             LEFT JOIN asset_locations l ON a.location_id = l.id
             LEFT JOIN users u ON a.created_by = u.id
             WHERE a.id = ?"
        );
        $stmt->execute([$id]);
        $asset = $stmt->fetch();
        if (!$asset) json_response(['success'=>false,'message'=>'ไม่พบรายการ'], 404);

        // Recent repairs
        $repStmt = $pdo->prepare(
            "SELECT r.*, u.full_name as reporter_name FROM repairs r
             LEFT JOIN users u ON r.reporter_id = u.id
             WHERE r.asset_id = ? ORDER BY r.created_at DESC LIMIT 10"
        );
        $repStmt->execute([$id]);
        $asset['repairs'] = $repStmt->fetchAll();

        json_response(['success'=>true,'data'=>$asset]);
    }

    $where  = [];
    $params = [];
    if ($_GET['search']   ?? '') { $where[] = '(a.asset_code LIKE ? OR a.name LIKE ?)'; $s = '%'.$_GET['search'].'%'; $params[]=$s;$params[]=$s; }
    if ($_GET['category'] ?? '') { $where[] = 'a.category_id = ?'; $params[] = (int)$_GET['category']; }
    if ($_GET['status']   ?? '') { $where[] = 'a.status = ?'; $params[] = $_GET['status']; }
    if ($_GET['building'] ?? '') { $where[] = 'l.building = ?'; $params[] = $_GET['building']; }

    $whereSql = $where ? 'WHERE '.implode(' AND ',$where) : '';
    $stmt = $pdo->prepare(
        "SELECT a.*, c.name as category_name, c.color as category_color,
                l.building, l.floor, l.room
         FROM assets a
         LEFT JOIN categories c ON a.category_id = c.id
         LEFT JOIN asset_locations l ON a.location_id = l.id
         {$whereSql}
         ORDER BY a.name ASC"
    );
    $stmt->execute($params);
    json_response(['success'=>true,'data'=>$stmt->fetchAll()]);
}

// ── POST create ───────────────────────────────────────────────────────────
if ($method === 'POST' && !$action) {
    if (!$canEdit) json_response(['success'=>false,'message'=>'ไม่มีสิทธิ์'], 403);
    $data = get_input();
    if (empty($data['name'])) json_response(['success'=>false,'message'=>'กรุณากรอกชื่ออุปกรณ์'], 400);

    // Generate asset code if not provided
    $code = clean($data['asset_code'] ?? '');
    if (!$code) {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM assets")->fetchColumn() + 1;
        $code  = 'ASS-' . str_pad($count, 5, '0', STR_PAD_LEFT);
    }
    // Check unique
    $stmt = $pdo->prepare("SELECT id FROM assets WHERE asset_code=?");
    $stmt->execute([$code]);
    if ($stmt->fetch()) json_response(['success'=>false,'message'=>'รหัสอุปกรณ์นี้มีอยู่แล้ว'], 409);

    $pdo->prepare(
        "INSERT INTO assets (asset_code,name,description,category_id,location_id,brand,model,serial_number,purchase_date,warranty_expire,created_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)"
    )->execute([
        $code, clean($data['name']), clean($data['description']??''),
        $data['category_id'] ?: null, $data['location_id'] ?: null,
        clean($data['brand']??''), clean($data['model']??''), clean($data['serial_number']??''),
        $data['purchase_date'] ?: null, $data['warranty_expire'] ?: null,
        $_SESSION['user_id']
    ]);
    json_response(['success'=>true,'message'=>'เพิ่มอุปกรณ์สำเร็จ','id'=>(int)$pdo->lastInsertId(),'asset_code'=>$code]);
}

// ── POST add location ─────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'location') {
    if (!$canEdit) json_response(['success'=>false,'message'=>'ไม่มีสิทธิ์'], 403);
    $data = get_input();
    $pdo->prepare("INSERT INTO asset_locations (building,floor,room,description) VALUES (?,?,?,?)")
        ->execute([clean($data['building']??''), clean($data['floor']??''), clean($data['room']??''), clean($data['description']??'')]);
    json_response(['success'=>true,'id'=>(int)$pdo->lastInsertId()]);
}

// ── PUT update ────────────────────────────────────────────────────────────
if ($method === 'PUT') {
    if (!$canEdit) json_response(['success'=>false,'message'=>'ไม่มีสิทธิ์'], 403);
    if (!$id) json_response(['success'=>false,'message'=>'ระบุ ID ด้วย'], 400);
    $data = get_input();
    $pdo->prepare(
        "UPDATE assets SET name=?,description=?,category_id=?,location_id=?,brand=?,model=?,serial_number=?,purchase_date=?,warranty_expire=?,status=? WHERE id=?"
    )->execute([
        clean($data['name']), clean($data['description']??''),
        $data['category_id'] ?: null, $data['location_id'] ?: null,
        clean($data['brand']??''), clean($data['model']??''), clean($data['serial_number']??''),
        $data['purchase_date'] ?: null, $data['warranty_expire'] ?: null,
        $data['status']??'active', $id
    ]);
    json_response(['success'=>true,'message'=>'แก้ไขข้อมูลสำเร็จ']);
}

// ── DELETE ────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    if (!$canEdit) json_response(['success'=>false,'message'=>'ไม่มีสิทธิ์'], 403);
    if (!$id) json_response(['success'=>false,'message'=>'ระบุ ID ด้วย'], 400);
    $pdo->prepare("DELETE FROM assets WHERE id=?")->execute([$id]);
    json_response(['success'=>true,'message'=>'ลบอุปกรณ์สำเร็จ']);
}

json_response(['success'=>false,'message'=>'Bad request'], 400);
