<?php
require '../config/db.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(400); die('Part ID missing.'); }
$stmt = $pdo->prepare('SELECT * FROM parts WHERE id = ?');
$stmt->execute([$id]);
$part = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$part) { http_response_code(404); die('Part not found.'); }
$pageTitle = 'Edit Part | CarFlip HQ';
require '../header.php';
?>
<div class="container"><h1>Edit Part</h1>
<form class="form-card" action="../actions/update-part.php" method="POST">
<input type="hidden" name="id" value="<?= (int) $part['id'] ?>">
<label>Part Name</label><input name="part_name" value="<?= htmlspecialchars($part['part_name']) ?>" required>
<label>Supplier</label><input name="supplier" value="<?= htmlspecialchars($part['supplier']) ?>">
<label>Cost</label><input name="cost" type="number" step="0.01" value="<?= htmlspecialchars($part['cost']) ?>">
<label>Status</label><select name="status"><?php foreach(['Needed','Ordered','Arrived','Installed','Cancelled'] as $status): ?><option <?= $part['status'] === $status ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select>
<label>Ordered Date</label><input name="ordered_date" type="date" value="<?= htmlspecialchars($part['ordered_date']) ?>">
<label>Arrived Date</label><input name="arrived_date" type="date" value="<?= htmlspecialchars($part['arrived_date']) ?>">
<label>Installed Date</label><input name="installed_date" type="date" value="<?= htmlspecialchars($part['installed_date']) ?>">
<label>Notes</label><textarea name="notes"><?= htmlspecialchars($part['notes']) ?></textarea><br><br>
<button class="btn" type="submit">Update Part</button>
<a class="btn secondary" href="car-detail.php?id=<?= (int) $part['car_id'] ?>">Cancel</a>
</form></div><?php require '../footer.php'; ?>
