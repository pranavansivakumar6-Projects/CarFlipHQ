<?php
require '../config/db.php';
require_once '../config/auth.php';
require_permission('can_manage_tasks');
$carId = filter_input(INPUT_GET, 'car_id', FILTER_VALIDATE_INT);
if (!$carId) { http_response_code(400); die('Car ID missing.'); }
require '../config/helpers.php';
require_car($pdo, $carId);
$pageTitle = 'Add Part | CarFlip HQ';
require '../header.php';
?>
<div class="container"><h1>Add Part</h1>
<form class="form-card" action="../actions/save-part.php" method="POST">
<input type="hidden" name="car_id" value="<?= (int) $carId ?>">
<label>Part Name</label><input name="part_name" required>
<label>Supplier</label><input name="supplier">
<label>Cost</label><input name="cost" type="number" step="0.01">
<label>Status</label><select name="status"><option>Needed</option><option>Ordered</option><option>Arrived</option><option>Installed</option><option>Cancelled</option></select>
<label>Ordered Date</label><input name="ordered_date" type="date">
<label>Arrived Date</label><input name="arrived_date" type="date">
<label>Installed Date</label><input name="installed_date" type="date">
<label>Notes</label><textarea name="notes"></textarea><br><br>
<button class="btn" type="submit">Save Part</button>
<a class="btn secondary" href="car-detail.php?id=<?= (int) $carId ?>">Cancel</a>
</form></div><?php require '../footer.php'; ?>
