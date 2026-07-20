<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';

require_admin();

$id = post_int('id', true);
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
if (!empty($permissionValues['can_manage_finance'])) {
    $permissionValues['can_view_finance'] = 1;
}
if (!empty($permissionValues['can_manage_imports']) || !empty($permissionValues['can_view_import_finance'])) {
    $permissionValues['can_view_imports'] = 1;
}

if (!$email || ($password !== '' && strlen($password) < 8)) {
    redirect_to('pages/edit-user.php?id=' . $id . '&error=1');
}

try {
    $permissionSql = implode(', ', array_map(fn($permission) => "$permission = ?", array_keys($permissionValues)));
    if ($password !== '') {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password_hash = ?, role = ?, access_requested_at = NULL, $permissionSql, session_version = session_version + 1 WHERE id = ?");
        $stmt->execute(array_merge([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role], array_values($permissionValues), [$id]));
    } else {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, access_requested_at = NULL, $permissionSql, session_version = session_version + 1 WHERE id = ?");
        $stmt->execute(array_merge([$name, $email, $role], array_values($permissionValues), [$id]));
    }
} catch (PDOException $e) {
    redirect_to('pages/edit-user.php?id=' . $id . '&error=1');
}

$current = current_user();
if ($current && (int) $current['id'] === $id) {
    $_SESSION['user']['name'] = $name;
    $_SESSION['user']['email'] = $email;
    $_SESSION['user']['role'] = $role;
    foreach ($permissionValues as $permission => $value) {
        $_SESSION['user'][$permission] = $value;
    }
    $_SESSION['user']['session_version'] = (int) ($_SESSION['user']['session_version'] ?? 0) + 1;
}

redirect_to('pages/users.php');
?>
