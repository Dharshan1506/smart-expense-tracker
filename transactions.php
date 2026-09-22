<?php
/**
 * Transactions Ledger & History - ExpenseIQ Fintech SaaS
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
        <div style="display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; align-items: center;">
            <div style="background: rgba(20, 31, 54, 0.7); backdrop-filter: blur(12px); padding: 14px 20px; border-radius: var(--radius-md); border: 1px solid rgba(59, 130, 246, 0.25); display: flex; align-items: center; gap: 12px; box-shadow: var(--shadow-sm);">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(59, 130, 246, 0.2); color: #60a5fa; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                    <i class="fa-solid fa-layer-group"></i>
                </div>
                <div>
                    <div style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); font-weight: 700;">Filtered Records</div>
                    <strong style="font-size: 1.15rem; color: #ffffff; font-family: var(--font-mono);"><?= count($transactions) ?></strong>
                </div>
            </div>

            <div style="background: rgba(20, 31, 54, 0.7); backdrop-filter: blur(12px); padding: 14px 20px; border-radius: var(--radius-md); border: 1px solid rgba(16, 185, 129, 0.25); display: flex; align-items: center; gap: 12px; box-shadow: var(--shadow-sm);">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(16, 185, 129, 0.2); color: #34d399; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                    <i class="fa-solid fa-arrow-trend-up"></i>
                </div>
                <div>
                    <div style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); font-weight: 700;">Filtered Inflow</div>
                    <strong style="color: #34d399; font-family: var(--font-mono); font-size: 1.15rem;"><?= format_currency($filteredIncome) ?></strong>
                </div>
            </div>

            <div style="background: rgba(20, 31, 54, 0.7); backdrop-filter: blur(12px); padding: 14px 20px; border-radius: var(--radius-md); border: 1px solid rgba(244, 63, 94, 0.25); display: flex; align-items: center; gap: 12px; box-shadow: var(--shadow-sm);">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(244, 63, 94, 0.2); color: #fb7185; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                    <i class="fa-solid fa-arrow-trend-down"></i>
                </div>
                <div>
                    <div style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); font-weight: 700;">Filtered Outflow</div>
                    <strong style="color: #f87171; font-family: var(--font-mono); font-size: 1.15rem;"><?= format_currency($filteredExpense) ?></strong>
                </div>
            </div>

            <div style="margin-left: auto;">
                <button type="button" class="btn btn-primary" onclick="openAddTransactionModal()" style="box-shadow: var(--shadow-glow-primary); font-weight: 700;">
                    <i class="fa-solid fa-plus"></i> Record Transaction
                </button>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-body" style="padding: 20px;">
                <form method="GET" action="transactions.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; align-items: flex-end;">
                    <div>
                        <label class="form-label" style="font-size: 0.78rem;">Search Description</label>
                        <div class="input-icon-wrapper">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" name="search" class="form-control" placeholder="Keywords..." value="<?= htmlspecialchars($search) ?>">
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
                        <label class="form-label" style="font-size: 0.78rem;">Sort Order</label>
                        <select name="sort" class="form-select">
                            <option value="date_desc" <?= ($sortBy === 'date_desc') ? 'selected' : '' ?>>Date: Newest First</option>
                            <option value="date_asc" <?= ($sortBy === 'date_asc') ? 'selected' : '' ?>>Date: Oldest First</option>
                            <option value="amount_desc" <?= ($sortBy === 'amount_desc') ? 'selected' : '' ?>>Amount: High to Low</option>
                            <option value="amount_asc" <?= ($sortBy === 'amount_asc') ? 'selected' : '' ?>>Amount: Low to High</option>
                        </select>
                    </div>

                    <div style="display: flex; gap: 8px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <i class="fa-solid fa-filter"></i> Filter
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
                    <i class="fa-solid fa-receipt" style="color: #60a5fa;"></i>
                    Financial Transaction Ledger
                </h3>
                <span style="font-size: 0.84rem; color: var(--text-muted); font-family: var(--font-mono);"><?= count($transactions) ?> records</span>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($transactions)): ?>
                    <div style="text-align: center; padding: 48px 20px; color: var(--text-muted);">
                        <i class="fa-solid fa-filter-circle-xmark" style="font-size: 2.2rem; margin-bottom: 12px; color: #64748b;"></i>
                        <h4 style="color: #ffffff; margin-bottom: 6px;">No transactions found</h4>
                        <p style="font-size: 0.88rem; color: var(--text-secondary); max-width: 400px; margin: 0 auto 18px;">No records matched your search query. Try adjusting your filters or record a new entry.</p>
                        <button type="button" class="btn btn-primary btn-sm" onclick="openAddTransactionModal()">+ Record Transaction</button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th>Classification</th>
                                    <th style="text-align: right;">Amount</th>
                                    <th style="text-align: center; width: 140px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $t): ?>
                                    <tr>
                                        <td style="white-space: nowrap; font-size: 0.84rem; color: var(--text-muted); font-family: var(--font-mono);">
                                            <?= format_date($t['transaction_date']) ?>
                                        </td>
                                        <td>
                                            <span style="display: inline-flex; align-items: center; gap: 8px; font-size: 0.82rem; font-weight: 600; padding: 4px 10px; border-radius: var(--radius-full); background: <?= htmlspecialchars($t['color'] ?? '#3b82f6') ?>20; color: <?= htmlspecialchars($t['color'] ?? '#60a5fa') ?>;">
                                                <i class="fa-solid <?= htmlspecialchars($t['icon'] ?? 'fa-tag') ?>"></i>
                                                <?= htmlspecialchars($t['category_name']) ?>
                                            </span>
                                        </td>
                                        <td style="font-weight: 600; color: #ffffff;">
                                            <?= htmlspecialchars($t['description']) ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= $t['transaction_type'] === 'income' ? 'badge-success' : 'badge-danger' ?>">
                                                <?= ucfirst($t['transaction_type']) ?>
                                            </span>
                                        </td>
                                        <td style="text-align: right; white-space: nowrap;">
                                            <?php if ($t['transaction_type'] === 'income'): ?>
                                                <span class="amount-income">+<?= format_currency($t['amount']) ?></span>
                                            <?php else: ?>
                                                <span class="amount-expense">-<?= format_currency($t['amount']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: center; white-space: nowrap;">
                                            <div style="display: flex; justify-content: center; gap: 6px;">
                                                <!-- View Details -->
                                                <button type="button" class="btn btn-outline btn-sm btn-icon" title="View Details" onclick='showTxDetails(<?= json_encode($t) ?>)'>
                                                    <i class="fa-solid fa-eye" style="color: #38bdf8;"></i>
                                                </button>

                                                <!-- Edit -->
                                                <a href="edit_transaction.php?id=<?= $t['transaction_id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Edit Transaction">
                                                    <i class="fa-solid fa-pen-to-square" style="color: #60a5fa;"></i>
                                                </a>

                                                <!-- Delete -->
                                                <form method="POST" action="delete_transaction.php" id="deleteForm_<?= $t['transaction_id'] ?>" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                    <input type="hidden" name="transaction_id" value="<?= $t['transaction_id'] ?>">
                                                    <button type="button" class="btn btn-outline btn-sm btn-icon" title="Delete Transaction" onclick="confirmDelete(<?= $t['transaction_id'] ?>, '<?= htmlspecialchars(addslashes($t['description'])) ?>', '<?= format_currency($t['amount']) ?>')">
                                                        <i class="fa-solid fa-trash-can" style="color: #fb7185;"></i>
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

<!-- Transaction Details Modal -->
<div class="modal-backdrop" id="txDetailsModal" onclick="if(event.target===this) closeTxDetailsModal()">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fa-solid fa-circle-info" style="color: #38bdf8;"></i>
                Transaction Details
            </h3>
            <button type="button" class="modal-close" onclick="closeTxDetailsModal()" aria-label="Close modal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body" id="txDetailsBody" style="font-size: 0.9rem;">
            <!-- Dynamically injected -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeTxDetailsModal()">Close</button>
        </div>
    </div>
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

function showTxDetails(tx) {
    const modal = document.getElementById('txDetailsModal');
    const body = document.getElementById('txDetailsBody');
    if (!modal || !body) return;

    body.innerHTML = `
        <div style="display: flex; flex-direction: column; gap: 14px;">
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 8px;">
                <span style="color: var(--text-muted);">Transaction ID (PK):</span>
                <strong style="font-family: var(--font-mono); color: #93c5fd;">#` + tx.transaction_id + `</strong>
            </div>
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 8px;">
                <span style="color: var(--text-muted);">Description:</span>
                <strong style="color: #ffffff;">` + tx.description + `</strong>
            </div>
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 8px;">
                <span style="color: var(--text-muted);">Amount:</span>
                <strong style="font-family: var(--font-mono); color: ` + (tx.transaction_type === 'income' ? '#34d399' : '#f87171') + `;">` + (tx.transaction_type === 'income' ? '+' : '-') + `₹` + Number(tx.amount).toLocaleString('en-IN', {minimumFractionDigits: 2}) + `</strong>
            </div>
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 8px;">
                <span style="color: var(--text-muted);">Category:</span>
                <strong style="color: #ffffff;">` + tx.category_name + `</strong>
            </div>
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 8px;">
                <span style="color: var(--text-muted);">Date of Transaction:</span>
                <strong style="color: #ffffff;">` + tx.transaction_date + `</strong>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);">System Timestamp:</span>
                <span style="font-family: var(--font-mono); font-size: 0.8rem; color: #94a3b8;">` + tx.created_at + `</span>
            </div>
        </div>
    `;
    modal.classList.add('show');
}

function closeTxDetailsModal() {
    const modal = document.getElementById('txDetailsModal');
    if (modal) modal.classList.remove('show');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
