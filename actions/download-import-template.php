<?php
require '../config/auth.php';
require_login();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="carfliphq-import-template.csv"');

$out = fopen('php://output', 'w');
$header = ['record_type','car_key','make','model','year','color','body_type','vin','rego','odometer','source','purchase_price','purchase_date','status','estimated_sale_price','actual_sale_price','sold_date','damage_notes','notes','category','expense_name','amount','paid_by','expense_date','task_title','description','assigned_to','priority','task_status','hours_spent','due_date','paid_date','part_name','supplier','part_status','ordered_date','arrived_date','installed_date','platform','listing_price','listing_status','listed_date','buyer_name','buyer_contact','offer_amount','deposit_amount'];
fputcsv($out, $header);

$row = array_fill_keys($header, '');
$row = array_merge($row, ['record_type' => 'car', 'car_key' => 'mazda-323', 'make' => 'Mazda', 'model' => '323', 'year' => '2003', 'color' => 'Silver', 'body_type' => 'Sedan', 'vin' => 'JM0BJ10P200240952', 'rego' => 'N/A', 'odometer' => '134000', 'source' => 'Auction', 'purchase_price' => '800', 'purchase_date' => '2026-06-01', 'status' => 'Bought', 'estimated_sale_price' => '3800', 'damage_notes' => 'Front collision']);
fputcsv($out, array_values($row));

$row = array_fill_keys($header, '');
$row = array_merge($row, ['record_type' => 'expense', 'car_key' => 'mazda-323', 'category' => 'Parts', 'expense_name' => 'Bonnet', 'amount' => '98', 'paid_by' => 'pranavan sivakumar', 'expense_date' => '2026-06-14']);
fputcsv($out, array_values($row));

$row = array_fill_keys($header, '');
$row = array_merge($row, ['record_type' => 'task', 'car_key' => 'mazda-323', 'task_title' => 'Bonnet removal', 'description' => 'Remove bonnet', 'assigned_to' => 'pranavan sivakumar, Pubudu Aiya', 'priority' => 'High', 'task_status' => 'Done', 'hours_spent' => '4', 'due_date' => '2026-06-14']);
fputcsv($out, array_values($row));
fclose($out);
exit;
?>
