<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';
require_once '../config/status.php';
require_permission('can_import_export');

if (empty($_FILES['sheet_file']) || $_FILES['sheet_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die('CSV upload failed.');
}

$handle = fopen($_FILES['sheet_file']['tmp_name'], 'r');
if (!$handle) { http_response_code(400); die('Could not read CSV.'); }

function row_value(array $row, string $key, $default = '') {
    return trim($row[$key] ?? $default);
}
function parse_money($value): float {
    $value = trim((string) $value);
    if ($value === '') { return 0.0; }
    $value = str_replace(['$', ',', ' '], '', $value);
    if (str_contains($value, '+')) {
        return 0.0;
    }
    return is_numeric($value) ? max((float) $value, 0.0) : 0.0;
}
function money_value(array $row, string $key): float {
    return parse_money(row_value($row, $key));
}
function int_or_null_value(array $row, string $key): ?int {
    $value = row_value($row, $key);
    return $value === '' ? null : (int) $value;
}
function date_or_null_value(array $row, string $key): ?string {
    $value = row_value($row, $key);
    return parse_date_value($value);
}
function parse_date_value($value): ?string {
    $value = trim((string) $value);
    if ($value === '') { return null; }
    foreach (['Y-m-d', 'd.m.Y', 'd/m/Y', 'm/d/Y'] as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date && $date->format($format) === $value) {
            return $date->format('Y-m-d');
        }
    }
    return null;
}
function normalise_header($value): string {
    return strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', (string) $value), '_'));
}
function payer_name($value): string {
    $name = trim(preg_replace('/^paid\s+by\s+/i', '', (string) $value));
    return $name === '' ? 'Imported' : ucwords(strtolower($name));
}
function resolve_payer_name(PDO $pdo, string $name): string {
    $stmt = $pdo->prepare('SELECT name FROM users WHERE LOWER(name) = LOWER(?) LIMIT 1');
    $stmt->execute([$name]);
    $exact = $stmt->fetchColumn();
    if ($exact) { return $exact; }

    $stmt = $pdo->prepare('SELECT name FROM users WHERE LOWER(name) LIKE LOWER(?) ORDER BY LENGTH(name) ASC LIMIT 1');
    $stmt->execute([$name . '%']);
    $prefix = $stmt->fetchColumn();
    return $prefix ?: $name;
}
function infer_car_from_filename(string $filename): array {
    $base = pathinfo($filename, PATHINFO_FILENAME);
    $base = preg_replace('/\s*-\s*sheet\d*$/i', '', $base);
    $base = preg_replace('/\b(expense|expenses|sheet)\b/i', '', $base);
    $base = trim(preg_replace('/\s+/', ' ', $base));

    $year = null;
    if (preg_match('/\b((?:19|20)\d{2})\b/', $base, $match)) {
        $year = (int) $match[1];
        $base = trim(str_replace($match[1], '', $base));
    }

    $parts = preg_split('/\s+/', $base) ?: [];
    $make = $parts[0] ?? 'Imported';
    $model = trim(implode(' ', array_slice($parts, 1)));
    if ($model === '') { $model = 'Imported'; }

    return [$make, $model, $year];
}
function row_has_value(array $row): bool {
    foreach ($row as $value) {
        if (trim((string) $value) !== '') { return true; }
    }
    return false;
}
function import_paid_by_sheet(PDO $pdo, $handle, array $headerRow, string $filename): int {
    $payers = [];
    foreach ($headerRow as $index => $label) {
        if (stripos((string) $label, 'paid by') !== false) {
            $payers[$index] = resolve_payer_name($pdo, payer_name($label));
        }
    }
    if (!$payers) {
        http_response_code(400);
        die('This CSV does not match the CarFlip HQ template or the paid-by expense sheet format.');
    }

    [$make, $model, $year] = infer_car_from_filename($filename);
    $stmt = $pdo->prepare('INSERT INTO cars (make, model, year, color, body_type, source, purchase_price, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$make, $model, $year, '', '', 'Spreadsheet import', 0, 'Sold', 'Imported from ' . $filename]);
    $carId = (int) $pdo->lastInsertId();
    if ((current_user()['role'] ?? '') !== 'admin') {
        sync_car_user_access($pdo, $carId, [(int) current_user()['id']]);
    }

    $imported = 1;
    $purchaseSet = false;
    while (($values = fgetcsv($handle)) !== false) {
        if (!row_has_value($values)) { continue; }

        $first = trim($values[0] ?? '');
        $second = trim($values[1] ?? '');
        if (stripos($first . ' ' . $second, 'total spent') !== false) {
            continue;
        }
        $date = parse_date_value($first);
        $payerAmounts = [];
        $payerTotal = 0.0;
        foreach ($payers as $index => $payer) {
            $amount = parse_money($values[$index] ?? '');
            if ($amount > 0) {
                $payerAmounts[$payer] = $amount;
                $payerTotal += $amount;
            }
        }
        $totalAmount = parse_money($values[2] ?? '');
        $amount = $payerTotal > 0 ? $payerTotal : $totalAmount;
        if ($amount <= 0) { continue; }

        $isPurchase = stripos($first, 'paid price') !== false || stripos($first . ' ' . $second, 'purchase') !== false;
        if ($isPurchase && !$purchaseSet) {
            $purchaseAmount = $totalAmount > 0 ? $totalAmount : $payerTotal;
            $update = $pdo->prepare('UPDATE cars SET purchase_price = ? WHERE id = ?');
            $update->execute([$purchaseAmount, $carId]);
            foreach ($payerAmounts as $payer => $payerAmount) {
                $stmt = $pdo->prepare('INSERT INTO car_purchase_payments (car_id, paid_by, amount, paid_date, notes) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$carId, $payer, $payerAmount, $date, 'Imported purchase payment']);
                $imported++;
            }
            $purchaseSet = true;
            continue;
        }

        $category = $date ? 'Parts' : ($first !== '' ? $first : 'Other');
        $name = $second !== '' ? $second : ($first !== '' ? $first : 'Imported expense');
        if ($date && $second === '') { $name = 'Imported expense'; }
        $notes = 'Imported from ' . $filename;

        if ($payerAmounts) {
            foreach ($payerAmounts as $payer => $payerAmount) {
                $stmt = $pdo->prepare('INSERT INTO expenses (car_id, category, expense_name, amount, paid_by, expense_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$carId, $category, $name, $payerAmount, $payer, $date, $notes]);
                $imported++;
            }
        } else {
            $stmt = $pdo->prepare('INSERT INTO expenses (car_id, category, expense_name, amount, paid_by, expense_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$carId, $category, $name, $totalAmount, '', $date, $notes]);
            $imported++;
        }
    }

    return $imported;
}

$rawHeaders = fgetcsv($handle);
if (!$rawHeaders) { http_response_code(400); die('CSV is empty.'); }
$headers = array_map('normalise_header', $rawHeaders);

if (!in_array('record_type', $headers, true)) {
    $imported = import_paid_by_sheet($pdo, $handle, $rawHeaders, $_FILES['sheet_file']['name']);
    fclose($handle);
    header('Location: ../pages/import-sheet.php?imported=' . $imported);
    exit;
}

$carsByKey = [];
$lastCarId = null;
$imported = 0;

while (($values = fgetcsv($handle)) !== false) {
    $row = [];
    foreach ($headers as $index => $key) {
        $row[$key] = $values[$index] ?? '';
    }
    $type = strtolower(row_value($row, 'record_type', 'car'));
    $carKey = row_value($row, 'car_key');

    if ($type === '' || $type === 'car') {
        $status = normalise_car_status(row_value($row, 'status', 'Bought') ?: 'Bought');
        if (!in_array($status, allowed_car_statuses(), true)) { $status = 'Bought'; }
        $stmt = $pdo->prepare('INSERT INTO cars (make, model, year, color, body_type, vin, rego, odometer, source, purchase_price, purchase_date, status, estimated_sale_price, actual_sale_price, sold_date, damage_notes, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([row_value($row, 'make', 'Unknown'), row_value($row, 'model', 'Vehicle'), int_or_null_value($row, 'year'), row_value($row, 'color'), row_value($row, 'body_type'), row_value($row, 'vin'), row_value($row, 'rego'), int_or_null_value($row, 'odometer'), row_value($row, 'source'), money_value($row, 'purchase_price'), date_or_null_value($row, 'purchase_date'), $status, money_value($row, 'estimated_sale_price'), money_value($row, 'actual_sale_price'), date_or_null_value($row, 'sold_date'), row_value($row, 'damage_notes'), row_value($row, 'notes')]);
        $lastCarId = (int) $pdo->lastInsertId();
        if ((current_user()['role'] ?? '') !== 'admin') {
            sync_car_user_access($pdo, $lastCarId, [(int) current_user()['id']]);
        }
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
