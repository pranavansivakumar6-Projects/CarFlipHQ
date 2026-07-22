<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';
require '../config/import-status.php';

require_permission('can_manage_imports');

$user = current_user();
$id = post_int('id', true);
$status = require_allowed_value(normalise_import_status(post_string('status', true)), import_status_steps(), 'status');
$returnTo = post_string('return_to') === 'assessment' ? 'assessment' : 'pipeline';

require_import_assessment($pdo, (int) $id);

$stmt = $pdo->prepare('SELECT status FROM import_assessments WHERE id = ?');
$stmt->execute([$id]);
$previousStatus = normalise_import_status((string) $stmt->fetchColumn());

if ($previousStatus !== $status) {
    $update = $pdo->prepare('UPDATE import_assessments SET status = ?, updated_by = ? WHERE id = ?');
    $update->execute([$status, (int) $user['id'], $id]);

    $audit = $pdo->prepare('INSERT INTO import_audit_log (assessment_id, user_id, action, details) VALUES (?, ?, ?, ?)');
    $audit->execute([(int) $id, (int) $user['id'], 'status_changed', 'Status changed from ' . $previousStatus . ' to ' . $status . '.']);
}

if ($returnTo === 'assessment') {
    redirect_to('pages/import-calculator.php?id=' . (int) $id . '&status_updated=1');
}

redirect_to('pages/import-pipeline.php?status_updated=1');
?>
