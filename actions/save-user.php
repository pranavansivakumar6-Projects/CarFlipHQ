<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';

require_admin();

$name = post_string('name', true);
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$password = $_POST['password'] ?? '';
$role = require_allowed_value(post_string('role', true), ['admin','partner'], 'role');
$postedPermissionList = is_array($_POST['permissions'] ?? null) ? $_POST['permissions'] : [];
$postedPermissions = array_flip($postedPermissionList);
$permissionValues = [];
foreach (array_keys(permission_fields()) as $permission) {
    $permissionValues[$permission] = $role === 'admin' ? 1 : (isset($postedPermissions[$permission]) ? 1 : 0);
}

if (!$email || strlen($password) < 8) {
    redirect_to('pages/add-user.php?error=1');
}

try {
    $columns = implode(', ', array_keys($permissionValues));
    $placeholders = implode(', ', array_fill(0, count($permissionValues), '?'));
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, $columns) VALUES (?, ?, ?, ?, $placeholders)");
    $stmt->execute(array_merge([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role], array_values($permissionValues)));
} catch (PDOException $e) {
    redirect_to('pages/add-user.php?error=1');
}

redirect_to('pages/users.php');
?>
