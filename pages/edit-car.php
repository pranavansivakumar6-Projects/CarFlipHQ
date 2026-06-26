<?php
require '../config/db.php';
require_once '../config/auth.php';
require_permission('can_manage_cars');
require_once '../config/helpers.php';
require_once '../config/status.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(400); die('Car ID missing.'); }
require_car($pdo, $id);
$stmt = $pdo->prepare("SELECT * FROM cars WHERE id=?");
$stmt->execute([$id]);
$car = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$car) { http_response_code(404); die('Car not found.'); }
$isAdmin = ((current_user()['role'] ?? '') === 'admin');
$users = $isAdmin
    ? $pdo->query("SELECT id, name, role FROM users ORDER BY name")->fetchAll(PDO::FETCH_ASSOC)
    : [];
$allowedStmt = $pdo->prepare('SELECT user_id FROM car_user_access WHERE car_id = ?');
$allowedStmt->execute([$id]);
$allowedUserIds = array_map('intval', $allowedStmt->fetchAll(PDO::FETCH_COLUMN));
$pageTitle='Edit Car | CarFlip HQ'; require '../header.php';
?>
<div class="container"><h1>Edit Car</h1>
<form class="form-card" action="../actions/update-car.php" method="POST" enctype="multipart/form-data">
<input type="hidden" name="id" value="<?= (int) $car['id'] ?>">
<label>Make</label><input name="make" value="<?= htmlspecialchars((string) $car['make']) ?>" required>
<label>Model</label><input name="model" value="<?= htmlspecialchars((string) $car['model']) ?>" required>
<label>Year</label><input name="year" type="number" value="<?= htmlspecialchars((string) ($car['year'] ?? '')) ?>">
<label>Color</label><input name="color" value="<?= htmlspecialchars($car['color'] ?? '') ?>">
<label>Body Type</label><input name="body_type" value="<?= htmlspecialchars($car['body_type'] ?? '') ?>" placeholder="Sedan, hatch, wagon, SUV">
<?php if (!empty($car['profile_photo'])): ?>
<div class="profile-photo-preview"><img src="../<?= htmlspecialchars($car['profile_photo']) ?>" alt="Current car profile photo"></div>
<?php endif; ?>
<label>Profile Photo</label><input name="profile_photo" type="file" accept="image/*">
<label>VIN</label><input name="vin" value="<?= htmlspecialchars((string) ($car['vin'] ?? '')) ?>">
<label>Rego</label><input name="rego" value="<?= htmlspecialchars((string) ($car['rego'] ?? '')) ?>">
<label>Odometer</label><input name="odometer" type="number" value="<?= htmlspecialchars((string) ($car['odometer'] ?? '')) ?>">
<label>Source / Auction</label><input name="source" value="<?= htmlspecialchars((string) ($car['source'] ?? '')) ?>">
<label>Purchase Price</label><input name="purchase_price" type="number" step="0.01" value="<?= $car['purchase_price'] ?>">
<label>Purchase Date</label><input name="purchase_date" type="date" value="<?= $car['purchase_date'] ?>">
<label>Status</label><select name="status">
<?php foreach(allowed_car_statuses() as $s): ?>
<option value="<?= htmlspecialchars($s) ?>" <?= $car['status']===$s?'selected':'' ?>><?= htmlspecialchars(car_status_label($s)) ?></option><?php endforeach; ?>
</select>
<label>Estimated Sale Price</label><input name="estimated_sale_price" type="number" step="0.01" value="<?= $car['estimated_sale_price'] ?>">
<label>Actual Sale Price</label><input name="actual_sale_price" type="number" step="0.01" value="<?= $car['actual_sale_price'] ?>">
<label>Sold Date</label><input name="sold_date" type="date" value="<?= $car['sold_date'] ?>">
<label>Damage Notes</label><textarea name="damage_notes"><?= htmlspecialchars((string) ($car['damage_notes'] ?? '')) ?></textarea>
<label>Notes</label><textarea name="notes"><?= htmlspecialchars((string) ($car['notes'] ?? '')) ?></textarea>
<?php if ($isAdmin): ?>
<div class="form-section">
<h2>Car Access</h2>
<p class="small">Choose which users can see this car. Admin accounts can always see every car.</p>
<div class="choice-grid">
<?php foreach ($users as $accessUser): ?>
<label class="choice-chip">
<input type="checkbox" name="access_user_ids[]" value="<?= (int) $accessUser['id'] ?>" <?= in_array((int) $accessUser['id'], $allowedUserIds, true) ? 'checked' : '' ?>>
<span><?= htmlspecialchars($accessUser['name']) ?><?= $accessUser['role'] === 'admin' ? ' (admin)' : '' ?></span>
</label>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>
<br><br>
<button class="btn" type="submit">Update Car</button>
</form></div><?php require '../footer.php'; ?>
