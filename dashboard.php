<?php
/**
 * Main Fintech Dashboard
 * Structured into:
 * 1. TOP STATISTICS (Total Income, Total Expenses, Balance, Savings)
 * 2. MIDDLE SECTION (Monthly Expense Chart & Income vs Expense Chart)
 * 3. BOTTOM SECTION (Expense by Category, Recent Transactions, Budget Progress, Smart Insights)
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$userId = $user['id'];
$pdo = getDBConnection();

$pageTitle = 'Fintech Dashboard';
$currentPage = 'dashboard';

// ----------------------------------------------------------
// 1. Fetch Top Statistics (Total Income, Total Expenses, Balance, Savings)
// ----------------------------------------------------------
$stmtLifetime = $pdo->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0) AS total_income,
        COALESCE(SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END), 0) AS total_expense
    FROM transactions 
    WHERE user_id = ?
");
$stmtLifetime->execute([$userId]);
$lifetime = $stmtLifetime->fetch();
$totalIncome = (float)$lifetime['total_income'];
$totalExpense = (float)$lifetime['total_expense'];
$balance = $totalIncome - $totalExpense;

// Total Accumulated Savings (from savings_goals)
$stmtSavings = $pdo->prepare("SELECT COALESCE(SUM(saved_amount), 0) FROM savings_goals WHERE user_id = ?");
$stmtSavings->execute([$userId]);
$totalSavings = (float)$stmtSavings->fetchColumn();

// ----------------------------------------------------------
// 2. Fetch Middle Section Charts Data
// ----------------------------------------------------------

// 2A. Monthly Expense Trend (Last 6 Months)
$stmtMonthlyExp = $pdo->prepare("
    SELECT 
        DATE_FORMAT(transaction_date, '%b %Y') AS month_label,
        YEAR(transaction_date) as t_year,
        MONTH(transaction_date) as t_month,
        SUM(amount) AS monthly_expense
    FROM transactions
    WHERE user_id = ? AND transaction_type = 'expense'
      AND transaction_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 5 MONTH)
    GROUP BY t_year, t_month, month_label
    ORDER BY t_year ASC, t_month ASC
");
$stmtMonthlyExp->execute([$userId]);
$monthlyExpData = $stmtMonthlyExp->fetchAll();

$expenseTrendLabels = array_column($monthlyExpData, 'month_label');
$expenseTrendAmounts = array_map('floatval', array_column($monthlyExpData, 'monthly_expense'));

// 2B. Income vs Expense Comparison (Last 6 Months)
$stmtCashflow = $pdo->prepare("
    SELECT 
        DATE_FORMAT(transaction_date, '%b %Y') AS month_label,
        YEAR(transaction_date) as t_year,
        MONTH(transaction_date) as t_month,
        SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) AS monthly_income,
        SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) AS monthly_expense
    FROM transactions
    WHERE user_id = ? AND transaction_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 5 MONTH)
    GROUP BY t_year, t_month, month_label
    ORDER BY t_year ASC, t_month ASC
");
$stmtCashflow->execute([$userId]);
$cashflowData = $stmtCashflow->fetchAll();

$trendLabels = array_column($cashflowData, 'month_label');
$trendIncome = array_map('floatval', array_column($cashflowData, 'monthly_income'));
$trendExpense = array_map('floatval', array_column($cashflowData, 'monthly_expense'));

// ----------------------------------------------------------
// 3. Fetch Bottom Section Data
// ----------------------------------------------------------

// 3A. Expense by Category (Current Month Doughnut)
$stmtCatChart = $pdo->prepare("
    SELECT c.category_name, c.color, SUM(t.amount) AS total_amount
    FROM transactions t
    INNER JOIN categories c ON t.category_id = c.category_id
    WHERE t.user_id = ? AND t.transaction_type = 'expense'
      AND MONTH(t.transaction_date) = MONTH(CURRENT_DATE())
      AND YEAR(t.transaction_date) = YEAR(CURRENT_DATE())
    GROUP BY c.category_id, c.category_name, c.color
    ORDER BY total_amount DESC
");
$stmtCatChart->execute([$userId]);
$catChartData = $stmtCatChart->fetchAll();

$catLabels = array_column($catChartData, 'category_name');
$catAmounts = array_map('floatval', array_column($catChartData, 'total_amount'));
$catColors = array_column($catChartData, 'color');

// 3B. Recent Transactions (Last 6 entries)
$stmtRecent = $pdo->prepare("
    SELECT 
        t.transaction_id, 
        t.amount, 
        t.transaction_type, 
        t.description, 
        t.transaction_date,
        c.category_name, 
        c.icon, 
        c.color
    FROM transactions t
    INNER JOIN categories c ON t.category_id = c.category_id
    WHERE t.user_id = ?
    ORDER BY t.transaction_date DESC, t.transaction_id DESC
    LIMIT 6
");
$stmtRecent->execute([$userId]);
$recentTransactions = $stmtRecent->fetchAll();

// 3C. Budget Progress
$stmtBudgets = $pdo->prepare("
    SELECT 
        b.budget_id, 
        b.budget_amount, 
        c.category_name, 
        c.color, 
        c.icon,
        COALESCE(SUM(t.amount), 0) AS spent_amount
    FROM budgets b
    INNER JOIN categories c ON b.category_id = c.category_id
    LEFT JOIN transactions t ON t.category_id = b.category_id 
        AND t.user_id = b.user_id 
        AND t.transaction_type = 'expense'
        AND t.transaction_date BETWEEN b.start_date AND b.end_date
    WHERE b.user_id = ? 
      AND CURRENT_DATE() BETWEEN b.start_date AND b.end_date
    GROUP BY b.budget_id, b.budget_amount, c.category_name, c.color, c.icon
    ORDER BY (COALESCE(SUM(t.amount), 0) / b.budget_amount) DESC
    LIMIT 4
");
$stmtBudgets->execute([$userId]);
$activeBudgets = $stmtBudgets->fetchAll();

// 3D. Rule-Based Smart Insights
$insights = get_smart_insights($pdo, $userId);

$flash = get_flash();
$extraScripts = ['assets/js/dashboard.js'];

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

        <!-- ====================================================
             1. TOP STATISTICS: Income, Expenses, Balance, Savings
             ==================================================== -->
        <div class="grid-stats">
            <!-- Total Income -->
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Total Income</span>
                    <div class="stat-icon-wrapper icon-income">
                        <i class="fa-solid fa-arrow-down-left"></i>
                    </div>
                </div>
                <div class="stat-value" style="color: #059669;"><?= format_currency($totalIncome) ?></div>
                <div class="stat-footer">
                    <span>Lifetime Inflow</span>
                </div>
            </div>

            <!-- Total Expenses -->
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Total Expenses</span>
                    <div class="stat-icon-wrapper icon-expense">
                        <i class="fa-solid fa-arrow-up-right"></i>
                    </div>
                </div>
                <div class="stat-value" style="color: #dc2626;"><?= format_currency($totalExpense) ?></div>
                <div class="stat-footer">
                    <span>Lifetime Outflow</span>
                </div>
            </div>

            <!-- Net Balance -->
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Net Balance</span>
                    <div class="stat-icon-wrapper icon-balance">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                </div>
                <div class="stat-value" style="color: <?= $balance >= 0 ? 'var(--text-primary)' : 'var(--danger)' ?>;">
                    <?= format_currency($balance) ?>
                </div>
                <div class="stat-footer">
                    <span>Available Liquidity</span>
                </div>
            </div>

            <!-- Total Savings -->
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Total Savings</span>
                    <div class="stat-icon-wrapper icon-savings">
                        <i class="fa-solid fa-piggy-bank"></i>
                    </div>
                </div>
                <div class="stat-value" style="color: #0284c7;"><?= format_currency($totalSavings) ?></div>
                <div class="stat-footer">
                    <span>Target Goals Reserves</span>
                </div>
            </div>
        </div>

        <!-- ====================================================
             2. MIDDLE SECTION: Monthly Expense Chart & Income vs Expense Chart
             ==================================================== -->
        <div class="grid-2">
            <!-- Chart 1: Monthly Expense Chart -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fa-solid fa-chart-area" style="color: #ef4444;"></i>
                        Monthly Expense Trend
                    </h3>
                    <span class="badge badge-danger">Outflow Trajectory</span>
                </div>
                <div class="card-body">
                    <?php if (empty($expenseTrendAmounts) || array_sum($expenseTrendAmounts) == 0): ?>
                        <div class="empty-state" style="padding: 28px 0;">
                            <div class="empty-state-icon"><i class="fa-solid fa-chart-line"></i></div>
                            <div class="empty-state-title">No Expense Data Recorded</div>
                            <p class="empty-state-text">Record expenses to visualize your monthly spending velocity over time.</p>
                        </div>
                    <?php else: ?>
                        <div style="height: 270px;">
                            <canvas id="monthlyExpenseChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Chart 2: Income vs Expense Chart -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fa-solid fa-chart-column" style="color: #3b82f6;"></i>
                        Income vs Expense Comparison
                    </h3>
                    <span class="badge badge-primary">Cash Flow Balance</span>
                </div>
                <div class="card-body">
                    <?php if (empty($trendLabels)): ?>
                        <div class="empty-state" style="padding: 28px 0;">
                            <div class="empty-state-icon"><i class="fa-solid fa-scale-balanced"></i></div>
                            <div class="empty-state-title">No Comparison Records</div>
                            <p class="empty-state-text">Add income and expenses to view side-by-side cashflow performance.</p>
                        </div>
                    <?php else: ?>
                        <div style="height: 270px;">
                            <canvas id="cashFlowChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ====================================================
             3. BOTTOM SECTION: Category Expense, Recent Tx, Budgets, Smart Insights
             ==================================================== -->
        <div class="grid-2">
            <!-- Bottom Item 1: Expense by Category -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fa-solid fa-chart-pie" style="color: #8b5cf6;"></i>
                        Expense by Category (Current Month)
                    </h3>
                    <a href="categories.php" class="btn btn-outline btn-sm">All Categories</a>
                </div>
                <div class="card-body">
                    <?php if (empty($catChartData)): ?>
                        <div class="empty-state" style="padding: 28px 0;">
                            <div class="empty-state-icon"><i class="fa-solid fa-tags"></i></div>
                            <div class="empty-state-title">No Expenses This Month</div>
                            <p class="empty-state-text">Expenses logged for <?= date('F Y') ?> will be partitioned by category here.</p>
                            <button type="button" class="btn btn-primary btn-sm" onclick="openAddTransactionModal()">Add Expense</button>
                        </div>
                    <?php else: ?>
                        <div style="height: 230px; margin-bottom: 16px;">
                            <canvas id="categoryChart"></canvas>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <?php foreach (array_slice($catChartData, 0, 4) as $cat): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; padding: 6px 10px; background: var(--bg-main); border-radius: var(--radius-sm);">
                                    <span style="display: flex; align-items: center; gap: 8px; font-weight: 600;">
                                        <span style="width: 10px; height: 10px; border-radius: 50%; background: <?= htmlspecialchars($cat['color']) ?>;"></span>
                                        <?= htmlspecialchars($cat['category_name']) ?>
                                    </span>
                                    <span style="font-family: monospace; font-weight: 700;"><?= format_currency($cat['total_amount']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Bottom Item 2: Budget Progress -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fa-solid fa-calculator" style="color: #f59e0b;"></i>
                        Active Budget Limits
                    </h3>
                    <a href="budgets.php" class="btn btn-outline btn-sm">Manage Budgets</a>
                </div>
                <div class="card-body">
                    <?php if (empty($activeBudgets)): ?>
                        <div class="empty-state" style="padding: 28px 0;">
                            <div class="empty-state-icon"><i class="fa-solid fa-shield-halved"></i></div>
                            <div class="empty-state-title">No Active Budgets</div>
                            <p class="empty-state-text">Set category spending limits to keep expenditure under control.</p>
                            <a href="budgets.php" class="btn btn-primary btn-sm">+ Create Budget</a>
                        </div>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 18px;">
                            <?php foreach ($activeBudgets as $b): 
                                $spent = (float)$b['spent_amount'];
                                $budgetAmt = (float)$b['budget_amount'];
                                $pct = $budgetAmt > 0 ? round(($spent / $budgetAmt) * 100) : 0;
                                $barClass = ($pct >= 100) ? 'progress-danger' : (($pct >= 80) ? 'progress-warning' : 'progress-normal');
                            ?>
                                <div>
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span style="width: 28px; height: 28px; border-radius: 6px; background: <?= htmlspecialchars($b['color']) ?>20; color: <?= htmlspecialchars($b['color']) ?>; display: inline-flex; align-items: center; justify-content: center; font-size: 0.85rem;">
                                                <i class="fa-solid <?= htmlspecialchars($b['icon']) ?>"></i>
                                            </span>
                                            <span style="font-weight: 700; font-size: 0.88rem;"><?= htmlspecialchars($b['category_name']) ?></span>
                                        </div>
                                        <div style="text-align: right;">
                                            <span style="font-size: 0.85rem; font-weight: 700;"><?= format_currency($spent) ?></span>
                                            <span style="font-size: 0.78rem; color: var(--text-secondary);">/ <?= format_currency($budgetAmt) ?></span>
                                            <span class="badge <?= ($pct >= 100) ? 'badge-danger' : (($pct >= 80) ? 'badge-warning' : 'badge-primary') ?>" style="margin-left: 6px;">
                                                <?= $pct ?>%
                                            </span>
                                        </div>
                                    </div>
                                    <div class="progress-bar-bg">
                                        <div class="progress-bar-fill <?= $barClass ?>" style="width: <?= min(100, $pct) ?>%;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Bottom Row 2: Recent Transactions & Smart Insights -->
        <div class="grid-2">
            <!-- Bottom Item 3: Recent Transactions -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fa-solid fa-clock-rotate-left" style="color: #3b82f6;"></i>
                        Recent Transactions
                    </h3>
                    <a href="transactions.php" class="btn btn-outline btn-sm">View Ledger</a>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php if (empty($recentTransactions)): ?>
                        <div class="empty-state" style="padding: 36px 20px;">
                            <div class="empty-state-icon"><i class="fa-solid fa-receipt"></i></div>
                            <div class="empty-state-title">No Transactions Yet</div>
                            <p class="empty-state-text">Your financial ledger is currently empty. Record your first transaction to populate your dashboard.</p>
                            <button type="button" class="btn btn-primary btn-sm" onclick="openAddTransactionModal()">+ Add Transaction</button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table" style="margin-bottom: 0;">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th>Category</th>
                                        <th style="text-align: right;">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentTransactions as $tx): ?>
                                        <tr>
                                            <td style="white-space: nowrap; font-size: 0.82rem; color: var(--text-secondary);">
                                                <?= format_date($tx['transaction_date']) ?>
                                            </td>
                                            <td style="font-weight: 600; color: var(--text-primary); max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                <?= htmlspecialchars($tx['description']) ?>
                                            </td>
                                            <td>
                                                <span class="category-badge">
                                                    <span style="color: <?= htmlspecialchars($tx['color']) ?>;"><i class="fa-solid <?= htmlspecialchars($tx['icon']) ?>"></i></span>
                                                    <?= htmlspecialchars($tx['category_name']) ?>
                                                </span>
                                            </td>
                                            <td style="text-align: right; white-space: nowrap;">
                                                <?php if ($tx['transaction_type'] === 'income'): ?>
                                                    <span class="amount-income">+<?= format_currency($tx['amount']) ?></span>
                                                <?php else: ?>
                                                    <span class="amount-expense">-<?= format_currency($tx['amount']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Bottom Item 4: Smart Financial Insights -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fa-solid fa-lightbulb" style="color: #f59e0b;"></i>
                        Smart Spending Insights
                    </h3>
                    <span class="badge badge-primary">DBMS Rule Engine</span>
                </div>
                <div class="card-body">
                    <?php if (empty($insights)): ?>
                        <div class="empty-state" style="padding: 28px 0;">
                            <div class="empty-state-icon"><i class="fa-solid fa-shield-check"></i></div>
                            <div class="empty-state-title">No Financial Alerts</div>
                            <p class="empty-state-text">Your financial activity is disciplined and no budget thresholds have been exceeded.</p>
                        </div>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <?php foreach ($insights as $insight): ?>
                                <div class="insight-item <?= htmlspecialchars($insight['type']) ?>" style="background: var(--bg-main); border: 1px solid var(--border-color);">
                                    <div class="insight-icon"><i class="fa-solid <?= htmlspecialchars($insight['icon']) ?>"></i></div>
                                    <div class="insight-text" style="color: var(--text-primary);">
                                        <strong style="color: var(--text-primary); font-size: 0.88rem;"><?= htmlspecialchars($insight['title']) ?>:</strong><br>
                                        <span style="font-size: 0.84rem; color: var(--text-secondary);"><?= $insight['message'] ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div> <!-- End content-body -->
</div>

<!-- Send Data to Chart.js Initialization Script -->
<script>
window.DashboardData = {
    expenseTrendLabels: <?= json_encode($expenseTrendLabels) ?>,
    expenseTrendAmounts: <?= json_encode($expenseTrendAmounts) ?>,
    trendLabels: <?= json_encode($trendLabels) ?>,
    trendIncome: <?= json_encode($trendIncome) ?>,
    trendExpense: <?= json_encode($trendExpense) ?>,
    catLabels: <?= json_encode($catLabels) ?>,
    catAmounts: <?= json_encode($catAmounts) ?>,
    catColors: <?= json_encode($catColors) ?>
};
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
