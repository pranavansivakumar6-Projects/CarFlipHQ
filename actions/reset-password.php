<?php
require '../config/db.php';
require '../config/auth.php';

redirect_if_logged_in();

$token = trim($_POST['token'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if ($token === '' || strlen($password) < 8 || $password !== $confirmPassword) {
    redirect_to('pages/reset-password.php?error=1');
}

$tokenHash = hash('sha256', $token);
$stmt = $pdo->prepare("
    SELECT pr.id, pr.user_id
    FROM password_resets pr
    JOIN users u ON u.id = pr.user_id
    WHERE pr.token_hash = ?
      AND pr.used_at IS NULL
      AND pr.expires_at > NOW()
    LIMIT 1
");
$stmt->execute([$tokenHash]);
$reset = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reset) {
    redirect_to('pages/reset-password.php?error=1');
}

$pdo->beginTransaction();
try {
    $updateUser = $pdo->prepare('UPDATE users SET password_hash = ?, session_version = session_version + 1 WHERE id = ?');
    $updateUser->execute([password_hash($password, PASSWORD_DEFAULT), (int) $reset['user_id']]);

    $updateReset = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?');
    $updateReset->execute([(int) $reset['id']]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    redirect_to('pages/reset-password.php?error=1');
}

redirect_to('pages/login.php?reset=1');
?>
