<?php
/**
 * Financial Reports Generator & Data Export
 * Generates Monthly, Category, Income, Budget, and Savings statements with CSV export
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$userId = $user['id'];
$pdo = getDBConnection();

$pageTitle = 'Financial Reports';
$currentPage = 'reports';

$reportType = $_GET['report_type'] ?? 'monthly_expense';
$selectedYear = (int)($_GET['year'] ?? date('Y'));
$selectedMonth = !empty($_GET['month']) ? (int)$_GET['month'] : 0;
$exportCsv = isset($_GET['export']) && $_GET['export'] === 'csv';

$reportData = [];
$reportTitle = 'Report';
$totalSum = 0.0;

switch ($reportType) {
    case 'category':
        $reportTitle = "Category-Wise Expenditure Statement ($selectedYear)";
        $sql = "
            SELECT 
                c.category_name,
                c.category_type,
                COUNT(t.transaction_id) as tx_count,
                SUM(t.amount) as total_amount,
                AVG(t.amount) as avg_amount,
                MAX(t.amount) as max_amount
            FROM categories c
            INNER JOIN transactions t ON t.category_id = c.category_id
            WHERE t.user_id = ? AND YEAR(t.transaction_date) = ?
        ";
        $params = [$userId, $selectedYear];
        if ($selectedMonth > 0) {
            $sql .= " AND MONTH(t.transaction_date) = ?";
            $params[] = $selectedMonth;
        }
        $sql .= " GROUP BY c.category_id, c.category_name, c.category_type ORDER BY total_amount DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $reportData = $stmt->fetchAll();
        break;

    case 'income':
        $reportTitle = "Income Ledger Statement ($selectedYear)";
        $sql = "
            SELECT 
                t.transaction_date,
                t.description,
                c.category_name,
                t.amount
            FROM transactions t
            INNER JOIN categories c ON t.category_id = c.category_id
            WHERE t.user_id = ? AND t.transaction_type = 'income' AND YEAR(t.transaction_date) = ?
        ";
        $params = [$userId, $selectedYear];
        if ($selectedMonth > 0) {
            $sql .= " AND MONTH(t.transaction_date) = ?";
            $params[] = $selectedMonth;
        }
        $sql .= " ORDER BY t.transaction_date DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $reportData = $stmt->fetchAll();
        break;

    case 'budget':
        $reportTitle = "Budget Performance & Variance Statement ($selectedYear)";
        $sql = "
            SELECT 
                c.category_name,
                b.budget_amount,
                b.start_date,
                b.end_date,
                COALESCE(SUM(t.amount), 0) as total_spent,
                (b.budget_amount - COALESCE(SUM(t.amount), 0)) as variance,
                ROUND((COALESCE(SUM(t.amount), 0) / b.budget_amount * 100), 1) as utilization_pct
            FROM budgets b
            INNER JOIN categories c ON b.category_id = c.category_id
            LEFT JOIN transactions t ON t.category_id = b.category_id 
                AND t.user_id = b.user_id 
                AND t.transaction_type = 'expense'
                AND t.transaction_date BETWEEN b.start_date AND b.end_date
            WHERE b.user_id = ? AND YEAR(b.start_date) = ?
            GROUP BY b.budget_id, c.category_name, b.budget_amount, b.start_date, b.end_date
            ORDER BY utilization_pct DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $selectedYear]);
        $reportData = $stmt->fetchAll();
        break;

    case 'savings':
        $reportTitle = "Savings Goals & Asset Accumulation Report";
        $sql = "
            SELECT 
                goal_name,
                target_amount,
                saved_amount,
                (target_amount - saved_amount) as remaining_amount,
                ROUND((saved_amount / target_amount * 100), 1) as progress_pct,
                target_date
            FROM savings_goals
            WHERE user_id = ?
            ORDER BY target_date ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $reportData = $stmt->fetchAll();
        break;

    case 'monthly_expense':
    default:
        $reportType = 'monthly_expense';
        $reportTitle = "Monthly Expense Detailed Statement ($selectedYear)";
        $sql = "
            SELECT 
                t.transaction_date,
                t.description,
                c.category_name,
                t.amount
            FROM transactions t
            INNER JOIN categories c ON t.category_id = c.category_id
            WHERE t.user_id = ? AND t.transaction_type = 'expense' AND YEAR(t.transaction_date) = ?
        ";
        $params = [$userId, $selectedYear];
        if ($selectedMonth > 0) {
            $sql .= " AND MONTH(t.transaction_date) = ?";
            $params[] = $selectedMonth;
        }
        $sql .= " ORDER BY t.transaction_date DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $reportData = $stmt->fetchAll();
        break;
}

// ----------------------------------------------------------
// CSV File Export Handler
// ----------------------------------------------------------
if ($exportCsv && !empty($reportData)) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Report_' . $reportType . '_' . date('Ymd_His') . '.csv"');
    $output = fopen('php://output', 'w');

    // Headers
    fputcsv($output, array_keys($reportData[0]));

    // Rows
    foreach ($reportData as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

$flash = get_flash();
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

        <!-- Report Generation Controls -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fa-solid fa-file-invoice" style="color: var(--brand-primary);"></i> Report Parameters</h3>
            </div>
            <div class="card-body">
                <form method="GET" action="reports.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; align-items: flex-end;">
                    <div>
                        <label class="form-label" style="font-size: 0.8rem;">Report Type</label>
                        <select name="report_type" class="form-select">
                            <option value="monthly_expense" <?= ($reportType === 'monthly_expense') ? 'selected' : '' ?>>Monthly Expense Statement</option>
                            <option value="category" <?= ($reportType === 'category') ? 'selected' : '' ?>>Category Analysis Report</option>
                            <option value="income" <?= ($reportType === 'income') ? 'selected' : '' ?>>Income Ledger Report</option>
                            <option value="budget" <?= ($reportType === 'budget') ? 'selected' : '' ?>>Budget Variance Report</option>
                            <option value="savings" <?= ($reportType === 'savings') ? 'selected' : '' ?>>Savings Goals Report</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label" style="font-size: 0.8rem;">Year</label>
                        <select name="year" class="form-select">
                            <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 3; $y--): ?>
                                <option value="<?= $y ?>" <?= ($selectedYear === $y) ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div>
                        <label class="form-label" style="font-size: 0.8rem;">Month (Optional)</label>
                        <select name="month" class="form-select">
                            <option value="0">Entire Year</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= ($selectedMonth === $m) ? 'selected' : '' ?>>
                                    <?= date('F', mktime(0, 0, 0, $m, 10)) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div style="display: flex; gap: 8px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <i class="fa-solid fa-arrows-rotate"></i> Generate
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Printable / Viewable Report Card -->
        <div class="card" id="printableArea">
            <div class="card-header" style="background: white;">
                <div>
                    <h3 class="card-title"><?= htmlspecialchars($reportTitle) ?></h3>
                    <span style="font-size: 0.82rem; color: var(--text-secondary);">Generated on <?= date('M d, Y h:i A') ?> for <?= htmlspecialchars($user['name']) ?></span>
                </div>
                <div style="display: flex; gap: 8px;">
                    <a href="reports.php?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-outline btn-sm">
                        <i class="fa-solid fa-file-csv" style="color: var(--success);"></i> Export CSV
                    </a>
                    <button onclick="window.print();" class="btn btn-outline btn-sm">
                        <i class="fa-solid fa-print"></i> Print Statement
                    </button>
                </div>
            </div>

            <div class="card-body" style="padding: 0;">
                <?php if (empty($reportData)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fa-solid fa-file-lines"></i>
                        </div>
                        <div class="empty-state-title">No Statement Data Available</div>
                        <div class="empty-state-desc">There are no records matching the selected parameters (<?= htmlspecialchars($reportTitle) ?>). Try selecting another time period or log transactions.</div>
                        <button class="btn btn-primary btn-sm" onclick="openAddModal()" style="margin-top: 14px;">
                            <i class="fa-solid fa-plus"></i> Log Transaction
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <?php foreach (array_keys($reportData[0]) as $col): ?>
                                        <th style="<?= in_array($col, ['amount', 'total_amount', 'avg_amount', 'max_amount', 'budget_amount', 'total_spent', 'variance', 'target_amount', 'saved_amount']) ? 'text-align: right;' : '' ?>">
                                            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $col))) ?>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $colKey => $val): ?>
                                            <td style="<?= in_array($colKey, ['amount', 'total_amount', 'avg_amount', 'max_amount', 'budget_amount', 'total_spent', 'variance', 'target_amount', 'saved_amount']) ? 'text-align: right; font-weight: 600;' : '' ?>">
                                                <?php 
                                                    if (in_array($colKey, ['amount', 'total_amount', 'avg_amount', 'max_amount', 'budget_amount', 'total_spent', 'variance', 'target_amount', 'saved_amount'])) {
                                                        echo format_currency($val);
                                                    } elseif (str_contains($colKey, 'date')) {
                                                        echo format_date($val);
                                                    } elseif (str_contains($colKey, 'pct')) {
                                                        echo $val . '%';
                                                    } else {
                                                        echo htmlspecialchars((string)$val);
                                                    }
                                                ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div> <!-- End content-body -->

<style>
@media print {
    .sidebar, .navbar, .card:first-child, .btn, .flash-alert {
        display: none !important;
    }
    .main-wrapper {
        margin-left: 0 !important;
    }
    body, .content-body {
        background: white !important;
        padding: 0 !important;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
    }
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
