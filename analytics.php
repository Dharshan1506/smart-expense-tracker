<?php
/**
 * Analytics & Spending Intelligence
 * Comprehensive financial breakdowns across daily, weekly, monthly, and category dimensions
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$userId = $user['id'];
$pdo = getDBConnection();

$pageTitle = 'Spending Analytics';
$currentPage = 'analytics';

// ----------------------------------------------------------
// 1. Highest Spending Category (Current Month & Lifetime)
// ----------------------------------------------------------
$stmtHighest = $pdo->prepare("
    SELECT c.category_name, c.color, c.icon, SUM(t.amount) as total_spent, COUNT(t.transaction_id) as tx_count
    FROM transactions t
    INNER JOIN categories c ON t.category_id = c.category_id
    WHERE t.user_id = ? AND t.transaction_type = 'expense'
    GROUP BY c.category_id, c.category_name, c.color, c.icon
    ORDER BY total_spent DESC
    LIMIT 1
");
$stmtHighest->execute([$userId]);
$highestCategory = $stmtHighest->fetch();

// ----------------------------------------------------------
// 2. Average Monthly Expense (DBMS Subquery + AVG)
// ----------------------------------------------------------
$stmtAvg = $pdo->prepare("
    SELECT AVG(monthly_sum) as avg_monthly_expense
    FROM (
        SELECT SUM(amount) as monthly_sum
        FROM transactions
        WHERE user_id = ? AND transaction_type = 'expense'
        GROUP BY YEAR(transaction_date), MONTH(transaction_date)
    ) as monthly_totals
");
$stmtAvg->execute([$userId]);
$avgMonthlyExpense = (float)($stmtAvg->fetchColumn() ?: 0.0);

// ----------------------------------------------------------
// 3. Daily Expense Breakdown (Last 14 Days)
// ----------------------------------------------------------
$stmtDaily = $pdo->prepare("
    SELECT 
        DATE_FORMAT(transaction_date, '%b %d') as day_label,
        transaction_date,
        SUM(amount) as daily_spent
    FROM transactions
    WHERE user_id = ? AND transaction_type = 'expense'
      AND transaction_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 14 DAY)
    GROUP BY transaction_date, day_label
    ORDER BY transaction_date ASC
");
$stmtDaily->execute([$userId]);
$dailyData = $stmtDaily->fetchAll();

// ----------------------------------------------------------
// 4. Monthly Cashflow & Trends (Last 12 Months)
// ----------------------------------------------------------
$stmtMonthly = $pdo->prepare("
    SELECT 
        DATE_FORMAT(transaction_date, '%b %Y') as month_label,
        YEAR(transaction_date) as y,
        MONTH(transaction_date) as m,
        SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) as income,
        SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) as expense
    FROM transactions
    WHERE user_id = ? AND transaction_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 11 MONTH)
    GROUP BY y, m, month_label
    ORDER BY y ASC, m ASC
");
$stmtMonthly->execute([$userId]);
$monthlyData = $stmtMonthly->fetchAll();

// ----------------------------------------------------------
// 5. Category-wise Distribution (All-Time Expense Breakdown)
// ----------------------------------------------------------
$stmtCatAll = $pdo->prepare("
    SELECT 
        c.category_name, 
        c.color, 
        c.icon, 
        SUM(t.amount) as total_spent, 
        COUNT(t.transaction_id) as tx_count,
        ROUND((SUM(t.amount) / (
            SELECT SUM(amount) FROM transactions WHERE user_id = ? AND transaction_type = 'expense'
        ) * 100), 1) as percentage
    FROM transactions t
    INNER JOIN categories c ON t.category_id = c.category_id
    WHERE t.user_id = ? AND t.transaction_type = 'expense'
    GROUP BY c.category_id, c.category_name, c.color, c.icon
    ORDER BY total_spent DESC
");
$stmtCatAll->execute([$userId, $userId]);
$categoryBreakdown = $stmtCatAll->fetchAll();

$flash = get_flash();
$extraScripts = ['assets/js/analytics.js'];

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="main-wrapper">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="content-body">
        <?php if ($flash): ?>
            <div class="flash-alert flash-<?= htmlspecialchars($flash['type']) ?>">
                <i class="fa-solid fa-circle-info"></i>
                <span><?= htmlspecialchars($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <!-- Analytical Highlights Grid -->
        <div class="grid-stats" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
            
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Highest Spending Category</span>
                    <div class="stat-icon-wrapper" style="background: <?= htmlspecialchars($highestCategory['color'] ?? '#ef4444') ?>20; color: <?= htmlspecialchars($highestCategory['color'] ?? '#ef4444') ?>;">
                        <i class="fa-solid <?= htmlspecialchars($highestCategory['icon'] ?? 'fa-fire') ?>"></i>
                    </div>
                </div>
                <div class="stat-value" style="font-size: 1.5rem;">
                    <?= htmlspecialchars($highestCategory['category_name'] ?? 'None') ?>
                </div>
                <div class="stat-footer">
                    <span><?= format_currency($highestCategory['total_spent'] ?? 0) ?> spent across <?= (int)($highestCategory['tx_count'] ?? 0) ?> transactions</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Average Monthly Expense</span>
                    <div class="stat-icon-wrapper icon-monthly">
                        <i class="fa-solid fa-calculator"></i>
                    </div>
                </div>
                <div class="stat-value"><?= format_currency($avgMonthlyExpense) ?></div>
                <div class="stat-footer">
                    <span>Derived from SQL AVG(SUM(amount))</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Active Expense Categories</span>
                    <div class="stat-icon-wrapper icon-balance">
                        <i class="fa-solid fa-chart-pie"></i>
                    </div>
                </div>
                <div class="stat-value"><?= count($categoryBreakdown) ?> Categories</div>
                <div class="stat-footer">
                    <span>Represented in your spending portfolio</span>
                </div>
            </div>
        </div>

        <!-- Trend Visualizations -->
        <div class="grid-2">
            <!-- Daily Spending Velocity (Last 14 Days) -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-chart-area" style="color: #06b6d4;"></i> Daily Expense Timeline (Last 14 Days)</h3>
                </div>
                <div class="card-body">
                    <div style="height: 300px; position: relative;">
                        <canvas id="dailyExpenseChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Monthly Income vs Expense Timeline -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-chart-line" style="color: var(--brand-primary);"></i> Monthly Cashflow (Past 12 Months)</h3>
                </div>
                <div class="card-body">
                    <div style="height: 300px; position: relative;">
                        <canvas id="monthlyTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Category Distribution Table & Chart -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fa-solid fa-table-list" style="color: #ec4899;"></i> Category-Wise Spending Allocation</h3>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($categoryBreakdown)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fa-solid fa-chart-pie"></i>
                        </div>
                        <div class="empty-state-title">No Spending Data Recorded</div>
                        <div class="empty-state-desc">Once you start logging daily expenses, this table will visualize each category's total volume and portfolio weight.</div>
                        <button class="btn btn-primary btn-sm" onclick="openAddModal('expense')" style="margin-top: 14px;">
                            <i class="fa-solid fa-plus"></i> Record Expense
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Transactions</th>
                                    <th>Total Expenditure</th>
                                    <th>Portfolio Share</th>
                                    <th style="width: 250px;">Visual Weight</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categoryBreakdown as $cat): ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <div style="width: 32px; height: 32px; border-radius: 6px; background: <?= htmlspecialchars($cat['color']) ?>20; color: <?= htmlspecialchars($cat['color']) ?>; display: flex; align-items: center; justify-content: center;">
                                                    <i class="fa-solid <?= htmlspecialchars($cat['icon']) ?>"></i>
                                                </div>
                                                <strong><?= htmlspecialchars($cat['category_name']) ?></strong>
                                            </div>
                                        </td>
                                        <td><?= $cat['tx_count'] ?></td>
                                        <td style="font-weight: 700;"><?= format_currency($cat['total_spent']) ?></td>
                                        <td><strong><?= $cat['percentage'] ?>%</strong></td>
                                        <td>
                                            <div class="progress-bar-bg" style="height: 8px;">
                                                <div class="progress-bar-fill" style="width: <?= $cat['percentage'] ?>%; background-color: <?= htmlspecialchars($cat['color']) ?>;"></div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div> <!-- End content-body -->

<script>
window.AnalyticsData = {
    dailyLabels: <?= json_encode(array_column($dailyData, 'day_label')) ?>,
    dailySpent: <?= json_encode(array_map('floatval', array_column($dailyData, 'daily_spent'))) ?>,
    monthlyLabels: <?= json_encode(array_column($monthlyData, 'month_label')) ?>,
    monthlyIncome: <?= json_encode(array_map('floatval', array_column($monthlyData, 'income'))) ?>,
    monthlyExpense: <?= json_encode(array_map('floatval', array_column($monthlyData, 'expense'))) ?>
};
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
