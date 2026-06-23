<?php
require '../config/db.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(400); die('Car ID missing.'); }
$stmt = $pdo->prepare("SELECT * FROM cars WHERE id=?");
$stmt->execute([$id]);
$car = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$car) { http_response_code(404); die('Car not found.'); }
$pageTitle='Edit Car | CarFlip HQ'; require '../header.php';
?>
<div class="container"><h1>Edit Car</h1>
<form class="form-card" action="../actions/update-car.php" method="POST">
<input type="hidden" name="id" value="<?= (int) $car['id'] ?>">
<label>Make</label><input name="make" value="<?= htmlspecialchars($car['make']) ?>" required>
<label>Model</label><input name="model" value="<?= htmlspecialchars($car['model']) ?>" required>
<label>Year</label><input name="year" type="number" value="<?= htmlspecialchars($car['year']) ?>">
<label>Color</label><input name="color" value="<?= htmlspecialchars($car['color'] ?? '') ?>">
<label>Body Type</label><input name="body_type" value="<?= htmlspecialchars($car['body_type'] ?? '') ?>" placeholder="Sedan, hatch, wagon, SUV">
<label>VIN</label><input name="vin" value="<?= htmlspecialchars($car['vin']) ?>">
<label>Rego</label><input name="rego" value="<?= htmlspecialchars($car['rego']) ?>">
<label>Odometer</label><input name="odometer" type="number" value="<?= htmlspecialchars($car['odometer']) ?>">
<label>Source / Auction</label><input name="source" value="<?= htmlspecialchars($car['source']) ?>">
<label>Purchase Price</label><input name="purchase_price" type="number" step="0.01" value="<?= $car['purchase_price'] ?>">
<label>Purchase Date</label><input name="purchase_date" type="date" value="<?= $car['purchase_date'] ?>">
<label>Status</label><select name="status">
<?php foreach(['Bought','Waiting for Parts','Under Repair','RWC Pending','Ready for Sale','Listed','Sold'] as $s): ?>
<option <?= $car['status']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
</select>
<label>Estimated Sale Price</label><input name="estimated_sale_price" type="number" step="0.01" value="<?= $car['estimated_sale_price'] ?>">
<label>Actual Sale Price</label><input name="actual_sale_price" type="number" step="0.01" value="<?= $car['actual_sale_price'] ?>">
<label>Sold Date</label><input name="sold_date" type="date" value="<?= $car['sold_date'] ?>">
<label>Damage Notes</label><textarea name="damage_notes"><?= htmlspecialchars($car['damage_notes']) ?></textarea>
<label>Notes</label><textarea name="notes"><?= htmlspecialchars($car['notes']) ?></textarea><br><br>
<button class="btn" type="submit">Update Car</button>
</form></div><?php require '../footer.php'; ?>
