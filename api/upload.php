<?php
require_once __DIR__ . '/../includes/helpers.php';
require_auth();

$pdo    = get_pdo();
$action = $_GET['action'] ?? 'upload';

// Ensure runtime dirs exist
foreach ([UPLOAD_DIR, UPLOAD_DIR.'repairs/', CHUNK_DIR] as $_dir) {
    if (!is_dir($_dir)) @mkdir($_dir, 0755, true);
}

// ── Chunked upload ───────────────────────────────────────────────────────
if ($action === 'chunk') {
    $upload_id   = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['upload_id']   ?? '');
    $chunk_index = (int)($_POST['chunk_index']  ?? 0);
    $total_chunks= (int)($_POST['total_chunks'] ?? 1);
    $repair_id   = (int)($_POST['repair_id']    ?? 0);
    $img_type    = in_array($_POST['img_type']??'before', ['before','after','other']) ? $_POST['img_type'] : 'before';

    if (!$upload_id) json_response(['success'=>false,'message'=>'Missing upload_id'], 400);

    if (!isset($_FILES['chunk']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
        json_response(['success'=>false,'message'=>'Upload error: ' . ($_FILES['chunk']['error']??'no file')], 400);
    }

    $chunk_dir = CHUNK_DIR . $upload_id . '/';
    if (!is_dir($chunk_dir)) mkdir($chunk_dir, 0755, true);

    move_uploaded_file($_FILES['chunk']['tmp_name'], $chunk_dir . $chunk_index);

    // Check if all chunks arrived
    $received = count(glob($chunk_dir . '*'));
    if ($received < $total_chunks) {
        json_response(['success'=>true,'status'=>'partial','received'=>$received,'total'=>$total_chunks]);
    }

    // Assemble
    $original_name = $_POST['original_name'] ?? 'upload.jpg';
    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $allowed_ext = ['jpg','jpeg','png','gif','webp'];
    if (!in_array($ext, $allowed_ext)) {
        array_map('unlink', glob($chunk_dir . '*'));
        rmdir($chunk_dir);
        json_response(['success'=>false,'message'=>'ประเภทไฟล์ไม่รองรับ'], 400);
    }

    $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest_dir = UPLOAD_DIR . 'repairs/' . ($repair_id ?: 'temp') . '/';
    if (!is_dir($dest_dir)) mkdir($dest_dir, 0755, true);
    $dest_path = $dest_dir . $filename;

    $fp = fopen($dest_path, 'wb');
    for ($i = 0; $i < $total_chunks; $i++) {
        fwrite($fp, file_get_contents($chunk_dir . $i));
        unlink($chunk_dir . $i);
    }
    fclose($fp);
    rmdir($chunk_dir);

    // Validate image
    $info = @getimagesize($dest_path);
    if (!$info) {
        unlink($dest_path);
        json_response(['success'=>false,'message'=>'ไฟล์ไม่ใช่รูปภาพ'], 400);
    }

    // Save to DB
    $rel_path = 'repairs/' . ($repair_id ?: 'temp') . '/' . $filename;
    if ($repair_id) {
        $pdo->prepare("INSERT INTO repair_images (repair_id,filename,original_name,type,uploaded_by) VALUES (?,?,?,?,?)")
            ->execute([$repair_id, $rel_path, $original_name, $img_type, $_SESSION['user_id']]);
        $img_id = (int)$pdo->lastInsertId();
    } else {
        $img_id = 0;
    }

    json_response([
        'success'  => true,
        'status'   => 'complete',
        'filename' => $rel_path,
        'img_id'   => $img_id,
        'url'      => 'uploads/' . $rel_path,
    ]);
}

// ── Simple single upload ─────────────────────────────────────────────────
if ($action === 'upload') {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        json_response(['success'=>false,'message'=>'Upload error'], 400);
    }

    $repair_id = (int)($_POST['repair_id'] ?? 0);
    $img_type  = in_array($_POST['img_type']??'before', ['before','after','other']) ? $_POST['img_type'] : 'before';

    $file = $_FILES['file'];
    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, ALLOWED_TYPES)) {
        json_response(['success'=>false,'message'=>'ประเภทไฟล์ไม่รองรับ'], 400);
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        json_response(['success'=>false,'message'=>'ไฟล์ใหญ่เกิน 50MB'], 400);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: 'jpg';
    $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest_dir = UPLOAD_DIR . 'repairs/' . ($repair_id ?: 'temp') . '/';
    if (!is_dir($dest_dir)) mkdir($dest_dir, 0755, true);

    move_uploaded_file($file['tmp_name'], $dest_dir . $filename);
    $rel_path = 'repairs/' . ($repair_id ?: 'temp') . '/' . $filename;

    if ($repair_id) {
        $pdo->prepare("INSERT INTO repair_images (repair_id,filename,original_name,type,uploaded_by) VALUES (?,?,?,?,?)")
            ->execute([$repair_id, $rel_path, $file['name'], $img_type, $_SESSION['user_id']]);
        $img_id = (int)$pdo->lastInsertId();
    } else {
        $img_id = 0;
    }

    json_response(['success'=>true,'filename'=>$rel_path,'img_id'=>$img_id,'url'=>'uploads/'.$rel_path]);
}

// ── Attach temp uploads to repair ────────────────────────────────────────
if ($action === 'attach' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data      = get_input();
    $repair_id = (int)($data['repair_id'] ?? 0);
    $filenames = $data['filenames'] ?? [];
    if ($repair_id && is_array($filenames)) {
        foreach ($filenames as $fn) {
            $fn = preg_replace('/[^a-zA-Z0-9_.\/\-]/', '', $fn);
            // Move file
            $src = UPLOAD_DIR . $fn;
            $new_fn = str_replace('/temp/', '/'. $repair_id . '/', $fn);
            $new_path = UPLOAD_DIR . $new_fn;
            if (!is_dir(dirname($new_path))) mkdir(dirname($new_path), 0755, true);
            if (file_exists($src)) rename($src, $new_path);
            $pdo->prepare("INSERT INTO repair_images (repair_id,filename,type,uploaded_by) VALUES (?,?,?,?)")
                ->execute([$repair_id, $new_fn, 'before', $_SESSION['user_id']]);
        }
    }
    json_response(['success'=>true]);
}

// ── Delete image ──────────────────────────────────────────────────────────
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $img_id = (int)($_GET['img_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM repair_images WHERE id=?");
    $stmt->execute([$img_id]);
    $img = $stmt->fetch();
    if ($img) {
        @unlink(UPLOAD_DIR . $img['filename']);
        $pdo->prepare("DELETE FROM repair_images WHERE id=?")->execute([$img_id]);
    }
    json_response(['success'=>true]);
}

json_response(['success'=>false,'message'=>'Bad request'], 400);
