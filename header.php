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
    <link rel="icon" type="image/svg+xml" href="<?= app_url('assets/favicon.svg') ?>">
    <link rel="stylesheet" href="<?= app_url('assets/css/style.css') ?>">
</head>
<body class="<?= !empty($publicPage) ? 'public-page' : 'app-page' ?>">
<div class="topbar">
    <a class="brand" href="<?= app_url($user ? 'index.php' : 'pages/login.php') ?>">
        <img src="<?= app_url('assets/brand-mark.svg') ?>" alt="">
        <span>CarFlip HQ</span>
    </a>
    <nav>
        <?php if ($user): ?>
        <span class="nav-user"><?= htmlspecialchars($user['name']) ?></span>
        <?php if (user_can('can_view_data')): ?>
        <a href="<?= app_url('index.php') ?>">Dashboard</a>
        <a href="<?= app_url('pages/cars.php') ?>">Cars</a>
        <?php endif; ?>
        <?php if (user_can('can_view_imports')): ?>
        <a href="<?= app_url('pages/imports.php') ?>">Japan Hub</a>
        <?php endif; ?>
        <?php if (user_can('can_manage_cars')): ?>
        <a href="<?= app_url('pages/add-car.php') ?>">Add Car</a>
        <?php endif; ?>
        <?php if (user_can('can_import_export')): ?>
        <a href="<?= app_url('pages/import-sheet.php') ?>">Import Sheet</a>
        <?php endif; ?>
        <?php if (user_can('can_view_finance')): ?>
        <a href="<?= app_url('pages/reports.php') ?>">Reports</a>
        <?php endif; ?>
        <?php if (user_can('can_use_ai')): ?>
        <a href="<?= app_url('pages/ai.php') ?>">AI Tools</a>
        <?php endif; ?>
        <a href="<?= app_url('pages/account.php') ?>">My Account</a>
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
