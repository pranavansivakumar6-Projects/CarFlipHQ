<?php
require '../config/db.php';
require_once '../config/auth.php';
require_permission('can_manage_tasks');
$carId = filter_input(INPUT_GET, 'car_id', FILTER_VALIDATE_INT);
if (!$carId) { http_response_code(400); die('Car ID missing.'); }
$stmt = $pdo->prepare("SELECT id, year, make, model FROM cars WHERE id = ?");
$stmt->execute([$carId]);
$car = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$car) { http_response_code(404); die('Car not found.'); }
$users = $pdo->query("SELECT name FROM users ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$pageTitle='Add Task | CarFlip HQ';
require '../header.php';
?>
<div class="container"><h1>Add Task</h1>
<p class="small"><?= htmlspecialchars($car['year'].' '.$car['make'].' '.$car['model']) ?></p>
<form class="form-card" action="../actions/save-task.php" method="POST" enctype="multipart/form-data">
<input type="hidden" name="car_id" value="<?= $carId ?>">
<label>Task Title</label><input name="task_title" required>
<label>Description</label><textarea name="description"></textarea>
<label>Assigned To</label>
<div class="check-grid">
<?php foreach ($users as $name): ?>
<label class="check-pill"><input type="checkbox" name="assigned_to[]" value="<?= htmlspecialchars($name) ?>"> <?= htmlspecialchars($name) ?></label>
<?php endforeach; ?>
</div>
<label>Priority</label><select name="priority"><option>Low</option><option selected>Medium</option><option>High</option></select>
<label>Status</label><select name="status"><option selected>To Do</option><option>In Progress</option><option>Done</option></select>
<label>Hours Spent</label><input name="hours_spent" type="number" step="0.25" min="0" value="0">
<label>Task Photo</label><input name="task_photo" type="file" accept="image/*" capture="environment">
<label>Due Date</label><input name="due_date" type="date"><br><br>
<button class="btn" type="submit">Save Task</button>
</form></div><?php require '../footer.php'; ?>
