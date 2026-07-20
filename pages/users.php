<?php
require '../config/db.php';
require '../config/auth.php';

require_admin();

$permissionColumns = implode(', ', array_keys(permission_fields()));
$users = $pdo->query("SELECT id, name, email, role, access_requested_at, created_at, $permissionColumns FROM users ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$pageTitle = 'Users | CarFlip HQ';
require '../header.php';
?>
<div class="container">
    <h1>Users</h1>
    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert success">User removed.</div>
    <?php endif; ?>
    <?php if (isset($_GET['delete_error'])): ?>
        <div class="alert">User could not be removed. You cannot delete yourself or the last admin account.</div>
    <?php endif; ?>
    <p><a class="btn" href="add-user.php">+ Add User</a></p>
    <table>
        <tr><th>Name</th><th>Email</th><th>Role</th><th>Access</th><th>Created</th><th>Action</th></tr>
        <?php foreach ($users as $account): ?>
        <tr>
            <td><?= htmlspecialchars($account['name']) ?></td>
            <td><?= htmlspecialchars($account['email']) ?></td>
            <td>
                <span class="badge"><?= htmlspecialchars($account['role']) ?></span>
                <?php if (!empty($account['access_requested_at'])): ?>
                    <span class="badge status-rwc-pending">Access requested</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($account['role'] === 'admin'): ?>
                    <span class="badge">Full access</span>
                <?php else: ?>
                    <div class="permission-tags">
                        <?php foreach (permission_fields() as $key => $label): ?>
                            <?php if (!empty($account[$key])): ?>
                            <span><?= htmlspecialchars($label) ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if (!array_filter(array_intersect_key($account, permission_fields()))): ?>
                            <span>No access yet</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($account['created_at']) ?></td>
            <td>
                <div class="row-actions">
                    <a class="btn secondary small-btn" href="edit-user.php?id=<?= (int) $account['id'] ?>">Manage</a>
                    <form action="../actions/delete-user.php" method="POST" onsubmit="return confirm('Remove this user account? This cannot be undone.');">
                        <input type="hidden" name="id" value="<?= (int) $account['id'] ?>">
                        <button class="btn danger small-btn" type="submit">Remove</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php require '../footer.php'; ?>
