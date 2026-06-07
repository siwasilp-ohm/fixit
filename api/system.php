<?php
require_once __DIR__ . '/../includes/helpers.php';
require_role('admin');

$pdo    = get_pdo();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'info';

// ── Helpers ───────────────────────────────────────────────────────────────
function folder_size(string $path): int {
    $size = 0;
    if (!is_dir($path)) return 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $f) {
        try { $size += $f->getSize(); } catch (\Exception $e) {}
    }
    return $size;
}

function human_size(int $bytes): string {
    if ($bytes >= 1073741824) return round($bytes/1073741824,2).' GB';
    if ($bytes >= 1048576)    return round($bytes/1048576,2).' MB';
    if ($bytes >= 1024)       return round($bytes/1024,2).' KB';
    return $bytes.' B';
}

// ── GET: System Info ──────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'info') {
    $db_ver = $pdo->query("SELECT VERSION()")->fetchColumn();

    $sz = $pdo->prepare(
        "SELECT ROUND(SUM(data_length+index_length),0) AS size, COUNT(*) AS tables
         FROM information_schema.TABLES WHERE table_schema = ?"
    );
    $sz->execute([DB_NAME]);
    $db_meta = $sz->fetch();

    $tbl = $pdo->prepare(
        "SELECT table_name, COALESCE(table_rows,0) AS rows,
                ROUND((data_length+index_length),0) AS size
         FROM information_schema.TABLES WHERE table_schema = ?
         ORDER BY (data_length+index_length) DESC"
    );
    $tbl->execute([DB_NAME]);

    $disk_free  = @disk_free_space(UPLOAD_DIR) ?: 0;
    $disk_total = @disk_total_space(UPLOAD_DIR) ?: 0;

    json_response([
        'success'        => true,
        'php_version'    => PHP_VERSION,
        'php_os'         => PHP_OS_FAMILY,
        'server_time'    => date('Y-m-d H:i:s'),
        'server_software'=> $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'db_version'     => $db_ver,
        'db_size'        => (int)($db_meta['size'] ?? 0),
        'db_tables'      => (int)($db_meta['tables'] ?? 0),
        'db_name'        => DB_NAME,
        'table_list'     => $tbl->fetchAll(),
        'upload_size'    => folder_size(UPLOAD_DIR),
        'backup_size'    => folder_size(__DIR__.'/../backups/'),
        'disk_free'      => $disk_free,
        'disk_total'     => $disk_total,
        'memory_limit'   => ini_get('memory_limit'),
        'upload_max'     => ini_get('upload_max_filesize'),
        'max_exec'       => ini_get('max_execution_time'),
        'post_max'       => ini_get('post_max_size'),
        'extensions'     => array_intersect(['gd','pdo_mysql','mbstring','zip','curl','json'],get_loaded_extensions()),
    ]);
}

// ── GET: Activity Logs ────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'logs') {
    $where  = ['1=1'];
    $params = [];

    if ($_GET['module']  ?? '') { $where[] = 'module = ?';         $params[] = $_GET['module']; }
    if ($_GET['user_id'] ?? '') { $where[] = 'user_id = ?';        $params[] = (int)$_GET['user_id']; }
    if ($_GET['status']  ?? '') { $where[] = 'status = ?';         $params[] = $_GET['status']; }
    if ($_GET['search']  ?? '') { $where[] = '(action LIKE ? OR target_name LIKE ?)'; $s='%'.$_GET['search'].'%'; $params[]=$s;$params[]=$s; }
    if ($_GET['date_from']??'') { $where[] = 'DATE(created_at) >= ?'; $params[] = $_GET['date_from']; }
    if ($_GET['date_to']  ??'') { $where[] = 'DATE(created_at) <= ?'; $params[] = $_GET['date_to']; }

    $page  = max(1, (int)($_GET['page']  ?? 1));
    $limit = min(100, (int)($_GET['limit'] ?? 50));
    $offset = ($page-1)*$limit;

    $w = implode(' AND ', $where);
    $count = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE {$w}");
    $count->execute($params);
    $total = (int)$count->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT * FROM activity_logs WHERE {$w}
         ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // distinct modules for filter
    $modules = $pdo->query("SELECT DISTINCT module FROM activity_logs WHERE module != '' ORDER BY module")->fetchAll(PDO::FETCH_COLUMN);

    // CSV export
    if (($_GET['export'] ?? '') === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="activity_log_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
        fputcsv($out, ['วันที่/เวลา','Username','Role','Module','Action','Target','Status','Details','IP']);
        foreach ($rows as $row) {
            fputcsv($out, [
                $row['created_at'], $row['username'], $row['role'],
                $row['module'], $row['action'], $row['target_name'],
                $row['status'], $row['details'], $row['ip_address'],
            ]);
        }
        fclose($out);
        exit;
    }

    json_response(['success'=>true,'data'=>$rows,'total'=>$total,'page'=>$page,'limit'=>$limit,'modules'=>$modules]);
}

// ── DELETE: Clear old logs ────────────────────────────────────────────────
if ($method === 'DELETE' && $action === 'logs') {
    $days = max(1, (int)($_GET['days'] ?? 30));
    $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->execute([$days]);
    $deleted = $stmt->rowCount();
    log_activity($pdo, "ล้าง Activity Log เก่ากว่า {$days} วัน", 'system', "ลบ {$deleted} รายการ");
    json_response(['success'=>true,'message'=>"ล้าง {$deleted} รายการสำเร็จ"]);
}

