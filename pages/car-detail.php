<?php
require '../config/db.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(400); die('Car ID missing'); }
$stmt = $pdo->prepare("SELECT * FROM cars WHERE id = ?");
$stmt->execute([$id]);
$car = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$car) { http_response_code(404); die('Car not found'); }

$expenseStmt = $pdo->prepare("SELECT * FROM expenses WHERE car_id = ? ORDER BY expense_date DESC, created_at DESC");
$expenseStmt->execute([$id]);
$expenses = $expenseStmt->fetchAll(PDO::FETCH_ASSOC);

$taskStmt = $pdo->prepare("SELECT * FROM tasks WHERE car_id = ? ORDER BY due_date ASC, created_at DESC");
$taskStmt->execute([$id]);
$tasks = $taskStmt->fetchAll(PDO::FETCH_ASSOC);
$users = $pdo->query("SELECT name FROM users ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

$totalExpenses = array_sum(array_column($expenses, 'amount'));
$totalCost = $car['purchase_price'] + $totalExpenses;
$estimatedProfit = $car['estimated_sale_price'] - $totalCost;
$actualProfit = $car['actual_sale_price'] > 0 ? $car['actual_sale_price'] - $totalCost : null;
$pageTitle = $car['make'].' '.$car['model'].' | CarFlip HQ';
require '../header.php';
?>
<div class="container">
    <h1><?= htmlspecialchars($car['year'].' '.$car['make'].' '.$car['model']) ?></h1>
    <div class="actions">
        <a class="btn" href="add-expense.php?car_id=<?= $id ?>">+ Add Expense</a>
        <a class="btn" href="add-task.php?car_id=<?= $id ?>">+ Add Task</a>
        <a class="btn secondary" href="edit-car.php?id=<?= $id ?>">Edit Car</a>
    </div>

    <div class="grid section-title">
        <div class="card"><b>Status</b><div class="stat"><?= htmlspecialchars($car['status']) ?></div></div>
        <div class="card"><b>Total Cost</b><div class="stat">$<?= number_format($totalCost, 2) ?></div></div>
        <div class="card"><b>Estimated Profit</b><div class="profit <?= $estimatedProfit >= 0 ? 'positive' : 'negative' ?>">$<?= number_format($estimatedProfit, 2) ?></div></div>
        <div class="card"><b>Actual Profit</b><div class="profit <?= ($actualProfit ?? 0) >= 0 ? 'positive' : 'negative' ?>"><?= $actualProfit === null ? 'Not sold' : '$'.number_format($actualProfit, 2) ?></div></div>
    </div>

    <h2 class="section-title">Car Details</h2>
    <div class="card">
        <p><b>VIN:</b> <?= htmlspecialchars($car['vin']) ?></p>
        <p><b>Rego:</b> <?= htmlspecialchars($car['rego']) ?></p>
        <p><b>Odometer:</b> <?= number_format((int)$car['odometer']) ?> km</p>
        <p><b>Source:</b> <?= htmlspecialchars($car['source']) ?></p>
        <p><b>Damage:</b> <?= nl2br(htmlspecialchars($car['damage_notes'])) ?></p>
        <p><b>Notes:</b> <?= nl2br(htmlspecialchars($car['notes'])) ?></p>
    </div>

    <h2 class="section-title">Expenses</h2>
    <table>
        <tr><th>Date</th><th>Category</th><th>Name</th><th>Amount</th><th>Receipt</th><th>Notes</th><th>Action</th></tr>
        <?php foreach ($expenses as $e): ?>
        <tr>
            <td><?= htmlspecialchars($e['expense_date']) ?></td>
            <td><?= htmlspecialchars($e['category']) ?></td>
            <td><?= htmlspecialchars($e['expense_name']) ?></td>
            <td>$<?= number_format($e['amount'], 2) ?></td>
            <td>
                <?php if (!empty($e['receipt_file'])): ?>
                <a href="../<?= htmlspecialchars($e['receipt_file']) ?>" target="_blank"><img class="thumb" src="../<?= htmlspecialchars($e['receipt_file']) ?>" alt="Receipt"></a>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($e['notes']) ?></td>
            <td>
                <div class="row-actions">
                    <a class="btn secondary small-btn" href="edit-expense.php?id=<?= (int) $e['id'] ?>">Edit</a>
                    <form action="../actions/delete-expense.php" method="POST" onsubmit="return confirm('Delete this expense?');">
                        <input type="hidden" name="id" value="<?= (int) $e['id'] ?>">
                        <button class="btn danger small-btn" type="submit">Delete</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

    <h2 class="section-title">Tasks</h2>
    <table>
        <tr><th>Due</th><th>Task</th><th>Assigned</th><th>Priority</th><th>Status</th><th>Action</th></tr>
        <?php foreach ($tasks as $t): ?>
        <tr>
            <td><?= htmlspecialchars($t['due_date']) ?></td>
            <td><?= htmlspecialchars($t['task_title']) ?><div class="small"><?= htmlspecialchars($t['description']) ?></div></td>
            <td>
                <form action="../actions/update-task-assignee.php" method="POST">
                    <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                    <select class="inline-select" name="assigned_to" onchange="this.form.submit()">
                        <option value="">Unassigned</option>
                        <?php foreach ($users as $name): ?>
                        <option value="<?= htmlspecialchars($name) ?>" <?= $t['assigned_to'] === $name ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                        <?php if ($t['assigned_to'] && !in_array($t['assigned_to'], $users, true)): ?>
                        <option value="<?= htmlspecialchars($t['assigned_to']) ?>" selected><?= htmlspecialchars($t['assigned_to']) ?></option>
                        <?php endif; ?>
                    </select>
                </form>
            </td>
            <td><?= htmlspecialchars($t['priority']) ?></td>
            <td>
                <form action="../actions/update-task-status.php" method="POST">
                    <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                    <select class="inline-select" name="status" onchange="this.form.submit()">
                        <?php foreach(['To Do','In Progress','Done'] as $status): ?>
                        <option <?= $t['status'] === $status ? 'selected' : '' ?>><?= $status ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </td>
            <td>
                <?php if (!empty($t['task_photo'])): ?>
                <a href="../<?= htmlspecialchars($t['task_photo']) ?>" target="_blank"><img class="thumb" src="../<?= htmlspecialchars($t['task_photo']) ?>" alt="Task photo"></a>
                <?php endif; ?>
                <div class="row-actions">
                    <a class="btn secondary small-btn" href="edit-task.php?id=<?= (int) $t['id'] ?>">Edit</a>
                    <form action="../actions/delete-task.php" method="POST" onsubmit="return confirm('Delete this task?');">
                        <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                        <button class="btn danger small-btn" type="submit">Delete</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php require '../footer.php'; ?>
