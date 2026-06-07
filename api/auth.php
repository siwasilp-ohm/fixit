<?php
require_once __DIR__ . '/../includes/helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'POST' && $action === 'login') {
    $data = get_input();
    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';

    if (!$username || !$password) {
        json_response(['success' => false, 'message' => 'กรุณากรอก Username และ Password'], 400);
    }

    $pdo = get_pdo();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND active = 1 LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        json_response(['success' => false, 'message' => 'Username หรือ Password ไม่ถูกต้อง'], 401);
    }

    $safe = $user;
    unset($safe['password']);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user']    = $safe;

    log_activity($pdo, 'เข้าสู่ระบบสำเร็จ', 'auth', $user['username'], $user['id'], 'success',
        "Role: {$user['role']} | IP: " . ($_SERVER['REMOTE_ADDR'] ?? ''));

    json_response(['success' => true, 'user' => $safe]);
}

if ($method === 'POST' && $action === 'logout') {
    if (!empty($_SESSION['user_id'])) {
        log_activity($pdo, 'ออกจากระบบ', 'auth', $_SESSION['user']['username'] ?? '');
    }
    session_destroy();
    json_response(['success' => true]);
}

if ($method === 'GET' && $action === 'me') {
    require_auth();
    json_response(['success' => true, 'user' => current_user()]);
}

if ($method === 'PUT' && $action === 'theme') {
    require_auth();
    $data  = get_input();
    $color = preg_replace('/[^#a-fA-F0-9]/', '', $data['theme_color'] ?? '#2196F3');
    $pdo   = get_pdo();
    $pdo->prepare("UPDATE users SET theme_color=? WHERE id=?")->execute([$color, $_SESSION['user_id']]);
    $_SESSION['user']['theme_color'] = $color;
    json_response(['success' => true]);
}

if ($method === 'PUT' && $action === 'profile') {
    require_auth();
    $data     = get_input();
    $full_name = clean($data['full_name'] ?? '');
    $email     = clean($data['email'] ?? '');
    $phone     = clean($data['phone'] ?? '');
    $password  = $data['password'] ?? '';

    $pdo = get_pdo();
    if ($password) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET full_name=?,email=?,phone=?,password=? WHERE id=?")
            ->execute([$full_name, $email, $phone, $hash, $_SESSION['user_id']]);
    } else {
        $pdo->prepare("UPDATE users SET full_name=?,email=?,phone=? WHERE id=?")
            ->execute([$full_name, $email, $phone, $_SESSION['user_id']]);
    }
    $_SESSION['user']['full_name'] = $full_name;
    $_SESSION['user']['email']     = $email;
    $_SESSION['user']['phone']     = $phone;
    json_response(['success' => true, 'user' => $_SESSION['user']]);
}

json_response(['success' => false, 'message' => 'Bad request'], 400);
