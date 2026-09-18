<?php
/**
 * Categories Management
 * Displays default & user custom categories with usage analytics
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$userId = $user['id'];
$pdo = getDBConnection();

$pageTitle = 'Category Management';
$currentPage = 'categories';

$errors = [];

// Handle Adding a Custom Category
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoryName = trim($_POST['category_name'] ?? '');
    $categoryType = trim($_POST['category_type'] ?? 'expense');
    $icon = trim($_POST['icon'] ?? 'fa-tag');
    $color = trim($_POST['color'] ?? '#4f46e5');
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Invalid security token.';
    }

    if (empty($categoryName) || strlen($categoryName) < 2) {
        $errors[] = 'Category name must be at least 2 characters.';
    }

    if (!in_array($categoryType, ['income', 'expense'])) {
        $errors[] = 'Invalid category type.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO categories (user_id, category_name, category_type, icon, color, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $categoryName, $categoryType, $icon, $color]);

            set_flash('success', 'Custom category "' . htmlspecialchars($categoryName) . '" created successfully!');
            header('Location: categories.php');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Failed to create category: ' . $e->getMessage();
        }
    }
}

// Fetch all categories (global defaults + user's custom ones) along with usage statistics via LEFT JOIN
$stmtCategories = $pdo->prepare("
    SELECT 
        c.category_id, 
        c.user_id, 
        c.category_name, 
        c.category_type, 
        c.icon, 
        c.color,
        COUNT(t.transaction_id) AS transaction_count,
        COALESCE(SUM(t.amount), 0) AS total_spent
    FROM categories c
    LEFT JOIN transactions t ON t.category_id = c.category_id AND t.user_id = ?
    WHERE c.user_id IS NULL OR c.user_id = ?
    GROUP BY c.category_id, c.user_id, c.category_name, c.category_type, c.icon, c.color
    ORDER BY c.category_type ASC, transaction_count DESC, c.category_name ASC
");
$stmtCategories->execute([$userId, $userId]);
$categories = $stmtCategories->fetchAll();

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
            
            <!-- Category List & Usage Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-layer-group" style="color: var(--brand-primary);"></i> Categories & Spending Usage</h3>
                    <span style="font-size: 0.85rem; color: var(--text-secondary);"><?= count($categories) ?> Available</span>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Type</th>
                                    <th>Transactions</th>
                                    <th style="text-align: right;">Total Volume</th>
                                    <th>Scope</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $cat): ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 12px;">
                                                <div style="width: 36px; height: 36px; border-radius: 8px; background: <?= htmlspecialchars($cat['color']) ?>20; color: <?= htmlspecialchars($cat['color']) ?>; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                                                    <i class="fa-solid <?= htmlspecialchars($cat['icon'] ?: 'fa-tag') ?>"></i>
                                                </div>
                                                <strong style="font-size: 0.95rem;"><?= htmlspecialchars($cat['category_name']) ?></strong>
                                            </div>
                                        </td>
                                        <td>
                                            <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; padding: 2px 8px; border-radius: 4px; background: <?= $cat['category_type'] === 'income' ? 'var(--success-light); color: var(--success);' : 'var(--danger-light); color: var(--danger);' ?>">
                                                <?= ucfirst($cat['category_type']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?= $cat['transaction_count'] ?></strong> entries
                                        </td>
                                        <td style="text-align: right; font-weight: 700;">
                                            <?= format_currency($cat['total_spent']) ?>
                                        </td>
                                        <td>
                                            <?php if ($cat['user_id'] === null): ?>
                                                <span style="font-size: 0.75rem; background: #e2e8f0; color: #475569; padding: 2px 8px; border-radius: 4px;">System Default</span>
                                            <?php else: ?>
                                                <span style="font-size: 0.75rem; background: #e0e7ff; color: #4338ca; padding: 2px 8px; border-radius: 4px;">Custom User</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Create Custom Category Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-plus-circle" style="color: var(--brand-primary);"></i> Add Custom Category</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="categories.php">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                        <div class="form-group">
                            <label class="form-label" for="category_name">Category Name</label>
                            <input type="text" id="category_name" name="category_name" class="form-control" placeholder="e.g. Gym & Fitness, Investments" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="category_type">Category Type</label>
                            <select id="category_type" name="category_type" class="form-select" required>
                                <option value="expense">Expense</option>
                                <option value="income">Income</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="icon">Icon (FontAwesome class)</label>
                            <select id="icon" name="icon" class="form-select">
                                <option value="fa-tag">🏷️ Tag (fa-tag)</option>
                                <option value="fa-dumbbell">🏋️ Fitness (fa-dumbbell)</option>
                                <option value="fa-car">🚗 Vehicle (fa-car)</option>
                                <option value="fa-house">🏠 Housing (fa-house)</option>
                                <option value="fa-gift">🎁 Gifts (fa-gift)</option>
                                <option value="fa-gamepad">🎮 Gaming (fa-gamepad)</option>
                                <option value="fa-laptop-code">💻 Tech / Dev (fa-laptop-code)</option>
                                <option value="fa-chart-line">📈 Investments (fa-chart-line)</option>
                                <option value="fa-paw">🐾 Pets (fa-paw)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="color">Badge Color</label>
                            <input type="color" id="color" name="color" class="form-control" value="#4f46e5" style="height: 44px; padding: 4px;">
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 11px;">
                            <i class="fa-solid fa-folder-plus"></i> Create Category
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div> <!-- End content-body -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
