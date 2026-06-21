<?php
$pageTitle = 'Add Car | CarFlip HQ';
require '../header.php';
?>
<div class="container">
    <h1>Add Car</h1>
    <p><a class="btn secondary" href="import-sheet.php">Import Cars From Sheet</a></p>
    <form class="form-card" action="../actions/save-car.php" method="POST">
        <label>Make</label><input name="make" required>
        <label>Model</label><input name="model" required>
        <label>Year</label><input name="year" type="number">
        <label>VIN</label><input name="vin">
        <label>Rego</label><input name="rego">
        <label>Odometer</label><input name="odometer" type="number">
        <label>Source / Auction</label><input name="source">
        <label>Purchase Price</label><input name="purchase_price" type="number" step="0.01">
        <label>Purchase Date</label><input name="purchase_date" type="date">
        <label>Estimated Sale Price</label><input name="estimated_sale_price" type="number" step="0.01">
        <label>Damage Notes</label><textarea name="damage_notes"></textarea>
        <label>General Notes</label><textarea name="notes"></textarea>
        <br><br><button class="btn" type="submit">Save Car</button>
    </form>
</div>
<?php require '../footer.php'; ?>
