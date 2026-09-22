<?php
/**
 * User Profile & Account Settings
 * Modern Fintech SaaS Architecture with Security Controls & DBMS Telemetry
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$userId = $user['id'];
$pdo = getDBConnection();

$pageTitle = 'Profile & Security';
$currentPage = 'profile';

$errors = [];

// Fetch current user record
$stmtUser = $pdo->prepare("SELECT user_id, name, email, created_at, updated_at FROM users WHERE user_id = ?");
$stmtUser->execute([$userId]);
$userData = $stmtUser->fetch();

// Handle Profile Update (Name change)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $name = trim($_POST['name'] ?? '');
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Invalid security token.';
    }

    if (empty($name) || strlen($name) < 2) {
        $errors[] = 'Name must be at least 2 characters long.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET name = ? WHERE user_id = ?");
            $stmt->execute([$name, $userId]);
            $_SESSION['user_name'] = $name;
            $userData['name'] = $name;
            set_flash('success', 'Profile identity updated successfully.');
            header('Location: profile.php');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Failed to update profile: ' . $e->getMessage();
        }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Invalid security token.';
    }

    if (empty($currentPassword) || empty($newPassword)) {
        $errors[] = 'Please fill in all password fields.';
    } elseif (strlen($newPassword) < 6) {
        $errors[] = 'New password must be at least 6 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $errors[] = 'New password and confirmation do not match.';
    } else {
        $stmtPass = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmtPass->execute([$userId]);
        $existingHash = $stmtPass->fetchColumn();

        if ($existingHash && password_verify($currentPassword, $existingHash)) {
            $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmtUpdate = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmtUpdate->execute([$newHash, $userId]);

            set_flash('success', 'Your password credentials have been updated securely.');
            header('Location: profile.php');
            exit;
        } else {
            $errors[] = 'Incorrect current password. Please verify and try again.';
        }
    }
}

// Fetch Account Telemetry
$stmtStats = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM transactions WHERE user_id = ?) as total_tx,
        (SELECT COUNT(*) FROM budgets WHERE user_id = ?) as total_budgets,
        (SELECT COUNT(*) FROM savings_goals WHERE user_id = ?) as total_goals
");
$stmtStats->execute([$userId, $userId, $userId]);
$accountStats = $stmtStats->fetch();

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

        <?php if (!empty($errors)): ?>
            <div class="flash-alert flash-danger" style="display: block;">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <strong>Error:</strong>
                </div>
                <ul style="margin: 0; padding-left: 20px; font-size: 0.84rem;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Page Header Bar -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
            <div>
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 4px;">
                    <span class="badge" style="background: rgba(99, 102, 241, 0.15); color: var(--accent-primary); border: 1px solid rgba(99, 102, 241, 0.3);">
                        <i class="fa-solid fa-id-card"></i> Identity & Access
                    </span>
                    <span class="badge badge-outline">Account Telemetry</span>
                </div>
                <h2 style="font-size: 1.6rem; font-weight: 800; color: var(--text-primary); margin: 0; letter-spacing: -0.02em;">
                    User Profile & Security Console
                </h2>
                <p style="color: var(--text-secondary); font-size: 0.88rem; margin: 4px 0 0 0;">
                    Manage your credentials, verify account telemetry, and configure security controls
                </p>
            </div>
            <a href="queries.php" class="btn btn-outline" style="border-color: rgba(99, 102, 241, 0.3); color: var(--accent-primary);">
                <i class="fa-solid fa-database"></i> Query Console
            </a>
        </div>

        <div class="grid-2" style="grid-template-columns: 340px 1fr; align-items: start; gap: 24px;">
            
            <!-- User Summary & DBMS Telemetry Card -->
            <div class="card card-accent-purple" style="position: sticky; top: 96px;">
                <div class="card-body" style="text-align: center; padding: 32px 22px;">
                    <div style="width: 80px; height: 80px; border-radius: var(--radius-full); background: linear-gradient(135deg, #6366f1 0%, #a855f7 50%, #ec4899 100%); color: white; display: flex; align-items: center; justify-content: center; font-size: 2.2rem; font-weight: 800; margin: 0 auto 16px; box-shadow: 0 8px 24px rgba(168, 85, 247, 0.35); border: 2px solid rgba(255, 255, 255, 0.15);">
                        <?= strtoupper(substr($userData['name'], 0, 1)) ?>
                    </div>
                    <h3 style="margin-bottom: 4px; font-size: 1.3rem; font-weight: 800; color: var(--text-primary);"><?= htmlspecialchars($userData['name']) ?></h3>
                    <p style="color: var(--text-secondary); font-size: 0.86rem; margin-bottom: 18px;"><?= htmlspecialchars($userData['email']) ?></p>

                    <div style="display: flex; justify-content: center; gap: 8px; margin-bottom: 24px;">
                        <span class="badge badge-success" style="font-size: 0.74rem;">
                            <i class="fa-solid fa-shield-check"></i> Verified Account
                        </span>
                        <span class="badge badge-outline" style="font-size: 0.74rem; font-family: var(--font-mono);">
                            UID: #<?= $userId ?>
                        </span>
                    </div>

                    <div style="border-top: 1px solid var(--border-card); padding-top: 20px; text-align: left; font-size: 0.86rem;">
                        <div style="margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: var(--text-secondary);"><i class="fa-regular fa-calendar" style="margin-right: 6px; color: var(--text-muted);"></i> Member Since:</span>
                            <strong style="color: var(--text-primary);"><?= format_date($userData['created_at']) ?></strong>
                        </div>
                        <div style="margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: var(--text-secondary);"><i class="fa-solid fa-table-list" style="margin-right: 6px; color: var(--accent-primary);"></i> Ledger Records:</span>
                            <span class="badge badge-outline" style="font-family: var(--font-mono); font-size: 0.82rem; font-weight: 700; color: var(--accent-primary);">
                                <?= $accountStats['total_tx'] ?>
                            </span>
                        </div>
                        <div style="margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: var(--text-secondary);"><i class="fa-solid fa-sliders" style="margin-right: 6px; color: var(--accent-amber);"></i> Active Ceilings:</span>
                            <span class="badge badge-outline" style="font-family: var(--font-mono); font-size: 0.82rem; font-weight: 700; color: var(--accent-amber);">
                                <?= $accountStats['total_budgets'] ?>
                            </span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: var(--text-secondary);"><i class="fa-solid fa-bullseye" style="margin-right: 6px; color: var(--accent-emerald);"></i> Savings Milestones:</span>
                            <span class="badge badge-outline" style="font-family: var(--font-mono); font-size: 0.82rem; font-weight: 700; color: var(--accent-emerald);">
                                <?= $accountStats['total_goals'] ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile & Security Forms -->
            <div>
                <!-- Update Name -->
                <div class="card card-accent-primary" style="margin-bottom: 24px;">
                    <div class="card-header">
                        <h3 class="card-title" style="font-size: 1.05rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 8px; margin: 0;">
                            <i class="fa-solid fa-user-pen" style="color: var(--accent-primary);"></i>
                            Profile Identity Details
                        </h3>
                    </div>
                    <div class="card-body" style="padding: 22px;">
                        <form method="POST" action="profile.php">
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                            <input type="hidden" name="action" value="update_profile">

                            <div class="form-group" style="margin-bottom: 18px;">
                                <label class="form-label" for="name">Display Name <span style="color: var(--accent-coral);">*</span></label>
                                <div class="input-icon-wrapper">
                                    <i class="fa-solid fa-user"></i>
                                    <input type="text" id="name" name="name" class="form-control" value="<?= htmlspecialchars($userData['name']) ?>" required>
                                </div>
                            </div>

                            <div class="form-group" style="margin-bottom: 20px;">
                                <label class="form-label" for="email">Registered Email Address</label>
                                <div class="input-icon-wrapper">
                                    <i class="fa-solid fa-envelope"></i>
                                    <input type="email" id="email" class="form-control" value="<?= htmlspecialchars($userData['email']) ?>" disabled style="cursor: not-allowed; opacity: 0.7;">
                                </div>
                                <small style="color: var(--text-muted); font-size: 0.78rem; margin-top: 6px; display: block;">
                                    Email is bound to your primary key in the MySQL <code>users</code> table. Contact support to transfer ownership.
                                </small>
                            </div>

                            <button type="submit" class="btn btn-primary btn-glow">
                                <i class="fa-solid fa-check"></i> Save Identity Changes
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="card card-accent-amber" style="margin-bottom: 0;">
                    <div class="card-header">
                        <h3 class="card-title" style="font-size: 1.05rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 8px; margin: 0;">
                            <i class="fa-solid fa-lock" style="color: var(--accent-amber);"></i>
                            Security Credentials & Password
                        </h3>
                    </div>
                    <div class="card-body" style="padding: 22px;">
                        <form method="POST" action="profile.php">
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                            <input type="hidden" name="action" value="change_password">

                            <div class="form-group" style="margin-bottom: 18px;">
                                <label class="form-label" for="current_password">Current Password <span style="color: var(--accent-coral);">*</span></label>
                                <div class="input-icon-wrapper" style="position: relative;">
                                    <i class="fa-solid fa-key"></i>
                                    <input type="password" id="current_password" name="current_password" class="form-control" placeholder="••••••••" required>
                                    <i class="fa-solid fa-eye" onclick="togglePassVisibility('current_password', this)" style="position: absolute; right: 14px; top: 14px; cursor: pointer; color: var(--text-muted); pointer-events: auto;"></i>
                                </div>
                            </div>

                            <div class="form-group" style="margin-bottom: 18px;">
                                <label class="form-label" for="new_password">New Password (Min 6 characters) <span style="color: var(--accent-coral);">*</span></label>
                                <div class="input-icon-wrapper" style="position: relative;">
                                    <i class="fa-solid fa-lock"></i>
                                    <input type="password" id="new_password" name="new_password" class="form-control" placeholder="••••••••" required minlength="6">
                                    <i class="fa-solid fa-eye" onclick="togglePassVisibility('new_password', this)" style="position: absolute; right: 14px; top: 14px; cursor: pointer; color: var(--text-muted); pointer-events: auto;"></i>
                                </div>
                            </div>

                            <div class="form-group" style="margin-bottom: 22px;">
                                <label class="form-label" for="confirm_password">Confirm New Password <span style="color: var(--accent-coral);">*</span></label>
                                <div class="input-icon-wrapper" style="position: relative;">
                                    <i class="fa-solid fa-shield-halved"></i>
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="••••••••" required>
                                    <i class="fa-solid fa-eye" onclick="togglePassVisibility('confirm_password', this)" style="position: absolute; right: 14px; top: 14px; cursor: pointer; color: var(--text-muted); pointer-events: auto;"></i>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #f59e0b, #d97706); border-color: #f59e0b; box-shadow: 0 4px 14px rgba(245, 158, 11, 0.35);">
                                <i class="fa-solid fa-shield-halved"></i> Update Password Credentials
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div> <!-- End content-body -->
</div>

<script>
function togglePassVisibility(inputId, icon) {
    const input = document.getElementById(inputId);
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
