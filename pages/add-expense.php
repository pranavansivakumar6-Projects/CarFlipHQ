<?php $carId = $_GET['car_id'] ?? ''; $pageTitle='Add Expense | CarFlip HQ'; require '../header.php'; ?>
<div class="container"><h1>Add Expense</h1>
<form class="form-card" action="../actions/save-expense.php" method="POST">
<input type="hidden" name="car_id" value="<?= htmlspecialchars($carId) ?>">
<label>Category</label><select name="category"><option>Parts</option><option>Labour</option><option>Towing</option><option>RWC</option><option>Registration</option><option>Detailing</option><option>Paint</option><option>Other</option></select>
<label>Expense Name</label><input name="expense_name" required>
<label>Amount</label><input name="amount" type="number" step="0.01" required>
<label>Date</label><input name="expense_date" type="date">
<label>Notes</label><textarea name="notes"></textarea><br><br>
<button class="btn" type="submit">Save Expense</button>
</form></div><?php require '../footer.php'; ?>
