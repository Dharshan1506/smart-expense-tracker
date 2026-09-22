<?php
/**
 * Add New Transaction Form - ExpenseIQ Fintech SaaS
 * Full form for creating and categorizing transactions with real-time budget verification
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$userId = $user['id'];
$pdo = getDBConnection();

$pageTitle = 'Record Transaction';
$currentPage = 'add_transaction';

$errors = [];
$amount = '';
$type = $_GET['type'] ?? 'expense';
if (!in_array($type, ['income', 'expense'])) {
    $type = 'expense';
}
$categoryId = '';
$transactionDate = date('Y-m-d');
$description = '';

// Fetch all available categories for the user
$stmtCats = $pdo->prepare("
    SELECT category_id, category_name, category_type, icon, color 
    FROM categories 
    WHERE user_id IS NULL OR user_id = ? 
    ORDER BY category_type ASC, category_name ASC
");
$stmtCats->execute([$userId]);
$allCategories = $stmtCats->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken       = $_POST['csrf_token'] ?? '';
    $amount          = trim($_POST['amount'] ?? '');
    $type            = trim($_POST['transaction_type'] ?? 'expense');
    $categoryId      = (int)($_POST['category_id'] ?? 0);
    $transactionDate = trim($_POST['transaction_date'] ?? date('Y-m-d'));
    $description     = trim($_POST['description'] ?? '');

    // CSRF Check
    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Security token validation failed. Please submit the form again.';
    }

    // Input Validation
    if (!is_numeric($amount) || (float)$amount <= 0) {
        $errors[] = 'Please enter a valid monetary amount greater than zero.';
    }

    if (!in_array($type, ['income', 'expense'])) {
        $errors[] = 'Invalid transaction classification specified.';
    }

    if ($categoryId <= 0) {
        $errors[] = 'Please select a valid category.';
    } else {
        // Verify category belongs to user or is global
        $stmtCheckCat = $pdo->prepare("SELECT category_id, category_type FROM categories WHERE category_id = ? AND (user_id IS NULL OR user_id = ?)");
        $stmtCheckCat->execute([$categoryId, $userId]);
        $catRow = $stmtCheckCat->fetch();
        if (!$catRow) {
            $errors[] = 'Selected category does not exist or you do not have permission to access it.';
        } elseif ($catRow['category_type'] !== $type) {
            $errors[] = 'Selected category type does not match the transaction classification (' . ucfirst($type) . ').';
        }
    }

    if (empty($transactionDate) || !strtotime($transactionDate)) {
        $errors[] = 'Please enter a valid transaction date.';
    }

    if (empty($description)) {
        $errors[] = 'Please provide a brief description or note for this transaction.';
    } elseif (strlen($description) > 255) {
        $errors[] = 'Description must not exceed 255 characters.';
    }

    // Database Insertion
    if (empty($errors)) {
        try {
            $amountVal = (float)$amount;
            $stmtInsert = $pdo->prepare("
                INSERT INTO transactions (user_id, category_id, amount, transaction_type, transaction_date, description, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmtInsert->execute([
                $userId,
                $categoryId,
                $amountVal,
                $type,
                $transactionDate,
                $description
            ]);

            // Budget Threshold Alert Check
            if ($type === 'expense') {
                $checkBudgetStmt = $pdo->prepare("
                    SELECT b.budget_amount, c.category_name, SUM(t.amount) as total_spent
                    FROM budgets b
                    INNER JOIN categories c ON b.category_id = c.category_id
                    INNER JOIN transactions t ON t.category_id = b.category_id AND t.user_id = b.user_id
                    WHERE b.user_id = ? AND b.category_id = ?
                      AND ? BETWEEN b.start_date AND b.end_date
                      AND t.transaction_type = 'expense'
                      AND t.transaction_date BETWEEN b.start_date AND b.end_date
                    GROUP BY b.budget_id, b.budget_amount, c.category_name
                ");
                $checkBudgetStmt->execute([$userId, $categoryId, $transactionDate]);
                $budgetRow = $checkBudgetStmt->fetch();

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

            set_flash('success', 'Transaction of ' . format_currency($amountVal) . ' recorded successfully!');
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
            
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
                <div>
                    <h2 style="font-size: 1.45rem; font-weight: 800; color: #ffffff; margin: 0 0 4px 0;">Record New Transaction</h2>
                    <p style="color: var(--text-secondary); font-size: 0.88rem; margin: 0;">Enter monetary outflow or inflow with category allocation</p>
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
                    <form method="POST" action="add_transaction.php">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                        <!-- Type Selector Buttons -->
                        <div class="form-group">
                            <label class="form-label">Transaction Classification</label>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                                <label style="cursor: pointer;">
                                    <input type="radio" name="transaction_type" value="expense" <?= ($type === 'expense') ? 'checked' : '' ?> style="display: none;" id="radioExpense">
                                    <div id="btnTypeExpense" style="padding: 14px; text-align: center; border-radius: var(--radius-md); border: 2px solid rgba(244, 63, 94, 0.4); background: rgba(244, 63, 94, 0.15); font-weight: 700; color: #fb7185; transition: var(--transition);">
                                        <i class="fa-solid fa-arrow-trend-down"></i> Expense (Debit)
                                    </div>
                                </label>
                                <label style="cursor: pointer;">
                                    <input type="radio" name="transaction_type" value="income" <?= ($type === 'income') ? 'checked' : '' ?> style="display: none;" id="radioIncome">
                                    <div id="btnTypeIncome" style="padding: 14px; text-align: center; border-radius: var(--radius-md); border: 2px solid rgba(255, 255, 255, 0.1); background: rgba(20, 31, 54, 0.6); font-weight: 700; color: #94a3b8; transition: var(--transition);">
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
                                <input type="number" step="0.01" min="0.01" id="amount" name="amount" class="form-control" style="font-family: var(--font-mono); font-size: 1.15rem; font-weight: 700;" placeholder="0.00" value="<?= htmlspecialchars((string)$amount) ?>" required autofocus>
                            </div>
                        </div>

                        <!-- Category Selector -->
                        <div class="form-group">
                            <label class="form-label" for="category_id">Category Allocation <span style="color: #f87171;">*</span></label>
                            <select id="category_id" name="category_id" class="form-select" required>
                                <option value="">-- Choose Category --</option>
                                <?php foreach ($allCategories as $cat): ?>
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
                            <input type="text" id="description" name="description" class="form-control" placeholder="e.g. Grocery store, Consulting payout, Power bill..." value="<?= htmlspecialchars($description) ?>" required>
                        </div>

                        <div style="display: flex; gap: 12px; margin-top: 28px;">
                            <button type="submit" class="btn btn-primary" style="flex: 1; padding: 13px; font-weight: 700;">
                                <i class="fa-solid fa-check"></i> Save Transaction
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
