<?php
require_once __DIR__ . '/../includes/helpers.php';
require_auth();

$pdo    = get_pdo();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$role   = $_SESSION['user']['role'];
$canEdit = in_array($role, ['admin', 'officer']);

if ($method === 'GET') {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
    json_response(['success' => true, 'data' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    if (!$canEdit) json_response(['success'=>false,'message'=>'ไม่มีสิทธิ์'], 403);
    $data = get_input();
    if (empty($data['name'])) json_response(['success'=>false,'message'=>'กรุณากรอกชื่อหมวดหมู่'], 400);
    $pdo->prepare("INSERT INTO categories (name,color,icon) VALUES (?,?,?)")
        ->execute([clean($data['name']), $data['color']??'#2196F3', clean($data['icon']??'fa-wrench')]);
    json_response(['success'=>true,'message'=>'เพิ่มหมวดหมู่สำเร็จ','id'=>(int)$pdo->lastInsertId()]);
}

if ($method === 'PUT') {
    if (!$canEdit) json_response(['success'=>false,'message'=>'ไม่มีสิทธิ์'], 403);
    if (!$id) json_response(['success'=>false,'message'=>'ระบุ ID ด้วย'], 400);
    $data = get_input();
    $pdo->prepare("UPDATE categories SET name=?,color=?,icon=?,active=? WHERE id=?")
        ->execute([clean($data['name']), $data['color']??'#2196F3', clean($data['icon']??'fa-wrench'), (int)($data['active']??1), $id]);
    json_response(['success'=>true,'message'=>'แก้ไขหมวดหมู่สำเร็จ']);
}

if ($method === 'DELETE') {
    if (!$canEdit) json_response(['success'=>false,'message'=>'ไม่มีสิทธิ์'], 403);
    if (!$id) json_response(['success'=>false,'message'=>'ระบุ ID ด้วย'], 400);
    // Check usage
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM repairs WHERE category_id=?");
    $stmt->execute([$id]);
    if ((int)$stmt->fetchColumn() > 0) {
        $pdo->prepare("UPDATE categories SET active=0 WHERE id=?")->execute([$id]);
        json_response(['success'=>true,'message'=>'ปิดการใช้งานหมวดหมู่แล้ว (มีงานซ่อมที่ใช้หมวดหมู่นี้อยู่)']);
    }
    $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);
    json_response(['success'=>true,'message'=>'ลบหมวดหมู่สำเร็จ']);
}

json_response(['success'=>false,'message'=>'Bad request'], 400);
