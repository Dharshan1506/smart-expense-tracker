<?php
/**
 * Add New Transaction Page
 * Securely records income and expense transactions using prepared statements
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$userId = $user['id'];
$pdo = getDBConnection();

$pageTitle = 'Add Transaction';
$currentPage = 'add_transaction';

$errors = [];
$type = $_GET['type'] ?? 'expense';
$amount = '';
$categoryId = '';
$description = '';
$transactionDate = date('Y-m-d');

// Fetch available categories
$stmtCats = $pdo->prepare("
    SELECT category_id, category_name, category_type, icon, color 
    FROM categories 
    WHERE user_id IS NULL OR user_id = ? 
    ORDER BY category_type ASC, category_name ASC
");
$stmtCats->execute([$userId]);
$allCategories = $stmtCats->fetchAll();

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
            $stmt = $pdo->prepare("
                INSERT INTO transactions (user_id, category_id, amount, transaction_type, description, transaction_date, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $categoryId, $amount, $type, $description, $transactionDate]);

            // If an expense is added, check if it pushes an active budget over the limit
            if ($type === 'expense') {
                $checkBudget = $pdo->prepare("
                    SELECT b.budget_amount, c.category_name, COALESCE(SUM(t.amount), 0) AS total_spent
                    FROM budgets b
                    INNER JOIN categories c ON b.category_id = c.category_id
                    LEFT JOIN transactions t ON t.category_id = b.category_id AND t.user_id = b.user_id AND t.transaction_type = 'expense'
                    WHERE b.user_id = ? AND b.category_id = ? AND ? BETWEEN b.start_date AND b.end_date
                    GROUP BY b.budget_id, b.budget_amount, c.category_name
                ");
                $checkBudget->execute([$userId, $categoryId, $transactionDate]);
                $budgetRow = $checkBudget->fetch();

                if ($budgetRow && (float)$budgetRow['total_spent'] > (float)$budgetRow['budget_amount']) {
                    $excess = (float)$budgetRow['total_spent'] - (float)$budgetRow['budget_amount'];
                    $alertStmt = $pdo->prepare("
                        INSERT INTO alerts (user_id, message, alert_type, created_at) 
                        VALUES (?, ?, 'danger', NOW())
                    ");
                    $alertStmt->execute([
                        $userId,
                        'Budget Alert: Your spending for ' . $budgetRow['category_name'] . ' has exceeded your budget by ' . format_currency($excess) . '!'
                    ]);
                }
            }

            set_flash('success', 'Transaction of ' . format_currency($amount) . ' added successfully!');
            header('Location: transactions.php');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Failed to record transaction: ' . $e->getMessage();
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
                <h2>Record New Transaction</h2>
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
                    <form method="POST" action="add_transaction.php">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                        <!-- Type Selector Buttons -->
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
                                <input type="number" step="0.01" min="0.01" id="amount" name="amount" class="form-control" style="padding-left: 30px; font-size: 1.15rem; font-weight: 700;" placeholder="0.00" value="<?= htmlspecialchars((string)$amount) ?>" required autofocus>
                            </div>
                        </div>

                        <!-- Category Selector -->
                        <div class="form-group">
                            <label class="form-label" for="category_id">Category</label>
                            <select id="category_id" name="category_id" class="form-select" required>
                                <option value="">Select Category...</option>
                                <?php foreach ($allCategories as $cat): ?>
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
                            <input type="text" id="description" name="description" class="form-control" placeholder="e.g. Grocery store, Client invoice, Dinner..." value="<?= htmlspecialchars($description) ?>" required>
                        </div>

                        <div style="display: flex; gap: 12px; margin-top: 24px;">
                            <button type="submit" class="btn btn-primary" style="flex: 1; padding: 12px;">
                                <i class="fa-solid fa-check"></i> Save Transaction
                            </button>
                            <a href="transactions.php" class="btn btn-outline">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div> <!-- End content-body -->

<script>
// Dynamic Category Filter by Type
document.addEventListener('DOMContentLoaded', () => {
    const radioExpense = document.getElementById('radioExpense');
    const radioIncome = document.getElementById('radioIncome');
    const btnExpense = document.getElementById('btnTypeExpense');
    const btnIncome = document.getElementById('btnTypeIncome');
    const categorySelect = document.getElementById('category_id');

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

        // Filter category dropdown options
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

    // Initial run
    updateTypeUI(radioIncome.checked ? 'income' : 'expense');
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
