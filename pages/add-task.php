<?php $carId = $_GET['car_id'] ?? ''; $pageTitle='Add Task | CarFlip HQ'; require '../header.php'; ?>
<div class="container"><h1>Add Task</h1>
<form class="form-card" action="../actions/save-task.php" method="POST">
<input type="hidden" name="car_id" value="<?= htmlspecialchars($carId) ?>">
<label>Task Title</label><input name="task_title" required>
<label>Description</label><textarea name="description"></textarea>
<label>Assigned To</label><input name="assigned_to" placeholder="Pranavan / Partner">
<label>Priority</label><select name="priority"><option>Low</option><option selected>Medium</option><option>High</option></select>
<label>Status</label><select name="status"><option selected>To Do</option><option>In Progress</option><option>Done</option></select>
<label>Due Date</label><input name="due_date" type="date"><br><br>
<button class="btn" type="submit">Save Task</button>
</form></div><?php require '../footer.php'; ?>
