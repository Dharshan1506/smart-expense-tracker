<?php
/**
 * Budget Management & Threshold Monitoring
 * Modern Fintech Budget Allocations with Real-Time Progress, Overrun Alerts, and Modals
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$userId = $user['id'];
$pdo = getDBConnection();

$pageTitle = 'Budget Management';
$currentPage = 'budgets';

$errors = [];

// Handle Budget Creation
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $budgetAmount = (float)($_POST['budget_amount'] ?? 0);
    $startDate = trim($_POST['start_date'] ?? date('Y-m-01'));
    $endDate = trim($_POST['end_date'] ?? date('Y-m-t'));
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Invalid security token.';
    }

    if ($categoryId <= 0) {
        $errors[] = 'Please select a valid expense category.';
    }

    if ($budgetAmount <= 0) {
        $errors[] = 'Please specify a positive budget limit amount.';
    }

    if (empty($startDate) || empty($endDate) || $startDate > $endDate) {
        $errors[] = 'Start date must be earlier than or equal to the end date.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO budgets (user_id, category_id, budget_amount, start_date, end_date, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $categoryId, $budgetAmount, $startDate, $endDate]);

            set_flash('success', 'Budget limit of ' . format_currency($budgetAmount) . ' established successfully!');
            header('Location: budgets.php');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Failed to establish budget limit: ' . $e->getMessage();
        }
    }
}

// Handle Budget Deletion
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $budgetId = (int)($_POST['budget_id'] ?? 0);
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (verify_csrf_token($csrfToken) && $budgetId > 0) {
        $stmt = $pdo->prepare("DELETE FROM budgets WHERE budget_id = ? AND user_id = ?");
        $stmt->execute([$budgetId, $userId]);
        set_flash('success', 'Budget limit was successfully deleted.');
        header('Location: budgets.php');
        exit;
    }
}

// Fetch all budgets with active spent amount calculated via LEFT JOIN
$stmtBudgets = $pdo->prepare("
    SELECT 
        b.budget_id,
        b.budget_amount,
        b.start_date,
        b.end_date,
        c.category_id,
        c.category_name,
        c.icon,
        c.color,
        COALESCE(SUM(t.amount), 0) AS total_spent
    FROM budgets b
    INNER JOIN categories c ON b.category_id = c.category_id
    LEFT JOIN transactions t ON t.category_id = b.category_id 
        AND t.user_id = b.user_id 
        AND t.transaction_type = 'expense'
        AND t.transaction_date BETWEEN b.start_date AND b.end_date
    WHERE b.user_id = ?
    GROUP BY b.budget_id, b.budget_amount, b.start_date, b.end_date, c.category_id, c.category_name, c.icon, c.color
    ORDER BY (COALESCE(SUM(t.amount), 0) / b.budget_amount) DESC, b.end_date DESC
");
$stmtBudgets->execute([$userId]);
$budgets = $stmtBudgets->fetchAll();

// Fetch expense categories for the creation dropdown
$stmtCats = $pdo->prepare("
    SELECT category_id, category_name 
    FROM categories 
    WHERE (user_id IS NULL OR user_id = ?) AND category_type = 'expense'
    ORDER BY category_name ASC
");
$stmtCats->execute([$userId]);
$expenseCategories = $stmtCats->fetchAll();

$flash = get_flash();
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="main-wrapper">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="content-body">
        <?php if ($flash): ?>
            <div class="flash-alert flash-<?= htmlspecialchars($flash['type']) ?>">
                <i class="fa-solid fa-circle-check"></i>
                <span><?= htmlspecialchars($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="flash-alert flash-danger" style="display: block;">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <strong>Error:</strong>
                </div>
                <ul style="margin: 0; padding-left: 20px; font-size: 0.84rem;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Page Header Bar -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 14px;">
            <div>
                <h2 style="font-size: 1.45rem; font-weight: 800; color: var(--text-primary); margin: 0 0 4px 0;">
                    Budget Allocations & Ceilings
                </h2>
                <p style="color: var(--text-secondary); font-size: 0.88rem; margin: 0;">
                    Monitor real-time expenditure thresholds and prevent overspending
                </p>
            </div>
            <button type="button" class="btn btn-primary" onclick="openCreateBudgetModal()">
                <i class="fa-solid fa-plus"></i> Set New Budget
            </button>
        </div>

        <div class="grid-2" style="grid-template-columns: 1fr 340px; align-items: start;">
            
            <!-- Active Budgets Card Grid -->
            <div>
                <?php if (empty($budgets)): ?>
                    <div class="card">
                        <div class="empty-state" style="padding: 48px 24px;">
                            <div class="empty-state-icon"><i class="fa-solid fa-calculator"></i></div>
                            <h3 class="empty-state-title">No Budgets Established</h3>
                            <p class="empty-state-text">
                                Define spending caps for specific categories to receive automated threshold warnings and maintain disciplined financial health.
                            </p>
                            <button type="button" class="btn btn-primary btn-sm" onclick="openCreateBudgetModal()">
                                + Create First Budget
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="display: grid; gap: 16px;">
                        <?php foreach ($budgets as $b): 
                            $limit = (float)$b['budget_amount'];
                            $spent = (float)$b['total_spent'];
                            $remaining = $limit - $spent;
                            $percentage = $limit > 0 ? round(($spent / $limit) * 100) : 0;
                            $barWidth = min($percentage, 100);

                            if ($percentage >= 100) {
                                $badgeClass = 'badge-danger';
                                $barColor = 'progress-danger';
                                $statusText = 'Over Budget!';
                            } elseif ($percentage >= 80) {
                                $badgeClass = 'badge-warning';
                                $barColor = 'progress-warning';
                                $statusText = 'Caution (80%+)';
                            } else {
                                $badgeClass = 'badge-success';
                                $barColor = 'progress-normal';
                                $statusText = 'On Track';
                            }
                        ?>
                            <div class="card" style="margin-bottom: 0;">
                                <div class="card-body" style="padding: 22px;">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px;">
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <div style="width: 44px; height: 44px; border-radius: var(--radius-md); background: <?= htmlspecialchars($b['color'] ?? '#4f46e5') ?>15; color: <?= htmlspecialchars($b['color'] ?? '#4f46e5') ?>; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; border: 1px solid <?= htmlspecialchars($b['color'] ?? '#4f46e5') ?>30;">
                                                <i class="fa-solid <?= htmlspecialchars($b['icon'] ?? 'fa-tag') ?>"></i>
                                            </div>
                                            <div>
                                                <h4 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 2px;"><?= htmlspecialchars($b['category_name']) ?></h4>
                                                <span style="font-size: 0.78rem; color: var(--text-secondary);">
                                                    <i class="fa-regular fa-calendar" style="margin-right: 4px;"></i>
                                                    <?= format_date($b['start_date']) ?> &rarr; <?= format_date($b['end_date']) ?>
                                                </span>
                                            </div>
                                        </div>

                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span class="badge <?= $badgeClass ?>">
                                                <?= $statusText ?>
                                            </span>

                                            <form method="POST" action="budgets.php" id="deleteBudgetForm_<?= $b['budget_id'] ?>" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="budget_id" value="<?= $b['budget_id'] ?>">
                                                <button type="button" class="btn btn-outline btn-sm btn-icon" title="Delete Budget" onclick="confirmDeleteBudget(<?= $b['budget_id'] ?>, '<?= htmlspecialchars(addslashes($b['category_name'])) ?>')">
                                                    <i class="fa-solid fa-trash-can" style="color: #ef4444;"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>

                                    <!-- Progress Indicator -->
                                    <div class="progress-container">
                                        <div class="progress-header">
                                            <span>
                                                Spent: <strong><?= format_currency($spent) ?></strong> 
                                                of <?= format_currency($limit) ?>
                                            </span>
                                            <span>
                                                <strong style="color: <?= $percentage >= 100 ? 'var(--danger)' : 'var(--text-primary)' ?>;"><?= $percentage ?>%</strong> utilized
                                            </span>
                                        </div>
                                        <div class="progress-bar-bg" style="height: 10px;">
                                            <div class="progress-bar-fill <?= $barColor ?>" style="width: <?= $barWidth ?>%;"></div>
                                        </div>
                                    </div>

                                    <div style="display: flex; justify-content: space-between; margin-top: 14px; font-size: 0.85rem; padding-top: 12px; border-top: 1px solid var(--border-card);">
                                        <span>
                                            Remaining: 
                                            <strong style="color: <?= $remaining < 0 ? 'var(--danger)' : '#059669' ?>; font-family: monospace;">
                                                <?= format_currency($remaining) ?>
                                            </strong>
                                        </span>
                                        <span style="color: var(--text-secondary); font-size: 0.8rem;">
                                            <?= $remaining < 0 ? '<span style="color: var(--danger); font-weight: 700;">Over budget by ' . format_currency(abs($remaining)) . '</span>' : 'Safe spending buffer' ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Create Budget Card (Side Panel) -->
            <div class="card" id="createBudgetCard">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fa-solid fa-plus-circle" style="color: var(--brand-primary);"></i>
                        New Budget Ceiling
                    </h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="budgets.php">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="action" value="create">

                        <div class="form-group">
                            <label class="form-label" for="category_id">Expense Category <span style="color: var(--danger);">*</span></label>
                            <select id="category_id" name="category_id" class="form-select" required>
                                <option value="">-- Select Category --</option>
                                <?php foreach ($expenseCategories as $cat): ?>
                                    <option value="<?= $cat['category_id'] ?>">
                                        <?= htmlspecialchars($cat['category_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="budget_amount">Limit Amount (₹) <span style="color: var(--danger);">*</span></label>
                            <div class="input-icon-wrapper">
                                <i class="fa-solid fa-indian-rupee-sign"></i>
                                <input type="number" step="0.01" min="1" id="budget_amount" name="budget_amount" class="form-control" placeholder="5000.00" required style="font-family: monospace; font-weight: 700;">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="start_date">Start Date</label>
                            <input type="date" id="start_date" name="start_date" class="form-control" value="<?= date('Y-m-01') ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="end_date">End Date</label>
                            <input type="date" id="end_date" name="end_date" class="form-control" value="<?= date('Y-m-t') ?>" required>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 11px;">
                            <i class="fa-solid fa-check"></i> Establish Budget
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div> <!-- End content-body -->
</div>

<script>
function openCreateBudgetModal() {
    const card = document.getElementById('createBudgetCard');
    if (card) {
        card.scrollIntoView({ behavior: 'smooth' });
        document.getElementById('category_id').focus();
    }
}

function confirmDeleteBudget(id, name) {
    confirmAction(
        'Are you sure you want to delete the budget allocation for "' + name + '"?',
        function() {
            document.getElementById('deleteBudgetForm_' + id).submit();
        },
        'Confirm Budget Deletion'
    );
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
