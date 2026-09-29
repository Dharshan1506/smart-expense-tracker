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
    // 1. Financial Summary (Income, Expense, Savings, Net Balance)
    $summary = get_user_financial_summary($pdo, $userId);
    $healthData = calculate_financial_health_score($pdo, $userId);

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
            'total_income'    => $summary['total_income'],
            'total_expense'   => $summary['total_expense'],
            'total_savings'   => $summary['total_savings'],
            'current_balance' => $summary['net_balance'],
            'net_balance'     => $summary['net_balance'],
            'savings_rate'    => $summary['savings_rate'],
            'health_score'    => $healthData['score'],
            'health_label'    => $healthData['label'],
            'month_expense'   => $monthExpense,
            'categories'      => $categories
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
