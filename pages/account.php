<?php
require '../config/db.php';
require '../config/auth.php';

require_login();
$account = current_user();
$pageTitle = 'My Account | CarFlip HQ';
require '../header.php';
?>
<div class="container">
    <div class="page-heading">
        <div>
            <h1>My Account</h1>
            <p class="small">Change your password if someone else has your login details.</p>
        </div>
    </div>

    <?php if (isset($_GET['changed'])): ?>
        <div class="alert success">Password changed. Old sessions for this account have been signed out.</div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert">Current password is wrong, or the new password is less than 8 characters.</div>
    <?php endif; ?>
    <?php if (!user_can('can_view_data')): ?>
        <div class="alert">Your account is active, but business access has not been approved yet. Ask an admin to enable permissions from Users.</div>
    <?php endif; ?>

    <div class="grid">
        <div class="card">
            <b>Signed in as</b>
            <div class="stat"><?= htmlspecialchars($account['name'] ?? '') ?></div>
            <div class="small"><?= htmlspecialchars($account['email'] ?? '') ?> / <?= htmlspecialchars($account['role'] ?? '') ?></div>
            <div class="permission-tags account-permissions">
                <?php if (($account['role'] ?? '') === 'admin'): ?>
                    <span>Full access</span>
                <?php else: ?>
                    <?php $hasPermission = false; ?>
                    <?php foreach (permission_fields() as $key => $label): ?>
                        <?php if (user_can($key)): ?>
                        <?php $hasPermission = true; ?>
                        <span><?= htmlspecialchars($label) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (!$hasPermission): ?>
                        <span>No business access yet</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <form class="form-card" action="../actions/change-password.php" method="POST">
            <h2>Change Password</h2>
            <label>Current Password</label>
            <input name="current_password" type="password" autocomplete="current-password" required>
            <label>New Password</label>
            <input name="new_password" type="password" autocomplete="new-password" minlength="8" required>
            <label>Confirm New Password</label>
            <input name="confirm_password" type="password" autocomplete="new-password" minlength="8" required>
            <br><br>
            <button class="btn" type="submit">Update Password</button>
        </form>
    </div>
</div>
<?php require '../footer.php'; ?>
