<?php
require '../config/db.php';
require_once '../config/auth.php';
require_permission('can_manage_cars');
$carId = filter_input(INPUT_GET, 'car_id', FILTER_VALIDATE_INT);
if (!$carId) { http_response_code(400); die('Car ID missing.'); }
$stmt = $pdo->prepare("SELECT id, year, make, model FROM cars WHERE id = ?");
$stmt->execute([$carId]);
$car = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$car) { http_response_code(404); die('Car not found.'); }
$pageTitle = 'Add File | CarFlip HQ';
require '../header.php';
?>
<div class="container">
    <h1>Add Photo / Document</h1>
    <p class="small"><?= htmlspecialchars($car['year'].' '.$car['make'].' '.$car['model']) ?></p>
    <form class="form-card" action="../actions/save-car-file.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="car_id" value="<?= (int) $carId ?>">
        <label>Type</label><select name="file_type"><option value="photo">Photo</option><option value="document">Document</option></select>
        <label>Title</label><input name="title" required>
        <label>File</label><input name="car_file" type="file" accept="image/*,application/pdf" capture="environment" required>
        <label>Notes</label><textarea name="notes"></textarea><br><br>
        <button class="btn" type="submit">Save File</button>
        <a class="btn secondary" href="car-detail.php?id=<?= (int) $carId ?>">Cancel</a>
    </form>
</div>
<?php require '../footer.php'; ?>
