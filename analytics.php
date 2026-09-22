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
// 1. Highest Spending Category (Lifetime / Current Month)
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
        ROUND((SUM(t.amount) / NULLIF((
            SELECT SUM(amount) FROM transactions WHERE user_id = ? AND transaction_type = 'expense'
        ), 0) * 100), 1) as percentage
    FROM transactions t
    INNER JOIN categories c ON t.category_id = c.category_id
    WHERE t.user_id = ? AND t.transaction_type = 'expense'
    GROUP BY c.category_id, c.category_name, c.color, c.icon
    ORDER BY total_spent DESC
");
$stmtCatAll->execute([$userId, $userId]);
$categoryBreakdown = $stmtCatAll->fetchAll();

// Calculate 14-day daily burn rate
$dailySum = 0.0;
foreach ($dailyData as $d) {
    $dailySum += (float)$d['daily_spent'];
}
$avgDailyBurn = count($dailyData) > 0 ? ($dailySum / count($dailyData)) : 0.0;

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
                <i class="fa-solid fa-circle-check"></i>
                <span><?= htmlspecialchars($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <!-- Page Header Bar -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
            <div>
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 4px;">
                    <span class="badge" style="background: rgba(6, 182, 212, 0.15); color: var(--accent-cyan); border: 1px solid rgba(6, 182, 212, 0.3);">
                        <i class="fa-solid fa-chart-pie"></i> Financial Intelligence
                    </span>
                    <span class="badge badge-outline">Multi-Dimensional Analytics</span>
                </div>
                <h2 style="font-size: 1.6rem; font-weight: 800; color: var(--text-primary); margin: 0; letter-spacing: -0.02em;">
                    Spending Analytics & Burn Velocity
                </h2>
                <p style="color: var(--text-secondary); font-size: 0.88rem; margin: 4px 0 0 0;">
                    Temporal cashflow dynamics, daily run rates, and portfolio diversification
                </p>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="reports.php" class="btn btn-outline" style="border-color: rgba(255,255,255,0.12); color: var(--text-secondary);">
                    <i class="fa-solid fa-file-invoice"></i> Audit Reports
                </a>
                <button type="button" class="btn btn-primary btn-glow" onclick="openAddModal('expense')">
                    <i class="fa-solid fa-plus"></i> Record Expense
                </button>
            </div>
        </div>

        <!-- 4-Stat Analytical Intelligence Strip -->
        <div class="grid-4" style="margin-bottom: 24px;">
            
            <div class="card" style="margin-bottom: 0; border-left: 4px solid var(--accent-coral);">
                <div class="card-body" style="padding: 18px 20px;">
                    <div style="color: var(--text-muted); font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">
                        Peak Outflow Category
                    </div>
                    <div style="font-size: 1.35rem; font-weight: 800; color: var(--text-primary); margin-bottom: 4px; display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid <?= htmlspecialchars($highestCategory['icon'] ?? 'fa-fire') ?>" style="color: var(--accent-coral); font-size: 1.1rem;"></i>
                        <?= htmlspecialchars($highestCategory['category_name'] ?? 'None Recorded') ?>
                    </div>
                    <div style="font-size: 0.82rem; color: var(--text-secondary); font-family: var(--font-mono);">
                        <?= format_currency($highestCategory['total_spent'] ?? 0) ?> (<?= (int)($highestCategory['tx_count'] ?? 0) ?> tx)
                    </div>
                </div>
            </div>

            <div class="card" style="margin-bottom: 0; border-left: 4px solid var(--accent-primary);">
                <div class="card-body" style="padding: 18px 20px;">
                    <div style="color: var(--text-muted); font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">
                        Monthly Expenditure Mean
                    </div>
                    <div style="font-size: 1.5rem; font-weight: 800; font-family: var(--font-mono); color: var(--text-primary);">
                        <?= format_currency($avgMonthlyExpense) ?>
                    </div>
                    <div style="font-size: 0.78rem; color: var(--text-secondary); margin-top: 4px;">
                        Calculated across recorded cycles
                    </div>
                </div>
            </div>

            <div class="card" style="margin-bottom: 0; border-left: 4px solid var(--accent-cyan);">
                <div class="card-body" style="padding: 18px 20px;">
                    <div style="color: var(--text-muted); font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">
                        Daily Burn Velocity
                    </div>
                    <div style="font-size: 1.5rem; font-weight: 800; font-family: var(--font-mono); color: var(--accent-cyan);">
                        <?= format_currency($avgDailyBurn) ?>
                    </div>
                    <div style="font-size: 0.78rem; color: var(--text-secondary); margin-top: 4px;">
                        Average daily outflow (14d window)
                    </div>
                </div>
            </div>

            <div class="card" style="margin-bottom: 0; border-left: 4px solid var(--accent-purple);">
                <div class="card-body" style="padding: 18px 20px;">
                    <div style="color: var(--text-muted); font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">
                        Active Taxonomies
                    </div>
                    <div style="font-size: 1.5rem; font-weight: 800; font-family: var(--font-mono); color: var(--text-primary);">
                        <?= count($categoryBreakdown) ?> Active
                    </div>
                    <div style="font-size: 0.78rem; color: var(--text-secondary); margin-top: 4px;">
                        Expense classification distribution
                    </div>
                </div>
            </div>

        </div>

        <!-- Trend Visualizations Grid -->
        <div class="grid-2" style="margin-bottom: 24px;">
            <!-- Daily Spending Velocity (Last 14 Days) -->
            <div class="card card-accent-cyan" style="margin-bottom: 0;">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h3 class="card-title" style="font-size: 1.05rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 8px; margin: 0;">
                        <i class="fa-solid fa-chart-area" style="color: var(--accent-cyan);"></i>
                        Daily Outflow Timeline (14-Day Velocity)
                    </h3>
                    <span class="badge" style="background: rgba(6, 182, 212, 0.12); color: var(--accent-cyan); font-size: 0.7rem;">Area Curve</span>
                </div>
                <div class="card-body">
                    <div style="height: 310px; position: relative;">
                        <canvas id="dailyExpenseChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Monthly Income vs Expense Timeline -->
            <div class="card card-accent-purple" style="margin-bottom: 0;">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h3 class="card-title" style="font-size: 1.05rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 8px; margin: 0;">
                        <i class="fa-solid fa-chart-column" style="color: var(--accent-purple);"></i>
                        Monthly Cashflow Dynamics (Past 12 Months)
                    </h3>
                    <span class="badge" style="background: rgba(168, 85, 247, 0.12); color: var(--accent-purple); font-size: 0.7rem;">Inflow vs Outflow</span>
                </div>
                <div class="card-body">
                    <div style="height: 310px; position: relative;">
                        <canvas id="monthlyTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Category Distribution Table & Weight Bar -->
        <div class="card card-accent-emerald" style="margin-bottom: 0;">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3 class="card-title" style="font-size: 1.05rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 8px; margin: 0;">
                    <i class="fa-solid fa-layer-group" style="color: var(--accent-emerald);"></i>
                    Portfolio Spending Weight & Category Breakdown
                </h3>
                <span style="font-size: 0.8rem; color: var(--text-secondary);"><?= count($categoryBreakdown) ?> categories ranked by volume</span>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($categoryBreakdown)): ?>
                    <div class="empty-state" style="padding: 48px 24px;">
                        <div class="empty-state-icon" style="background: rgba(16, 185, 129, 0.12); color: var(--accent-emerald); width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px auto; font-size: 1.8rem; border: 1px solid rgba(16, 185, 129, 0.25);">
                            <i class="fa-solid fa-chart-pie"></i>
                        </div>
                        <h3 class="empty-state-title" style="font-size: 1.25rem; font-weight: 700;">No Spending Data Recorded</h3>
                        <p class="empty-state-text" style="max-width: 440px; margin: 8px auto 20px auto; color: var(--text-secondary); font-size: 0.88rem;">
                            Once you log transactions, this section dynamically computes portfolio weight, category ranking, and visual disbursement indicators.
                        </p>
                        <button class="btn btn-primary btn-glow" onclick="openAddModal('expense')">
                            <i class="fa-solid fa-plus"></i> Record First Expense
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Category Taxonomy</th>
                                    <th>Transactions</th>
                                    <th>Total Outflow</th>
                                    <th>Portfolio Share</th>
                                    <th style="min-width: 220px;">Visual Distribution Weight</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categoryBreakdown as $cat): 
                                    $catColor = $cat['color'] ?: '#6366f1';
                                    $catIcon = $cat['icon'] ?: 'fa-tag';
                                    $pct = (float)$cat['percentage'];
                                ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 12px;">
                                                <div style="width: 38px; height: 38px; border-radius: var(--radius-md); background: <?= htmlspecialchars($catColor) ?>22; color: <?= htmlspecialchars($catColor) ?>; display: flex; align-items: center; justify-content: center; font-size: 1.05rem; border: 1px solid <?= htmlspecialchars($catColor) ?>40;">
                                                    <i class="fa-solid <?= htmlspecialchars($catIcon) ?>"></i>
                                                </div>
                                                <strong style="font-size: 0.95rem; color: var(--text-primary);"><?= htmlspecialchars($cat['category_name']) ?></strong>
                                            </div>
                                        </td>
                                        <td>
                                            <span style="font-family: var(--font-mono); font-weight: 700; color: var(--text-primary);"><?= $cat['tx_count'] ?></span>
                                            <span style="color: var(--text-muted); font-size: 0.8rem;">entries</span>
                                        </td>
                                        <td style="font-weight: 800; font-family: var(--font-mono); color: var(--accent-coral);">
                                            <?= format_currency((float)$cat['total_spent']) ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-outline" style="font-family: var(--font-mono); font-weight: 700; color: var(--text-primary);">
                                                <?= $pct ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <div class="progress-bar-bg" style="height: 8px; background: rgba(255, 255, 255, 0.06);">
                                                <div class="progress-bar-fill" style="width: <?= min($pct, 100) ?>%; background: <?= htmlspecialchars($catColor) ?>; box-shadow: 0 0 8px <?= htmlspecialchars($catColor) ?>50;"></div>
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
</div>

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
