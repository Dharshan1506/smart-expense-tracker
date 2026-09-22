<?php
/**
 * Sidebar Navigation Component - ExpenseIQ Fintech SaaS
 * High-End Navigation with Exact Categorization & Active Indicators
 */
declare(strict_types=1);

if (!isset($currentPage)) {
    $currentPage = basename($_SERVER['PHP_SELF'], '.php');
}
$user = current_user();
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand-icon">
            <i class="fa-solid fa-wallet"></i>
        </div>
        <div>
            <div class="sidebar-brand-title">ExpenseIQ</div>
        </div>
        <span class="sidebar-brand-badge">SaaS 3NF</span>
    </div>

    <ul class="sidebar-menu">
        <!-- OVERVIEW -->
        <li class="sidebar-heading">Overview</li>
        <li class="sidebar-item">
            <a href="dashboard.php" class="sidebar-link <?= ($currentPage === 'dashboard') ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-pie" style="color: #60a5fa;"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <!-- MONEY -->
        <li class="sidebar-heading">Money</li>
        <li class="sidebar-item">
            <a href="transactions.php" class="sidebar-link <?= in_array($currentPage, ['transactions', 'add_transaction', 'edit_transaction']) ? 'active' : '' ?>">
                <i class="fa-solid fa-arrow-right-arrow-left" style="color: #34d399;"></i>
                <span>Transactions</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="categories.php" class="sidebar-link <?= ($currentPage === 'categories') ? 'active' : '' ?>">
                <i class="fa-solid fa-layer-group" style="color: #a78bfa;"></i>
                <span>Categories</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="budgets.php" class="sidebar-link <?= ($currentPage === 'budgets') ? 'active' : '' ?>">
                <i class="fa-solid fa-calculator" style="color: #fbbf24;"></i>
                <span>Budgets</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="savings.php" class="sidebar-link <?= ($currentPage === 'savings') ? 'active' : '' ?>">
                <i class="fa-solid fa-piggy-bank" style="color: #38bdf8;"></i>
                <span>Savings Goals</span>
            </a>
        </li>

        <!-- INSIGHTS -->
        <li class="sidebar-heading">Insights</li>
        <li class="sidebar-item">
            <a href="analytics.php" class="sidebar-link <?= ($currentPage === 'analytics') ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-line" style="color: #f472b6;"></i>
                <span>Analytics</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="reports.php" class="sidebar-link <?= ($currentPage === 'reports') ? 'active' : '' ?>">
                <i class="fa-solid fa-file-invoice" style="color: #fb923c;"></i>
                <span>Reports</span>
            </a>
        </li>

        <!-- DBMS -->
        <li class="sidebar-heading">DBMS</li>
        <li class="sidebar-item">
            <a href="queries.php" class="sidebar-link <?= ($currentPage === 'queries') ? 'active' : '' ?>" style="border: 1px dashed rgba(59, 130, 246, 0.4); background: rgba(30, 58, 138, 0.2);">
                <i class="fa-solid fa-database" style="color: #60a5fa;"></i>
                <span style="font-weight: 700; color: #93c5fd;">SQL Queries</span>
                <span class="sidebar-badge" style="background: linear-gradient(135deg, #2563eb, #7c3aed); color: #fff;">20 Live</span>
            </a>
        </li>

        <!-- ACCOUNT -->
        <li class="sidebar-heading">Account</li>
        <li class="sidebar-item">
            <a href="profile.php" class="sidebar-link <?= ($currentPage === 'profile') ? 'active' : '' ?>">
                <i class="fa-solid fa-user-gear" style="color: #c084fc;"></i>
                <span>Profile</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="logout.php" class="sidebar-link" onclick="return confirmAction('Are you sure you want to sign out from your account?', () => { window.location.href = 'logout.php'; });">
                <i class="fa-solid fa-arrow-right-from-bracket" style="color: #fb7185;"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>

    <?php if ($user): ?>
    <div class="sidebar-footer">
        <div class="user-avatar">
            <?= strtoupper(substr($user['name'], 0, 1)) ?>
        </div>
        <div class="user-info">
            <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
            <div class="user-role"><?= htmlspecialchars($user['email']) ?></div>
        </div>
    </div>
    <?php endif; ?>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
