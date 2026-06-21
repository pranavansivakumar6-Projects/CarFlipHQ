<?php
require '../config/db.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(400); die('Task ID missing.'); }

$stmt = $pdo->prepare("SELECT tasks.*, cars.year, cars.make, cars.model FROM tasks JOIN cars ON cars.id = tasks.car_id WHERE tasks.id = ?");
$stmt->execute([$id]);
$task = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$task) { http_response_code(404); die('Task not found.'); }
$users = $pdo->query("SELECT name FROM users ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Edit Task | CarFlip HQ';
require '../header.php';
?>
<div class="container">
    <h1>Edit Task</h1>
    <p class="small"><?= htmlspecialchars($task['year'].' '.$task['make'].' '.$task['model']) ?></p>
    <form class="form-card" action="../actions/update-task.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
        <label>Task Title</label><input name="task_title" value="<?= htmlspecialchars($task['task_title']) ?>" required>
        <label>Description</label><textarea name="description"><?= htmlspecialchars($task['description']) ?></textarea>
        <label>Assigned To</label>
        <?php $assignedNames = array_map('trim', explode(',', $task['assigned_to'] ?? '')); ?>
        <select name="assigned_to[]" multiple size="<?= max(min(count($users), 4), 2) ?>">
            <?php foreach ($users as $name): ?>
            <option value="<?= htmlspecialchars($name) ?>" <?= in_array($name, $assignedNames, true) ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
            <?php endforeach; ?>
        </select>
        <p class="small">Hold Ctrl to select more than one person.</p>
        <label>Priority</label>
        <select name="priority">
            <?php foreach(['Low','Medium','High'] as $priority): ?>
            <option <?= $task['priority'] === $priority ? 'selected' : '' ?>><?= $priority ?></option>
            <?php endforeach; ?>
        </select>
        <label>Status</label>
        <select name="status">
            <?php foreach(['To Do','In Progress','Done'] as $status): ?>
            <option <?= $task['status'] === $status ? 'selected' : '' ?>><?= $status ?></option>
            <?php endforeach; ?>
        </select>
        <label>Hours Spent</label><input name="hours_spent" type="number" step="0.25" min="0" value="<?= htmlspecialchars($task['hours_spent'] ?? 0) ?>">
        <?php if (!empty($task['task_photo'])): ?>
        <p><a href="../<?= htmlspecialchars($task['task_photo']) ?>" target="_blank">View current task photo</a></p>
        <?php endif; ?>
        <label>Replace Task Photo</label><input name="task_photo" type="file" accept="image/*" capture="environment">
        <label>Due Date</label><input name="due_date" type="date" value="<?= htmlspecialchars($task['due_date']) ?>"><br><br>
        <button class="btn" type="submit">Update Task</button>
        <a class="btn secondary" href="car-detail.php?id=<?= (int) $task['car_id'] ?>">Cancel</a>
    </form>
</div>
<?php require '../footer.php'; ?>
