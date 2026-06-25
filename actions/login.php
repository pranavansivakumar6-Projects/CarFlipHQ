<?php
require '../config/db.php';
require '../config/auth.php';

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

$stmt = $pdo->prepare('SELECT id, name, email, password_hash, role, session_version, can_view_data, can_manage_cars, can_manage_finance, can_manage_tasks, can_manage_sales, can_import_export, can_use_ai FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    redirect_to('pages/login.php?error=1');
}

session_regenerate_id(true);
$_SESSION['user'] = [
    'id' => (int) $user['id'],
    'name' => $user['name'],
    'email' => $user['email'],
    'role' => $user['role'],
    'session_version' => (int) ($user['session_version'] ?? 0),
];

foreach (array_keys(permission_fields()) as $permission) {
    $_SESSION['user'][$permission] = (int) ($user[$permission] ?? 0);
}

if (!user_can('can_view_data')) {
    redirect_to('pages/account.php');
}

redirect_to('index.php');
?>
