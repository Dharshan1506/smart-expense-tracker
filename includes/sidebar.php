<?php
/**
 * Sidebar Navigation Component
 * Responsive Fintech Sidebar with Active State & Academic DBMS Branding
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
        <span class="sidebar-brand-badge">DBMS 3NF</span>
    </div>

    <ul class="sidebar-menu">
        <li class="sidebar-heading">Core Modules</li>
        <li class="sidebar-item">
            <a href="dashboard.php" class="sidebar-link <?= ($currentPage === 'dashboard') ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="transactions.php" class="sidebar-link <?= in_array($currentPage, ['transactions', 'add_transaction', 'edit_transaction']) ? 'active' : '' ?>">
                <i class="fa-solid fa-arrow-right-arrow-left"></i>
                <span>Transactions</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="categories.php" class="sidebar-link <?= ($currentPage === 'categories') ? 'active' : '' ?>">
                <i class="fa-solid fa-layer-group"></i>
                <span>Categories</span>
            </a>
        </li>

        <li class="sidebar-heading">Budgeting & Goals</li>
        <li class="sidebar-item">
            <a href="budgets.php" class="sidebar-link <?= ($currentPage === 'budgets') ? 'active' : '' ?>">
                <i class="fa-solid fa-calculator"></i>
                <span>Budgets</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="savings.php" class="sidebar-link <?= ($currentPage === 'savings') ? 'active' : '' ?>">
                <i class="fa-solid fa-piggy-bank"></i>
                <span>Savings Goals</span>
            </a>
        </li>

        <li class="sidebar-heading">Intelligence & Analytics</li>
        <li class="sidebar-item">
            <a href="analytics.php" class="sidebar-link <?= ($currentPage === 'analytics') ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-line"></i>
                <span>Analytics</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="reports.php" class="sidebar-link <?= ($currentPage === 'reports') ? 'active' : '' ?>">
                <i class="fa-solid fa-file-invoice"></i>
                <span>Reports</span>
            </a>
        </li>

        <li class="sidebar-heading">College DBMS Showcase</li>
        <li class="sidebar-item">
            <a href="queries.php" class="sidebar-link <?= ($currentPage === 'queries') ? 'active' : '' ?>" style="border: 1px dashed rgba(59, 130, 246, 0.45);">
                <i class="fa-solid fa-database" style="color: #60a5fa;"></i>
                <span style="font-weight: 700; color: #93c5fd;">SQL Queries Demo</span>
                <span class="sidebar-badge" style="background: #2563eb; color: #fff;">20 Queries</span>
            </a>
        </li>

        <li class="sidebar-heading">Account</li>
        <li class="sidebar-item">
            <a href="profile.php" class="sidebar-link <?= ($currentPage === 'profile') ? 'active' : '' ?>">
                <i class="fa-solid fa-user-gear"></i>
                <span>Profile Settings</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="logout.php" class="sidebar-link" onclick="return confirmAction('Are you sure you want to sign out?', () => { window.location.href = 'logout.php'; });">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                <span>Sign Out</span>
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
