<?php
/**
 * User Profile & Account Settings
 * Modern Fintech Redesign with Security Controls & Database Account Telemetry
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
            set_flash('success', 'Profile name updated successfully.');
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

            set_flash('success', 'Your password has been changed successfully.');
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

        <div class="grid-2" style="grid-template-columns: 320px 1fr; align-items: start;">
            
            <!-- User Summary & DBMS Telemetry Card -->
            <div class="card">
                <div class="card-body" style="text-align: center; padding: 32px 22px;">
                    <div style="width: 76px; height: 76px; border-radius: var(--radius-full); background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 800; margin: 0 auto 16px; box-shadow: 0 8px 20px rgba(59, 130, 246, 0.35);">
                        <?= strtoupper(substr($userData['name'], 0, 1)) ?>
                    </div>
                    <h3 style="margin-bottom: 4px; font-size: 1.2rem; font-weight: 800; color: var(--text-primary);"><?= htmlspecialchars($userData['name']) ?></h3>
                    <p style="color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 20px;"><?= htmlspecialchars($userData['email']) ?></p>

                    <span class="badge badge-primary" style="margin-bottom: 22px;">
                        <i class="fa-solid fa-shield-halved" style="margin-right: 5px;"></i> Verified Account
                    </span>

                    <div style="border-top: 1px solid var(--border-color); padding-top: 18px; text-align: left; font-size: 0.86rem;">
                        <div style="margin-bottom: 12px; display: flex; justify-content: space-between;">
                            <span style="color: var(--text-secondary);">Member Since:</span>
                            <strong><?= format_date($userData['created_at']) ?></strong>
                        </div>
                        <div style="margin-bottom: 12px; display: flex; justify-content: space-between;">
                            <span style="color: var(--text-secondary);">Ledger Records:</span>
                            <span class="badge" style="background: #f1f5f9; color: #334155; font-family: monospace; font-size: 0.82rem;">
                                <?= $accountStats['total_tx'] ?>
                            </span>
                        </div>
                        <div style="margin-bottom: 12px; display: flex; justify-content: space-between;">
                            <span style="color: var(--text-secondary);">Active Budgets:</span>
                            <span class="badge" style="background: #f1f5f9; color: #334155; font-family: monospace; font-size: 0.82rem;">
                                <?= $accountStats['total_budgets'] ?>
                            </span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--text-secondary);">Savings Goals:</span>
                            <span class="badge" style="background: #f1f5f9; color: #334155; font-family: monospace; font-size: 0.82rem;">
                                <?= $accountStats['total_goals'] ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile & Security Forms -->
            <div>
                <!-- Update Name -->
                <div class="card" style="margin-bottom: 22px;">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fa-solid fa-user-pen" style="color: var(--brand-primary);"></i> Profile Information</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="profile.php">
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                            <input type="hidden" name="action" value="update_profile">

                            <div class="form-group">
                                <label class="form-label" for="name">Full Name</label>
                                <div class="input-icon-wrapper">
                                    <i class="fa-solid fa-user"></i>
                                    <input type="text" id="name" name="name" class="form-control" value="<?= htmlspecialchars($userData['name']) ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="email">Registered Email Address</label>
                                <div class="input-icon-wrapper">
                                    <i class="fa-solid fa-envelope"></i>
                                    <input type="email" id="email" class="form-control" value="<?= htmlspecialchars($userData['email']) ?>" disabled style="background: #f8fafc; cursor: not-allowed;">
                                </div>
                                <small style="color: var(--text-secondary); font-size: 0.78rem; margin-top: 4px; display: block;">
                                    Email is bound to your primary user key in the MySQL <code>users</code> table.
                                </small>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-check"></i> Save Changes
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fa-solid fa-lock" style="color: #f59e0b;"></i> Security & Password</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="profile.php">
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                            <input type="hidden" name="action" value="change_password">

                            <div class="form-group">
                                <label class="form-label" for="current_password">Current Password</label>
                                <div class="input-icon-wrapper">
                                    <i class="fa-solid fa-key"></i>
                                    <input type="password" id="current_password" name="current_password" class="form-control" placeholder="••••••••" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="new_password">New Password (Min 6 characters)</label>
                                <div class="input-icon-wrapper">
                                    <i class="fa-solid fa-lock"></i>
                                    <input type="password" id="new_password" name="new_password" class="form-control" placeholder="••••••••" required minlength="6">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="confirm_password">Confirm New Password</label>
                                <div class="input-icon-wrapper">
                                    <i class="fa-solid fa-shield-check"></i>
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="••••••••" required>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary" style="background: #f59e0b; border-color: #f59e0b;">
                                <i class="fa-solid fa-shield-halved"></i> Update Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div> <!-- End content-body -->
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
