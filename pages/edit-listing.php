<?php
require '../config/db.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(400); die('Listing ID missing.'); }
$stmt = $pdo->prepare('SELECT * FROM sale_listings WHERE id = ?');
$stmt->execute([$id]);
$listing = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$listing) { http_response_code(404); die('Listing not found.'); }
$pageTitle = 'Edit Listing | CarFlip HQ';
require '../header.php';
?>
<div class="container"><h1>Edit Listing / Offer</h1>
<form class="form-card" action="../actions/update-listing.php" method="POST">
<input type="hidden" name="id" value="<?= (int) $listing['id'] ?>">
<label>Platform</label><input name="platform" value="<?= htmlspecialchars($listing['platform']) ?>">
<label>Listing Price</label><input name="listing_price" type="number" step="0.01" value="<?= htmlspecialchars($listing['listing_price']) ?>">
<label>Status</label><select name="status"><?php foreach(['Draft','Listed','Offer Received','Deposit Taken','Sold','Withdrawn'] as $status): ?><option <?= $listing['status'] === $status ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select>
<label>Listed Date</label><input name="listed_date" type="date" value="<?= htmlspecialchars($listing['listed_date']) ?>">
<label>Buyer Name</label><input name="buyer_name" value="<?= htmlspecialchars($listing['buyer_name']) ?>">
<label>Buyer Contact</label><input name="buyer_contact" value="<?= htmlspecialchars($listing['buyer_contact']) ?>">
<label>Offer Amount</label><input name="offer_amount" type="number" step="0.01" value="<?= htmlspecialchars($listing['offer_amount']) ?>">
<label>Deposit Amount</label><input name="deposit_amount" type="number" step="0.01" value="<?= htmlspecialchars($listing['deposit_amount']) ?>">
<label>Notes</label><textarea name="notes"><?= htmlspecialchars($listing['notes']) ?></textarea><br><br>
<button class="btn" type="submit">Update Listing</button>
<a class="btn secondary" href="car-detail.php?id=<?= (int) $listing['car_id'] ?>">Cancel</a>
</form></div><?php require '../footer.php'; ?>
