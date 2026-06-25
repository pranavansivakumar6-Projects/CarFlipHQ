<?php
require '../config/db.php';
require '../config/auth.php';

redirect_if_logged_in();
$pageTitle = 'Forgot Password | CarFlip HQ';
$publicPage = true;
require '../header.php';
?>
<div class="auth-page">
    <form class="form-card auth-card" action="../actions/request-password-reset.php" method="POST">
        <h1>Reset password</h1>
        <p class="small">Enter your email and CarFlip HQ will create a password reset link.</p>
        <?php if (isset($_GET['sent'])): ?>
            <div class="alert success">If that email exists, a reset link has been prepared.</div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['dev_reset_link'])): ?>
            <div class="alert">
                Email sending is not configured yet. Use this reset link:<br>
                <a href="<?= htmlspecialchars($_SESSION['dev_reset_link']) ?>"><?= htmlspecialchars($_SESSION['dev_reset_link']) ?></a>
            </div>
            <?php unset($_SESSION['dev_reset_link']); ?>
        <?php endif; ?>
        <label>Email</label><input name="email" type="email" autocomplete="email" required>
        <br><br>
        <button class="btn" type="submit">Get Reset Link</button>
        <p class="small"><a href="login.php">Back to sign in</a></p>
    </form>
</div>
<?php require '../footer.php'; ?>
