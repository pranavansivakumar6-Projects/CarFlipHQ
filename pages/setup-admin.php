<?php
require '../config/db.php';
require '../config/auth.php';

if (user_count($pdo) > 0) {
    redirect_to('pages/login.php');
}

$pageTitle = 'Create Admin | CarFlip HQ';
$publicPage = true;
require '../header.php';
?>
<div class="auth-page">
    <form class="form-card auth-card" action="../actions/create-admin.php" method="POST">
        <h1>Create admin</h1>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert">Name, email, and a password of at least 8 characters are required.</div>
        <?php endif; ?>
        <label>Name</label><input name="name" autocomplete="name" required>
        <label>Email</label><input name="email" type="email" autocomplete="email" required>
        <label>Password</label><input name="password" type="password" autocomplete="new-password" minlength="8" required>
        <br><br><button class="btn" type="submit">Create admin</button>
    </form>
</div>
<?php require '../footer.php'; ?>
