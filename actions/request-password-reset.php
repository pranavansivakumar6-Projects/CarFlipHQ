<?php
require '../config/db.php';
require '../config/auth.php';

redirect_if_logged_in();

$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
if ($email) {
    $stmt = $pdo->prepare('SELECT id, name, email FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($account) {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

        $delete = $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? OR expires_at < NOW() OR used_at IS NOT NULL');
        $delete->execute([(int) $account['id']]);

        $insert = $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
        $insert->execute([(int) $account['id'], $tokenHash, $expiresAt]);

        $resetLink = app_absolute_url('pages/reset-password.php?token=' . $token);
        $message = "Hi {$account['name']},\n\nUse this link to reset your CarFlip HQ password. It expires in 1 hour:\n{$resetLink}\n\nIf you did not request this, ignore this email.";

        if (!send_app_email($account['email'], 'Reset your CarFlip HQ password', $message)) {
            $_SESSION['dev_reset_link'] = $resetLink;
        }
    }
}

redirect_to('pages/forgot-password.php?sent=1');
?>
