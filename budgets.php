<?php
/**
 * Budget Management & Threshold Monitoring
 * Modern Fintech SaaS Budget Allocations with SVG Gauges, Overrun Alerts, and Modals
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

// Aggregated stats
$totalBudgeted = 0;
$totalSpent = 0;
$overBudgetCount = 0;
$warningCount = 0;

foreach ($budgets as $b) {
    $limit = (float)$b['budget_amount'];
    $spent = (float)$b['total_spent'];
    $totalBudgeted += $limit;
    $totalSpent += $spent;
    $pct = $limit > 0 ? ($spent / $limit) * 100 : 0;
    if ($pct >= 100) {
        $overBudgetCount++;
    } elseif ($pct >= 80) {
        $warningCount++;
    }
}
$overallRemaining = $totalBudgeted - $totalSpent;
$overallUtilization = $totalBudgeted > 0 ? round(($totalSpent / $totalBudgeted) * 100) : 0;

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
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
            <div>
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 4px;">
                    <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3);">
                        <i class="fa-solid fa-shield-halved"></i> Guardrails Active
                    </span>
                    <?php if ($overBudgetCount > 0): ?>
                        <span class="badge badge-danger" style="animation: pulse-border 2s infinite;">
                            <i class="fa-solid fa-bell"></i> <?= $overBudgetCount ?> Limit Exceeded
                        </span>
                    <?php endif; ?>
                </div>
                <h2 style="font-size: 1.6rem; font-weight: 800; color: var(--text-primary); margin: 0; letter-spacing: -0.02em;">
                    Budget Allocations & Ceilings
                </h2>
                <p style="color: var(--text-secondary); font-size: 0.88rem; margin: 4px 0 0 0;">
                    Automated expenditure thresholds with real-time depletion monitoring
                </p>
            </div>
            <button type="button" class="btn btn-primary btn-glow" onclick="openBudgetModal()">
                <i class="fa-solid fa-plus"></i> Set New Budget
            </button>
        </div>

        <!-- 4-Stat Financial Strip -->
        <div class="grid-4" style="margin-bottom: 24px;">
            <div class="card" style="margin-bottom: 0; border-left: 4px solid var(--accent-primary);">
                <div class="card-body" style="padding: 18px 20px;">
                    <div style="color: var(--text-muted); font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">
                        Total Budgeted
                    </div>
                    <div style="font-size: 1.5rem; font-weight: 800; font-family: var(--font-mono); color: var(--text-primary);">
                        <?= format_currency($totalBudgeted) ?>
                    </div>
                    <div style="font-size: 0.78rem; color: var(--text-secondary); margin-top: 4px;">
                        Across <?= count($budgets) ?> category allocations
                    </div>
                </div>
            </div>

            <div class="card" style="margin-bottom: 0; border-left: 4px solid var(--accent-coral);">
                <div class="card-body" style="padding: 18px 20px;">
                    <div style="color: var(--text-muted); font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">
                        Total Consumed
                    </div>
                    <div style="font-size: 1.5rem; font-weight: 800; font-family: var(--font-mono); color: var(--accent-coral);">
                        <?= format_currency($totalSpent) ?>
                    </div>
                    <div style="font-size: 0.78rem; color: var(--text-secondary); margin-top: 4px;">
                        Spent within active cycles
                    </div>
                </div>
            </div>

            <div class="card" style="margin-bottom: 0; border-left: 4px solid var(--accent-emerald);">
                <div class="card-body" style="padding: 18px 20px;">
                    <div style="color: var(--text-muted); font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">
                        Remaining Capacity
                    </div>
                    <div style="font-size: 1.5rem; font-weight: 800; font-family: var(--font-mono); color: <?= $overallRemaining < 0 ? 'var(--accent-coral)' : 'var(--accent-emerald)' ?>;">
                        <?= format_currency($overallRemaining) ?>
                    </div>
                    <div style="font-size: 0.78rem; color: var(--text-secondary); margin-top: 4px;">
                        <?= $overallRemaining >= 0 ? 'Safe unallocated buffer' : 'Deficit across caps' ?>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-bottom: 0; border-left: 4px solid var(--accent-amber);">
                <div class="card-body" style="padding: 18px 20px;">
                    <div style="color: var(--text-muted); font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">
                        Overall Burn Rate
                    </div>
                    <div style="display: flex; align-items: baseline; gap: 8px;">
                        <span style="font-size: 1.5rem; font-weight: 800; font-family: var(--font-mono); color: <?= $overallUtilization > 90 ? 'var(--accent-coral)' : ($overallUtilization > 75 ? 'var(--accent-amber)' : 'var(--accent-primary)') ?>;">
                            <?= $overallUtilization ?>%
                        </span>
                        <span style="font-size: 0.78rem; color: var(--text-secondary);">utilized</span>
                    </div>
                    <div class="progress-bar-bg" style="height: 6px; margin-top: 8px;">
                        <div class="progress-bar-fill <?= $overallUtilization >= 100 ? 'progress-danger' : ($overallUtilization >= 80 ? 'progress-warning' : 'progress-normal') ?>" style="width: <?= min($overallUtilization, 100) ?>%;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid-2" style="grid-template-columns: 1fr 340px; align-items: start; gap: 24px;">
            
            <!-- Active Budgets Card Grid -->
            <div>
                <?php if (empty($budgets)): ?>
                    <div class="card">
                        <div class="empty-state" style="padding: 56px 24px;">
                            <div class="empty-state-icon" style="background: rgba(99, 102, 241, 0.12); color: var(--accent-primary); width: 68px; height: 68px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px auto; font-size: 1.8rem; border: 1px solid rgba(99, 102, 241, 0.25);">
                                <i class="fa-solid fa-calculator"></i>
                            </div>
                            <h3 class="empty-state-title" style="font-size: 1.25rem; font-weight: 700;">No Budgets Established</h3>
                            <p class="empty-state-text" style="max-width: 440px; margin: 8px auto 20px auto; color: var(--text-secondary); font-size: 0.88rem;">
                                Define spending ceilings for specific expense categories to receive automated threshold warnings and maintain disciplined financial health.
                            </p>
                            <button type="button" class="btn btn-primary btn-glow" onclick="openBudgetModal()">
                                <i class="fa-solid fa-plus"></i> Create First Budget Ceiling
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="display: grid; gap: 18px;">
                        <?php foreach ($budgets as $b): 
                            $limit = (float)$b['budget_amount'];
                            $spent = (float)$b['total_spent'];
                            $remaining = $limit - $spent;
                            $percentage = $limit > 0 ? round(($spent / $limit) * 100) : 0;
                            $barWidth = min($percentage, 100);

                            // Calculate days remaining
                            $endDateTime = new DateTime($b['end_date']);
                            $nowDateTime = new DateTime(date('Y-m-d'));
                            $daysLeft = (int)$nowDateTime->diff($endDateTime)->format("%r%a");

                            if ($percentage >= 100) {
                                $badgeClass = 'badge-danger';
                                $barColor = 'progress-danger';
                                $statusText = 'Limit Exceeded';
                                $cardBorder = 'rgba(244, 63, 94, 0.4)';
                                $glowColor = 'rgba(244, 63, 94, 0.1)';
                                $strokeColor = '#f43f5e';
                            } elseif ($percentage >= 80) {
                                $badgeClass = 'badge-warning';
                                $barColor = 'progress-warning';
                                $statusText = 'Caution (80%+)';
                                $cardBorder = 'rgba(245, 158, 11, 0.4)';
                                $glowColor = 'rgba(245, 158, 11, 0.08)';
                                $strokeColor = '#f59e0b';
                            } else {
                                $badgeClass = 'badge-success';
                                $barColor = 'progress-normal';
                                $statusText = 'On Track';
                                $cardBorder = 'var(--border-card)';
                                $glowColor = 'transparent';
                                $strokeColor = '#10b981';
                            }

                            // SVG circular math (radius 28 -> circum 175.9)
                            $radius = 28;
                            $circumference = 2 * M_PI * $radius;
                            $offset = $circumference - ($barWidth / 100 * $circumference);
                        ?>
                            <div class="card" style="margin-bottom: 0; border: 1px solid <?= $cardBorder ?>; background: linear-gradient(180deg, <?= $glowColor ?> 0%, rgba(14, 21, 38, 0.75) 100%); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
                                <div class="card-body" style="padding: 22px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 18px;">
                                        
                                        <!-- Category info -->
                                        <div style="display: flex; align-items: center; gap: 14px;">
                                            <div style="width: 48px; height: 48px; border-radius: var(--radius-md); background: <?= htmlspecialchars($b['color'] ?? '#6366f1') ?>20; color: <?= htmlspecialchars($b['color'] ?? '#6366f1') ?>; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; border: 1px solid <?= htmlspecialchars($b['color'] ?? '#6366f1') ?>40; box-shadow: 0 4px 12px <?= htmlspecialchars($b['color'] ?? '#6366f1') ?>25;">
                                                <i class="fa-solid <?= htmlspecialchars($b['icon'] ?? 'fa-tag') ?>"></i>
                                            </div>
                                            <div>
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <h4 style="font-size: 1.15rem; font-weight: 700; margin: 0; color: var(--text-primary);"><?= htmlspecialchars($b['category_name']) ?></h4>
                                                    <span class="badge <?= $badgeClass ?>" style="font-size: 0.72rem;">
                                                        <?= $statusText ?>
                                                    </span>
                                                </div>
                                                <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 4px; display: flex; align-items: center; gap: 12px;">
                                                    <span>
                                                        <i class="fa-regular fa-calendar" style="margin-right: 4px; color: var(--text-muted);"></i>
                                                        <?= format_date($b['start_date']) ?> &rarr; <?= format_date($b['end_date']) ?>
                                                    </span>
                                                    <span>&bull;</span>
                                                    <span>
                                                        <i class="fa-regular fa-clock" style="margin-right: 4px; color: var(--text-muted);"></i>
                                                        <?= $daysLeft >= 0 ? ($daysLeft . ' days left') : 'Period expired' ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Circular mini gauge + Delete button -->
                                        <div style="display: flex; align-items: center; gap: 16px;">
                                            <div style="position: relative; width: 64px; height: 64px; display: flex; align-items: center; justify-content: center;">
                                                <svg width="64" height="64" viewBox="0 0 64 64" style="transform: rotate(-90deg);">
                                                    <circle cx="32" cy="32" r="<?= $radius ?>" stroke="rgba(255,255,255,0.08)" stroke-width="5" fill="none"/>
                                                    <circle cx="32" cy="32" r="<?= $radius ?>" stroke="<?= $strokeColor ?>" stroke-width="5" fill="none"
                                                            stroke-dasharray="<?= $circumference ?>" stroke-dashoffset="<?= $offset ?>"
                                                            stroke-linecap="round" style="transition: stroke-dashoffset 0.8s ease;"/>
                                                </svg>
                                                <div style="position: absolute; text-align: center;">
                                                    <span style="font-size: 0.75rem; font-weight: 800; font-family: var(--font-mono); color: var(--text-primary);"><?= $percentage ?>%</span>
                                                </div>
                                            </div>

                                            <form method="POST" action="budgets.php" id="deleteBudgetForm_<?= $b['budget_id'] ?>" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="budget_id" value="<?= $b['budget_id'] ?>">
                                                <button type="button" class="btn btn-outline btn-sm btn-icon" title="Delete Budget" onclick="confirmDeleteBudget(<?= $b['budget_id'] ?>, '<?= htmlspecialchars(addslashes($b['category_name'])) ?>')" style="border-color: rgba(244, 63, 94, 0.3); color: var(--accent-coral);">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>

                                    <!-- Progress Indicator -->
                                    <div class="progress-container">
                                        <div class="progress-header" style="font-size: 0.84rem; margin-bottom: 8px;">
                                            <span>
                                                Spent: <strong style="color: var(--text-primary); font-family: var(--font-mono);"><?= format_currency($spent) ?></strong> 
                                                <span style="color: var(--text-muted);">of <?= format_currency($limit) ?></span>
                                            </span>
                                            <span>
                                                <strong style="color: <?= $percentage >= 100 ? 'var(--accent-coral)' : ($percentage >= 80 ? 'var(--accent-amber)' : 'var(--accent-emerald)') ?>; font-family: var(--font-mono);"><?= $percentage ?>%</strong> limit used
                                            </span>
                                        </div>
                                        <div class="progress-bar-bg" style="height: 8px; background: rgba(255,255,255,0.06);">
                                            <div class="progress-bar-fill <?= $barColor ?>" style="width: <?= $barWidth ?>%;"></div>
                                        </div>
                                    </div>

                                    <!-- Remaining Capacity Footer -->
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 14px; font-size: 0.85rem; padding-top: 12px; border-top: 1px solid var(--border-card);">
                                        <span>
                                            Remaining: 
                                            <strong style="color: <?= $remaining < 0 ? 'var(--accent-coral)' : 'var(--accent-emerald)' ?>; font-family: var(--font-mono); font-weight: 700;">
                                                <?= format_currency($remaining) ?>
                                            </strong>
                                        </span>
                                        <span style="color: var(--text-secondary); font-size: 0.8rem;">
                                            <?php if ($remaining < 0): ?>
                                                <span style="color: var(--accent-coral); font-weight: 700;">
                                                    <i class="fa-solid fa-triangle-exclamation"></i> Over ceiling by <?= format_currency(abs($remaining)) ?>
                                                </span>
                                            <?php elseif ($percentage >= 80): ?>
                                                <span style="color: var(--accent-amber); font-weight: 600;">
                                                    <i class="fa-solid fa-shield-cat"></i> Near threshold capacity
                                                </span>
                                            <?php else: ?>
                                                <span style="color: var(--accent-emerald); font-weight: 500;">
                                                    <i class="fa-solid fa-circle-check"></i> Safe spending buffer
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Create Budget Card (Side Panel) -->
            <div class="card card-accent-primary" id="createBudgetCard" style="position: sticky; top: 96px;">
                <div class="card-header" style="display: flex; align-items: center; justify-content: space-between;">
                    <h3 class="card-title" style="font-size: 1rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 8px; margin: 0;">
                        <i class="fa-solid fa-sliders" style="color: var(--accent-primary);"></i>
                        Set Budget Ceiling
                    </h3>
                    <span class="badge badge-outline" style="font-size: 0.7rem;">Monthly</span>
                </div>
                <div class="card-body" style="padding: 20px;">
                    <form method="POST" action="budgets.php">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="action" value="create">

                        <div class="form-group" style="margin-bottom: 16px;">
                            <label class="form-label" for="category_id">Expense Category <span style="color: var(--accent-coral);">*</span></label>
                            <select id="category_id" name="category_id" class="form-select" required>
                                <option value="">-- Choose Category --</option>
                                <?php foreach ($expenseCategories as $cat): ?>
                                    <option value="<?= $cat['category_id'] ?>">
                                        <?= htmlspecialchars($cat['category_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom: 16px;">
                            <label class="form-label" for="budget_amount">Ceiling Limit (₹) <span style="color: var(--accent-coral);">*</span></label>
                            <div class="input-icon-wrapper">
                                <i class="fa-solid fa-indian-rupee-sign" style="color: var(--accent-primary);"></i>
                                <input type="number" step="0.01" min="1" id="budget_amount" name="budget_amount" class="form-control" placeholder="10000.00" required style="font-family: var(--font-mono); font-weight: 700;">
                            </div>
                            <div style="font-size: 0.74rem; color: var(--text-muted); margin-top: 4px;">Exceeding triggers alerts & dashboard warnings</div>
                        </div>

                        <div class="form-group" style="margin-bottom: 16px;">
                            <label class="form-label" for="start_date">Cycle Start Date</label>
                            <input type="date" id="start_date" name="start_date" class="form-control" value="<?= date('Y-m-01') ?>" required>
                        </div>

                        <div class="form-group" style="margin-bottom: 20px;">
                            <label class="form-label" for="end_date">Cycle End Date</label>
                            <input type="date" id="end_date" name="end_date" class="form-control" value="<?= date('Y-m-t') ?>" required>
                        </div>

                        <button type="submit" class="btn btn-primary btn-glow" style="width: 100%; padding: 12px; font-weight: 700;">
                            <i class="fa-solid fa-check"></i> Establish Budget
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div> <!-- End content-body -->
</div>

<script>
function openBudgetModal() {
    const card = document.getElementById('createBudgetCard');
    if (card) {
        card.scrollIntoView({ behavior: 'smooth' });
        const catSelect = document.getElementById('category_id');
        if (catSelect) catSelect.focus();
    }
}

function confirmDeleteBudget(id, name) {
    confirmAction(
        'Are you sure you want to delete the budget allocation for "' + name + '"? This will not delete associated transactions.',
        function() {
            document.getElementById('deleteBudgetForm_' + id).submit();
        },
        'Confirm Budget Deletion'
    );
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
