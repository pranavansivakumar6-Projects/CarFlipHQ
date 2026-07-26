<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';

require_permission('can_manage_imports');

$id = post_int('id', true);
require_import_assessment($pdo, $id);

$stmt = $pdo->prepare('UPDATE import_assessments SET archived_at = NOW(), archived_by = ?, updated_by = ? WHERE id = ?');
$stmt->execute([(int) current_user()['id'], (int) current_user()['id'], $id]);

$audit = $pdo->prepare('INSERT INTO import_audit_log (assessment_id, user_id, action, details) VALUES (?, ?, ?, ?)');
$audit->execute([$id, (int) current_user()['id'], 'archive', 'Import assessment archived.']);

redirect_to('pages/imports.php?archived=1');
?>
