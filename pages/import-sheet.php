<?php
$pageTitle = 'Import Sheet | CarFlip HQ';
require '../header.php';
?>
<div class="container">
    <h1>Import Sheet</h1>
    <div class="card">
        <p>Upload a CSV exported from Excel or Google Sheets. Use <b>record_type</b> values like car, expense, task, purchase_payment, part, and listing. Use the same <b>car_key</b> to connect rows to the same car.</p>
        <p><a class="btn secondary" href="../actions/download-import-template.php">Download Template</a></p>
    </div>
    <?php if (isset($_GET['imported'])): ?>
    <div class="alert success">Imported <?= (int) $_GET['imported'] ?> rows.</div>
    <?php endif; ?>
    <form class="form-card section-title" action="../actions/import-sheet.php" method="POST" enctype="multipart/form-data">
        <label>CSV File</label><input name="sheet_file" type="file" accept=".csv,text/csv" required>
        <br><br><button class="btn" type="submit">Import Sheet</button>
    </form>
</div>
<?php require '../footer.php'; ?>
