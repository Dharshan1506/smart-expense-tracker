<?php
/**
 * Top Navigation Bar Component - ExpenseIQ Modern Fintech SaaS
 * Responsive Navbar with Search, Notifications, Net Balance, and Quick Modal Action
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
        <h1 class="page-title">
            <?= htmlspecialchars($pageTitle ?? 'Dashboard') ?>
        </h1>
    </div>

    <div class="navbar-right">
        <?php if ($user): ?>
            <!-- Global Quick Search -->
            <div class="navbar-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" placeholder="Search transactions, budgets..." onkeydown="if(event.key === 'Enter'){ window.location.href = 'transactions.php?search=' + encodeURIComponent(this.value); }">
            </div>

            <!-- Net Balance Pill -->
            <div class="navbar-balance-pill" title="Total Available Net Liquidity">
                <i class="fa-solid fa-wallet"></i>
                <span>Net:</span>
                <span class="navbar-balance-amount"><?= format_currency($quickBalance) ?></span>
            </div>

            <!-- Quick Add Transaction Button -->
            <button type="button" class="btn btn-primary btn-sm" onclick="openAddTransactionModal()" style="font-weight: 700;">
                <i class="fa-solid fa-plus"></i>
                <span>Quick Add</span>
            </button>

            <!-- Notification Bell -->
            <a href="budgets.php" class="navbar-action-btn" title="Alerts & Threshold Notifications">
                <i class="fa-regular fa-bell"></i>
                <span class="navbar-notif-dot"></span>
            </a>

            <!-- Profile Settings Link -->
            <a href="profile.php" class="navbar-action-btn" title="Profile & Account Settings">
                <i class="fa-regular fa-user"></i>
            </a>
        <?php else: ?>
            <a href="login.php" class="btn btn-outline btn-sm">Sign In</a>
            <a href="register.php" class="btn btn-primary btn-sm">Create Account</a>
        <?php endif; ?>
    </div>
</header>
