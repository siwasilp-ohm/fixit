<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function require_auth(): void {
    if (empty($_SESSION['user_id'])) {
        json_response(['success' => false, 'message' => 'Unauthorized'], 401);
    }
}

function require_role(string ...$roles): void {
    require_auth();
    if (!in_array($_SESSION['user']['role'] ?? '', $roles, true)) {
        json_response(['success' => false, 'message' => 'Forbidden'], 403);
    }
}

function current_user(): array {
    return $_SESSION['user'] ?? [];
}

function get_input(): array {
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($ct, 'application/json')) {
        $body = file_get_contents('php://input');
        return json_decode($body, true) ?? [];
    }
    return array_merge($_GET, $_POST);
}

function clean(string $str): string {
    return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES, 'UTF-8');
}

function generate_repair_number(PDO $pdo): string {
    $be_year = (int)date('Y') + 543;
    $yy = substr((string)$be_year, -2);
    $year_start = date('Y') . '-01-01 00:00:00';
    $year_end   = ((int)date('Y') + 1) . '-01-01 00:00:00';

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM repairs WHERE created_at >= ? AND created_at < ?");
    $stmt->execute([$year_start, $year_end]);
    $count = (int)$stmt->fetchColumn() + 1;

    return sprintf('RE-%s/%03d', $yy, $count);
}

function log_repair_history(PDO $pdo, int $repair_id, ?string $old_status, string $new_status, string $action, string $comment = '', ?float $cost = null): void {
    $stmt = $pdo->prepare(
        "INSERT INTO repair_history (repair_id, user_id, action, old_status, new_status, comment, cost)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $repair_id,
        $_SESSION['user_id'] ?? null,
        $action,
        $old_status,
        $new_status,
        $comment,
        $cost
    ]);
}
