<?php
require '../config/db.php';
require_once '../config/auth.php';

require_permission('can_manage_finance');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(400); die('Purchase payment ID missing.'); }

$stmt = $pdo->prepare("SELECT car_purchase_payments.*, cars.year, cars.make, cars.model, cars.purchase_price FROM car_purchase_payments JOIN cars ON cars.id = car_purchase_payments.car_id WHERE car_purchase_payments.id = ?");
$stmt->execute([$id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$payment) { http_response_code(404); die('Purchase payment not found.'); }

$users = $pdo->query("SELECT name FROM users ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$pageTitle = 'Edit Purchase Payment | CarFlip HQ';
require '../header.php';
?>
<div class="container">
    <h1>Edit Purchase Payment</h1>
    <p class="small"><?= htmlspecialchars($payment['year'].' '.$payment['make'].' '.$payment['model']) ?> purchase price: $<?= number_format($payment['purchase_price'], 2) ?></p>
    <form class="form-card" action="../actions/update-purchase-payment.php" method="POST">
        <input type="hidden" name="id" value="<?= (int) $payment['id'] ?>">
        <label>Paid By</label>
        <select name="paid_by" required>
            <option value="">Select person</option>
            <?php foreach ($users as $name): ?>
            <option value="<?= htmlspecialchars($name) ?>" <?= $payment['paid_by'] === $name ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
            <?php endforeach; ?>
            <?php if ($payment['paid_by'] && !in_array($payment['paid_by'], $users, true)): ?>
            <option value="<?= htmlspecialchars($payment['paid_by']) ?>" selected><?= htmlspecialchars($payment['paid_by']) ?></option>
            <?php endif; ?>
        </select>
        <label>Amount</label><input name="amount" type="number" step="0.01" value="<?= htmlspecialchars($payment['amount']) ?>" required>
        <label>Date</label><input name="paid_date" type="date" value="<?= htmlspecialchars($payment['paid_date']) ?>">
        <label>Notes</label><textarea name="notes"><?= htmlspecialchars($payment['notes']) ?></textarea><br><br>
        <button class="btn" type="submit">Update Payment</button>
        <a class="btn secondary" href="car-detail.php?id=<?= (int) $payment['car_id'] ?>">Cancel</a>
    </form>
</div>
<?php require '../footer.php'; ?>
