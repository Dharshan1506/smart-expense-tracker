<?php
/**
 * Transaction Data Feed API
 * Returns filtered transaction list in JSON format
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

$limit = max(1, min((int)($_GET['limit'] ?? 50), 100));
$type = $_GET['type'] ?? '';

try {
    $sql = "
        SELECT t.transaction_id, t.amount, t.transaction_type, t.description, t.transaction_date,
               c.category_name, c.icon, c.color
        FROM transactions t
        INNER JOIN categories c ON t.category_id = c.category_id
        WHERE t.user_id = ?
    ";
    $params = [$userId];

    if (in_array($type, ['income', 'expense'])) {
        $sql .= " AND t.transaction_type = ?";
        $params[] = $type;
    }

    $sql .= " ORDER BY t.transaction_date DESC, t.transaction_id DESC LIMIT " . $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    echo json_encode([
        'status' => 'success',
        'count' => count($rows),
        'transactions' => $rows
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
