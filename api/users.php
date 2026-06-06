<?php
require_once __DIR__ . '/../includes/helpers.php';
require_role('admin', 'officer');

$pdo    = get_pdo();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($method === 'GET') {
    $search = clean($_GET['search'] ?? '');
    $role   = clean($_GET['role']   ?? '');
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
    json_response(['success'=>true,'message'=>'เพิ่มผู้ใช้งานสำเร็จ','id'=>(int)$pdo->lastInsertId()]);
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
    json_response(['success'=>true,'message'=>'แก้ไขผู้ใช้งานสำเร็จ']);
}

if ($method === 'DELETE') {
    if (!$id) json_response(['success'=>false,'message'=>'ระบุ ID ด้วย'], 400);
    if ($id === $_SESSION['user_id']) json_response(['success'=>false,'message'=>'ไม่สามารถลบตัวเองได้'], 400);
    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
    json_response(['success'=>true,'message'=>'ลบผู้ใช้งานสำเร็จ']);
}

json_response(['success'=>false,'message'=>'Bad request'], 400);
