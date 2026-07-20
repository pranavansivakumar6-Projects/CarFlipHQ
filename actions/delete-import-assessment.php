<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';

require_permission('can_manage_imports');

$id = post_int('id', true);
require_import_assessment($pdo, $id);

$stmt = $pdo->prepare('DELETE FROM import_assessments WHERE id = ?');
$stmt->execute([$id]);

redirect_to('pages/imports.php?deleted=1');
?>
