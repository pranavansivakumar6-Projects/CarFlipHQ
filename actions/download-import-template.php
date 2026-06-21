<?php
require '../config/auth.php';
require_login();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="carfliphq-import-template.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['record_type','car_key','make','model','year','vin','rego','odometer','source','purchase_price','purchase_date','status','estimated_sale_price','actual_sale_price','sold_date','damage_notes','notes','category','expense_name','amount','paid_by','expense_date','task_title','description','assigned_to','priority','task_status','hours_spent','due_date','paid_date','part_name','supplier','part_status','ordered_date','arrived_date','installed_date','platform','listing_price','listing_status','listed_date','buyer_name','buyer_contact','offer_amount','deposit_amount']);
fputcsv($out, ['car','mazda-323','Mazda','323','2003','JM0BJ10P200240952','N/A','134000','Auction','800','2026-06-01','Bought','3800','','','Front collision','','','','','','','','','','','','','','','','','','','','','','','','','','','','']);
fputcsv($out, ['expense','mazda-323','','','','','','','','','','','','','','','','Parts','Bonnet','98','pranavan sivakumar','2026-06-14','','','','','','','','','','','','','','','','','','','','','','']);
fputcsv($out, ['task','mazda-323','','','','','','','','','','','','','','','','','','','','','Bonnet removal','Remove bonnet','pranavan sivakumar, Pubudu Aiya','High','Done','4','2026-06-14','','','','','','','','','','','','','','']);
fclose($out);
exit;
?>
