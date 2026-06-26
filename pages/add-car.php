<?php
require '../config/db.php';
require_once '../config/auth.php';
require_permission('can_manage_cars');
$isAdmin = ((current_user()['role'] ?? '') === 'admin');
$users = $isAdmin
    ? $pdo->query("SELECT id, name, role FROM users ORDER BY name")->fetchAll(PDO::FETCH_ASSOC)
    : [];
$pageTitle = 'Add Car | CarFlip HQ';
require '../header.php';
?>
<div class="container">
    <h1>Add Car</h1>
    <p><a class="btn secondary" href="import-sheet.php">Import Cars From Sheet</a></p>
    <form class="form-card" action="../actions/save-car.php" method="POST" enctype="multipart/form-data">
        <label>Make</label><input name="make" required>
        <label>Model</label><input name="model" required>
        <label>Year</label><input name="year" type="number">
        <label>Color</label><input name="color">
        <label>Body Type</label><input name="body_type" placeholder="Sedan, hatch, wagon, SUV">
        <label>Profile Photo</label><input name="profile_photo" type="file" accept="image/*">
        <label>VIN</label><input name="vin">
        <label>Rego</label><input name="rego">
        <label>Odometer</label><input name="odometer" type="number">
        <label>Source / Auction</label><input name="source">
        <label>Purchase Price</label><input name="purchase_price" type="number" step="0.01">
        <label>Purchase Date</label><input name="purchase_date" type="date">
        <label>Estimated Sale Price</label><input name="estimated_sale_price" type="number" step="0.01">
        <label>Damage Notes</label><textarea name="damage_notes"></textarea>
        <label>General Notes</label><textarea name="notes"></textarea>
        <?php if ($isAdmin): ?>
        <div class="form-section">
            <h2>Car Access</h2>
            <p class="small">Choose which users can see this car. Admin accounts can always see every car.</p>
            <div class="choice-grid">
                <?php foreach ($users as $accessUser): ?>
                <label class="choice-chip">
                    <input type="checkbox" name="access_user_ids[]" value="<?= (int) $accessUser['id'] ?>">
                    <span><?= htmlspecialchars($accessUser['name']) ?><?= $accessUser['role'] === 'admin' ? ' (admin)' : '' ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <br><br><button class="btn" type="submit">Save Car</button>
    </form>
</div>
<?php require '../footer.php'; ?>
