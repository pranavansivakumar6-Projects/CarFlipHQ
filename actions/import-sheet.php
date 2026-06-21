<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';
require_login();

if (empty($_FILES['sheet_file']) || $_FILES['sheet_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die('CSV upload failed.');
}

$handle = fopen($_FILES['sheet_file']['tmp_name'], 'r');
if (!$handle) { http_response_code(400); die('Could not read CSV.'); }

$headers = fgetcsv($handle);
if (!$headers) { http_response_code(400); die('CSV is empty.'); }
$headers = array_map(fn($value) => strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $value), '_')), $headers);

$carsByKey = [];
$lastCarId = null;
$imported = 0;

function row_value(array $row, string $key, $default = '') {
    return trim($row[$key] ?? $default);
}
function money_value(array $row, string $key): float {
    $value = row_value($row, $key);
    return $value === '' ? 0.0 : max((float) $value, 0);
}
function int_or_null_value(array $row, string $key): ?int {
    $value = row_value($row, $key);
    return $value === '' ? null : (int) $value;
}
function date_or_null_value(array $row, string $key): ?string {
    $value = row_value($row, $key);
    return $value === '' ? null : $value;
}

while (($values = fgetcsv($handle)) !== false) {
    $row = [];
    foreach ($headers as $index => $key) {
        $row[$key] = $values[$index] ?? '';
    }
    $type = strtolower(row_value($row, 'record_type', 'car'));
    $carKey = row_value($row, 'car_key');

    if ($type === '' || $type === 'car') {
        $status = row_value($row, 'status', 'Bought') ?: 'Bought';
        $allowedStatuses = ['Bought','Waiting for Parts','Under Repair','RWC Pending','Ready for Sale','Listed','Sold'];
        if (!in_array($status, $allowedStatuses, true)) { $status = 'Bought'; }
        $stmt = $pdo->prepare('INSERT INTO cars (make, model, year, vin, rego, odometer, source, purchase_price, purchase_date, status, estimated_sale_price, actual_sale_price, sold_date, damage_notes, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([row_value($row, 'make', 'Unknown'), row_value($row, 'model', 'Vehicle'), int_or_null_value($row, 'year'), row_value($row, 'vin'), row_value($row, 'rego'), int_or_null_value($row, 'odometer'), row_value($row, 'source'), money_value($row, 'purchase_price'), date_or_null_value($row, 'purchase_date'), $status, money_value($row, 'estimated_sale_price'), money_value($row, 'actual_sale_price'), date_or_null_value($row, 'sold_date'), row_value($row, 'damage_notes'), row_value($row, 'notes')]);
        $lastCarId = (int) $pdo->lastInsertId();
        if ($carKey !== '') { $carsByKey[$carKey] = $lastCarId; }
        $imported++;
        continue;
    }

    $carId = $carKey !== '' && isset($carsByKey[$carKey]) ? $carsByKey[$carKey] : $lastCarId;
    if (!$carId) { continue; }

    if ($type === 'expense') {
        $stmt = $pdo->prepare('INSERT INTO expenses (car_id, category, expense_name, amount, paid_by, expense_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$carId, row_value($row, 'category', 'Other') ?: 'Other', row_value($row, 'expense_name', 'Imported expense') ?: 'Imported expense', money_value($row, 'amount'), row_value($row, 'paid_by'), date_or_null_value($row, 'expense_date'), row_value($row, 'notes')]);
    } elseif ($type === 'task') {
        $status = row_value($row, 'task_status', row_value($row, 'status', 'To Do')) ?: 'To Do';
        if (!in_array($status, ['To Do','In Progress','Done'], true)) { $status = 'To Do'; }
        $priority = row_value($row, 'priority', 'Medium') ?: 'Medium';
        if (!in_array($priority, ['Low','Medium','High'], true)) { $priority = 'Medium'; }
        $stmt = $pdo->prepare('INSERT INTO tasks (car_id, task_title, description, assigned_to, priority, status, hours_spent, due_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$carId, row_value($row, 'task_title', 'Imported task') ?: 'Imported task', row_value($row, 'description'), row_value($row, 'assigned_to'), $priority, $status, money_value($row, 'hours_spent'), date_or_null_value($row, 'due_date')]);
    } elseif ($type === 'purchase_payment') {
        $stmt = $pdo->prepare('INSERT INTO car_purchase_payments (car_id, paid_by, amount, paid_date, notes) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$carId, row_value($row, 'paid_by', 'Imported'), money_value($row, 'amount'), date_or_null_value($row, 'paid_date'), row_value($row, 'notes')]);
    } elseif ($type === 'part') {
        $status = row_value($row, 'part_status', 'Needed') ?: 'Needed';
        if (!in_array($status, ['Needed','Ordered','Arrived','Installed','Cancelled'], true)) { $status = 'Needed'; }
        $partCost = row_value($row, 'cost') !== '' ? money_value($row, 'cost') : money_value($row, 'amount');
        $stmt = $pdo->prepare('INSERT INTO parts (car_id, part_name, supplier, cost, status, ordered_date, arrived_date, installed_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$carId, row_value($row, 'part_name', 'Imported part') ?: 'Imported part', row_value($row, 'supplier'), $partCost, $status, date_or_null_value($row, 'ordered_date'), date_or_null_value($row, 'arrived_date'), date_or_null_value($row, 'installed_date'), row_value($row, 'notes')]);
    } elseif ($type === 'listing') {
        $status = row_value($row, 'listing_status', 'Draft') ?: 'Draft';
        if (!in_array($status, ['Draft','Listed','Offer Received','Deposit Taken','Sold','Withdrawn'], true)) { $status = 'Draft'; }
        $stmt = $pdo->prepare('INSERT INTO sale_listings (car_id, platform, listing_price, status, listed_date, buyer_name, buyer_contact, offer_amount, deposit_amount, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$carId, row_value($row, 'platform'), money_value($row, 'listing_price'), $status, date_or_null_value($row, 'listed_date'), row_value($row, 'buyer_name'), row_value($row, 'buyer_contact'), money_value($row, 'offer_amount'), money_value($row, 'deposit_amount'), row_value($row, 'notes')]);
    }
    $imported++;
}

fclose($handle);
header('Location: ../pages/import-sheet.php?imported=' . $imported);
exit;
?>
