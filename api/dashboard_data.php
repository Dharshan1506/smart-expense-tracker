<?php
/**
 * Dashboard Data API Endpoint
 * Returns JSON-encoded financial metrics for dynamic asynchronous updates
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = current_user();
$userId = $user['id'];
$pdo = getDBConnection();

try {
    // 1. Totals
    $stmtTotals = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0) AS total_income,
            COALESCE(SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END), 0) AS total_expense
        FROM transactions 
        WHERE user_id = ?
    ");
    $stmtTotals->execute([$userId]);
    $totals = $stmtTotals->fetch();
    $totalIncome = (float)$totals['total_income'];
    $totalExpense = (float)$totals['total_expense'];
    $balance = $totalIncome - $totalExpense;

    // 2. Monthly Expenses
    $stmtMonth = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) FROM transactions 
        WHERE user_id = ? AND transaction_type = 'expense' 
          AND MONTH(transaction_date) = MONTH(CURRENT_DATE())
          AND YEAR(transaction_date) = YEAR(CURRENT_DATE())
    ");
    $stmtMonth->execute([$userId]);
    $monthExpense = (float)$stmtMonth->fetchColumn();

    // 3. Category Breakdown (Current Month)
    $stmtCats = $pdo->prepare("
        SELECT c.category_name, c.color, SUM(t.amount) as amount
        FROM transactions t
        INNER JOIN categories c ON t.category_id = c.category_id
        WHERE t.user_id = ? AND t.transaction_type = 'expense'
          AND MONTH(t.transaction_date) = MONTH(CURRENT_DATE())
          AND YEAR(t.transaction_date) = YEAR(CURRENT_DATE())
        GROUP BY c.category_id, c.category_name, c.color
        ORDER BY amount DESC
    ");
    $stmtCats->execute([$userId]);
    $categories = $stmtCats->fetchAll();

    echo json_encode([
        'status' => 'success',
        'data' => [
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'current_balance' => $balance,
            'month_expense' => $monthExpense,
            'categories' => $categories
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
