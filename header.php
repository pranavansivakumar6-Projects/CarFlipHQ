<?php if (!isset($pageTitle)) { $pageTitle = 'CarFlip HQ'; } ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="/carfliphq/assets/css/style.css">
</head>
<body>
<div class="topbar">
    <div class="brand">CarFlip HQ</div>
    <nav>
        <a href="/carfliphq/index.php">Dashboard</a>
        <a href="/carfliphq/pages/cars.php">Cars</a>
        <a href="/carfliphq/pages/add-car.php">Add Car</a>
    </nav>
</div>
