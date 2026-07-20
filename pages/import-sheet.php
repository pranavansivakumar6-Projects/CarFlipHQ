<?php
require '../config/db.php';
require_once '../config/auth.php';
require_permission('can_import_export');
$users = $pdo->query("SELECT name, role FROM users ORDER BY role = 'admin', name")->fetchAll(PDO::FETCH_ASSOC);
$pageTitle = 'Import Sheet | CarFlip HQ';
require '../header.php';
?>
<div class="container">
    <h1>Import Sheet</h1>
    <div class="card">
        <p>Upload a CSV exported from Excel or Google Sheets. You can use the CarFlip HQ template with <b>record_type</b> values like car, expense, task, purchase_payment, part, and listing.</p>
        <p>Expense sheets can also include paid-by columns for each database user. CarFlip HQ will create the car, purchase payment splits, and expenses under the right payer.</p>
        <p><a class="btn secondary" href="../actions/download-import-template.php">Download Template</a></p>
    </div>
    <div class="card">
        <h2>Database Users</h2>
        <p class="small">Use these exact names in <b>paid_by</b>, <b>assigned_to</b>, or paid-by spreadsheet columns. Add or edit people from the Users page first, then download a fresh template.</p>
        <?php if ($users): ?>
            <div class="permission-grid">
                <?php foreach ($users as $appUser): ?>
                    <span class="check-pill"><?= htmlspecialchars($appUser['name']) ?> <span class="small">(<?= htmlspecialchars($appUser['role']) ?>)</span></span>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="small">No users found yet.</p>
        <?php endif; ?>
    </div>
    <?php if (isset($_GET['imported'])): ?>
    <div class="alert success">Imported <?= (int) $_GET['imported'] ?> rows.</div>
    <?php endif; ?>
    <form class="form-card section-title" action="../actions/import-sheet.php" method="POST" enctype="multipart/form-data">
        <label>CSV File</label><input name="sheet_file" type="file" accept=".csv,text/csv" required>
        <br><br><button class="btn" type="submit">Import Sheet</button>
    </form>
</div>
<?php require '../footer.php'; ?>
