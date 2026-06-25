<?php
require '../config/db.php';
require_once '../config/auth.php';
require_permission('can_manage_sales');
$carId = filter_input(INPUT_GET, 'car_id', FILTER_VALIDATE_INT);
if (!$carId) { http_response_code(400); die('Car ID missing.'); }
require '../config/helpers.php';
require_car($pdo, $carId);
$pageTitle = 'Add Listing | CarFlip HQ';
require '../header.php';
?>
<div class="container"><h1>Add Listing / Offer</h1>
<form class="form-card" action="../actions/save-listing.php" method="POST">
<input type="hidden" name="car_id" value="<?= (int) $carId ?>">
<label>Platform</label><input name="platform" placeholder="Marketplace / Carsales / Gumtree">
<label>Listing Price</label><input name="listing_price" type="number" step="0.01">
<label>Status</label><select name="status"><option>Draft</option><option>Listed</option><option>Offer Received</option><option>Deposit Taken</option><option>Sold</option><option>Withdrawn</option></select>
<label>Listed Date</label><input name="listed_date" type="date">
<label>Buyer Name</label><input name="buyer_name">
<label>Buyer Contact</label><input name="buyer_contact">
<label>Offer Amount</label><input name="offer_amount" type="number" step="0.01">
<label>Deposit Amount</label><input name="deposit_amount" type="number" step="0.01">
<label>Notes</label><textarea name="notes"></textarea><br><br>
<button class="btn" type="submit">Save Listing</button>
<a class="btn secondary" href="car-detail.php?id=<?= (int) $carId ?>">Cancel</a>
</form></div><?php require '../footer.php'; ?>
