<?php
require_once __DIR__ . '/../includes/helpers.php';
require_auth();

$pdo        = get_pdo();
$method     = $_SERVER['REQUEST_METHOD'];
$id         = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$canManage  = in_array($_SESSION['user']['role'], ['admin','officer']);

// All authenticated users may fetch a specific role list (e.g. technicians for dropdowns)
// Full CRUD requires admin / officer
if ($method !== 'GET' || ($id === 0 && !$_GET['role'] ?? true)) {
    if (!$canManage) json_response(['success'=>false,'message'=>'Forbidden'], 403);
}

if ($method === 'GET') {
    $search = clean($_GET['search'] ?? '');
    $role   = clean($_GET['role']   ?? '');

    // Non-managers can only fetch specific role list (limited fields)
    if (!$canManage && $role) {
        $stmt = $pdo->prepare("SELECT id, full_name, role, department FROM users WHERE role=? AND active=1 ORDER BY full_name");
        $stmt->execute([$role]);
        json_response(['success'=>true,'data'=>$stmt->fetchAll()]);
    }
    if (!$canManage) json_response(['success'=>false,'message'=>'Forbidden'], 403);
    $sql    = "SELECT id,username,full_name,role,department,email,phone,theme_color,active,created_at FROM users WHERE 1=1";
    $params = [];
    if ($search) { $sql .= " AND (username LIKE ? OR full_name LIKE ?)"; $params[] = "%{$search}%"; $params[] = "%{$search}%"; }
    if ($role)   { $sql .= " AND role = ?";                               $params[] = $role; }
    $sql .= " ORDER BY full_name ASC";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    json_response(['success' => true, 'data' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $data = get_input();
    $required = ['username','password','full_name','role'];
    foreach ($required as $f) {
        if (empty($data[$f])) json_response(['success'=>false,'message'=>"กรุณากรอก {$f}"], 400);
    }
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username=?");
    $stmt->execute([$data['username']]);
    if ($stmt->fetch()) json_response(['success'=>false,'message'=>'Username นี้มีอยู่แล้ว'], 409);

    $hash = password_hash($data['password'], PASSWORD_BCRYPT);
    $pdo->prepare("INSERT INTO users (username,password,full_name,role,department,email,phone,theme_color) VALUES (?,?,?,?,?,?,?,?)")
        ->execute([
            clean($data['username']), $hash, clean($data['full_name']),
            $data['role'], clean($data['department']??''), clean($data['email']??''),
            clean($data['phone']??''), $data['theme_color']??'#2196F3'
        ]);
    $newId = (int)$pdo->lastInsertId();
    log_activity($pdo, 'เพิ่มผู้ใช้งาน', 'users', clean($data['username']), $newId, 'success', "Role: {$data['role']}");
    json_response(['success'=>true,'message'=>'เพิ่มผู้ใช้งานสำเร็จ','id'=>$newId]);
}

if ($method === 'PUT') {
    if (!$id) json_response(['success'=>false,'message'=>'ระบุ ID ด้วย'], 400);
    $data = get_input();
    if ($data['password'] ?? '') {
        $hash = password_hash($data['password'], PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET full_name=?,role=?,department=?,email=?,phone=?,theme_color=?,password=?,active=? WHERE id=?")
            ->execute([clean($data['full_name']), $data['role'], clean($data['department']??''),
                       clean($data['email']??''), clean($data['phone']??''), $data['theme_color']??'#2196F3',
                       $hash, (int)($data['active']??1), $id]);
    } else {
        $pdo->prepare("UPDATE users SET full_name=?,role=?,department=?,email=?,phone=?,theme_color=?,active=? WHERE id=?")
            ->execute([clean($data['full_name']), $data['role'], clean($data['department']??''),
                       clean($data['email']??''), clean($data['phone']??''), $data['theme_color']??'#2196F3',
                       (int)($data['active']??1), $id]);
    }
    log_activity($pdo, 'แก้ไขผู้ใช้งาน', 'users', '', $id);
    json_response(['success'=>true,'message'=>'แก้ไขผู้ใช้งานสำเร็จ']);
}

if ($method === 'DELETE') {
    if (!$id) json_response(['success'=>false,'message'=>'ระบุ ID ด้วย'], 400);
    if ($id === $_SESSION['user_id']) json_response(['success'=>false,'message'=>'ไม่สามารถลบตัวเองได้'], 400);
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id=?"); $stmt->execute([$id]); $u=$stmt->fetch();
    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
    log_activity($pdo, 'ลบผู้ใช้งาน', 'users', $u['username']??'', $id, 'warning');
    json_response(['success'=>true,'message'=>'ลบผู้ใช้งานสำเร็จ']);
}

json_response(['success'=>false,'message'=>'Bad request'], 400);
