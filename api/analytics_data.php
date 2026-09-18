<?php
/**
 * Analytics Data API Endpoint
 * Returns time-series JSON data for interactive graphs
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
    // 12 Months Trend
    $stmtTrend = $pdo->prepare("
        SELECT 
            DATE_FORMAT(transaction_date, '%b %Y') as month_label,
            YEAR(transaction_date) as y,
            MONTH(transaction_date) as m,
            SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) as income,
            SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) as expense
        FROM transactions
        WHERE user_id = ? AND transaction_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 11 MONTH)
        GROUP BY y, m, month_label
        ORDER BY y ASC, m ASC
    ");
    $stmtTrend->execute([$userId]);
    $trend = $stmtTrend->fetchAll();

    echo json_encode([
        'status' => 'success',
        'trend' => $trend
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
