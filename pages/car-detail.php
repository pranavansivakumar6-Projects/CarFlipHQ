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

$fileStmt = $pdo->prepare("SELECT * FROM car_files WHERE car_id = ? ORDER BY created_at DESC");
$fileStmt->execute([$id]);
$carFiles = $fileStmt->fetchAll(PDO::FETCH_ASSOC);

$partStmt = $pdo->prepare("SELECT * FROM parts WHERE car_id = ? ORDER BY FIELD(status, 'Needed','Ordered','Arrived','Installed','Cancelled'), created_at DESC");
$partStmt->execute([$id]);
$parts = $partStmt->fetchAll(PDO::FETCH_ASSOC);

$listingStmt = $pdo->prepare("SELECT * FROM sale_listings WHERE car_id = ? ORDER BY listed_date DESC, created_at DESC");
$listingStmt->execute([$id]);
$listings = $listingStmt->fetchAll(PDO::FETCH_ASSOC);

$purchaseStmt = $pdo->prepare("SELECT * FROM car_purchase_payments WHERE car_id = ? ORDER BY paid_date DESC, created_at DESC");
$purchaseStmt->execute([$id]);
$purchasePayments = $purchaseStmt->fetchAll(PDO::FETCH_ASSOC);

$taskStmt = $pdo->prepare("SELECT * FROM tasks WHERE car_id = ? ORDER BY due_date ASC, created_at DESC");
$taskStmt->execute([$id]);
$tasks = $taskStmt->fetchAll(PDO::FETCH_ASSOC);
$users = $pdo->query("SELECT name FROM users ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

$totalTaskHours = array_sum(array_map(fn($task) => (float) ($task['hours_spent'] ?? 0), $tasks));
$partsCost = array_sum(array_column($parts, 'cost'));
$openParts = count(array_filter($parts, fn($part) => !in_array($part['status'], ['Installed','Cancelled'], true)));
$totalExpenses = array_sum(array_column($expenses, 'amount'));
$totalCost = $car['purchase_price'] + $totalExpenses;
$estimatedProfit = $car['estimated_sale_price'] - $totalCost;
$actualProfit = $car['actual_sale_price'] > 0 ? $car['actual_sale_price'] - $totalCost : null;
$saleValue = $car['actual_sale_price'] > 0 ? (float) $car['actual_sale_price'] : (float) $car['estimated_sale_price'];
$profitForSplit = $saleValue - $totalCost;
$partnerCount = max(count($users), 1);
$equalCostShare = $totalCost / $partnerCount;
$equalProfitShare = $profitForSplit / $partnerCount;
$paidTotals = array_fill_keys($users, 0.0);
foreach ($expenses as $expense) {
    if (!empty($expense['paid_by'])) {
        $paidTotals[$expense['paid_by']] = ($paidTotals[$expense['paid_by']] ?? 0) + (float) $expense['amount'];
    }
}
$purchasePaidTotal = array_sum(array_column($purchasePayments, 'amount'));
foreach ($purchasePayments as $payment) {
    if (!empty($payment['paid_by'])) {
        $paidTotals[$payment['paid_by']] = ($paidTotals[$payment['paid_by']] ?? 0) + (float) $payment['amount'];
    }
}
$unassignedExpenses = array_sum(array_map(fn($expense) => empty($expense['paid_by']) ? (float) $expense['amount'] : 0.0, $expenses));
$unassignedPurchase = max((float) $car['purchase_price'] - $purchasePaidTotal, 0);
$settlements = [];
foreach ($paidTotals as $name => $paidAmount) {
    $settlements[$name] = $paidAmount - $equalCostShare;
}
$pageTitle = $car['make'].' '.$car['model'].' | CarFlip HQ';
require '../header.php';
?>
<div class="container">
    <h1><?= htmlspecialchars($car['year'].' '.$car['make'].' '.$car['model']) ?></h1>
    <div class="actions">
        <a class="btn secondary" href="edit-car.php?id=<?= $id ?>">Edit Car</a>
        <a class="btn secondary" href="../actions/export-car-sheet.php?id=<?= $id ?>">Download Sheet</a>
    </div>

    <div class="grid section-title">
        <div class="card"><b>Status</b><div class="stat"><?= htmlspecialchars($car['status']) ?></div></div>
        <div class="card"><b>Total Cost</b><div class="stat">$<?= number_format($totalCost, 2) ?></div></div>
        <div class="card"><b>Task Hours</b><div class="stat"><?= number_format($totalTaskHours, 2) ?></div></div>
        <div class="card"><b>Open Parts</b><div class="stat"><?= $openParts ?></div><div class="small">$<?= number_format($partsCost, 2) ?> tracked</div></div>
        <div class="card"><b>Estimated Profit</b><div class="profit <?= $estimatedProfit >= 0 ? 'positive' : 'negative' ?>">$<?= number_format($estimatedProfit, 2) ?></div></div>
        <div class="card"><b>Actual Profit</b><div class="profit <?= ($actualProfit ?? 0) >= 0 ? 'positive' : 'negative' ?>"><?= $actualProfit === null ? 'Not sold' : '$'.number_format($actualProfit, 2) ?></div></div>
    </div>

    <h2 class="section-title">Finance Split</h2>
    <div class="grid">
        <div class="card"><b>Sale Value Used</b><div class="stat">$<?= number_format($saleValue, 2) ?></div><div class="small"><?= $car['actual_sale_price'] > 0 ? 'Actual sale price' : 'Estimated sale price' ?></div></div>
        <div class="card"><b>Purchase Paid</b><div class="stat">$<?= number_format($purchasePaidTotal, 2) ?></div><div class="small">Recorded against $<?= number_format($car['purchase_price'], 2) ?> purchase price</div></div>
        <div class="card"><b>50/50 Cost Share</b><div class="stat">$<?= number_format($equalCostShare, 2) ?></div><div class="small">Per person across <?= $partnerCount ?> account<?= $partnerCount === 1 ? '' : 's' ?></div></div>
        <div class="card"><b>50/50 Profit Share</b><div class="profit <?= $equalProfitShare >= 0 ? 'positive' : 'negative' ?>">$<?= number_format($equalProfitShare, 2) ?></div><div class="small">Per person after costs</div></div>
    </div>
    <table class="section-title">
        <tr><th>Person</th><th>Paid So Far</th><th>Cost Share</th><th>Settlement</th><th>Profit Share</th></tr>
        <?php foreach ($settlements as $name => $settlement): ?>
        <tr>
            <td><?= htmlspecialchars($name) ?></td>
            <td>$<?= number_format($paidTotals[$name], 2) ?></td>
            <td>$<?= number_format($equalCostShare, 2) ?></td>
            <td class="<?= $settlement >= 0 ? 'positive' : 'negative' ?>"><?= $settlement >= 0 ? 'Owed $'.number_format($settlement, 2) : 'Pays $'.number_format(abs($settlement), 2) ?></td>
            <td>$<?= number_format($equalProfitShare, 2) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if ($unassignedExpenses > 0): ?>
        <tr><td>Unassigned expenses</td><td colspan="4">$<?= number_format($unassignedExpenses, 2) ?> needs Paid By set</td></tr>
        <?php endif; ?>
        <?php if ($unassignedPurchase > 0): ?>
        <tr><td>Unassigned purchase amount</td><td colspan="4">$<?= number_format($unassignedPurchase, 2) ?> needs purchase payment records</td></tr>
        <?php endif; ?>
    </table>

    <h2 class="section-title">Purchase Payments</h2>
    <table>
        <tr><th>Date</th><th>Paid By</th><th>Amount</th><th>Notes</th><th>Action</th></tr>
        <?php foreach ($purchasePayments as $payment): ?>
        <tr>
            <td><?= htmlspecialchars($payment['paid_date']) ?></td>
            <td><?= htmlspecialchars($payment['paid_by']) ?></td>
            <td>$<?= number_format($payment['amount'], 2) ?></td>
            <td><?= htmlspecialchars($payment['notes']) ?></td>
            <td>
                <div class="row-actions">
                    <a class="btn secondary small-btn" href="edit-purchase-payment.php?id=<?= (int) $payment['id'] ?>">Edit</a>
                    <form action="../actions/delete-purchase-payment.php" method="POST" onsubmit="return confirm('Delete this purchase payment?');">
                        <input type="hidden" name="id" value="<?= (int) $payment['id'] ?>">
                        <button class="btn danger small-btn" type="submit">Delete</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <p><a class="btn" href="add-purchase-payment.php?car_id=<?= $id ?>">+ Add Purchase Payment</a></p>

    <h2 class="section-title">Car Details</h2>
    <div class="card">
        <p><b>VIN:</b> <?= htmlspecialchars($car['vin']) ?></p>
        <p><b>Rego:</b> <?= htmlspecialchars($car['rego']) ?></p>
        <p><b>Odometer:</b> <?= number_format((int)$car['odometer']) ?> km</p>
        <p><b>Source:</b> <?= htmlspecialchars($car['source']) ?></p>
        <p><b>Damage:</b> <?= nl2br(htmlspecialchars($car['damage_notes'])) ?></p>
        <p><b>Notes:</b> <?= nl2br(htmlspecialchars($car['notes'])) ?></p>
    </div>

    <h2 class="section-title">Photos & Documents</h2>
    <div class="grid">
        <?php foreach ($carFiles as $file): ?>
        <div class="card">
            <b><?= htmlspecialchars($file['title']) ?></b>
            <div class="small"><?= htmlspecialchars($file['file_type']) ?></div>
            <?php if (preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $file['file_path'])): ?>
            <p><a href="../<?= htmlspecialchars($file['file_path']) ?>" target="_blank"><img class="media-thumb" src="../<?= htmlspecialchars($file['file_path']) ?>" alt="<?= htmlspecialchars($file['title']) ?>"></a></p>
            <?php else: ?>
            <p><a class="btn secondary small-btn" href="../<?= htmlspecialchars($file['file_path']) ?>" target="_blank">Open Document</a></p>
            <?php endif; ?>
            <p class="small"><?= htmlspecialchars($file['notes']) ?></p>
            <form action="../actions/delete-car-file.php" method="POST" onsubmit="return confirm('Delete this file?');">
                <input type="hidden" name="id" value="<?= (int) $file['id'] ?>">
                <button class="btn danger small-btn" type="submit">Delete</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <p><a class="btn" href="add-car-file.php?car_id=<?= $id ?>">+ Add Photo / Document</a></p>

    <h2 class="section-title">Parts</h2>
    <table>
        <tr><th>Part</th><th>Supplier</th><th>Cost</th><th>Status</th><th>Dates</th><th>Action</th></tr>
        <?php foreach ($parts as $part): ?>
        <tr>
            <td><?= htmlspecialchars($part['part_name']) ?><div class="small"><?= htmlspecialchars($part['notes']) ?></div></td>
            <td><?= htmlspecialchars($part['supplier']) ?></td>
            <td>$<?= number_format($part['cost'], 2) ?></td>
            <td><span class="badge"><?= htmlspecialchars($part['status']) ?></span></td>
            <td class="small">Ordered <?= htmlspecialchars($part['ordered_date']) ?><br>Arrived <?= htmlspecialchars($part['arrived_date']) ?><br>Installed <?= htmlspecialchars($part['installed_date']) ?></td>
            <td><div class="row-actions"><a class="btn secondary small-btn" href="edit-part.php?id=<?= (int) $part['id'] ?>">Edit</a><form action="../actions/delete-part.php" method="POST" onsubmit="return confirm('Delete this part?');"><input type="hidden" name="id" value="<?= (int) $part['id'] ?>"><button class="btn danger small-btn" type="submit">Delete</button></form></div></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <p><a class="btn" href="add-part.php?car_id=<?= $id ?>">+ Add Part</a></p>

    <h2 class="section-title">Sale Listings & Offers</h2>
    <table>
        <tr><th>Platform</th><th>Price</th><th>Status</th><th>Buyer / Offer</th><th>Notes</th><th>Action</th></tr>
        <?php foreach ($listings as $listing): ?>
        <tr>
            <td><?= htmlspecialchars($listing['platform']) ?><div class="small"><?= htmlspecialchars($listing['listed_date']) ?></div></td>
            <td>$<?= number_format($listing['listing_price'], 2) ?></td>
            <td><span class="badge"><?= htmlspecialchars($listing['status']) ?></span></td>
            <td><?= htmlspecialchars($listing['buyer_name']) ?><div class="small"><?= htmlspecialchars($listing['buyer_contact']) ?><br>Offer $<?= number_format($listing['offer_amount'], 2) ?> / Deposit $<?= number_format($listing['deposit_amount'], 2) ?></div></td>
            <td><?= htmlspecialchars($listing['notes']) ?></td>
            <td><div class="row-actions"><a class="btn secondary small-btn" href="edit-listing.php?id=<?= (int) $listing['id'] ?>">Edit</a><form action="../actions/delete-listing.php" method="POST" onsubmit="return confirm('Delete this listing?');"><input type="hidden" name="id" value="<?= (int) $listing['id'] ?>"><button class="btn danger small-btn" type="submit">Delete</button></form></div></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <p><a class="btn" href="add-listing.php?car_id=<?= $id ?>">+ Add Listing / Offer</a></p>

    <h2 class="section-title">Expenses</h2>
    <table>
        <tr><th>Date</th><th>Category</th><th>Name</th><th>Amount</th><th>Paid By</th><th>Receipt</th><th>Notes</th><th>Action</th></tr>
        <?php foreach ($expenses as $e): ?>
        <tr>
            <td><?= htmlspecialchars($e['expense_date']) ?></td>
            <td><?= htmlspecialchars($e['category']) ?></td>
            <td><?= htmlspecialchars($e['expense_name']) ?></td>
            <td>$<?= number_format($e['amount'], 2) ?></td>
            <td><?= htmlspecialchars($e['paid_by'] ?? '') ?></td>
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
    <p><a class="btn" href="add-expense.php?car_id=<?= $id ?>">+ Add Expense</a></p>

    <h2 class="section-title">Tasks</h2>
    <table>
        <tr><th>Due</th><th>Task</th><th>Assigned</th><th>Hours</th><th>Priority</th><th>Status</th><th>Action</th></tr>
        <?php foreach ($tasks as $t): ?>
        <?php $assignedNames = array_map('trim', explode(',', $t['assigned_to'] ?? '')); ?>
        <tr>
            <td><?= htmlspecialchars($t['due_date']) ?></td>
            <td><?= htmlspecialchars($t['task_title']) ?><div class="small"><?= htmlspecialchars($t['description']) ?></div></td>
            <td>
                <form action="../actions/update-task-assignee.php" method="POST">
                    <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                    <div class="check-grid compact-checks">
                        <?php foreach ($users as $name): ?>
                        <label class="check-pill"><input type="checkbox" name="assigned_to[]" value="<?= htmlspecialchars($name) ?>" <?= in_array($name, $assignedNames, true) ? 'checked' : '' ?>> <?= htmlspecialchars($name) ?></label>
                        <?php endforeach; ?>
                    </div>
                    <button class="btn secondary small-btn" type="submit">Save</button>
                </form>
            </td>
            <td><?= number_format((float) ($t['hours_spent'] ?? 0), 2) ?></td>
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
    <p><a class="btn" href="add-task.php?car_id=<?= $id ?>">+ Add Task</a></p>
</div>
<?php require '../footer.php'; ?>
