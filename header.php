<?php
require_once __DIR__ . '/config/auth.php';
if (!isset($pageTitle)) { $pageTitle = 'CarFlip HQ'; }
if (empty($publicPage)) { require_login(); }
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= app_url('assets/css/style.css') ?>">
</head>
<body>
<div class="topbar">
    <div class="brand">CarFlip HQ</div>
    <nav>
        <?php if ($user): ?>
        <span class="nav-user"><?= htmlspecialchars($user['name']) ?></span>
        <a href="<?= app_url('index.php') ?>">Dashboard</a>
        <a href="<?= app_url('pages/cars.php') ?>">Cars</a>
        <a href="<?= app_url('pages/add-car.php') ?>">Add Car</a>
        <a href="<?= app_url('pages/import-sheet.php') ?>">Import Sheet</a>
        <a href="<?= app_url('pages/reports.php') ?>">Reports</a>
        <a href="<?= app_url('pages/ai.php') ?>">AI Tools</a>
        <?php if (($user['role'] ?? '') === 'admin'): ?>
        <a href="<?= app_url('pages/users.php') ?>">Users</a>
        <a href="<?= app_url('pages/restore-backup.php') ?>">Restore</a>
        <?php endif; ?>
        <a href="<?= app_url('actions/logout.php') ?>">Logout</a>
        <?php else: ?>
        <a href="<?= app_url('pages/login.php') ?>">Login</a>
        <?php endif; ?>
    </nav>
</div>
<main class="app-main">
