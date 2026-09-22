<?php
/**
 * Global Footer Include - ExpenseIQ Modern Fintech SaaS
 * Includes Shared Modals, Confirmation Dialogs, Responsive Drawer Logic, Chart.js, and Floating AI Assistant
 */
declare(strict_types=1);

$user = current_user();
$modalCategories = [];
if ($user && isset($pdo)) {
    try {
        $stmtModCats = $pdo->prepare("SELECT category_id, category_name, category_type FROM categories WHERE user_id IS NULL OR user_id = ? ORDER BY category_type ASC, category_name ASC");
        $stmtModCats->execute([$user['id']]);
        $modalCategories = $stmtModCats->fetchAll();
    } catch (Exception $e) {
        $modalCategories = [];
    }
}
?>
        </div> <!-- End of content-body -->
    </div> <!-- End of main-wrapper -->
</div> <!-- End of app-container -->

<?php if ($user): ?>
<!-- Global Quick Add Transaction Modal -->
<div class="modal-backdrop" id="addTransactionModal" onclick="if(event.target === this) closeAddTransactionModal()">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fa-solid fa-receipt" style="color: var(--brand-primary);"></i>
                Record New Transaction
            </h3>
            <button type="button" class="modal-close" onclick="closeAddTransactionModal()" aria-label="Close modal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form action="add_transaction.php" method="POST" id="modalTransactionForm">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                <!-- Type Selector -->
                <div class="form-group">
                    <label class="form-label">Transaction Classification</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <label style="display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px; border: 1px solid rgba(255,255,255,0.12); border-radius: var(--radius-md); cursor: pointer; font-weight: 700; font-size: 0.9rem; background: rgba(20, 31, 54, 0.7); transition: var(--transition);" id="modalExpenseLabel">
                            <input type="radio" name="transaction_type" value="expense" checked onchange="toggleModalType('expense')" style="accent-color: var(--danger);">
                            <span style="color: var(--danger-text);"><i class="fa-solid fa-arrow-down"></i> Expense</span>
                        </label>
                        <label style="display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px; border: 1px solid rgba(255,255,255,0.12); border-radius: var(--radius-md); cursor: pointer; font-weight: 700; font-size: 0.9rem; background: rgba(20, 31, 54, 0.7); transition: var(--transition);" id="modalIncomeLabel">
                            <input type="radio" name="transaction_type" value="income" onchange="toggleModalType('income')" style="accent-color: var(--success);">
                            <span style="color: var(--success-text);"><i class="fa-solid fa-arrow-up"></i> Income</span>
                        </label>
                    </div>
                </div>

                <!-- Amount & Date -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label">Amount (₹) <span style="color: var(--danger);">*</span></label>
                        <div class="input-icon-wrapper">
                            <i class="fa-solid fa-indian-rupee-sign"></i>
                            <input type="number" step="0.01" min="0.01" name="amount" required placeholder="0.00" class="form-control" style="font-family: var(--font-mono); font-size: 1rem; font-weight: 700;">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date <span style="color: var(--danger);">*</span></label>
                        <input type="date" name="transaction_date" required value="<?= date('Y-m-d') ?>" class="form-control">
                    </div>
                </div>

                <!-- Category -->
                <div class="form-group">
                    <label class="form-label">Category <span style="color: var(--danger);">*</span></label>
                    <select name="category_id" required class="form-select" id="modalCategorySelect">
                        <option value="">-- Choose Category --</option>
                        <?php foreach ($modalCategories as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>" data-type="<?= $cat['category_type'] ?>">
                                [<?= ucfirst($cat['category_type']) ?>] <?= htmlspecialchars($cat['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Description -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Description / Note <span style="color: var(--danger);">*</span></label>
                    <input type="text" name="description" required placeholder="e.g. Grocery store, Client invoice payment" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddTransactionModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-check"></i> Save Transaction
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Global Confirmation Dialog Modal -->
<div class="modal-backdrop" id="confirmDialogModal" onclick="if(event.target === this) closeConfirmDialog()">
    <div class="modal-dialog" style="max-width: 440px;">
        <div class="modal-header" style="border-bottom: none; padding-bottom: 0;">
            <h3 class="modal-title" id="confirmDialogTitle" style="color: var(--danger); font-size: 1.15rem;">
                <i class="fa-solid fa-triangle-exclamation"></i>
                Confirm Action
            </h3>
            <button type="button" class="modal-close" onclick="closeConfirmDialog()" aria-label="Close modal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body" style="padding-top: 14px;">
            <p id="confirmDialogMessage" style="font-size: 0.92rem; color: var(--text-secondary); line-height: 1.5; margin: 0;">
                Are you sure you want to proceed with this operation?
            </p>
        </div>
        <div class="modal-footer" style="border-top: none; background: transparent; padding-top: 0;">
            <button type="button" class="btn btn-secondary" onclick="closeConfirmDialog()">Cancel</button>
            <button type="button" class="btn btn-danger" id="confirmDialogProceedBtn">
                Proceed
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Chart.js 4.4 CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Core Global UI Interactions & Drawer Script -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Mobile Sidebar Drawer with Backdrop
    const mobileToggle = document.getElementById('mobileToggle');
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');

    function toggleSidebar() {
        if (!sidebar) return;
        const isOpen = sidebar.classList.toggle('open');
        if (backdrop) {
            backdrop.classList.toggle('active', isOpen);
        }
    }

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('open');
        if (backdrop) backdrop.classList.remove('active');
    }

    if (mobileToggle) {
        mobileToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleSidebar();
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeSidebar);
    }

    // Auto-hide flash alerts after 5 seconds with slide-out transition
    const flashAlerts = document.querySelectorAll('.flash-alert');
    flashAlerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-8px)';
            setTimeout(() => alert.remove(), 400);
        }, 5000);
    });
});

