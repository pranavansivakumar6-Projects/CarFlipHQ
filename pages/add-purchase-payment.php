<?php
require '../config/db.php';
require_once '../config/auth.php';

require_permission('can_manage_finance');
$carId = filter_input(INPUT_GET, 'car_id', FILTER_VALIDATE_INT);
if (!$carId) { http_response_code(400); die('Car ID missing.'); }

$stmt = $pdo->prepare("SELECT id, year, make, model, purchase_price FROM cars WHERE id = ?");
$stmt->execute([$carId]);
$car = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$car) { http_response_code(404); die('Car not found.'); }

$users = $pdo->query("SELECT name FROM users ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$pageTitle = 'Add Purchase Payment | CarFlip HQ';
require '../header.php';
?>
<div class="container">
    <h1>Add Purchase Payment</h1>
    <p class="small"><?= htmlspecialchars($car['year'].' '.$car['make'].' '.$car['model']) ?> purchase price: $<?= number_format($car['purchase_price'], 2) ?></p>
    <form class="form-card" action="../actions/save-purchase-payment.php" method="POST">
        <input type="hidden" name="car_id" value="<?= (int) $carId ?>">
        <label>Paid By</label>
        <select name="paid_by" required>
            <option value="">Select person</option>
            <?php foreach ($users as $name): ?>
            <option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
            <?php endforeach; ?>
        </select>
        <label>Amount</label><input name="amount" type="number" step="0.01" required>
        <label>Date</label><input name="paid_date" type="date">
        <label>Notes</label><textarea name="notes"></textarea><br><br>
        <button class="btn" type="submit">Save Payment</button>
        <a class="btn secondary" href="car-detail.php?id=<?= (int) $carId ?>">Cancel</a>
    </form>
</div>
<?php require '../footer.php'; ?>
