<?php
/**
 * Savings Goals Management & Progress Tracker
 * Allows setting milestone targets and contributing funds
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$userId = $user['id'];
$pdo = getDBConnection();

$pageTitle = 'Savings Goals';
$currentPage = 'savings';

$errors = [];

// Handle Creating New Savings Goal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_goal') {
    $goalName = trim($_POST['goal_name'] ?? '');
    $targetAmount = (float)($_POST['target_amount'] ?? 0);
    $savedAmount = (float)($_POST['saved_amount'] ?? 0);
    $targetDate = trim($_POST['target_date'] ?? '');
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Invalid security token.';
    }

    if (empty($goalName) || strlen($goalName) < 2) {
        $errors[] = 'Please provide a valid goal name.';
    }

    if ($targetAmount <= 0) {
        $errors[] = 'Target amount must be a positive number.';
    }

    if (empty($targetDate)) {
        $errors[] = 'Please specify a target completion date.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO savings_goals (user_id, goal_name, target_amount, saved_amount, target_date, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $goalName, $targetAmount, $savedAmount, $targetDate]);

            set_flash('success', 'Savings goal "' . htmlspecialchars($goalName) . '" created successfully!');
            header('Location: savings.php');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Failed to create goal: ' . $e->getMessage();
        }
    }
}

// Handle Contributing to an existing Savings Goal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'contribute') {
    $goalId = (int)($_POST['goal_id'] ?? 0);
    $depositAmount = (float)($_POST['deposit_amount'] ?? 0);
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Invalid security token.';
    }

    if ($goalId <= 0 || $depositAmount <= 0) {
        $errors[] = 'Please enter a valid contribution amount.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE savings_goals 
                SET saved_amount = saved_amount + ? 
                WHERE goal_id = ? AND user_id = ?
            ");
            $stmt->execute([$depositAmount, $goalId, $userId]);

            set_flash('success', 'Deposited ' . format_currency($depositAmount) . ' towards your savings goal!');
            header('Location: savings.php');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Failed to record contribution: ' . $e->getMessage();
        }
    }
}

// Handle Goal Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $goalId = (int)($_POST['goal_id'] ?? 0);
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (verify_csrf_token($csrfToken) && $goalId > 0) {
        $stmt = $pdo->prepare("DELETE FROM savings_goals WHERE goal_id = ? AND user_id = ?");
        $stmt->execute([$goalId, $userId]);
        set_flash('success', 'Savings goal was deleted.');
        header('Location: savings.php');
        exit;
    }
}

// Fetch all goals
$stmtGoals = $pdo->prepare("
    SELECT * FROM savings_goals 
    WHERE user_id = ? 
    ORDER BY (saved_amount / target_amount) DESC, target_date ASC
");
$stmtGoals->execute([$userId]);
$goals = $stmtGoals->fetchAll();

// Calculate total saved
$totalSavedAll = 0.0;
$totalTargetAll = 0.0;
foreach ($goals as $g) {
    $totalSavedAll += (float)$g['saved_amount'];
    $totalTargetAll += (float)$g['target_amount'];
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

        <?php if (!empty($errors)): ?>
            <div class="flash-alert flash-danger">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div>
                    <?php foreach ($errors as $err): ?>
                        <div><?= htmlspecialchars($err) ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid-2" style="grid-template-columns: 1fr 340px; align-items: start;">
            
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <div>
                        <h2>Savings Goals</h2>
                        <p style="color: var(--text-secondary); font-size: 0.88rem;">Track progress towards milestone purchases and emergency buffers</p>
                    </div>
                    <div style="background: white; padding: 8px 16px; border-radius: var(--radius-md); border: 1px solid var(--border-color); font-size: 0.85rem;">
                        Total Saved: <strong style="color: var(--success); font-size: 1rem;"><?= format_currency($totalSavedAll) ?></strong>
                    </div>
                </div>

                <?php if (empty($goals)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fa-solid fa-piggy-bank"></i>
                        </div>
                        <div class="empty-state-title">No Savings Goals Set Yet</div>
                        <div class="empty-state-desc">Define target milestone funds (such as Vacation, Laptop, or Emergency Buffer) to visualize your progress and stay motivated!</div>
                    </div>
                <?php else: ?>
                    <div style="display: grid; gap: 18px;">
                        <?php foreach ($goals as $g): 
                            $target = (float)$g['target_amount'];
                            $saved = (float)$g['saved_amount'];
                            $pct = $target > 0 ? min(round(($saved / $target) * 100), 100) : 0;
                            $remaining = max($target - $saved, 0);
                            $isCompleted = $saved >= $target;
                        ?>
                            <div class="card" style="margin-bottom: 0;">
                                <div class="card-body" style="padding: 22px;">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                                        <div>
                                            <h4 style="font-size: 1.1rem; margin-bottom: 4px; display: flex; align-items: center; gap: 8px;">
                                                <i class="fa-solid fa-bullseye" style="color: var(--brand-primary);"></i>
                                                <?= htmlspecialchars($g['goal_name']) ?>
                                                <?php if ($isCompleted): ?>
                                                    <span style="font-size: 0.72rem; background: var(--success-light); color: var(--success); padding: 2px 8px; border-radius: 12px;">Achieved!</span>
                                                <?php endif; ?>
                                            </h4>
                                            <span style="font-size: 0.78rem; color: var(--text-secondary);">
                                                Target Completion: <?= format_date($g['target_date']) ?>
                                            </span>
                                        </div>

                                        <form method="POST" action="savings.php" id="deleteGoalForm_<?= $g['goal_id'] ?>" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="goal_id" value="<?= $g['goal_id'] ?>">
                                            <button type="button" onclick="confirmAction('Are you sure you want to delete the savings goal &quot;<?= htmlspecialchars(addslashes($g['goal_name'])) ?>&quot;?', () => document.getElementById('deleteGoalForm_<?= $g['goal_id'] ?>').submit(), 'Delete Savings Goal')" style="background: none; border: none; color: #94a3b8; cursor: pointer; padding: 4px;" title="Delete Goal">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <div class="progress-container">
                                        <div class="progress-header">
                                            <span><strong><?= format_currency($saved) ?></strong> of <?= format_currency($target) ?></span>
                                            <span><strong><?= $pct ?>%</strong></span>
                                        </div>
                                        <div class="progress-bar-bg" style="height: 10px;">
                                            <div class="progress-bar-fill <?= $isCompleted ? 'progress-success' : 'progress-normal' ?>" style="width: <?= $pct ?>%;"></div>
                                        </div>
                                    </div>

                                    <!-- Quick Deposit Form -->
                                    <div style="margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--border-card); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                                        <span style="font-size: 0.82rem; color: var(--text-secondary);">
                                            Remaining: <strong><?= format_currency($remaining) ?></strong>
                                        </span>

                                        <form method="POST" action="savings.php" style="display: flex; gap: 8px;">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                            <input type="hidden" name="action" value="contribute">
                                            <input type="hidden" name="goal_id" value="<?= $g['goal_id'] ?>">
                                            <input type="number" step="0.01" min="1" name="deposit_amount" placeholder="₹ Amount" class="form-control" style="width: 100px; padding: 5px 8px; font-size: 0.85rem;" required>
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                <i class="fa-solid fa-plus"></i> Add Funds
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Create New Goal Form -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-plus-circle" style="color: var(--brand-primary);"></i> New Savings Goal</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="savings.php">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="action" value="create_goal">

                        <div class="form-group">
                            <label class="form-label" for="goal_name">Goal Title</label>
                            <input type="text" id="goal_name" name="goal_name" class="form-control" placeholder="e.g. New Car, Trip to Japan" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="target_amount">Target Amount (₹)</label>
                            <input type="number" step="0.01" min="1" id="target_amount" name="target_amount" class="form-control" placeholder="25000.00" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="saved_amount">Initial Starting Deposit (₹)</label>
                            <input type="number" step="0.01" min="0" id="saved_amount" name="saved_amount" class="form-control" value="0.00">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="target_date">Target Completion Date</label>
                            <input type="date" id="target_date" name="target_date" class="form-control" value="<?= date('Y-m-d', strtotime('+3 months')) ?>" required>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 11px;">
                            <i class="fa-solid fa-piggy-bank"></i> Start Goal
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div> <!-- End content-body -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
