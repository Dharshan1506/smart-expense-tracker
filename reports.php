<?php
/**
 * Financial Reports Generator & Data Export
 * Modern Fintech SaaS Statement Generator with CSV Export & Ledger Audits
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
        $reportTitle = "Category-Wise Expenditure Audit ($selectedYear)";
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
                ROUND((COALESCE(SUM(t.amount), 0) / NULLIF(b.budget_amount, 0) * 100), 1) as utilization_pct
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
        $reportTitle = "Savings Goals & Asset Accumulation Audit";
        $sql = "
            SELECT 
                goal_name,
                target_amount,
                saved_amount,
                (target_amount - saved_amount) as remaining_amount,
                ROUND((saved_amount / NULLIF(target_amount, 0) * 100), 1) as progress_pct,
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
        $reportTitle = "Monthly Expense Detailed Ledger ($selectedYear)";
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
    header('Content-Disposition: attachment; filename="ExpenseIQ_Report_' . $reportType . '_' . date('Ymd_His') . '.csv"');
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

// Calculate summary volume
$reportSum = 0.0;
foreach ($reportData as $row) {
    if (isset($row['amount'])) $reportSum += (float)$row['amount'];
    elseif (isset($row['total_amount'])) $reportSum += (float)$row['total_amount'];
    elseif (isset($row['total_spent'])) $reportSum += (float)$row['total_spent'];
    elseif (isset($row['saved_amount'])) $reportSum += (float)$row['saved_amount'];
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
                <i class="fa-solid fa-circle-check"></i>
                <span><?= htmlspecialchars($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <!-- Page Header Bar -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
            <div>
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 4px;">
                    <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: var(--accent-emerald); border: 1px solid rgba(16, 185, 129, 0.3);">
                        <i class="fa-solid fa-file-shield"></i> Financial Auditing
                    </span>
                    <span class="badge badge-outline">CSV / Print Export</span>
                </div>
                <h2 style="font-size: 1.6rem; font-weight: 800; color: var(--text-primary); margin: 0; letter-spacing: -0.02em;">
                    Financial Statements & Reports
                </h2>
                <p style="color: var(--text-secondary); font-size: 0.88rem; margin: 4px 0 0 0;">
                    Generate compliant ledger statements, category summaries, and performance audits
                </p>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="reports.php?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-outline" style="border-color: rgba(16, 185, 129, 0.3); color: var(--accent-emerald);">
                    <i class="fa-solid fa-file-csv"></i> Export CSV
                </a>
                <button type="button" onclick="window.print()" class="btn btn-primary btn-glow">
                    <i class="fa-solid fa-print"></i> Print Statement
                </button>
            </div>
        </div>

        <!-- Report Parameters Toolbar -->
        <div class="card card-accent-primary" style="margin-bottom: 24px;">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3 class="card-title" style="font-size: 1rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 8px; margin: 0;">
                    <i class="fa-solid fa-sliders" style="color: var(--accent-primary);"></i>
                    Report Parameters & Filters
                </h3>
            </div>
            <div class="card-body" style="padding: 20px;">
                <form method="GET" action="reports.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: flex-end;">
                    <div>
                        <label class="form-label" style="font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em;">Report Type</label>
                        <select name="report_type" class="form-select">
                            <option value="monthly_expense" <?= ($reportType === 'monthly_expense') ? 'selected' : '' ?>>Monthly Expense Ledger</option>
                            <option value="category" <?= ($reportType === 'category') ? 'selected' : '' ?>>Category Analysis Audit</option>
                            <option value="income" <?= ($reportType === 'income') ? 'selected' : '' ?>>Income Ledger Statement</option>
                            <option value="budget" <?= ($reportType === 'budget') ? 'selected' : '' ?>>Budget Variance Report</option>
                            <option value="savings" <?= ($reportType === 'savings') ? 'selected' : '' ?>>Savings Goals Report</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label" style="font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em;">Fiscal Year</label>
                        <select name="year" class="form-select">
                            <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 3; $y--): ?>
                                <option value="<?= $y ?>" <?= ($selectedYear === $y) ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div>
                        <label class="form-label" style="font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em;">Month (Optional)</label>
                        <select name="month" class="form-select">
                            <option value="0">Entire Fiscal Year</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= ($selectedMonth === $m) ? 'selected' : '' ?>>
                                    <?= date('F', mktime(0, 0, 0, $m, 10)) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div>
                        <button type="submit" class="btn btn-primary btn-glow" style="width: 100%; padding: 11px;">
                            <i class="fa-solid fa-rotate"></i> Generate Statement
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 3-Stat Summary Strip -->
        <div class="grid-3" style="margin-bottom: 24px;">
            <div class="card" style="margin-bottom: 0; border-left: 4px solid var(--accent-primary);">
                <div class="card-body" style="padding: 16px 20px;">
                    <div style="color: var(--text-muted); font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">
                        Statement Total Volume
                    </div>
                    <div style="font-size: 1.45rem; font-weight: 800; font-family: var(--font-mono); color: var(--text-primary);">
                        <?= format_currency($reportSum) ?>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-bottom: 0; border-left: 4px solid var(--accent-cyan);">
                <div class="card-body" style="padding: 16px 20px;">
                    <div style="color: var(--text-muted); font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">
                        Record Count
                    </div>
                    <div style="font-size: 1.45rem; font-weight: 800; font-family: var(--font-mono); color: var(--accent-cyan);">
                        <?= count($reportData) ?> Rows
                    </div>
                </div>
            </div>

            <div class="card" style="margin-bottom: 0; border-left: 4px solid var(--accent-emerald);">
                <div class="card-body" style="padding: 16px 20px;">
                    <div style="color: var(--text-muted); font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">
                        Audit Status
                    </div>
                    <div style="font-size: 1.15rem; font-weight: 700; color: var(--accent-emerald); display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-circle-check"></i> Verified Ledger
                    </div>
                </div>
            </div>
        </div>

        <!-- Printable / Viewable Report Card -->
        <div class="card card-accent-emerald" id="printableArea" style="margin-bottom: 0;">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
                <div>
                    <h3 class="card-title" style="font-size: 1.1rem; font-weight: 700; color: var(--text-primary); margin: 0 0 4px 0;">
                        <?= htmlspecialchars($reportTitle) ?>
                    </h3>
                    <span style="font-size: 0.8rem; color: var(--text-secondary);">
                        Generated on <?= date('M d, Y h:i A') ?> &bull; Account: <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['email']) ?>)
                    </span>
                </div>
            </div>

            <div class="card-body" style="padding: 0;">
                <?php if (empty($reportData)): ?>
                    <div class="empty-state" style="padding: 56px 24px;">
                        <div class="empty-state-icon" style="background: rgba(99, 102, 241, 0.12); color: var(--accent-primary); width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px auto; font-size: 1.8rem; border: 1px solid rgba(99, 102, 241, 0.25);">
                            <i class="fa-solid fa-file-lines"></i>
                        </div>
                        <h3 class="empty-state-title" style="font-size: 1.25rem; font-weight: 700;">No Statement Records Available</h3>
                        <p class="empty-state-text" style="max-width: 440px; margin: 8px auto 20px auto; color: var(--text-secondary); font-size: 0.88rem;">
                            There are no recorded transactions or data matching your selection parameters. Try choosing an entire fiscal year or record new transactions.
                        </p>
                        <button class="btn btn-primary btn-glow" onclick="openAddModal()">
                            <i class="fa-solid fa-plus"></i> Record Transaction
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
                                            <td style="<?= in_array($colKey, ['amount', 'total_amount', 'avg_amount', 'max_amount', 'budget_amount', 'total_spent', 'variance', 'target_amount', 'saved_amount']) ? 'text-align: right; font-weight: 700; font-family: var(--font-mono);' : '' ?>">
                                                <?php 
                                                    if (in_array($colKey, ['amount', 'total_amount', 'avg_amount', 'max_amount', 'budget_amount', 'total_spent', 'variance', 'target_amount', 'saved_amount'])) {
                                                        echo format_currency((float)$val);
                                                    } elseif (str_contains($colKey, 'date')) {
                                                        echo '<span style="color: var(--text-secondary); font-size: 0.82rem;">' . format_date((string)$val) . '</span>';
                                                    } elseif (str_contains($colKey, 'pct')) {
                                                        echo '<span class="badge badge-outline">' . $val . '%</span>';
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
</div>

<style>
@media print {
    .sidebar, .navbar, .card:first-child, .btn, .flash-alert, #chatbotWidget {
        display: none !important;
    }
    .main-wrapper {
        margin-left: 0 !important;
        width: 100% !important;
    }
    body, .content-body {
        background: #ffffff !important;
        color: #000000 !important;
        padding: 0 !important;
    }
    .card {
        border: 1px solid #e2e8f0 !important;
        box-shadow: none !important;
        background: #ffffff !important;
    }
    .card-title, table th, table td {
        color: #000000 !important;
    }
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
