<?php
require '../config/db.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(400); die('Expense ID missing.'); }

$stmt = $pdo->prepare("SELECT expenses.*, cars.year, cars.make, cars.model FROM expenses JOIN cars ON cars.id = expenses.car_id WHERE expenses.id = ?");
$stmt->execute([$id]);
$expense = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$expense) { http_response_code(404); die('Expense not found.'); }

$pageTitle = 'Edit Expense | CarFlip HQ';
require '../header.php';
?>
<div class="container">
    <h1>Edit Expense</h1>
    <p class="small"><?= htmlspecialchars($expense['year'].' '.$expense['make'].' '.$expense['model']) ?></p>
    <form class="form-card" action="../actions/update-expense.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= (int) $expense['id'] ?>">
        <label>Category</label>
        <select name="category">
            <?php foreach(['Parts','Labour','Towing','RWC','Registration','Detailing','Paint','Other'] as $category): ?>
            <option <?= $expense['category'] === $category ? 'selected' : '' ?>><?= $category ?></option>
            <?php endforeach; ?>
        </select>
        <label>Expense Name</label><input name="expense_name" value="<?= htmlspecialchars($expense['expense_name']) ?>" required>
        <label>Amount</label><input name="amount" type="number" step="0.01" value="<?= htmlspecialchars($expense['amount']) ?>" required>
        <label>Date</label><input name="expense_date" type="date" value="<?= htmlspecialchars($expense['expense_date']) ?>">
        <?php if (!empty($expense['receipt_file'])): ?>
        <p><a href="../<?= htmlspecialchars($expense['receipt_file']) ?>" target="_blank">View current receipt</a></p>
        <?php endif; ?>
        <label>Replace Receipt / Bill Photo</label><input name="receipt_file" type="file" accept="image/*" capture="environment">
        <label>Notes</label><textarea name="notes"><?= htmlspecialchars($expense['notes']) ?></textarea><br><br>
        <button class="btn" type="submit">Update Expense</button>
        <a class="btn secondary" href="car-detail.php?id=<?= (int) $expense['car_id'] ?>">Cancel</a>
    </form>
</div>
<?php require '../footer.php'; ?>
