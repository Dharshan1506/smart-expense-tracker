<?php
/**
 * Main Fintech Dashboard - ExpenseIQ Command Center
 * Modern Fintech Financial Command Center with Circular Gauges,
 * Spending Heatmap, Depth KPI Cards, and 3NF Relational Intelligence.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$userId = $user['id'];
$pdo = getDBConnection();

$pageTitle = 'Fintech Command Center';
$currentPage = 'dashboard';

// ----------------------------------------------------------
// 1. Fetch Top Statistics (Income, Expense, Balance, Savings)
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

// Total Accumulated Savings
$stmtSavings = $pdo->prepare("SELECT COALESCE(SUM(saved_amount), 0) FROM savings_goals WHERE user_id = ?");
$stmtSavings->execute([$userId]);
$totalSavings = (float)$stmtSavings->fetchColumn();

// Financial Health Score & Savings Rate
$savingsRate = ($totalIncome > 0) ? max(0, round((($totalIncome - $totalExpense) / $totalIncome) * 100)) : 0;
$healthScore = min(98, max(25, round(60 + ($balance > 0 ? 24 : -25) + min(14, $savingsRate * 0.35))));
$healthLabel = ($healthScore >= 80) ? 'Excellent' : (($healthScore >= 60) ? 'Healthy' : 'Needs Caution');

// ----------------------------------------------------------
// 2. Fetch Middle Section Charts Data (6-Month Curves)
// ----------------------------------------------------------
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

// 2B. Cashflow Inflow vs Outflow
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
// 3. 30-Day Spending Heatmap Activity
// ----------------------------------------------------------
$stmtHeatmap = $pdo->prepare("
    SELECT DATE(transaction_date) as t_date, SUM(amount) as daily_spent
    FROM transactions
    WHERE user_id = ? AND transaction_type = 'expense'
      AND transaction_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 29 DAY)
    GROUP BY t_date
");
$stmtHeatmap->execute([$userId]);
$rawHeatmap = $stmtHeatmap->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

$heatmapDays = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $spent = (float)($rawHeatmap[$d] ?? 0);
    $level = 0;
    if ($spent > 0) {
        if ($spent < 500) $level = 1;
        elseif ($spent < 2000) $level = 2;
        elseif ($spent < 5000) $level = 3;
        else $level = 4;
    }
    $heatmapDays[] = ['date' => date('d M', strtotime($d)), 'spent' => $spent, 'level' => $level];
}

// ----------------------------------------------------------
// 4. Current Month Category Breakdown
// ----------------------------------------------------------
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

// ----------------------------------------------------------
// 5. Recent Transactions
// ----------------------------------------------------------
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
    LIMIT 5
");
$stmtRecent->execute([$userId]);
$recentTransactions = $stmtRecent->fetchAll();

// ----------------------------------------------------------
// 6. Active Budgets
// ----------------------------------------------------------
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
    LIMIT 3
");
$stmtBudgets->execute([$userId]);
$activeBudgets = $stmtBudgets->fetchAll();

// ----------------------------------------------------------
// 7. Active Savings Goals
// ----------------------------------------------------------
$stmtGoals = $pdo->prepare("
    SELECT goal_id, goal_name, target_amount, saved_amount, target_date
    FROM savings_goals
    WHERE user_id = ?
    ORDER BY target_date ASC
    LIMIT 2
");
$stmtGoals->execute([$userId]);
$activeGoals = $stmtGoals->fetchAll();

// ----------------------------------------------------------
// 8. Rule-Based Smart Insights
// ----------------------------------------------------------
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

        <!-- Quick Actions Ribbon -->
        <div class="quick-actions-bar">
            <button type="button" class="quick-action-btn" onclick="openAddTransactionModal('expense')" style="background: rgba(244, 63, 94, 0.15); border-color: rgba(244, 63, 94, 0.35);">
                <i class="fa-solid fa-minus" style="color: #fb7185;"></i>
                <span style="color: #fca5a5;">Add Expense</span>
            </button>
            <button type="button" class="quick-action-btn" onclick="openAddTransactionModal('income')" style="background: rgba(59, 130, 246, 0.15); border-color: rgba(59, 130, 246, 0.35);">
                <i class="fa-solid fa-plus" style="color: #60a5fa;"></i>
                <span style="color: #93c5fd;">Add Income</span>
            </button>
            <a href="budgets.php" class="quick-action-btn">
                <i class="fa-solid fa-calculator" style="color: #fbbf24;"></i>
                <span>Set Budget</span>
            </a>
            <a href="savings.php" class="quick-action-btn">
                <i class="fa-solid fa-piggy-bank" style="color: #38bdf8;"></i>
                <span>Savings Goal</span>
            </a>
            <a href="reports.php" class="quick-action-btn">
                <i class="fa-solid fa-file-invoice" style="color: #c084fc;"></i>
                <span>Statements</span>
            </a>
            <a href="queries.php" class="quick-action-btn" style="margin-left: auto; border-color: rgba(59, 130, 246, 0.45); background: rgba(30, 58, 138, 0.3); color: #93c5fd;">
                <i class="fa-solid fa-database" style="color: #60a5fa;"></i>
                <span style="font-weight: 700;">DBMS 20 SQL Queries</span>
            </a>
        </div>

        <!-- ====================================================
             1. 4 DEPTH KPI CARDS WITH NUMBER COUNTERS
             ==================================================== -->
        <div class="grid-stats">
            <!-- Total Income (Blue/Purple) -->
            <div class="stat-card stat-card-income">
                <div class="stat-header">
                    <span class="stat-label">Total Income</span>
                    <div class="stat-icon-wrapper">
                        <i class="fa-solid fa-arrow-trend-up"></i>
                    </div>
                </div>
                <div class="stat-value animate-counter" data-target="<?= $totalIncome ?>" data-currency="true">
                    <?= format_currency($totalIncome) ?>
                </div>
                <div class="stat-footer">
                    <span>Lifetime Inflow</span>
                    <span class="stat-pill"><i class="fa-solid fa-circle-arrow-up"></i> Active</span>
                </div>
            </div>

            <!-- Total Expenses (Coral/Red) -->
            <div class="stat-card stat-card-expense">
                <div class="stat-header">
                    <span class="stat-label">Total Expenses</span>
                    <div class="stat-icon-wrapper">
                        <i class="fa-solid fa-arrow-trend-down"></i>
                    </div>
                </div>
                <div class="stat-value animate-counter" data-target="<?= $totalExpense ?>" data-currency="true">
                    <?= format_currency($totalExpense) ?>
                </div>
                <div class="stat-footer">
                    <span>Cumulative Outflow</span>
                    <span class="stat-pill"><i class="fa-solid fa-circle-arrow-down"></i> Debited</span>
                </div>
            </div>

            <!-- Net Balance (Cyan/Blue) -->
            <div class="stat-card stat-card-balance">
                <div class="stat-header">
                    <span class="stat-label">Net Balance</span>
                    <div class="stat-icon-wrapper">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                </div>
                <div class="stat-value animate-counter" data-target="<?= $balance ?>" data-currency="true">
                    <?= format_currency($balance) ?>
                </div>
                <div class="stat-footer">
                    <span>Available Liquidity</span>
                    <span class="stat-pill"><i class="fa-solid fa-shield-halved"></i> Surplus</span>
                </div>
            </div>

            <!-- Total Savings (Emerald/Green) -->
            <div class="stat-card stat-card-savings">
                <div class="stat-header">
                    <span class="stat-label">Total Savings</span>
                    <div class="stat-icon-wrapper">
                        <i class="fa-solid fa-piggy-bank"></i>
                    </div>
                </div>
                <div class="stat-value animate-counter" data-target="<?= $totalSavings ?>" data-currency="true">
                    <?= format_currency($totalSavings) ?>
                </div>
                <div class="stat-footer">
                    <span>Milestone Reserves</span>
                    <span class="stat-pill"><i class="fa-solid fa-bullseye"></i> Locked</span>
                </div>
            </div>
        </div>

        <!-- ====================================================
             2. ADVANCED VISUALS: HEALTH GAUGE & SPENDING HEATMAP
             ==================================================== -->
        <div class="grid-2" style="grid-template-columns: 320px 1fr; align-items: stretch; margin-bottom: 24px;">
            <!-- Financial Health Score Card -->
            <div class="card" style="margin-bottom: 0; display: flex; flex-direction: column; justify-content: space-between;">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fa-solid fa-heart-pulse" style="color: #10b981;"></i>
                        Financial Health
                    </h3>
                    <span class="badge badge-success"><?= $healthLabel ?></span>
                </div>
                <div class="card-body" style="text-align: center; padding: 24px 18px;">
                    <div class="circle-gauge" style="width: 140px; height: 140px; margin: 0 auto 16px;">
                        <svg width="140" height="140" viewBox="0 0 140 140">
                            <circle class="circle-gauge-bg" cx="70" cy="70" r="58" stroke-width="12"></circle>
                            <circle cx="70" cy="70" r="58" stroke-width="12" stroke="url(#healthRingGrad)" stroke-dasharray="364.4" stroke-dashoffset="<?= 364.4 - (364.4 * ($healthScore / 100)) ?>"></circle>
                            <defs>
                                <linearGradient id="healthRingGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#10b981" />
                                    <stop offset="50%" stop-color="#06b6d4" />
                                    <stop offset="100%" stop-color="#3b82f6" />
                                </linearGradient>
                            </defs>
                        </svg>
                        <div class="circle-gauge-val">
                            <div class="circle-gauge-number"><?= $healthScore ?></div>
                            <div class="circle-gauge-label">Score / 100</div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px; font-size: 0.8rem;">
                        <div style="background: rgba(20, 31, 54, 0.6); padding: 8px; border-radius: var(--radius-sm); border: 1px solid rgba(255,255,255,0.06);">
                            <div style="color: var(--text-muted);">Savings Rate</div>
                            <strong style="color: #34d399; font-family: var(--font-mono); font-size: 0.95rem;"><?= $savingsRate ?>%</strong>
                        </div>
                        <div style="background: rgba(20, 31, 54, 0.6); padding: 8px; border-radius: var(--radius-sm); border: 1px solid rgba(255,255,255,0.06);">
                            <div style="color: var(--text-muted);">Liquidity Buffer</div>
                            <strong style="color: #60a5fa; font-family: var(--font-mono); font-size: 0.95rem;">Positive</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Spending Heatmap Activity Matrix (30 Days) -->
            <div class="card" style="margin-bottom: 0;">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fa-solid fa-fire" style="color: #f43f5e;"></i>
                        30-Day Spending Intensity Matrix
                    </h3>
                    <div style="display: flex; align-items: center; gap: 6px; font-size: 0.72rem; color: var(--text-muted);">
                        <span>Less</span>
                        <span style="width: 10px; height: 10px; border-radius: 2px; background: rgba(255,255,255,0.05);"></span>
                        <span style="width: 10px; height: 10px; border-radius: 2px; background: rgba(59,130,246,0.4);"></span>
                        <span style="width: 10px; height: 10px; border-radius: 2px; background: rgba(139,92,246,0.7);"></span>
                        <span style="width: 10px; height: 10px; border-radius: 2px; background: #f43f5e;"></span>
                        <span>More</span>
                    </div>
                </div>
                <div class="card-body">
                    <p style="font-size: 0.84rem; color: var(--text-secondary); margin-bottom: 14px;">
                        Daily expenditure velocity over the preceding 30 days. Darker/hotter cells represent higher spending volume.
                    </p>
                    <div class="heatmap-container">
                        <div class="heatmap-grid">
                            <?php foreach ($heatmapDays as $day): ?>
                                <div class="heatmap-cell heatmap-lvl-<?= $day['level'] ?>" title="<?= $day['date'] ?>: <?= format_currency($day['spent']) ?>"></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ====================================================
             3. INTERACTIVE CHARTS: TREND & CASHFLOW COMPARISON
             ==================================================== -->
        <div class="grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
            <!-- Chart 1: Monthly Expense Trend -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fa-solid fa-chart-area" style="color: #fb7185;"></i>
                        Monthly Expense Trend (₹)
                    </h3>
                    <span class="badge badge-danger">Outflow Curve</span>
                </div>
                <div class="card-body">
                    <?php if (empty($expenseTrendAmounts) || array_sum($expenseTrendAmounts) == 0): ?>
                        <div style="text-align: center; padding: 40px 0; color: var(--text-muted);">
                            <i class="fa-solid fa-chart-line" style="font-size: 2rem; margin-bottom: 10px;"></i>
                            <div>No Expense Data Logged</div>
                        </div>
                    <?php else: ?>
                        <div style="height: 270px;">
                            <canvas id="monthlyExpenseChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Chart 2: Income vs Expense Comparison -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fa-solid fa-chart-column" style="color: #60a5fa;"></i>
                        Income vs Expense Performance
                    </h3>
                    <span class="badge badge-primary">Cash Flow Balance</span>
                </div>
                <div class="card-body">
                    <?php if (empty($trendLabels)): ?>
                        <div style="text-align: center; padding: 40px 0; color: var(--text-muted);">
                            <i class="fa-solid fa-scale-balanced" style="font-size: 2rem; margin-bottom: 10px;"></i>
                            <div>No Comparison Records Available</div>
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
             4. CATEGORY BREAKDOWN & BUDGET PROGRESS
             ==================================================== -->
        <div class="grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
            <!-- Category Breakdown -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fa-solid fa-chart-pie" style="color: #c084fc;"></i>
                        Category Spending (<?= date('F Y') ?>)
                    </h3>
                    <a href="categories.php" class="btn btn-outline btn-sm">All Categories</a>
                </div>
                <div class="card-body">
                    <?php if (empty($catChartData)): ?>
                        <div style="text-align: center; padding: 36px 0; color: var(--text-muted);">
                            <i class="fa-solid fa-tags" style="font-size: 1.8rem; margin-bottom: 10px;"></i>
                            <div>No category expenditures this month</div>
                        </div>
                    <?php else: ?>
                        <div style="height: 210px; margin-bottom: 16px;">
                            <canvas id="categoryChart"></canvas>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <?php foreach (array_slice($catChartData, 0, 4) as $cat): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; padding: 8px 12px; background: rgba(20, 31, 54, 0.6); border: 1px solid rgba(255,255,255,0.06); border-radius: var(--radius-sm);">
                                    <span style="display: flex; align-items: center; gap: 8px; font-weight: 600;">
                                        <span style="width: 10px; height: 10px; border-radius: 50%; background: <?= htmlspecialchars($cat['color']) ?>;"></span>
                                        <?= htmlspecialchars($cat['category_name']) ?>
                                    </span>
                                    <span style="font-family: var(--font-mono); font-weight: 700;"><?= format_currency($cat['total_amount']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Active Budgets & Threshold Warnings -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fa-solid fa-calculator" style="color: #fbbf24;"></i>
                        Budget Caps & Progress
                    </h3>
                    <a href="budgets.php" class="btn btn-outline btn-sm">Manage</a>
                </div>
                <div class="card-body">
                    <?php if (empty($activeBudgets)): ?>
                        <div style="text-align: center; padding: 36px 0; color: var(--text-muted);">
                            <i class="fa-solid fa-shield-halved" style="font-size: 1.8rem; margin-bottom: 10px;"></i>
                            <div>No active budget thresholds established</div>
                            <div style="margin-top: 12px;">
                                <a href="budgets.php" class="btn btn-primary btn-sm">+ Set Budget</a>
                            </div>
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
                                            <span style="width: 28px; height: 28px; border-radius: 6px; background: <?= htmlspecialchars($b['color']) ?>25; color: <?= htmlspecialchars($b['color']) ?>; display: inline-flex; align-items: center; justify-content: center; font-size: 0.85rem;">
                                                <i class="fa-solid <?= htmlspecialchars($b['icon']) ?>"></i>
                                            </span>
                                            <span style="font-weight: 700; font-size: 0.88rem;"><?= htmlspecialchars($b['category_name']) ?></span>
                                        </div>
                                        <div style="text-align: right;">
                                            <span style="font-size: 0.85rem; font-weight: 700; font-family: var(--font-mono);"><?= format_currency($spent) ?></span>
                                            <span style="font-size: 0.76rem; color: var(--text-muted);">/ <?= format_currency($budgetAmt) ?></span>
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

        <!-- ====================================================
             5. RECENT TRANSACTIONS & SMART INSIGHTS
             ==================================================== -->
        <div class="grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
            <!-- Recent Transactions Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fa-solid fa-clock-rotate-left" style="color: #38bdf8;"></i>
                        Recent Transactions
                    </h3>
                    <a href="transactions.php" class="btn btn-outline btn-sm">Full Ledger</a>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php if (empty($recentTransactions)): ?>
                        <div style="text-align: center; padding: 36px 0; color: var(--text-muted);">
                            <i class="fa-solid fa-receipt" style="font-size: 1.8rem; margin-bottom: 10px;"></i>
                            <div>No transactions logged yet</div>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
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
                                            <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;">
                                                <?= format_date($tx['transaction_date']) ?>
                                            </td>
                                            <td style="font-weight: 600; color: #ffffff; max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                <?= htmlspecialchars($tx['description']) ?>
                                            </td>
                                            <td>
                                                <span style="font-size: 0.75rem; background: <?= htmlspecialchars($tx['color']) ?>20; color: <?= htmlspecialchars($tx['color']) ?>; padding: 3px 8px; border-radius: var(--radius-full); font-weight: 700;">
                                                    <i class="fa-solid <?= htmlspecialchars($tx['icon']) ?>"></i> <?= htmlspecialchars($tx['category_name']) ?>
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

            <!-- Smart Financial Insights -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fa-solid fa-wand-magic-sparkles" style="color: #60a5fa;"></i>
                        Smart Financial Insights
                    </h3>
                    <span class="badge badge-primary">Rule Engine</span>
                </div>
                <div class="card-body">
                    <?php if (empty($insights)): ?>
                        <div style="text-align: center; padding: 36px 0; color: var(--text-muted);">
                            <i class="fa-solid fa-shield-check" style="font-size: 1.8rem; margin-bottom: 10px; color: #10b981;"></i>
                            <div>All financial metrics within healthy safety limits</div>
                        </div>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <?php foreach ($insights as $insight): ?>
                                <div style="background: rgba(20, 31, 54, 0.6); border: 1px solid rgba(255, 255, 255, 0.08); border-left: 3px solid <?= ($insight['type'] === 'danger') ? '#ef4444' : (($insight['type'] === 'warning') ? '#f59e0b' : '#3b82f6') ?>; border-radius: var(--radius-md); padding: 12px 14px; display: flex; gap: 12px; align-items: flex-start;">
                                    <div style="color: <?= ($insight['type'] === 'danger') ? '#fb7185' : (($insight['type'] === 'warning') ? '#fbbf24' : '#60a5fa') ?>; font-size: 1.1rem; margin-top: 2px;">
                                        <i class="fa-solid <?= htmlspecialchars($insight['icon']) ?>"></i>
                                    </div>
                                    <div>
                                        <div style="font-weight: 700; font-size: 0.88rem; color: #ffffff; margin-bottom: 2px;"><?= htmlspecialchars($insight['title']) ?></div>
                                        <div style="font-size: 0.82rem; color: #cbd5e1; line-height: 1.5;"><?= $insight['message'] ?></div>
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
