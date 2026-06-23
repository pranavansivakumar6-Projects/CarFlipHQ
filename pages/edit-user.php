<?php
require '../config/db.php';
require '../config/auth.php';

require_admin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(400); die('User ID missing.'); }

$stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
$stmt->execute([$id]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$account) { http_response_code(404); die('User not found.'); }

$pageTitle = 'Edit User | CarFlip HQ';
require '../header.php';
?>
<div class="container">
    <h1>Edit User</h1>
    <?php if (isset($_GET['error'])): ?>
    <div class="alert">Name, valid email, and role are required. New passwords must be at least 8 characters.</div>
    <?php endif; ?>
    <form class="form-card" action="../actions/update-user.php" method="POST">
        <input type="hidden" name="id" value="<?= (int) $account['id'] ?>">
        <label>Name</label><input name="name" value="<?= htmlspecialchars($account['name']) ?>" autocomplete="name" required>
        <label>Email</label><input name="email" type="email" value="<?= htmlspecialchars($account['email']) ?>" autocomplete="email" required>
        <label>New Password</label><input name="password" type="password" autocomplete="new-password" minlength="8">
        <p class="small">Leave password blank to keep the current password.</p>
        <label>Role</label>
        <select name="role">
            <?php foreach(['partner' => 'Partner', 'admin' => 'Admin'] as $role => $label): ?>
            <option value="<?= $role ?>" <?= $account['role'] === $role ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
        <br><br><button class="btn" type="submit">Update User</button>
        <a class="btn secondary" href="users.php">Cancel</a>
    </form>
</div>
<?php require '../footer.php'; ?>
