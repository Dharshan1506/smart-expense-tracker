<?php
/**
 * Edit Transaction Handler & Form - ExpenseIQ Fintech SaaS
 * Edit existing transaction details with ownership authorization & input validation
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$userId = $user['id'];
$pdo = getDBConnection();

$pageTitle = 'Edit Transaction';
$currentPage = 'transactions';

$transactionId = (int)($_GET['id'] ?? 0);
if ($transactionId <= 0) {
    set_flash('danger', 'Invalid transaction identifier provided.');
    header('Location: transactions.php');
    exit;
}

// Fetch existing transaction
$stmt = $pdo->prepare("
    SELECT transaction_id, amount, transaction_type, category_id, transaction_date, description
    FROM transactions
    WHERE transaction_id = ? AND user_id = ?
");
$stmt->execute([$transactionId, $userId]);
$transaction = $stmt->fetch();

if (!$transaction) {
    set_flash('warning', 'Transaction not found or you are not authorized to modify it.');
    header('Location: transactions.php');
    exit;
}

$errors = [];
$amount = $transaction['amount'];
$type = $transaction['transaction_type'];
$categoryId = $transaction['category_id'];
$transactionDate = $transaction['transaction_date'];
$description = $transaction['description'];

// Fetch categories
$stmtCats = $pdo->prepare("
    SELECT category_id, category_name, category_type 
    FROM categories 
    WHERE user_id IS NULL OR user_id = ? 
    ORDER BY category_type ASC, category_name ASC
");
$stmtCats->execute([$userId]);
$categories = $stmtCats->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken       = $_POST['csrf_token'] ?? '';
    $amount          = trim($_POST['amount'] ?? '');
    $type            = trim($_POST['transaction_type'] ?? 'expense');
    $categoryId      = (int)($_POST['category_id'] ?? 0);
    $transactionDate = trim($_POST['transaction_date'] ?? '');
    $description     = trim($_POST['description'] ?? '');

    // CSRF Check
    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Security token validation failed. Please refresh the page and try again.';
    }

    if (!is_numeric($amount) || (float)$amount <= 0) {
        $errors[] = 'Please provide a valid monetary amount greater than zero.';
    }

    if (!in_array($type, ['income', 'expense'])) {
        $errors[] = 'Invalid transaction classification.';
    }

    if ($categoryId <= 0) {
        $errors[] = 'Please select a valid category.';
    }

    if (empty($transactionDate) || !strtotime($transactionDate)) {
        $errors[] = 'Please enter a valid transaction date.';
    }

    if (empty($description)) {
        $errors[] = 'Description cannot be empty.';
    } elseif (strlen($description) > 255) {
        $errors[] = 'Description must not exceed 255 characters.';
    }

    if (empty($errors)) {
        try {
            $amountVal = (float)$amount;
            $stmtUpdate = $pdo->prepare("
                UPDATE transactions 
                SET amount = ?, transaction_type = ?, category_id = ?, transaction_date = ?, description = ?
                WHERE transaction_id = ? AND user_id = ?
            ");
            $stmtUpdate->execute([
                $amountVal,
                $type,
                $categoryId,
                $transactionDate,
                $description,
                $transactionId,
                $userId
            ]);

            set_flash('success', 'Transaction #' . $transactionId . ' was updated successfully!');
            header('Location: transactions.php');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Failed to update transaction: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="main-wrapper">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="content-body">
        <div style="max-width: 680px; margin: 0 auto;">
            
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
                <div>
                    <h2 style="font-size: 1.45rem; font-weight: 800; color: #ffffff; margin: 0 0 4px 0;">Edit Transaction #<?= $transactionId ?></h2>
                    <p style="color: var(--text-secondary); font-size: 0.88rem; margin: 0;">Modify amount, classification, category or narrative</p>
                </div>
                <a href="transactions.php" class="btn btn-outline btn-sm">
                    <i class="fa-solid fa-arrow-left"></i> Back to Ledger
                </a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="flash-alert flash-danger">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <div>
                        <?php foreach ($errors as $err): ?>
                            <div><?= htmlspecialchars($err) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body" style="padding: 32px;">
                    <form method="POST" action="edit_transaction.php?id=<?= $transactionId ?>">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                        <!-- Type Selector -->
                        <div class="form-group">
                            <label class="form-label">Transaction Classification</label>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                                <label style="cursor: pointer;">
                                    <input type="radio" name="transaction_type" value="expense" <?= ($type === 'expense') ? 'checked' : '' ?> style="display: none;" id="radioExpense">
                                    <div id="btnTypeExpense" style="padding: 14px; text-align: center; border-radius: var(--radius-md); border: 2px solid <?= ($type === 'expense') ? 'rgba(244, 63, 94, 0.6)' : 'rgba(255, 255, 255, 0.1)' ?>; background: <?= ($type === 'expense') ? 'rgba(244, 63, 94, 0.2)' : 'rgba(20, 31, 54, 0.6)' ?>; font-weight: 700; color: <?= ($type === 'expense') ? '#fb7185' : '#94a3b8' ?>; transition: var(--transition);">
                                        <i class="fa-solid fa-arrow-trend-down"></i> Expense (Debit)
                                    </div>
                                </label>
                                <label style="cursor: pointer;">
                                    <input type="radio" name="transaction_type" value="income" <?= ($type === 'income') ? 'checked' : '' ?> style="display: none;" id="radioIncome">
                                    <div id="btnTypeIncome" style="padding: 14px; text-align: center; border-radius: var(--radius-md); border: 2px solid <?= ($type === 'income') ? 'rgba(16, 185, 129, 0.6)' : 'rgba(255, 255, 255, 0.1)' ?>; background: <?= ($type === 'income') ? 'rgba(16, 185, 129, 0.2)' : 'rgba(20, 31, 54, 0.6)' ?>; font-weight: 700; color: <?= ($type === 'income') ? '#34d399' : '#94a3b8' ?>; transition: var(--transition);">
                                        <i class="fa-solid fa-arrow-trend-up"></i> Income (Credit)
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Amount Input -->
                        <div class="form-group">
                            <label class="form-label" for="amount">Amount in Indian Rupees (₹) <span style="color: #f87171;">*</span></label>
                            <div class="input-icon-wrapper">
                                <i class="fa-solid fa-indian-rupee-sign" style="color: #60a5fa;"></i>
                                <input type="number" step="0.01" min="0.01" id="amount" name="amount" class="form-control" style="font-family: var(--font-mono); font-size: 1.15rem; font-weight: 700;" value="<?= htmlspecialchars((string)$amount) ?>" required>
                            </div>
                        </div>

                        <!-- Category Selector -->
                        <div class="form-group">
                            <label class="form-label" for="category_id">Category Allocation <span style="color: #f87171;">*</span></label>
                            <select id="category_id" name="category_id" class="form-select" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['category_id'] ?>" data-type="<?= $cat['category_type'] ?>" <?= ($categoryId == $cat['category_id']) ? 'selected' : '' ?>>
                                        [<?= ucfirst($cat['category_type']) ?>] <?= htmlspecialchars($cat['category_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Date Picker -->
                        <div class="form-group">
                            <label class="form-label" for="transaction_date">Date of Transaction <span style="color: #f87171;">*</span></label>
                            <input type="date" id="transaction_date" name="transaction_date" class="form-control" value="<?= htmlspecialchars($transactionDate) ?>" required>
                        </div>

                        <!-- Description -->
                        <div class="form-group">
                            <label class="form-label" for="description">Description / Narrative <span style="color: #f87171;">*</span></label>
                            <input type="text" id="description" name="description" class="form-control" value="<?= htmlspecialchars($description) ?>" required>
                        </div>

                        <div style="display: flex; gap: 12px; margin-top: 28px;">
                            <button type="submit" class="btn btn-primary" style="flex: 1; padding: 13px; font-weight: 700;">
                                <i class="fa-solid fa-floppy-disk"></i> Save Modifications
                            </button>
                            <a href="transactions.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div> <!-- End content-body -->

<script>
document.addEventListener('DOMContentLoaded', () => {
    const radioExpense = document.getElementById('radioExpense');
    const radioIncome = document.getElementById('radioIncome');
    const btnExpense = document.getElementById('btnTypeExpense');
    const btnIncome = document.getElementById('btnTypeIncome');
    const categorySelect = document.getElementById('category_id');

    function updateTypeUI(type) {
        if (type === 'expense') {
            btnExpense.style.borderColor = 'rgba(244, 63, 94, 0.6)';
            btnExpense.style.background = 'rgba(244, 63, 94, 0.2)';
            btnExpense.style.color = '#fb7185';
            btnIncome.style.borderColor = 'rgba(255, 255, 255, 0.1)';
            btnIncome.style.background = 'rgba(20, 31, 54, 0.6)';
            btnIncome.style.color = '#94a3b8';
        } else {
            btnIncome.style.borderColor = 'rgba(16, 185, 129, 0.6)';
            btnIncome.style.background = 'rgba(16, 185, 129, 0.2)';
            btnIncome.style.color = '#34d399';
            btnExpense.style.borderColor = 'rgba(255, 255, 255, 0.1)';
            btnExpense.style.background = 'rgba(20, 31, 54, 0.6)';
            btnExpense.style.color = '#94a3b8';
        }

        Array.from(categorySelect.options).forEach(opt => {
            if (!opt.value) return;
            const optType = opt.getAttribute('data-type');
            if (optType === type) {
                opt.style.display = '';
            } else {
                opt.style.display = 'none';
            }
        });
    }

    radioExpense.addEventListener('change', () => updateTypeUI('expense'));
    radioIncome.addEventListener('change', () => updateTypeUI('income'));

    updateTypeUI(radioIncome.checked ? 'income' : 'expense');
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
