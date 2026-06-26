<?php
require '../config/db.php';
require_once '../config/auth.php';
require_permission('can_use_ai');
require_once '../config/helpers.php';
require_once '../config/ai.php';
require_once '../config/status.php';

$pageTitle = 'AI Tools | CarFlip HQ';
$accessWhere = car_access_filter_sql('cars');
$cars = $pdo->query("SELECT id, year, make, model, status FROM cars WHERE $accessWhere ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$selectedCarId = filter_input(INPUT_GET, 'car_id', FILTER_VALIDATE_INT) ?: ($cars[0]['id'] ?? null);
$result = $_SESSION['ai_result'] ?? null;
unset($_SESSION['ai_result']);

function ai_car_options(array $cars, ?int $selectedCarId): void
{
    foreach ($cars as $car) {
        $label = trim($car['year'] . ' ' . $car['make'] . ' ' . $car['model'] . ' - ' . car_status_label((string) $car['status']));
        $selected = (int) $selectedCarId === (int) $car['id'] ? 'selected' : '';
        echo '<option value="' . (int) $car['id'] . '" ' . $selected . '>' . htmlspecialchars($label) . '</option>';
    }
}

require '../header.php';
?>
<div class="container">
    <div class="page-heading">
        <div>
            <h1>AI Tools</h1>
            <p class="small">Use your CarFlip HQ data to draft listings, plan repairs, prepare job documents, read receipts, and ask business questions.</p>
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
                <?php ai_car_options($cars, $selectedCarId); ?>
            </select>
            <button class="btn" type="submit">Draft Listing</button>
        </form>

        <form class="tool-card" action="../actions/ai-tools.php" method="POST">
            <input type="hidden" name="tool" value="tasks">
            <h2>Suggest Tasks</h2>
            <label>Car</label>
            <select name="car_id" required>
                <?php ai_car_options($cars, $selectedCarId); ?>
            </select>
            <button class="btn" type="submit">Suggest Work</button>
        </form>

        <form class="tool-card tool-card-wide" action="../actions/ai-tools.php" method="POST">
            <input type="hidden" name="tool" value="quotation">
            <h2>Generate Quotation</h2>
            <label>Car</label>
            <select name="car_id" required>
                <?php ai_car_options($cars, $selectedCarId); ?>
            </select>
            <div class="tool-form-grid">
                <div>
                    <label>Customer Name</label>
                    <input type="text" name="customer_name" required>
                </div>
                <div>
                    <label>Customer Contact</label>
                    <input type="text" name="customer_contact" placeholder="Phone, email, or address">
                </div>
            </div>
            <label>Job Title</label>
            <input type="text" name="job_title" placeholder="Front end repair and RWC preparation" required>
            <label>Job Details</label>
            <textarea name="job_details" rows="4" placeholder="Describe what needs to be quoted, parts required, labour, inclusions, exclusions, and anything the customer asked for." required></textarea>
            <div class="tool-form-grid tool-form-grid-three">
                <div>
                    <label>Labour</label>
                    <input type="number" name="labour_amount" min="0" step="0.01" placeholder="0.00">
                </div>
                <div>
                    <label>Parts</label>
                    <input type="number" name="parts_amount" min="0" step="0.01" placeholder="0.00">
                </div>
                <div>
                    <label>Other</label>
                    <input type="number" name="other_amount" min="0" step="0.01" placeholder="0.00">
                </div>
            </div>
            <div class="tool-form-grid">
                <div>
                    <label>Quote Date</label>
                    <input type="date" name="document_date" value="<?= htmlspecialchars(date('Y-m-d')) ?>">
                </div>
                <div>
                    <label>Valid Until</label>
                    <input type="date" name="valid_until">
                </div>
            </div>
            <label>Terms or Notes</label>
            <textarea name="notes" rows="3" placeholder="Example: Subject to parts availability. No GST unless stated."></textarea>
            <button class="btn" type="submit">Draft Quotation</button>
        </form>

        <form class="tool-card tool-card-wide" action="../actions/ai-tools.php" method="POST">
            <input type="hidden" name="tool" value="invoice">
            <h2>Generate Invoice</h2>
            <label>Car</label>
            <select name="car_id" required>
                <?php ai_car_options($cars, $selectedCarId); ?>
            </select>
            <div class="tool-form-grid">
                <div>
                    <label>Customer Name</label>
                    <input type="text" name="customer_name" required>
                </div>
                <div>
                    <label>Customer Contact</label>
                    <input type="text" name="customer_contact" placeholder="Phone, email, or address">
                </div>
            </div>
            <label>Job Title</label>
            <input type="text" name="job_title" placeholder="Repair work completed" required>
            <label>Job Details</label>
            <textarea name="job_details" rows="4" placeholder="Describe the completed job, work performed, parts supplied, labour, and payment notes." required></textarea>
            <div class="tool-form-grid tool-form-grid-three">
                <div>
                    <label>Labour</label>
                    <input type="number" name="labour_amount" min="0" step="0.01" placeholder="0.00">
                </div>
                <div>
                    <label>Parts</label>
                    <input type="number" name="parts_amount" min="0" step="0.01" placeholder="0.00">
                </div>
                <div>
                    <label>Other</label>
                    <input type="number" name="other_amount" min="0" step="0.01" placeholder="0.00">
                </div>
            </div>
            <div class="tool-form-grid">
                <div>
                    <label>Invoice Date</label>
                    <input type="date" name="document_date" value="<?= htmlspecialchars(date('Y-m-d')) ?>">
                </div>
                <div>
                    <label>Payment Terms</label>
                    <input type="text" name="payment_terms" placeholder="Due on receipt, 7 days, deposit paid...">
                </div>
            </div>
            <label>Notes</label>
            <textarea name="notes" rows="3" placeholder="Payment instructions, warranty notes, or extra customer information."></textarea>
            <button class="btn" type="submit">Draft Invoice</button>
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
