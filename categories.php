<?php
/**
 * Categories Management & Taxonomy Registry
 * Modern Fintech Category Architecture with Volume Analytics & Custom Definition Panel
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
    $color = trim($_POST['color'] ?? '#6366f1');
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

// Telemetry Stats
$totalCategories = count($categories);
$expenseCategoriesCount = 0;
$incomeCategoriesCount = 0;
$customCategoriesCount = 0;
$totalVolumeAll = 0.0;

foreach ($categories as $cat) {
    if ($cat['category_type'] === 'expense') $expenseCategoriesCount++;
    if ($cat['category_type'] === 'income') $incomeCategoriesCount++;
    if ($cat['user_id'] !== null) $customCategoriesCount++;
    $totalVolumeAll += (float)$cat['total_spent'];
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
                    <span class="badge" style="background: rgba(168, 85, 247, 0.15); color: var(--accent-purple); border: 1px solid rgba(168, 85, 247, 0.3);">
                        <i class="fa-solid fa-tags"></i> Taxonomy Registry
                    </span>
                    <span class="badge badge-outline">3NF Normalized Table</span>
                </div>
                <h2 style="font-size: 1.6rem; font-weight: 800; color: var(--text-primary); margin: 0; letter-spacing: -0.02em;">
                    Category Management & Analytics
                </h2>
                <p style="color: var(--text-secondary); font-size: 0.88rem; margin: 4px 0 0 0;">
                    Organize financial ledger classification with custom tags and spending volume metrics
                </p>
            </div>
            <button type="button" class="btn btn-primary btn-glow" onclick="focusCategoryCard()">
                <i class="fa-solid fa-folder-plus"></i> Add Category
            </button>
        </div>

        <!-- 4-Stat Strip -->
        <div class="grid-4" style="margin-bottom: 24px;">
            <div class="card" style="margin-bottom: 0; border-left: 4px solid var(--accent-purple);">
                <div class="card-body" style="padding: 18px 20px;">
                    <div style="color: var(--text-muted); font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">
                        Total Taxonomies
                    </div>
                    <div style="font-size: 1.5rem; font-weight: 800; font-family: var(--font-mono); color: var(--text-primary);">
                        <?= $totalCategories ?>
                    </div>
                    <div style="font-size: 0.78rem; color: var(--text-secondary); margin-top: 4px;">
                        System defaults + custom
                    </div>
                </div>
            </div>

            <div class="card" style="margin-bottom: 0; border-left: 4px solid var(--accent-coral);">
                <div class="card-body" style="padding: 18px 20px;">
                    <div style="color: var(--text-muted); font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">
                        Expense Classes
                    </div>
                    <div style="font-size: 1.5rem; font-weight: 800; font-family: var(--font-mono); color: var(--accent-coral);">
                        <?= $expenseCategoriesCount ?>
                    </div>
                    <div style="font-size: 0.78rem; color: var(--text-secondary); margin-top: 4px;">
                        Outflow classifications
                    </div>
                </div>
            </div>

            <div class="card" style="margin-bottom: 0; border-left: 4px solid var(--accent-emerald);">
                <div class="card-body" style="padding: 18px 20px;">
                    <div style="color: var(--text-muted); font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">
                        Income Streams
                    </div>
                    <div style="font-size: 1.5rem; font-weight: 800; font-family: var(--font-mono); color: var(--accent-emerald);">
                        <?= $incomeCategoriesCount ?>
                    </div>
                    <div style="font-size: 0.78rem; color: var(--text-secondary); margin-top: 4px;">
                        Inflow classifications
                    </div>
                </div>
            </div>

            <div class="card" style="margin-bottom: 0; border-left: 4px solid var(--accent-cyan);">
                <div class="card-body" style="padding: 18px 20px;">
                    <div style="color: var(--text-muted); font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">
                        Custom User Defined
                    </div>
                    <div style="font-size: 1.5rem; font-weight: 800; font-family: var(--font-mono); color: var(--accent-cyan);">
                        <?= $customCategoriesCount ?>
                    </div>
                    <div style="font-size: 0.78rem; color: var(--text-secondary); margin-top: 4px;">
                        Tailored to your ledger
                    </div>
                </div>
            </div>
        </div>

        <div class="grid-2" style="grid-template-columns: 1fr 340px; align-items: start; gap: 24px;">
            
            <!-- Category List & Usage Table -->
            <div class="card card-accent-purple" style="margin-bottom: 0;">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                    <div>
                        <h3 class="card-title" style="font-size: 1.05rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 8px; margin: 0;">
                            <i class="fa-solid fa-layer-group" style="color: var(--accent-purple);"></i>
                            Classification Registry & Lifetime Volume
                        </h3>
                    </div>
                    <div style="display: flex; gap: 6px;">
                        <button type="button" class="btn btn-outline btn-sm active" id="filterAll" onclick="filterCatType('all')">All</button>
                        <button type="button" class="btn btn-outline btn-sm" id="filterExpense" onclick="filterCatType('expense')">Expense</button>
                        <button type="button" class="btn btn-outline btn-sm" id="filterIncome" onclick="filterCatType('income')">Income</button>
                    </div>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="table" id="categoryTable">
                            <thead>
                                <tr>
                                    <th>Category Tag</th>
                                    <th>Type</th>
                                    <th>Activity Count</th>
                                    <th style="text-align: right;">Lifetime Volume</th>
                                    <th>Scope</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $cat): 
                                    $catColor = $cat['color'] ?: '#6366f1';
                                    $catIcon = $cat['icon'] ?: 'fa-tag';
                                    $isExpense = $cat['category_type'] === 'expense';
                                ?>
                                    <tr class="cat-row" data-type="<?= $cat['category_type'] ?>">
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 12px;">
                                                <div style="width: 38px; height: 38px; border-radius: var(--radius-md); background: <?= htmlspecialchars($catColor) ?>22; color: <?= htmlspecialchars($catColor) ?>; display: flex; align-items: center; justify-content: center; font-size: 1.05rem; border: 1px solid <?= htmlspecialchars($catColor) ?>40;">
                                                    <i class="fa-solid <?= htmlspecialchars($catIcon) ?>"></i>
                                                </div>
                                                <div>
                                                    <strong style="font-size: 0.95rem; color: var(--text-primary);"><?= htmlspecialchars($cat['category_name']) ?></strong>
                                                    <div style="font-size: 0.72rem; color: var(--text-muted); font-family: var(--font-mono);">ID: #CAT-<?= $cat['category_id'] ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge <?= $isExpense ? 'badge-danger' : 'badge-success' ?>" style="font-size: 0.72rem;">
                                                <?= ucfirst($cat['category_type']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span style="font-family: var(--font-mono); font-weight: 700; color: var(--text-primary);"><?= $cat['transaction_count'] ?></span>
                                            <span style="color: var(--text-muted); font-size: 0.8rem;">records</span>
                                        </td>
                                        <td style="text-align: right; font-weight: 800; font-family: var(--font-mono); color: <?= $isExpense ? 'var(--accent-coral)' : 'var(--accent-emerald)' ?>;">
                                            <?= format_currency((float)$cat['total_spent']) ?>
                                        </td>
                                        <td>
                                            <?php if ($cat['user_id'] === null): ?>
                                                <span class="badge badge-outline" style="font-size: 0.7rem; color: var(--text-muted);">System Default</span>
                                            <?php else: ?>
                                                <span class="badge" style="background: rgba(99, 102, 241, 0.15); color: var(--accent-primary); border: 1px solid rgba(99, 102, 241, 0.3); font-size: 0.7rem;">
                                                    <i class="fa-solid fa-user-check"></i> Custom User
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Create Custom Category Card (Side Panel) -->
            <div class="card card-accent-cyan" id="createCategoryCard" style="position: sticky; top: 96px;">
                <div class="card-header" style="display: flex; align-items: center; justify-content: space-between;">
                    <h3 class="card-title" style="font-size: 1rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 8px; margin: 0;">
                        <i class="fa-solid fa-plus-circle" style="color: var(--accent-cyan);"></i>
                        New Taxonomy
                    </h3>
                    <span class="badge badge-outline" style="font-size: 0.7rem;">Custom</span>
                </div>
                <div class="card-body" style="padding: 20px;">
                    <form method="POST" action="categories.php">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                        <div class="form-group" style="margin-bottom: 16px;">
                            <label class="form-label" for="category_name">Category Title <span style="color: var(--accent-coral);">*</span></label>
                            <input type="text" id="category_name" name="category_name" class="form-control" placeholder="e.g. AI Subscriptions, Freelance" required>
                        </div>

                        <div class="form-group" style="margin-bottom: 16px;">
                            <label class="form-label" for="category_type">Ledger Direction <span style="color: var(--accent-coral);">*</span></label>
                            <select id="category_type" name="category_type" class="form-select" required>
                                <option value="expense">Expense (Debit / Outflow)</option>
                                <option value="income">Income (Credit / Inflow)</option>
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom: 16px;">
                            <label class="form-label" for="icon">Icon Class</label>
                            <select id="icon" name="icon" class="form-select">
                                <option value="fa-tag">🏷️ Tag (fa-tag)</option>
                                <option value="fa-robot">🤖 AI & Tech (fa-robot)</option>
                                <option value="fa-dumbbell">🏋️ Fitness & Health (fa-dumbbell)</option>
                                <option value="fa-car">🚗 Auto & Travel (fa-car)</option>
                                <option value="fa-house">🏠 Housing & Rent (fa-house)</option>
                                <option value="fa-gift">🎁 Gifts & Perks (fa-gift)</option>
                                <option value="fa-gamepad">🎮 Gaming & Media (fa-gamepad)</option>
                                <option value="fa-laptop-code">💻 Dev Tools (fa-laptop-code)</option>
                                <option value="fa-chart-line">📈 Capital & Stocks (fa-chart-line)</option>
                                <option value="fa-paw">🐾 Pets & Veterinary (fa-paw)</option>
                                <option value="fa-coffee">☕ Coffee & Dining (fa-coffee)</option>
                                <option value="fa-plane">✈️ Aviation & Holiday (fa-plane)</option>
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom: 20px;">
                            <label class="form-label" for="color">Accent Tint</label>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <input type="color" id="color" name="color" class="form-control" value="#6366f1" style="height: 44px; width: 64px; padding: 4px; cursor: pointer;">
                                <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                    <span class="color-preset" onclick="setColor('#6366f1')" style="width: 24px; height: 24px; border-radius: 50%; background: #6366f1; cursor: pointer; border: 1px solid rgba(255,255,255,0.2);"></span>
                                    <span class="color-preset" onclick="setColor('#06b6d4')" style="width: 24px; height: 24px; border-radius: 50%; background: #06b6d4; cursor: pointer; border: 1px solid rgba(255,255,255,0.2);"></span>
                                    <span class="color-preset" onclick="setColor('#10b981')" style="width: 24px; height: 24px; border-radius: 50%; background: #10b981; cursor: pointer; border: 1px solid rgba(255,255,255,0.2);"></span>
                                    <span class="color-preset" onclick="setColor('#f59e0b')" style="width: 24px; height: 24px; border-radius: 50%; background: #f59e0b; cursor: pointer; border: 1px solid rgba(255,255,255,0.2);"></span>
                                    <span class="color-preset" onclick="setColor('#f43f5e')" style="width: 24px; height: 24px; border-radius: 50%; background: #f43f5e; cursor: pointer; border: 1px solid rgba(255,255,255,0.2);"></span>
                                    <span class="color-preset" onclick="setColor('#a855f7')" style="width: 24px; height: 24px; border-radius: 50%; background: #a855f7; cursor: pointer; border: 1px solid rgba(255,255,255,0.2);"></span>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-glow" style="width: 100%; padding: 12px; font-weight: 700;">
                            <i class="fa-solid fa-folder-plus"></i> Register Taxonomy
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div> <!-- End content-body -->
</div>

<script>
function focusCategoryCard() {
    const card = document.getElementById('createCategoryCard');
    if (card) {
        card.scrollIntoView({ behavior: 'smooth' });
        const nameInput = document.getElementById('category_name');
        if (nameInput) nameInput.focus();
    }
}

function setColor(hex) {
    const colorInput = document.getElementById('color');
    if (colorInput) colorInput.value = hex;
}

function filterCatType(type) {
    document.getElementById('filterAll').classList.remove('active');
    document.getElementById('filterExpense').classList.remove('active');
    document.getElementById('filterIncome').classList.remove('active');

    if (type === 'all') document.getElementById('filterAll').classList.add('active');
    if (type === 'expense') document.getElementById('filterExpense').classList.add('active');
    if (type === 'income') document.getElementById('filterIncome').classList.add('active');

    const rows = document.querySelectorAll('.cat-row');
    rows.forEach(row => {
        if (type === 'all' || row.getAttribute('data-type') === type) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
