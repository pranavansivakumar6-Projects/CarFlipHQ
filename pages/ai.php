<?php
require '../config/db.php';
require_once '../config/auth.php';
require_once '../config/ai.php';

$pageTitle = 'AI Tools | CarFlip HQ';
$cars = $pdo->query("SELECT id, year, make, model, status FROM cars ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$selectedCarId = filter_input(INPUT_GET, 'car_id', FILTER_VALIDATE_INT) ?: ($cars[0]['id'] ?? null);
$result = $_SESSION['ai_result'] ?? null;
unset($_SESSION['ai_result']);

require '../header.php';
?>
<div class="container">
    <div class="page-heading">
        <div>
            <h1>AI Tools</h1>
            <p class="small">Use your CarFlip HQ data to draft listings, plan repairs, read receipts, and ask business questions.</p>
        </div>
    </div>

    <?php if (!ai_is_available()): ?>
        <div class="alert">AI is not connected yet. Add <b>OPENAI_API_KEY</b> in Railway Variables. Optional: add <b>OPENAI_MODEL</b> if you want to choose the model.</div>
    <?php endif; ?>

    <?php if ($result): ?>
        <div class="ai-result <?= $result['ok'] ? 'success-result' : 'error-result' ?>">
            <b><?= $result['ok'] ? 'AI Result' : 'AI Error' ?></b>
            <pre><?= htmlspecialchars($result['message']) ?></pre>
        </div>
    <?php endif; ?>

    <div class="tool-grid section-title">
        <form class="tool-card" action="../actions/ai-tools.php" method="POST">
            <input type="hidden" name="tool" value="business_chat">
            <h2>Ask Business Data</h2>
            <textarea name="question" rows="5" placeholder="Example: Which car has the best profit and what tasks are still open?" required></textarea>
            <button class="btn" type="submit">Ask AI</button>
        </form>

        <form class="tool-card" action="../actions/ai-tools.php" method="POST">
            <input type="hidden" name="tool" value="listing">
            <h2>Generate Listing</h2>
            <label>Car</label>
            <select name="car_id" required>
                <?php foreach ($cars as $car): ?>
                    <option value="<?= (int) $car['id'] ?>" <?= (int) $selectedCarId === (int) $car['id'] ? 'selected' : '' ?>><?= htmlspecialchars(trim($car['year'] . ' ' . $car['make'] . ' ' . $car['model'] . ' - ' . $car['status'])) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn" type="submit">Draft Listing</button>
        </form>

        <form class="tool-card" action="../actions/ai-tools.php" method="POST">
            <input type="hidden" name="tool" value="tasks">
            <h2>Suggest Tasks</h2>
            <label>Car</label>
            <select name="car_id" required>
                <?php foreach ($cars as $car): ?>
                    <option value="<?= (int) $car['id'] ?>" <?= (int) $selectedCarId === (int) $car['id'] ? 'selected' : '' ?>><?= htmlspecialchars(trim($car['year'] . ' ' . $car['make'] . ' ' . $car['model'] . ' - ' . $car['status'])) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn" type="submit">Suggest Work</button>
        </form>

        <form class="tool-card" action="../actions/ai-tools.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="tool" value="receipt">
            <h2>Read Receipt</h2>
            <label>Receipt photo</label>
            <input type="file" name="receipt_image" accept="image/*" capture="environment">
            <label>Notes</label>
            <textarea name="notes" rows="4" placeholder="Optional: who paid, what car it belongs to, or anything written on the receipt."></textarea>
            <button class="btn" type="submit">Extract Details</button>
        </form>
    </div>
</div>
<?php require '../footer.php'; ?>
