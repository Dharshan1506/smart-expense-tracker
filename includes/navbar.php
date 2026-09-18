<?php
/**
 * Top Navigation Bar Component
 * Responsive Fintech Navbar with Net Balance pill and Quick Modal Trigger
 */
declare(strict_types=1);

$user = current_user();

// Calculate current quick balance for display in top navbar
$quickBalance = 0.00;
if ($user && isset($pdo)) {
    try {
        $stmtBal = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) -
                SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) AS net_balance
            FROM transactions 
            WHERE user_id = ?
        ");
        $stmtBal->execute([$user['id']]);
        $quickBalance = (float)($stmtBal->fetchColumn() ?: 0.00);
    } catch (Exception $e) {
        $quickBalance = 0.00;
    }
}
?>
<header class="navbar">
    <div class="navbar-left">
        <button class="mobile-toggle" id="mobileToggle" aria-label="Toggle Navigation Sidebar">
            <i class="fa-solid fa-bars"></i>
        </button>
        <h1 class="page-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h1>
    </div>

    <div class="navbar-right">
        <?php if ($user): ?>
            <div class="navbar-balance-pill" title="Total Available Net Liquidity">
                <i class="fa-solid fa-wallet"></i>
                <span>Net:</span>
                <span class="navbar-balance-amount"><?= format_currency($quickBalance) ?></span>
            </div>

            <button type="button" class="btn btn-primary btn-sm" onclick="openAddTransactionModal()">
                <i class="fa-solid fa-plus"></i>
                <span>Add Transaction</span>
            </button>

            <a href="profile.php" class="btn btn-outline btn-sm btn-icon" title="Account Settings">
                <i class="fa-solid fa-user"></i>
            </a>
        <?php else: ?>
            <a href="login.php" class="btn btn-outline btn-sm">Sign In</a>
            <a href="register.php" class="btn btn-primary btn-sm">Create Account</a>
        <?php endif; ?>
    </div>
</header>