// ── POST: Create Backup ───────────────────────────────────────────────────
if ($method === 'POST' && $action === 'backup') {
    $note      = clean(get_input()['note'] ?? '');
    $backup_dir = __DIR__ . '/../backups/';
    if (!is_dir($backup_dir)) mkdir($backup_dir, 0755, true);

    $be_year  = (int)date('Y') + 543;
    $filename = 'backup_' . date('Ymd_His') . '.sql';
    $filepath = $backup_dir . $filename;

    // ── Pure-PHP mysqldump ───────────────────────────────────────
    set_time_limit(300);
    $fp = fopen($filepath, 'w');
    if (!$fp) json_response(['success'=>false,'message'=>'ไม่สามารถสร้างไฟล์ backup ได้ ตรวจสอบสิทธิ์โฟลเดอร์'], 500);

    $total_rows = 0;
    $tables     = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    fwrite($fp, "-- FixIt Database Backup\n");
    fwrite($fp, "-- Generated : " . date('Y-m-d H:i:s') . " (Server Time)\n");
    fwrite($fp, "-- Database  : " . DB_NAME . "\n");
    fwrite($fp, "-- Tables    : " . count($tables) . "\n");
    fwrite($fp, "-- Created by: " . ($_SESSION['user']['username'] ?? 'system') . "\n");
    if ($note) fwrite($fp, "-- Note      : {$note}\n");
    fwrite($fp, "-- -----------------------------------------------\n\n");
    fwrite($fp, "SET FOREIGN_KEY_CHECKS=0;\n");
    fwrite($fp, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n");
    fwrite($fp, "SET NAMES utf8mb4;\n");
    fwrite($fp, "SET CHARACTER_SET_CLIENT=utf8mb4;\n\n");

    foreach ($tables as $table) {
        // Table structure
        $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
        fwrite($fp, "-- -----------------------------------------------\n");
        fwrite($fp, "-- Table: `{$table}`\n");
        fwrite($fp, "-- -----------------------------------------------\n");
        fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;\n");
        fwrite($fp, $create[1] . ";\n\n");

        // Data
        $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_NUM);
        if ($rows) {
            $cols = $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN);
            $colList = '`' . implode('`,`', $cols) . '`';
            $total_rows += count($rows);
            foreach (array_chunk($rows, 200) as $chunk) {
                $vals = array_map(function($row) use ($pdo) {
                    return '(' . implode(',', array_map(function($v) use ($pdo) {
                        return is_null($v) ? 'NULL' : $pdo->quote($v);
                    }, $row)) . ')';
                }, $chunk);
                fwrite($fp, "INSERT INTO `{$table}` ({$colList}) VALUES\n" . implode(",\n", $vals) . ";\n");
            }
        }
        fwrite($fp, "\n");
    }

    fwrite($fp, "SET FOREIGN_KEY_CHECKS=1;\n");
    fwrite($fp, "-- END OF BACKUP\n");
    fclose($fp);

    $filesize = filesize($filepath);

    // Save record to DB
    $pdo->prepare(
        "INSERT INTO backups (filename,filesize,tables_count,rows_count,type,note,created_by) VALUES (?,?,?,?,?,?,?)"
    )->execute([$filename, $filesize, count($tables), $total_rows, 'manual', $note, $_SESSION['user_id']]);

    log_activity($pdo, 'สร้าง Database Backup', 'system', $filename, (int)$pdo->lastInsertId(), 'success',
        "ขนาด: " . human_size($filesize) . " | {$total_rows} rows | " . count($tables) . " ตาราง");

    json_response(['success'=>true,'message'=>'สำรองข้อมูลสำเร็จ!','filename'=>$filename,'filesize'=>$filesize,'tables'=>count($tables),'rows'=>$total_rows]);
}

// ── GET: List Backups ─────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'backups') {
    $stmt = $pdo->query(
        "SELECT b.*, u.full_name as created_by_name
         FROM backups b LEFT JOIN users u ON b.created_by = u.id
         ORDER BY b.created_at DESC"
    );
    $list = $stmt->fetchAll();

    // Sync file existence
    $backup_dir = __DIR__ . '/../backups/';
    foreach ($list as &$b) {
        $b['exists'] = file_exists($backup_dir . $b['filename']);
    }
    unset($b);

    json_response(['success'=>true,'data'=>$list]);
}

// ── GET: Download Backup ──────────────────────────────────────────────────
if ($method === 'GET' && $action === 'download') {
    $id       = (int)($_GET['id'] ?? 0);
    $stmt     = $pdo->prepare("SELECT * FROM backups WHERE id=?");
    $stmt->execute([$id]);
    $backup   = $stmt->fetch();
    if (!$backup) json_response(['success'=>false,'message'=>'ไม่พบข้อมูล'], 404);

    $filepath = __DIR__ . '/../backups/' . $backup['filename'];
    if (!file_exists($filepath)) json_response(['success'=>false,'message'=>'ไม่พบไฟล์ backup'], 404);

    log_activity($pdo, 'ดาวน์โหลด Backup', 'system', $backup['filename'], $id);

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $backup['filename'] . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: no-cache');
    readfile($filepath);
    exit;
}

// ── DELETE: Delete Backup ─────────────────────────────────────────────────
if ($method === 'DELETE' && $action !== 'logs') {
    $id   = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM backups WHERE id=?");
    $stmt->execute([$id]);
    $backup = $stmt->fetch();
    if (!$backup) json_response(['success'=>false,'message'=>'ไม่พบข้อมูล'], 404);

    $filepath = __DIR__ . '/../backups/' . $backup['filename'];
    if (file_exists($filepath)) unlink($filepath);
    $pdo->prepare("DELETE FROM backups WHERE id=?")->execute([$id]);

    log_activity($pdo, 'ลบ Backup', 'system', $backup['filename'], $id, 'warning');
    json_response(['success'=>true,'message'=>'ลบ backup สำเร็จ']);
}

json_response(['success'=>false,'message'=>'Bad request'], 400);
