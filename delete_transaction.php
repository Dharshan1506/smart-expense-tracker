<?php
/**
 * Delete Transaction Handler
 * Safely removes transaction record ensuring user authorization & CSRF protection
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('danger', 'Invalid request method.');
    header('Location: transactions.php');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrfToken)) {
    set_flash('danger', 'Security validation failed. Please try again.');
    header('Location: transactions.php');
    exit;
}

$user = current_user();
$userId = $user['id'];
$transactionId = (int)($_POST['transaction_id'] ?? 0);

if ($transactionId <= 0) {
    set_flash('danger', 'Invalid transaction identifier.');
    header('Location: transactions.php');
    exit;
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("DELETE FROM transactions WHERE transaction_id = ? AND user_id = ?");
    $stmt->execute([$transactionId, $userId]);

    if ($stmt->rowCount() > 0) {
        set_flash('success', 'Transaction was successfully deleted.');
    } else {
        set_flash('warning', 'Transaction not found or you are not authorized to delete it.');
    }
} catch (Exception $e) {
    set_flash('danger', 'Failed to delete transaction: ' . $e->getMessage());
}

header('Location: transactions.php');
exit;