// Modal Logic
function openAddTransactionModal(defaultType = null) {
    const modal = document.getElementById('addTransactionModal');
    if (modal) {
        modal.classList.add('show');
        if (defaultType === 'income' || defaultType === 'expense') {
            const radio = modal.querySelector(`input[name="transaction_type"][value="${defaultType}"]`);
            if (radio) {
                radio.checked = true;
                toggleModalType(defaultType);
            }
        } else {
            const checkedRadio = modal.querySelector('input[name="transaction_type"]:checked');
            toggleModalType(checkedRadio ? checkedRadio.value : 'expense');
        }
    }
}

function openAddModal(defaultType = null) {
    openAddTransactionModal(defaultType);
}

function closeAddTransactionModal() {
    const modal = document.getElementById('addTransactionModal');
    if (modal) modal.classList.remove('show');
}

function toggleModalType(type) {
    const select = document.getElementById('modalCategorySelect');
    if (!select) return;
    const options = select.querySelectorAll('option');
    options.forEach(opt => {
        if (!opt.value) return;
        const optType = opt.getAttribute('data-type');
        if (optType === type) {
            opt.style.display = '';
        } else {
            opt.style.display = 'none';
        }
    });
}

// Global Confirmation Dialog Interceptor
let confirmCallback = null;
function confirmAction(message, onConfirm, title = 'Confirm Action') {
    const modal = document.getElementById('confirmDialogModal');
    const titleEl = document.getElementById('confirmDialogTitle');
    const msgEl = document.getElementById('confirmDialogMessage');
    const proceedBtn = document.getElementById('confirmDialogProceedBtn');

    if (!modal) {
        if (confirm(message)) {
            onConfirm();
        }
        return false;
    }

    if (titleEl) titleEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> ' + title;
    if (msgEl) msgEl.textContent = message;

    confirmCallback = onConfirm;
    modal.classList.add('show');

    proceedBtn.onclick = function() {
        modal.classList.remove('show');
        if (confirmCallback) {
            confirmCallback();
            confirmCallback = null;
        }
    };
    return false;
}

function closeConfirmDialog() {
    const modal = document.getElementById('confirmDialogModal');
    if (modal) modal.classList.remove('show');
    confirmCallback = null;
}
</script>

<?php if (isset($extraScripts) && is_array($extraScripts)): ?>
    <?php foreach ($extraScripts as $script): ?>
        <script src="<?= htmlspecialchars($script) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Floating Ask Me Help AI Chatbot -->
<?php require_once __DIR__ . '/chatbot.php'; ?>

</body>
</html>
