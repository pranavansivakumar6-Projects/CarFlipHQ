<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';
require_permission('can_import_export');

$carId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$carId) { http_response_code(400); die('Car ID missing.'); }
require_car($pdo, $carId);

$stmt = $pdo->prepare('SELECT * FROM cars WHERE id = ?');
$stmt->execute([$carId]);
$car = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$car) { http_response_code(404); die('Car not found.'); }

$slug = preg_replace('/[^a-z0-9]+/i', '-', trim($car['year'].'-'.$car['make'].'-'.$car['model']));
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="carfliphq-'.$slug.'.csv"');

$out = fopen('php://output', 'w');
$header = ['record_type','car_key','make','model','year','color','body_type','vin','rego','odometer','source','purchase_price','purchase_date','status','estimated_sale_price','actual_sale_price','sold_date','damage_notes','notes','category','expense_name','amount','paid_by','expense_date','task_title','description','assigned_to','priority','task_status','hours_spent','due_date','paid_date','part_name','supplier','part_status','ordered_date','arrived_date','installed_date','platform','listing_price','listing_status','listed_date','buyer_name','buyer_contact','offer_amount','deposit_amount'];
fputcsv($out, $header);

$carKey = 'car-'.$car['id'];
$carData = array_fill_keys($header, '');
$carData['record_type'] = 'car';
$carData['car_key'] = $carKey;
foreach ($car as $key => $value) {
    if (array_key_exists($key, $carData)) { $carData[$key] = $value; }
}
fputcsv($out, array_values($carData));

$queries = [
    'purchase_payment' => 'SELECT * FROM car_purchase_payments WHERE car_id = ? ORDER BY paid_date, id',
    'expense' => 'SELECT * FROM expenses WHERE car_id = ? ORDER BY expense_date, id',
    'task' => 'SELECT * FROM tasks WHERE car_id = ? ORDER BY due_date, id',
    'part' => 'SELECT * FROM parts WHERE car_id = ? ORDER BY id',
    'listing' => 'SELECT * FROM sale_listings WHERE car_id = ? ORDER BY listed_date, id',
];

foreach ($queries as $type => $sql) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$carId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $data = array_fill_keys($header, '');
        $data['record_type'] = $type;
        $data['car_key'] = $carKey;
        foreach ($row as $key => $value) {
            if ($key === 'status' && $type === 'task') { $data['task_status'] = $value; continue; }
            if ($key === 'status' && $type === 'part') { $data['part_status'] = $value; continue; }
            if ($key === 'status' && $type === 'listing') { $data['listing_status'] = $value; continue; }
            if (array_key_exists($key, $data)) { $data[$key] = $value; }
        }
        fputcsv($out, array_values($data));
    }
}

fclose($out);
exit;
?>
