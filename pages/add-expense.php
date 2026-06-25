<?php
require '../config/db.php';
require_once '../config/auth.php';
require_permission('can_manage_finance');
$carId = filter_input(INPUT_GET, 'car_id', FILTER_VALIDATE_INT);
if (!$carId) { http_response_code(400); die('Car ID missing.'); }
$stmt = $pdo->prepare("SELECT id, year, make, model FROM cars WHERE id = ?");
$stmt->execute([$carId]);
$car = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$car) { http_response_code(404); die('Car not found.'); }
$users = $pdo->query("SELECT name FROM users ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$pageTitle='Add Expense | CarFlip HQ';
require '../header.php';
?>
<div class="container"><h1>Add Expense</h1>
<p class="small"><?= htmlspecialchars($car['year'].' '.$car['make'].' '.$car['model']) ?></p>
<form class="form-card" action="../actions/save-expense.php" method="POST" enctype="multipart/form-data">
<input type="hidden" name="car_id" value="<?= $carId ?>">
<label>Category</label><select name="category"><option>Parts</option><option>Labour</option><option>Towing</option><option>RWC</option><option>Registration</option><option>Detailing</option><option>Paint</option><option>Other</option></select>
<label>Expense Name</label><input name="expense_name" required>
<label>Amount</label><input name="amount" type="number" step="0.01" required>
<label>Paid By</label><select name="paid_by"><option value="">Select person</option><?php foreach ($users as $name): ?><option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option><?php endforeach; ?></select>
<label>Date</label><input name="expense_date" type="date">
<label>Receipt / Bill Photo</label><input name="receipt_file" type="file" accept="image/*" capture="environment">
<label>Notes</label><textarea name="notes"></textarea><br><br>
<button class="btn" type="submit">Save Expense</button>
</form></div><?php require '../footer.php'; ?>
