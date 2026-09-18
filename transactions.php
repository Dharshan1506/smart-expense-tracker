<?php
/**
 * Transactions Ledger & History
 * Modern Fintech Ledger with Searching, Filtering, Sorting, Modals, and Confirmation Dialogs
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$userId = $user['id'];
$pdo = getDBConnection();

$pageTitle = 'Transactions Ledger';
$currentPage = 'transactions';

// Process Filter & Search Parameters
$search     = trim($_GET['search'] ?? '');
$categoryId = !empty($_GET['category']) ? (int)$_GET['category'] : null;
$type       = trim($_GET['type'] ?? '');
$startDate  = trim($_GET['start_date'] ?? '');
$endDate    = trim($_GET['end_date'] ?? '');
$sortBy     = trim($_GET['sort'] ?? 'date_desc');

// Dynamic SQL Query with Parameters
$sql = "
    SELECT 
        t.transaction_id, 
        t.amount, 
        t.transaction_type, 
        t.description, 
        t.transaction_date, 
        t.created_at,
        c.category_id,
        c.category_name, 
        c.icon, 
        c.color
    FROM transactions t
    INNER JOIN categories c ON t.category_id = c.category_id
    WHERE t.user_id = ?
";
$params = [$userId];

if ($search !== '') {
    $sql .= " AND t.description LIKE ?";
    $params[] = '%' . $search . '%';
}

if ($categoryId) {
    $sql .= " AND t.category_id = ?";
    $params[] = $categoryId;
}

if (in_array($type, ['income', 'expense'])) {
    $sql .= " AND t.transaction_type = ?";
    $params[] = $type;
}

if ($startDate !== '') {
    $sql .= " AND t.transaction_date >= ?";
    $params[] = $startDate;
}

if ($endDate !== '') {
    $sql .= " AND t.transaction_date <= ?";
    $params[] = $endDate;
}

// Sorting logic
switch ($sortBy) {
    case 'date_asc':
        $sql .= " ORDER BY t.transaction_date ASC, t.transaction_id ASC";
        break;
    case 'amount_desc':
        $sql .= " ORDER BY t.amount DESC";
        break;
    case 'amount_asc':
        $sql .= " ORDER BY t.amount ASC";
        break;
    case 'date_desc':
    default:
        $sql .= " ORDER BY t.transaction_date DESC, t.transaction_id DESC";
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Fetch categories for filter dropdown
$stmtCats = $pdo->prepare("
    SELECT category_id, category_name, category_type 
    FROM categories 
    WHERE user_id IS NULL OR user_id = ? 
    ORDER BY category_name ASC
");
$stmtCats->execute([$userId]);
$categories = $stmtCats->fetchAll();

// Compute Filtered Totals
$filteredIncome = 0.00;
$filteredExpense = 0.00;
foreach ($transactions as $t) {
    if ($t['transaction_type'] === 'income') {
        $filteredIncome += (float)$t['amount'];
    } else {
        $filteredExpense += (float)$t['amount'];
    }
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

        <!-- Ledger Summary KPI Bar -->
        <div style="display: flex; gap: 16px; margin-bottom: 22px; flex-wrap: wrap; align-items: center;">
            <div style="background: white; padding: 12px 18px; border-radius: var(--radius-md); border: 1px solid var(--border-color); display: flex; align-items: center; gap: 10px; box-shadow: var(--shadow-xs);">
                <i class="fa-solid fa-layer-group" style="color: var(--brand-primary);"></i>
                <span style="font-size: 0.85rem; color: var(--text-secondary);">Filtered Count:</span>
                <strong><?= count($transactions) ?></strong>
            </div>
            <div style="background: white; padding: 12px 18px; border-radius: var(--radius-md); border: 1px solid var(--border-color); display: flex; align-items: center; gap: 10px; box-shadow: var(--shadow-xs);">
                <i class="fa-solid fa-arrow-down-left" style="color: var(--success);"></i>
                <span style="font-size: 0.85rem; color: var(--text-secondary);">Filtered Inflow:</span>
                <strong style="color: var(--success); font-family: monospace;"><?= format_currency($filteredIncome) ?></strong>
            </div>
            <div style="background: white; padding: 12px 18px; border-radius: var(--radius-md); border: 1px solid var(--border-color); display: flex; align-items: center; gap: 10px; box-shadow: var(--shadow-xs);">
                <i class="fa-solid fa-arrow-up-right" style="color: var(--danger);"></i>
                <span style="font-size: 0.85rem; color: var(--text-secondary);">Filtered Outflow:</span>
                <strong style="color: var(--danger); font-family: monospace;"><?= format_currency($filteredExpense) ?></strong>
            </div>
            <div style="margin-left: auto;">
                <button type="button" class="btn btn-primary" onclick="openAddTransactionModal()">
                    <i class="fa-solid fa-plus"></i> Record Transaction
                </button>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="card" style="margin-bottom: 22px;">
            <div class="card-body" style="padding: 18px 22px;">
                <form method="GET" action="transactions.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; align-items: flex-end;">
                    <div>
                        <label class="form-label" style="font-size: 0.78rem;">Search Description</label>
                        <div class="input-icon-wrapper">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" name="search" class="form-control" placeholder="Search keywords..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>

                    <div>
                        <label class="form-label" style="font-size: 0.78rem;">Category</label>
                        <select name="category" class="form-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['category_id'] ?>" <?= ($categoryId === (int)$cat['category_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['category_name']) ?> (<?= ucfirst($cat['category_type']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="form-label" style="font-size: 0.78rem;">Type</label>
                        <select name="type" class="form-select">
                            <option value="">All Types</option>
                            <option value="income" <?= ($type === 'income') ? 'selected' : '' ?>>Income Only</option>
                            <option value="expense" <?= ($type === 'expense') ? 'selected' : '' ?>>Expense Only</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label" style="font-size: 0.78rem;">From Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
                    </div>

                    <div>
                        <label class="form-label" style="font-size: 0.78rem;">To Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
                    </div>

                    <div>
                        <label class="form-label" style="font-size: 0.78rem;">Sort By</label>
                        <select name="sort" class="form-select">
                            <option value="date_desc" <?= ($sortBy === 'date_desc') ? 'selected' : '' ?>>Date: Newest First</option>
                            <option value="date_asc" <?= ($sortBy === 'date_asc') ? 'selected' : '' ?>>Date: Oldest First</option>
                            <option value="amount_desc" <?= ($sortBy === 'amount_desc') ? 'selected' : '' ?>>Amount: Highest First</option>
                            <option value="amount_asc" <?= ($sortBy === 'amount_asc') ? 'selected' : '' ?>>Amount: Lowest First</option>
                        </select>
                    </div>

                    <div style="display: flex; gap: 8px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <i class="fa-solid fa-filter"></i> Apply
                        </button>
                        <a href="transactions.php" class="btn btn-secondary" title="Reset All Filters">
                            <i class="fa-solid fa-rotate-left"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Transactions Ledger Table Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-receipt" style="color: var(--brand-primary);"></i>
                    Financial Transaction Ledger
                </h3>
                <span style="font-size: 0.84rem; color: var(--text-secondary);"><?= count($transactions) ?> records loaded</span>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($transactions)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="fa-solid fa-filter-circle-xmark"></i></div>
                        <h4 class="empty-state-title">No transactions found</h4>
                        <p class="empty-state-text">No records matched your search filters. Try adjusting your search query or reset your filters.</p>
                        <div style="display: flex; gap: 10px;">
                            <a href="transactions.php" class="btn btn-secondary btn-sm">Reset Filters</a>
                            <button type="button" class="btn btn-primary btn-sm" onclick="openAddTransactionModal()">+ Record Transaction</button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th>Type</th>
                                    <th style="text-align: right;">Amount</th>
                                    <th style="text-align: center; width: 110px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $t): ?>
                                    <tr>
                                        <td style="white-space: nowrap; font-weight: 500; font-size: 0.85rem; color: var(--text-secondary);">
                                            <?= format_date($t['transaction_date']) ?>
                                        </td>
                                        <td>
                                            <span class="category-badge">
                                                <i class="fa-solid <?= htmlspecialchars($t['icon'] ?? 'fa-tag') ?>" style="color: <?= htmlspecialchars($t['color'] ?? '#4f46e5') ?>;"></i>
                                                <?= htmlspecialchars($t['category_name']) ?>
                                            </span>
                                        </td>
                                        <td style="font-weight: 600; color: var(--text-primary);">
                                            <?= htmlspecialchars($t['description']) ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= $t['transaction_type'] === 'income' ? 'badge-success' : 'badge-danger' ?>">
                                                <?= ucfirst($t['transaction_type']) ?>
                                            </span>
                                        </td>
                                        <td style="text-align: right; white-space: nowrap;">
                                            <span class="amount-<?= $t['transaction_type'] ?>">
                                                <?= ($t['transaction_type'] === 'income' ? '+' : '-') . format_currency($t['amount']) ?>
                                            </span>
                                        </td>
                                        <td style="text-align: center; white-space: nowrap;">
                                            <div style="display: flex; justify-content: center; gap: 6px;">
                                                <a href="edit_transaction.php?id=<?= $t['transaction_id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Edit Transaction">
                                                    <i class="fa-solid fa-pen-to-square" style="color: #3b82f6;"></i>
                                                </a>

                                                <form method="POST" action="delete_transaction.php" id="deleteForm_<?= $t['transaction_id'] ?>" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                    <input type="hidden" name="transaction_id" value="<?= $t['transaction_id'] ?>">
                                                    <button type="button" class="btn btn-outline btn-sm btn-icon" title="Delete Transaction" onclick="confirmDelete(<?= $t['transaction_id'] ?>, '<?= htmlspecialchars(addslashes($t['description'])) ?>', '<?= format_currency($t['amount']) ?>')">
                                                        <i class="fa-solid fa-trash-can" style="color: #ef4444;"></i>
                                                    </button>
                                                </form>
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
function confirmDelete(id, desc, amount) {
    confirmAction(
        'Are you sure you want to permanently delete transaction "' + desc + '" (' + amount + ')? This action cannot be undone.',
        function() {
            document.getElementById('deleteForm_' + id).submit();
        },
        'Confirm Transaction Deletion'
    );
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
