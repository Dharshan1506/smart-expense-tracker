<?php
/**
 * Edit Existing Transaction
 * Validates ownership and applies changes using UPDATE prepared statements
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$userId = $user['id'];
$pdo = getDBConnection();

$pageTitle = 'Edit Transaction';
$currentPage = 'edit_transaction';

$transactionId = (int)($_GET['id'] ?? 0);
if ($transactionId <= 0) {
    set_flash('danger', 'Invalid transaction specified.');
    header('Location: transactions.php');
    exit;
}

// Fetch transaction ensuring user ownership
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE transaction_id = ? AND user_id = ?");
$stmt->execute([$transactionId, $userId]);
$transaction = $stmt->fetch();

if (!$transaction) {
    set_flash('danger', 'Transaction not found or unauthorized access.');
    header('Location: transactions.php');
    exit;
}

// Fetch available categories
$stmtCats = $pdo->prepare("
    SELECT category_id, category_name, category_type 
    FROM categories 
    WHERE user_id IS NULL OR user_id = ? 
    ORDER BY category_type ASC, category_name ASC
");
$stmtCats->execute([$userId]);
$categories = $stmtCats->fetchAll();

$errors = [];
$type = $transaction['transaction_type'];
$amount = $transaction['amount'];
$categoryId = $transaction['category_id'];
$description = $transaction['description'];
$transactionDate = $transaction['transaction_date'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = trim($_POST['transaction_type'] ?? 'expense');
    $amountInput = trim($_POST['amount'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $transactionDate = trim($_POST['transaction_date'] ?? date('Y-m-d'));
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Invalid security token. Please try again.';
    }

    if (!in_array($type, ['income', 'expense'])) {
        $errors[] = 'Invalid transaction type.';
    }

    if (!is_numeric($amountInput) || (float)$amountInput <= 0) {
        $errors[] = 'Please enter a valid positive numerical amount.';
    } else {
        $amount = round((float)$amountInput, 2);
    }

    if (empty($categoryId)) {
        $errors[] = 'Please select a valid category.';
    }

    if (empty($description)) {
        $errors[] = 'Please provide a short description for this transaction.';
    }

    if (empty($transactionDate)) {
        $errors[] = 'Please provide a valid date.';
    }

    if (empty($errors)) {
        try {
            $updateStmt = $pdo->prepare("
                UPDATE transactions 
                SET category_id = ?, amount = ?, transaction_type = ?, description = ?, transaction_date = ?
                WHERE transaction_id = ? AND user_id = ?
            ");
            $updateStmt->execute([$categoryId, $amount, $type, $description, $transactionDate, $transactionId, $userId]);

            set_flash('success', 'Transaction updated successfully!');
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
            
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
                <h2>Edit Transaction #<?= $transactionId ?></h2>
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
                            <label class="form-label">Transaction Type</label>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                <label style="cursor: pointer;">
                                    <input type="radio" name="transaction_type" value="expense" <?= ($type === 'expense') ? 'checked' : '' ?> style="display: none;" id="radioExpense">
                                    <div id="btnTypeExpense" style="padding: 14px; text-align: center; border-radius: var(--radius-md); border: 2px solid <?= ($type === 'expense') ? '#ef4444' : '#e2e8f0' ?>; background: <?= ($type === 'expense') ? '#fee2e2' : '#ffffff' ?>; font-weight: 700; color: <?= ($type === 'expense') ? '#b91c1c' : '#64748b' ?>;">
                                        <i class="fa-solid fa-arrow-trend-down"></i> Expense
                                    </div>
                                </label>
                                <label style="cursor: pointer;">
                                    <input type="radio" name="transaction_type" value="income" <?= ($type === 'income') ? 'checked' : '' ?> style="display: none;" id="radioIncome">
                                    <div id="btnTypeIncome" style="padding: 14px; text-align: center; border-radius: var(--radius-md); border: 2px solid <?= ($type === 'income') ? '#10b981' : '#e2e8f0' ?>; background: <?= ($type === 'income') ? '#d1fae5' : '#ffffff' ?>; font-weight: 700; color: <?= ($type === 'income') ? '#065f46' : '#64748b' ?>;">
                                        <i class="fa-solid fa-arrow-trend-up"></i> Income
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Amount Input -->
                        <div class="form-group">
                            <label class="form-label" for="amount">Amount (₹)</label>
                            <div style="position: relative;">
                                <span style="position: absolute; left: 14px; top: 12px; font-weight: 700; color: var(--text-secondary);">₹</span>
                                <input type="number" step="0.01" min="0.01" id="amount" name="amount" class="form-control" style="padding-left: 30px; font-size: 1.15rem; font-weight: 700;" value="<?= htmlspecialchars((string)$amount) ?>" required>
                            </div>
                        </div>

                        <!-- Category Selector -->
                        <div class="form-group">
                            <label class="form-label" for="category_id">Category</label>
                            <select id="category_id" name="category_id" class="form-select" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['category_id'] ?>" data-type="<?= $cat['category_type'] ?>" <?= ($categoryId == $cat['category_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['category_name']) ?> (<?= ucfirst($cat['category_type']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Date Picker -->
                        <div class="form-group">
                            <label class="form-label" for="transaction_date">Date of Transaction</label>
                            <input type="date" id="transaction_date" name="transaction_date" class="form-control" value="<?= htmlspecialchars($transactionDate) ?>" required>
                        </div>

                        <!-- Description -->
                        <div class="form-group">
                            <label class="form-label" for="description">Description / Note</label>
                            <input type="text" id="description" name="description" class="form-control" value="<?= htmlspecialchars($description) ?>" required>
                        </div>

                        <div style="display: flex; gap: 12px; margin-top: 24px;">
                            <button type="submit" class="btn btn-primary" style="flex: 1; padding: 12px;">
                                <i class="fa-solid fa-floppy-disk"></i> Update Transaction
                            </button>
                            <a href="transactions.php" class="btn btn-outline">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const radioExpense = document.getElementById('radioExpense');
    const radioIncome = document.getElementById('radioIncome');
    const btnExpense = document.getElementById('btnTypeExpense');
    const btnIncome = document.getElementById('btnTypeIncome');

    function updateTypeUI(type) {
        if (type === 'expense') {
            btnExpense.style.borderColor = '#ef4444';
            btnExpense.style.background = '#fee2e2';
            btnExpense.style.color = '#b91c1c';
            btnIncome.style.borderColor = '#e2e8f0';
            btnIncome.style.background = '#ffffff';
            btnIncome.style.color = '#64748b';
        } else {
            btnIncome.style.borderColor = '#10b981';
            btnIncome.style.background = '#d1fae5';
            btnIncome.style.color = '#065f46';
            btnExpense.style.borderColor = '#e2e8f0';
            btnExpense.style.background = '#ffffff';
            btnExpense.style.color = '#64748b';
        }
    }

    radioExpense.addEventListener('change', () => updateTypeUI('expense'));
    radioIncome.addEventListener('change', () => updateTypeUI('income'));
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
