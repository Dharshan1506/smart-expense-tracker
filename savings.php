<?php
/**
 * Savings Goals Management & Progress Tracker
 * Modern Fintech SaaS Milestone Targets with SVG Progress Rings & Fund Contribution
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

            set_flash('success', 'Deposited ' . format_currency($depositAmount) . ' towards your savings milestone!');
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
        set_flash('success', 'Savings goal was removed.');
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
$completedGoalsCount = 0;

foreach ($goals as $g) {
    $target = (float)$g['target_amount'];
    $saved = (float)$g['saved_amount'];
    $totalSavedAll += $saved;
    $totalTargetAll += $target;
    if ($saved >= $target && $target > 0) {
        $completedGoalsCount++;
    }
}

$totalOutstanding = max(0, $totalTargetAll - $totalSavedAll);
$overallPct = $totalTargetAll > 0 ? min(round(($totalSavedAll / $totalTargetAll) * 100), 100) : 0;

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
            <div class="flash-alert flash-danger">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div>
                    <?php foreach ($errors as $err): ?>
                        <div><?= htmlspecialchars($err) ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Page Header Bar -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
            <div>
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 4px;">
                    <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: var(--accent-emerald); border: 1px solid rgba(16, 185, 129, 0.3);">
                        <i class="fa-solid fa-piggy-bank"></i> Capital Accumulator
                    </span>
                    <?php if ($completedGoalsCount > 0): ?>
                        <span class="badge badge-success">
                            <i class="fa-solid fa-trophy"></i> <?= $completedGoalsCount ?> Achieved
                        </span>
                    <?php endif; ?>
                </div>
                <h2 style="font-size: 1.6rem; font-weight: 800; color: var(--text-primary); margin: 0; letter-spacing: -0.02em;">
                    Savings Goals & Milestone Funds
                </h2>
                <p style="color: var(--text-secondary); font-size: 0.88rem; margin: 4px 0 0 0;">
                    Track milestone purchases, emergency buffers, and wealth targets
                </p>
            </div>
            <button type="button" class="btn btn-primary btn-glow" onclick="focusNewGoalCard()">
                <i class="fa-solid fa-plus"></i> Launch New Goal
            </button>
        </div>

        <!-- 4-Stat Financial Strip -->
        <div class="grid-4" style="margin-bottom: 24px;">
            <div class="card" style="margin-bottom: 0; border-left: 4px solid var(--accent-cyan);">
                <div class="card-body" style="padding: 18px 20px;">
                    <div style="color: var(--text-muted); font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">
                        Total Target Capital
                    </div>
                    <div style="font-size: 1.5rem; font-weight: 800; font-family: var(--font-mono); color: var(--text-primary);">
                        <?= format_currency($totalTargetAll) ?>
                    </div>
                    <div style="font-size: 0.78rem; color: var(--text-secondary); margin-top: 4px;">
                        Across <?= count($goals) ?> milestone objectives
                    </div>
                </div>
            </div>

            <div class="card" style="margin-bottom: 0; border-left: 4px solid var(--accent-emerald);">
                <div class="card-body" style="padding: 18px 20px;">
                    <div style="color: var(--text-muted); font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">
                        Cumulative Saved
                    </div>
                    <div style="font-size: 1.5rem; font-weight: 800; font-family: var(--font-mono); color: var(--accent-emerald);">
                        <?= format_currency($totalSavedAll) ?>
                    </div>
                    <div style="font-size: 0.78rem; color: var(--text-secondary); margin-top: 4px;">
                        Secured in reserves
                    </div>
                </div>
            </div>

            <div class="card" style="margin-bottom: 0; border-left: 4px solid var(--accent-primary);">
                <div class="card-body" style="padding: 18px 20px;">
                    <div style="color: var(--text-muted); font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">
                        Remaining to Fund
                    </div>
                    <div style="font-size: 1.5rem; font-weight: 800; font-family: var(--font-mono); color: var(--text-primary);">
                        <?= format_currency($totalOutstanding) ?>
                    </div>
                    <div style="font-size: 0.78rem; color: var(--text-secondary); margin-top: 4px;">
                        Outstanding capital needed
                    </div>
                </div>
            </div>

            <div class="card" style="margin-bottom: 0; border-left: 4px solid var(--accent-purple);">
                <div class="card-body" style="padding: 18px 20px;">
                    <div style="color: var(--text-muted); font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">
                        Overall Progress
                    </div>
                    <div style="display: flex; align-items: baseline; gap: 8px;">
                        <span style="font-size: 1.5rem; font-weight: 800; font-family: var(--font-mono); color: var(--accent-cyan);">
                            <?= $overallPct ?>%
                        </span>
                        <span style="font-size: 0.78rem; color: var(--text-secondary);">milestones funded</span>
                    </div>
                    <div class="progress-bar-bg" style="height: 6px; margin-top: 8px;">
                        <div class="progress-bar-fill progress-normal" style="width: <?= $overallPct ?>%;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid-2" style="grid-template-columns: 1fr 340px; align-items: start; gap: 24px;">
            
            <div>
                <?php if (empty($goals)): ?>
                    <div class="card">
                        <div class="empty-state" style="padding: 56px 24px;">
                            <div class="empty-state-icon" style="background: rgba(6, 182, 212, 0.12); color: var(--accent-cyan); width: 68px; height: 68px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px auto; font-size: 1.8rem; border: 1px solid rgba(6, 182, 212, 0.25);">
                                <i class="fa-solid fa-piggy-bank"></i>
                            </div>
                            <h3 class="empty-state-title" style="font-size: 1.25rem; font-weight: 700;">No Savings Goals Set Yet</h3>
                            <p class="empty-state-text" style="max-width: 440px; margin: 8px auto 20px auto; color: var(--text-secondary); font-size: 0.88rem;">
                                Define target milestone funds (such as Vacation, Mac Studio, Emergency Buffer, or Investment Seed) to track progress and stay motivated!
                            </p>
                            <button type="button" class="btn btn-primary btn-glow" onclick="focusNewGoalCard()">
                                <i class="fa-solid fa-plus"></i> Create First Goal
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="display: grid; gap: 18px;">
                        <?php foreach ($goals as $g): 
                            $target = (float)$g['target_amount'];
                            $saved = (float)$g['saved_amount'];
                            $pct = $target > 0 ? min(round(($saved / $target) * 100), 100) : 0;
                            $remaining = max($target - $saved, 0);
                            $isCompleted = $saved >= $target;

                            // Calculate countdown
                            $endDateTime = new DateTime($g['target_date']);
                            $nowDateTime = new DateTime(date('Y-m-d'));
                            $daysLeft = (int)$nowDateTime->diff($endDateTime)->format("%r%a");

                            // SVG circular math (radius 28 -> circum 175.9)
                            $radius = 28;
                            $circumference = 2 * M_PI * $radius;
                            $offset = $circumference - ($pct / 100 * $circumference);
                        ?>
                            <div class="card" style="margin-bottom: 0; border: 1px solid <?= $isCompleted ? 'rgba(16, 185, 129, 0.4)' : 'var(--border-card)' ?>; background: linear-gradient(180deg, <?= $isCompleted ? 'rgba(16, 185, 129, 0.08)' : 'rgba(14, 21, 38, 0.75)' ?> 0%, rgba(14, 21, 38, 0.75) 100%);">
                                <div class="card-body" style="padding: 22px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 16px;">
                                        
                                        <!-- Title and info -->
                                        <div style="display: flex; align-items: center; gap: 14px;">
                                            <div style="width: 48px; height: 48px; border-radius: var(--radius-md); background: <?= $isCompleted ? 'rgba(16, 185, 129, 0.15)' : 'rgba(6, 182, 212, 0.15)' ?>; color: <?= $isCompleted ? 'var(--accent-emerald)' : 'var(--accent-cyan)' ?>; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; border: 1px solid <?= $isCompleted ? 'rgba(16, 185, 129, 0.3)' : 'rgba(6, 182, 212, 0.3)' ?>;">
                                                <i class="fa-solid <?= $isCompleted ? 'fa-trophy' : 'fa-bullseye' ?>"></i>
                                            </div>
                                            <div>
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <h4 style="font-size: 1.15rem; font-weight: 700; margin: 0; color: var(--text-primary);"><?= htmlspecialchars($g['goal_name']) ?></h4>
                                                    <?php if ($isCompleted): ?>
                                                        <span class="badge badge-success" style="font-size: 0.72rem;">
                                                            <i class="fa-solid fa-check"></i> 100% Achieved!
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge" style="background: rgba(6, 182, 212, 0.12); color: var(--accent-cyan); border: 1px solid rgba(6, 182, 212, 0.25); font-size: 0.72rem;">
                                                            <?= $pct ?>% Funded
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 4px; display: flex; align-items: center; gap: 12px;">
                                                    <span>
                                                        <i class="fa-regular fa-calendar-check" style="margin-right: 4px; color: var(--text-muted);"></i>
                                                        Target: <?= format_date($g['target_date']) ?>
                                                    </span>
                                                    <span>&bull;</span>
                                                    <span>
                                                        <i class="fa-regular fa-clock" style="margin-right: 4px; color: var(--text-muted);"></i>
                                                        <?= $daysLeft >= 0 ? ($daysLeft . ' days remaining') : '<span style="color: var(--accent-coral);">Past target date</span>' ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Circular gauge & Delete -->
                                        <div style="display: flex; align-items: center; gap: 14px;">
                                            <div style="position: relative; width: 62px; height: 62px; display: flex; align-items: center; justify-content: center;">
                                                <svg width="62" height="62" viewBox="0 0 64 64" style="transform: rotate(-90deg);">
                                                    <circle cx="32" cy="32" r="<?= $radius ?>" stroke="rgba(255,255,255,0.08)" stroke-width="5" fill="none"/>
                                                    <circle cx="32" cy="32" r="<?= $radius ?>" stroke="<?= $isCompleted ? 'var(--accent-emerald)' : 'var(--accent-cyan)' ?>" stroke-width="5" fill="none"
                                                            stroke-dasharray="<?= $circumference ?>" stroke-dashoffset="<?= $offset ?>"
                                                            stroke-linecap="round" style="transition: stroke-dashoffset 0.8s ease;"/>
                                                </svg>
                                                <div style="position: absolute; text-align: center;">
                                                    <span style="font-size: 0.75rem; font-weight: 800; font-family: var(--font-mono); color: var(--text-primary);"><?= $pct ?>%</span>
                                                </div>
                                            </div>

                                            <form method="POST" action="savings.php" id="deleteGoalForm_<?= $g['goal_id'] ?>" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="goal_id" value="<?= $g['goal_id'] ?>">
                                                <button type="button" class="btn btn-outline btn-sm btn-icon" title="Delete Goal" onclick="confirmDeleteGoal(<?= $g['goal_id'] ?>, '<?= htmlspecialchars(addslashes($g['goal_name'])) ?>')" style="border-color: rgba(244, 63, 94, 0.3); color: var(--accent-coral);">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>

                                    <!-- Progress indicator -->
                                    <div class="progress-container">
                                        <div class="progress-header" style="font-size: 0.84rem; margin-bottom: 8px;">
                                            <span>
                                                Saved: <strong style="color: var(--accent-emerald); font-family: var(--font-mono);"><?= format_currency($saved) ?></strong> 
                                                <span style="color: var(--text-muted);">of <?= format_currency($target) ?></span>
                                            </span>
                                            <span>
                                                Remaining: <strong style="color: var(--text-primary); font-family: var(--font-mono);"><?= format_currency($remaining) ?></strong>
                                            </span>
                                        </div>
                                        <div class="progress-bar-bg" style="height: 8px; background: rgba(255,255,255,0.06);">
                                            <div class="progress-bar-fill <?= $isCompleted ? 'progress-success' : 'progress-normal' ?>" style="width: <?= $pct ?>%;"></div>
                                        </div>
                                    </div>

                                    <!-- Quick Fund Contribution Strip -->
                                    <div style="margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--border-card); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span style="font-size: 0.8rem; color: var(--text-secondary);">Quick deposit:</span>
                                            <button type="button" class="btn btn-outline btn-sm" onclick="setQuickDeposit(<?= $g['goal_id'] ?>, 500)" style="padding: 2px 8px; font-size: 0.76rem; border-color: rgba(255,255,255,0.12); color: var(--text-secondary);">+₹500</button>
                                            <button type="button" class="btn btn-outline btn-sm" onclick="setQuickDeposit(<?= $g['goal_id'] ?>, 1000)" style="padding: 2px 8px; font-size: 0.76rem; border-color: rgba(255,255,255,0.12); color: var(--text-secondary);">+₹1,000</button>
                                            <button type="button" class="btn btn-outline btn-sm" onclick="setQuickDeposit(<?= $g['goal_id'] ?>, 5000)" style="padding: 2px 8px; font-size: 0.76rem; border-color: rgba(255,255,255,0.12); color: var(--text-secondary);">+₹5,000</button>
                                        </div>

                                        <form method="POST" action="savings.php" id="depositForm_<?= $g['goal_id'] ?>" style="display: flex; gap: 8px; align-items: center;">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                            <input type="hidden" name="action" value="contribute">
                                            <input type="hidden" name="goal_id" value="<?= $g['goal_id'] ?>">
                                            <div class="input-icon-wrapper" style="width: 130px;">
                                                <i class="fa-solid fa-indian-rupee-sign" style="font-size: 0.76rem;"></i>
                                                <input type="number" step="0.01" min="1" id="deposit_amount_<?= $g['goal_id'] ?>" name="deposit_amount" placeholder="Amount" class="form-control" style="padding: 6px 8px 6px 26px; font-size: 0.84rem; font-family: var(--font-mono); font-weight: 700;" required>
                                            </div>
                                            <button type="submit" class="btn btn-primary btn-sm" style="padding: 6px 12px; font-size: 0.82rem; white-space: nowrap;">
                                                <i class="fa-solid fa-circle-plus"></i> Add Funds
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Create New Goal Form (Side Panel) -->
            <div class="card card-accent-emerald" id="createGoalCard" style="position: sticky; top: 96px;">
                <div class="card-header" style="display: flex; align-items: center; justify-content: space-between;">
                    <h3 class="card-title" style="font-size: 1rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 8px; margin: 0;">
                        <i class="fa-solid fa-bullseye" style="color: var(--accent-emerald);"></i>
                        New Milestone Goal
                    </h3>
                    <span class="badge badge-outline" style="font-size: 0.7rem;">Target</span>
                </div>
                <div class="card-body" style="padding: 20px;">
                    <form method="POST" action="savings.php">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="action" value="create_goal">

                        <div class="form-group" style="margin-bottom: 16px;">
                            <label class="form-label" for="goal_name">Goal Title <span style="color: var(--accent-coral);">*</span></label>
                            <input type="text" id="goal_name" name="goal_name" class="form-control" placeholder="e.g. MacBook Pro M4, Emergency Reserve" required>
                        </div>

                        <div class="form-group" style="margin-bottom: 16px;">
                            <label class="form-label" for="target_amount">Target Amount (₹) <span style="color: var(--accent-coral);">*</span></label>
                            <div class="input-icon-wrapper">
                                <i class="fa-solid fa-indian-rupee-sign" style="color: var(--accent-emerald);"></i>
                                <input type="number" step="0.01" min="1" id="target_amount" name="target_amount" class="form-control" placeholder="150000.00" required style="font-family: var(--font-mono); font-weight: 700;">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 16px;">
                            <label class="form-label" for="saved_amount">Initial Starting Deposit (₹)</label>
                            <div class="input-icon-wrapper">
                                <i class="fa-solid fa-indian-rupee-sign" style="color: var(--text-muted);"></i>
                                <input type="number" step="0.01" min="0" id="saved_amount" name="saved_amount" class="form-control" value="0.00" style="font-family: var(--font-mono);">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 20px;">
                            <label class="form-label" for="target_date">Target Completion Date <span style="color: var(--accent-coral);">*</span></label>
                            <input type="date" id="target_date" name="target_date" class="form-control" value="<?= date('Y-m-d', strtotime('+3 months')) ?>" required>
                        </div>

                        <button type="submit" class="btn btn-primary btn-glow" style="width: 100%; padding: 12px; font-weight: 700;">
                            <i class="fa-solid fa-piggy-bank"></i> Launch Milestone
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div> <!-- End content-body -->
</div>

<script>
function focusNewGoalCard() {
    const card = document.getElementById('createGoalCard');
    if (card) {
        card.scrollIntoView({ behavior: 'smooth' });
        const nameInput = document.getElementById('goal_name');
        if (nameInput) nameInput.focus();
    }
}

function setQuickDeposit(goalId, amount) {
    const input = document.getElementById('deposit_amount_' + goalId);
    if (input) {
        input.value = amount;
        input.focus();
    }
}

function confirmDeleteGoal(id, name) {
    confirmAction(
        'Are you sure you want to delete the savings goal "' + name + '"? Accumulated balance records for this goal will be removed.',
        function() {
            document.getElementById('deleteGoalForm_' + id).submit();
        },
        'Confirm Goal Deletion'
    );
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
