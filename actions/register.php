<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';

if (user_count($pdo) === 0) {
    redirect_to('pages/setup-admin.php');
}

redirect_if_logged_in();

$name = post_string('name', true);
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if (!$email || strlen($password) < 8 || $password !== $confirmPassword) {
    redirect_to('pages/register.php?error=1');
}

try {
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
    $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), 'partner']);
} catch (PDOException $e) {
    redirect_to('pages/register.php?error=1');
}

redirect_to('pages/login.php?registered=1');
?>
