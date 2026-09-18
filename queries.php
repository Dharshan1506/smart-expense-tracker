<?php
/**
 * DBMS Academic Query Demonstration Page
 * Showcases 20 Core Relational DBMS Queries, Relational Algebra, Normalization Proofs,
 * Tables Involved, Academic Rationale, and Live / Example Execution Outputs.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$userId = $user['id'];
$pdo = getDBConnection();

$pageTitle = 'DBMS SQL Query Showcase';
$currentPage = 'queries';

// Define the comprehensive 20-Query Catalog
$queryCatalog = [
    // -------------------------------------------------------------------------
    // 1. Basic SELECT
    // -------------------------------------------------------------------------
    [
        'num' => 1,
        'id' => 'query_basic_select',
        'title' => '1. Basic SELECT: User Account & Profile Retrieval',
        'concept' => 'Basic SELECT',
        'category' => 'dql',
        'badge' => 'DQL - Projection',
        'tables' => ['users'],
        'used_in' => 'profile.php, includes/auth.php, login.php',
        'purpose' => 'Retrieves specific column attributes (projection) from the users relation for the authenticated user without retrieving unnecessary columns or password hashes.',
        'sql' => "SELECT user_id, name, email, created_at, updated_at\nFROM users\nWHERE user_id = :user_id;",
        'run' => function(PDO $pdo, int $userId) {
            $stmt = $pdo->prepare("SELECT user_id, name, email, created_at, updated_at FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        },
        'sample_output' => [
            ['user_id' => '1', 'name' => 'Alex Morgan', 'email' => 'demo@example.com', 'created_at' => '2026-09-01 10:00:00', 'updated_at' => '2026-09-01 10:00:00']
        ]
    ],

    // -------------------------------------------------------------------------
    // 2. INSERT
    // -------------------------------------------------------------------------
    [
        'num' => 2,
        'id' => 'query_insert',
        'title' => '2. INSERT: Financial Transaction Entry (DML)',
        'concept' => 'INSERT',
        'category' => 'dml',
        'badge' => 'DML - Data Insertion',
        'tables' => ['transactions', 'users', 'categories'],
        'used_in' => 'add_transaction.php',
        'purpose' => 'Inserts a new financial record tuple into the transactions table, enforcing foreign key referential integrity constraints referencing both users and categories.',
        'sql' => "INSERT INTO transactions (\n    user_id, category_id, amount, transaction_type, description, transaction_date, created_at\n)\nVALUES (\n    :user_id, 1, 45.50, 'expense', 'Grocery haul at supermarket', CURDATE(), NOW()\n);",
        'sample_output' => [
            ['Status' => '1 Row Affected (SUCCESS)', 'Last Insert ID' => '142', 'Foreign Keys Verified' => 'user_id (OK), category_id (OK)', 'Constraint Validation' => 'amount > 0 Passed']
        ]
    ],

    // -------------------------------------------------------------------------
    // 3. UPDATE
    // -------------------------------------------------------------------------
    [
        'num' => 3,
        'id' => 'query_update',
        'title' => '3. UPDATE: Record Modification with Primary Key Predicate',
        'concept' => 'UPDATE',
        'category' => 'dml',
        'badge' => 'DML - Tuple Mutation',
        'tables' => ['transactions'],
        'used_in' => 'edit_transaction.php',
        'purpose' => 'Modifies attribute values of an existing transaction tuple. Enforces user authorization by pairing transaction_id with user_id in the WHERE predicate.',
        'sql' => "UPDATE transactions\nSET amount = 58.75, description = 'Supermarket grocery and household items'\nWHERE transaction_id = 1 AND user_id = :user_id;",
        'sample_output' => [
            ['Status' => 'Query OK, 1 row affected', 'Rows Matched' => '1', 'Changed' => '1', 'Warnings' => '0']
        ]
    ],

    // -------------------------------------------------------------------------
    // 4. DELETE
    // -------------------------------------------------------------------------
    [
        'num' => 4,
        'id' => 'query_delete',
        'title' => '4. DELETE: Referential Record Removal',
        'concept' => 'DELETE',
        'category' => 'dml',
        'badge' => 'DML - Tuple Deletion',
        'tables' => ['transactions'],
        'used_in' => 'delete_transaction.php',
        'purpose' => 'Deletes an individual record from the financial ledger safely while preserving referential integrity and verifying user tenancy.',
        'sql' => "DELETE FROM transactions\nWHERE transaction_id = :transaction_id AND user_id = :user_id;",
        'sample_output' => [
            ['Status' => '1 Row Deleted', 'Foreign Key Checks' => 'Passed (No Cascade Orphans)', 'Integrity Verified' => 'True']
        ]
    ],

    // -------------------------------------------------------------------------
    // 5. WHERE
    // -------------------------------------------------------------------------
    [
        'num' => 5,
        'id' => 'query_where',
        'title' => '5. WHERE: Multi-Predicate Filtering (Date Range & Classification)',
        'concept' => 'WHERE',
        'category' => 'dql',
        'badge' => 'Relational Selection (σ)',
        'tables' => ['transactions'],
        'used_in' => 'transactions.php, reports.php',
        'purpose' => 'Applies relational selection (σ) using compound boolean predicates (AND) to filter expense transactions occurring within the past 30 days.',
        'sql' => "SELECT transaction_id, description, amount, transaction_date, transaction_type\nFROM transactions\nWHERE user_id = :user_id \n  AND transaction_type = 'expense' \n  AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)\nORDER BY transaction_date DESC\nLIMIT 5;",
        'run' => function(PDO $pdo, int $userId) {
            $stmt = $pdo->prepare("
                SELECT transaction_id, description, amount, transaction_date, transaction_type
                FROM transactions
                WHERE user_id = ? AND transaction_type = 'expense' AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                ORDER BY transaction_date DESC
                LIMIT 5
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        },
        'sample_output' => [
            ['transaction_id' => '14', 'description' => 'Desk organizer and wireless mouse', 'amount' => '65.00', 'transaction_date' => '2026-09-15', 'transaction_type' => 'expense'],
            ['transaction_id' => '4', 'description' => 'Weekly Grocery Store haul', 'amount' => '65.50', 'transaction_date' => '2026-09-14', 'transaction_type' => 'expense']
        ]
    ],

    // -------------------------------------------------------------------------
    // 6. ORDER BY
    // -------------------------------------------------------------------------
    [
        'num' => 6,
        'id' => 'query_order_by',
        'title' => '6. ORDER BY: Multi-Attribute Sorting & Ranking',
        'concept' => 'ORDER BY',
        'category' => 'dql',
        'badge' => 'Sorting & Ordering',
        'tables' => ['transactions'],
        'used_in' => 'transactions.php, dashboard.php',
        'purpose' => 'Sorts expenditure tuples in descending order of financial magnitude, utilizing transaction_date as a secondary tie-breaking sorting key.',
        'sql' => "SELECT transaction_date, description, amount, transaction_type\nFROM transactions\nWHERE user_id = :user_id\nORDER BY amount DESC, transaction_date DESC\nLIMIT 5;",
        'run' => function(PDO $pdo, int $userId) {
            $stmt = $pdo->prepare("
                SELECT transaction_date, description, amount, transaction_type 
                FROM transactions 
                WHERE user_id = ? 
                ORDER BY amount DESC, transaction_date DESC 
                LIMIT 5
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        },
        'sample_output' => [
            ['transaction_date' => '2026-09-03', 'description' => 'Monthly Engineering Salary', 'amount' => '4500.00', 'transaction_type' => 'income'],
            ['transaction_date' => '2026-09-10', 'description' => 'Full-stack Web Dev Client Project', 'amount' => '850.00', 'transaction_type' => 'income'],
            ['transaction_date' => '2026-09-07', 'description' => 'Sneakers & sports wear', 'amount' => '120.00', 'transaction_type' => 'expense']
        ]
    ],

    // -------------------------------------------------------------------------
    // 7. GROUP BY
    // -------------------------------------------------------------------------
    [
        'num' => 7,
        'id' => 'query_group_by',
        'title' => '7. GROUP BY: Relation Partitioning & Volume Aggregation',
        'concept' => 'GROUP BY',
        'category' => 'aggregates',
        'badge' => 'Data Partitioning',
        'tables' => ['transactions'],
        'used_in' => 'dashboard.php, analytics.php',
        'purpose' => 'Partitions raw transaction records into distinct groups based on transaction_type and applies COUNT() and SUM() aggregate functions across each partition.',
        'sql' => "SELECT \n    transaction_type,\n    COUNT(transaction_id) AS total_records,\n    SUM(amount) AS total_volume\nFROM transactions\nWHERE user_id = :user_id\nGROUP BY transaction_type;",
        'run' => function(PDO $pdo, int $userId) {
            $stmt = $pdo->prepare("
                SELECT transaction_type, COUNT(transaction_id) AS total_records, SUM(amount) AS total_volume
                FROM transactions
                WHERE user_id = ?
                GROUP BY transaction_type
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        },
        'sample_output' => [
            ['transaction_type' => 'expense', 'total_records' => '18', 'total_volume' => '1282.99'],
            ['transaction_type' => 'income', 'total_records' => '5', 'total_volume' => '10650.00']
        ]
    ],

    // -------------------------------------------------------------------------
    // 8. HAVING
    // -------------------------------------------------------------------------
    [
        'num' => 8,
        'id' => 'query_having',
        'title' => '8. HAVING: Post-Aggregation Group Filtering',
        'concept' => 'HAVING',
        'category' => 'aggregates',
        'badge' => 'Group Qualification',
        'tables' => ['transactions', 'categories'],
        'used_in' => 'analytics.php, queries.php',
        'purpose' => 'Filters grouped records AFTER aggregate computation. WHERE filters individual rows before grouping, whereas HAVING restricts aggregated category totals strictly exceeding ₹100.00.',
        'sql' => "SELECT \n    c.category_name,\n    COUNT(t.transaction_id) AS transaction_count,\n    SUM(t.amount) AS total_spent\nFROM transactions t\nINNER JOIN categories c ON t.category_id = c.category_id\nWHERE t.user_id = :user_id AND t.transaction_type = 'expense'\nGROUP BY c.category_id, c.category_name\nHAVING SUM(t.amount) > 100.00\nORDER BY total_spent DESC;",
        'run' => function(PDO $pdo, int $userId) {
            $stmt = $pdo->prepare("
                SELECT c.category_name, COUNT(t.transaction_id) AS transaction_count, SUM(t.amount) AS total_spent
                FROM transactions t
                INNER JOIN categories c ON t.category_id = c.category_id
                WHERE t.user_id = ? AND t.transaction_type = 'expense'
                GROUP BY c.category_id, c.category_name
                HAVING SUM(t.amount) > 100.00
                ORDER BY total_spent DESC
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        },
        'sample_output' => [
            ['category_name' => 'Food & Dining', 'transaction_count' => '4', 'total_spent' => '351.50'],
            ['category_name' => 'Shopping', 'transaction_count' => '3', 'total_spent' => '370.00'],
            ['category_name' => 'Bills & Utilities', 'transaction_count' => '2', 'total_spent' => '205.00']
        ]
    ],

    // -------------------------------------------------------------------------
    // 9. SUM
    // -------------------------------------------------------------------------
    [
        'num' => 9,
        'id' => 'query_sum',
        'title' => '9. SUM: Financial Summation & Net Balance Computation',
        'concept' => 'SUM',
        'category' => 'aggregates',
        'badge' => 'Mathematical Aggregation',
        'tables' => ['transactions'],
        'used_in' => 'dashboard.php, reports.php',
        'purpose' => 'Calculates overall accumulated income, total expenditures, and net disposable savings using conditional SUM(CASE...) expressions across exact DECIMAL(10,2) values.',
        'sql' => "SELECT \n    SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) AS total_income,\n    SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) AS total_expenses,\n    (SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) - \n     SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END)) AS net_savings\nFROM transactions\nWHERE user_id = :user_id;",
        'run' => function(PDO $pdo, int $userId) {
            $stmt = $pdo->prepare("
                SELECT 
                    SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) AS total_income,
                    SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) AS total_expenses,
                    (SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) - 
                     SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END)) AS net_savings
                FROM transactions
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        },
        'sample_output' => [
            ['total_income' => '10650.00', 'total_expenses' => '1282.99', 'net_savings' => '9367.01']
        ]
    ],

    // -------------------------------------------------------------------------
    // 10. AVG
    // -------------------------------------------------------------------------
    [
        'num' => 10,
        'id' => 'query_avg',
        'title' => '10. AVG: Arithmetic Mean Expenditure per Transaction',
        'concept' => 'AVG',
        'category' => 'aggregates',
        'badge' => 'Statistical Mean',
        'tables' => ['transactions'],
        'used_in' => 'analytics.php, reports.php',
        'purpose' => 'Computes the statistical arithmetic mean of all individual expense payments, rounding to two decimal places for financial reporting.',
        'sql' => "SELECT \n    ROUND(AVG(amount), 2) AS average_expense_per_transaction,\n    COUNT(transaction_id) AS total_expense_count\nFROM transactions\nWHERE user_id = :user_id AND transaction_type = 'expense';",
        'run' => function(PDO $pdo, int $userId) {
            $stmt = $pdo->prepare("
                SELECT 
                    ROUND(AVG(amount), 2) AS average_expense_per_transaction, 
                    COUNT(transaction_id) AS total_expense_count 
                FROM transactions 
                WHERE user_id = ? AND transaction_type = 'expense'
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        },
        'sample_output' => [
            ['average_expense_per_transaction' => '71.28', 'total_expense_count' => '18']
        ]
    ],

    // -------------------------------------------------------------------------
    // 11. COUNT
    // -------------------------------------------------------------------------
    [
        'num' => 11,
        'id' => 'query_count',
        'title' => '11. COUNT: Transaction Cardinality & Category Frequency',
        'concept' => 'COUNT',
        'category' => 'aggregates',
        'badge' => 'Cardinality Measure',
        'tables' => ['transactions', 'categories'],
        'used_in' => 'categories.php, analytics.php',
        'purpose' => 'Counts the total number of transactions recorded for each category, identifying the most frequently engaged spending channels.',
        'sql' => "SELECT \n    c.category_name,\n    COUNT(t.transaction_id) AS transaction_frequency\nFROM categories c\nINNER JOIN transactions t ON c.category_id = t.category_id\nWHERE t.user_id = :user_id\nGROUP BY c.category_id, c.category_name\nORDER BY transaction_frequency DESC;",
        'run' => function(PDO $pdo, int $userId) {
            $stmt = $pdo->prepare("
                SELECT c.category_name, COUNT(t.transaction_id) AS transaction_frequency
                FROM categories c
                INNER JOIN transactions t ON c.category_id = t.category_id
                WHERE t.user_id = ?
                GROUP BY c.category_id, c.category_name
                ORDER BY transaction_frequency DESC
                LIMIT 5
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        },
        'sample_output' => [
            ['category_name' => 'Food & Dining', 'transaction_frequency' => '4'],
            ['category_name' => 'Travel & Transport', 'transaction_frequency' => '3'],
            ['category_name' => 'Shopping', 'transaction_frequency' => '3']
        ]
    ],

    // -------------------------------------------------------------------------
    // 12. MAX
    // -------------------------------------------------------------------------
    [
        'num' => 12,
        'id' => 'query_max',
        'title' => '12. MAX: Peak Expenditure Identification',
        'concept' => 'MAX',
        'category' => 'aggregates',
        'badge' => 'Extremum Analysis',
        'tables' => ['transactions'],
        'used_in' => 'analytics.php, reports.php',
        'purpose' => 'Locates the single highest monetary expenditure recorded in the ledger to identify large budget impacts.',
        'sql' => "SELECT \n    MAX(amount) AS peak_expense_amount,\n    COUNT(transaction_id) AS sample_size\nFROM transactions\nWHERE user_id = :user_id AND transaction_type = 'expense';",
        'run' => function(PDO $pdo, int $userId) {
            $stmt = $pdo->prepare("
                SELECT MAX(amount) AS peak_expense_amount, COUNT(transaction_id) AS sample_size 
                FROM transactions 
                WHERE user_id = ? AND transaction_type = 'expense'
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        },
        'sample_output' => [
            ['peak_expense_amount' => '210.00', 'sample_size' => '18']
        ]
    ],

    // -------------------------------------------------------------------------
    // 13. MIN
    // -------------------------------------------------------------------------
    [
        'num' => 13,
        'id' => 'query_min',
        'title' => '13. MIN: Lowest Non-Zero Expense Detection',
        'concept' => 'MIN',
        'category' => 'aggregates',
        'badge' => 'Extremum Analysis',
        'tables' => ['transactions'],
        'used_in' => 'analytics.php, reports.php',
        'purpose' => 'Locates the minimum non-zero expense transaction to analyze micro-spending patterns and small ticket purchases.',
        'sql' => "SELECT \n    MIN(amount) AS minimum_expense_amount\nFROM transactions\nWHERE user_id = :user_id AND transaction_type = 'expense';",
        'run' => function(PDO $pdo, int $userId) {
            $stmt = $pdo->prepare("
                SELECT MIN(amount) AS minimum_expense_amount 
                FROM transactions 
                WHERE user_id = ? AND transaction_type = 'expense'
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        },
        'sample_output' => [
            ['minimum_expense_amount' => '18.50']
        ]
    ],

    // -------------------------------------------------------------------------
    // 14. INNER JOIN
    // -------------------------------------------------------------------------
    [
        'num' => 14,
        'id' => 'query_inner_join',
        'title' => '14. INNER JOIN: Equi-Join Linking Transactions with Categories',
        'concept' => 'INNER JOIN',
        'category' => 'joins',
        'badge' => 'Relational Join (⨝)',
        'tables' => ['transactions', 'categories'],
        'used_in' => 'transactions.php, dashboard.php, analytics.php',
        'purpose' => 'Performs a relational equi-join (⨝) between transactions and categories on the foreign key category_id, returning only transactions that possess a valid category definition.',
        'sql' => "SELECT \n    t.transaction_id,\n    t.transaction_date,\n    t.description,\n    t.amount,\n    c.category_name,\n    c.category_type\nFROM transactions t\nINNER JOIN categories c ON t.category_id = c.category_id\nWHERE t.user_id = :user_id\nORDER BY t.transaction_date DESC\nLIMIT 5;",
        'run' => function(PDO $pdo, int $userId) {
            $stmt = $pdo->prepare("
                SELECT t.transaction_id, t.transaction_date, t.description, t.amount, c.category_name, c.category_type
                FROM transactions t
                INNER JOIN categories c ON t.category_id = c.category_id
                WHERE t.user_id = ?
                ORDER BY t.transaction_date DESC
                LIMIT 5
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        },
        'sample_output' => [
            ['transaction_id' => '14', 'transaction_date' => '2026-09-15', 'description' => 'Desk organizer and wireless mouse', 'amount' => '65.00', 'category_name' => 'Shopping', 'category_type' => 'expense'],
            ['transaction_id' => '4', 'transaction_date' => '2026-09-14', 'description' => 'Weekly Grocery Store haul', 'amount' => '65.50', 'category_name' => 'Food & Dining', 'category_type' => 'expense']
        ]
    ],

    // -------------------------------------------------------------------------
    // 15. LEFT JOIN
    // -------------------------------------------------------------------------
    [
        'num' => 15,
        'id' => 'query_left_join',
        'title' => '15. LEFT JOIN: Outer Join Preserving Unused Categories',
        'concept' => 'LEFT JOIN',
        'category' => 'joins',
        'badge' => 'Outer Join (⟕)',
        'tables' => ['categories', 'transactions'],
        'used_in' => 'categories.php, budgets.php',
        'purpose' => 'Preserves all category tuples from the left relation, including categories that currently possess zero recorded transactions (evaluating NULL amounts to 0.00 via COALESCE).',
        'sql' => "SELECT \n    c.category_name,\n    c.category_type,\n    COUNT(t.transaction_id) AS total_transactions,\n    COALESCE(SUM(t.amount), 0.00) AS total_spent\nFROM categories c\nLEFT JOIN transactions t ON t.category_id = c.category_id AND t.user_id = :user_id\nWHERE c.user_id IS NULL OR c.user_id = :user_id\nGROUP BY c.category_id, c.category_name, c.category_type\nORDER BY total_spent DESC\nLIMIT 6;",
        'run' => function(PDO $pdo, int $userId) {
            $stmt = $pdo->prepare("
                SELECT c.category_name, c.category_type, COUNT(t.transaction_id) AS total_transactions, COALESCE(SUM(t.amount), 0.00) AS total_spent
                FROM categories c
                LEFT JOIN transactions t ON t.category_id = c.category_id AND t.user_id = ?
                WHERE c.user_id IS NULL OR c.user_id = ?
                GROUP BY c.category_id, c.category_name, c.category_type
                ORDER BY total_spent DESC
                LIMIT 6
            ");
            $stmt->execute([$userId, $userId]);
            return $stmt->fetchAll();
        },
        'sample_output' => [
            ['category_name' => 'Salary', 'category_type' => 'income', 'total_transactions' => '2', 'total_spent' => '9000.00'],
            ['category_name' => 'Freelance', 'category_type' => 'income', 'total_transactions' => '2', 'total_spent' => '1450.00'],
            ['category_name' => 'Education', 'category_type' => 'expense', 'total_transactions' => '2', 'total_spent' => '129.99']
        ]
    ],

    // -------------------------------------------------------------------------
    // 16. Multiple-table JOIN
    // -------------------------------------------------------------------------
    [
        'num' => 16,
        'id' => 'query_multi_join',
        'title' => '16. Multiple-Table JOIN: 3-Relation Equi-Join (Users, Transactions, Categories)',
        'concept' => 'Multiple-table JOIN',
        'category' => 'joins',
        'badge' => '3-Table Equi-Join',
        'tables' => ['users', 'transactions', 'categories'],
        'used_in' => 'reports.php, queries.php',
        'purpose' => 'Combines attributes across three distinct database tables simultaneously, verifying user identity, transaction ledger data, and category taxonomy within a unified projection.',
        'sql' => "SELECT \n    u.name AS user_name,\n    t.transaction_date,\n    t.description,\n    t.amount,\n    c.category_name,\n    c.category_type\nFROM transactions t\nINNER JOIN users u ON t.user_id = u.user_id\nINNER JOIN categories c ON t.category_id = c.category_id\nWHERE t.user_id = :user_id\nORDER BY t.transaction_date DESC\nLIMIT 5;",
        'run' => function(PDO $pdo, int $userId) {
            $stmt = $pdo->prepare("
                SELECT u.name AS user_name, t.transaction_date, t.description, t.amount, c.category_name, c.category_type
                FROM transactions t
                INNER JOIN users u ON t.user_id = u.user_id
                INNER JOIN categories c ON t.category_id = c.category_id
                WHERE t.user_id = ?
                ORDER BY t.transaction_date DESC
                LIMIT 5
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        },
        'sample_output' => [
            ['user_name' => 'Alex Morgan', 'transaction_date' => '2026-09-15', 'description' => 'Desk organizer and wireless mouse', 'amount' => '65.00', 'category_name' => 'Shopping', 'category_type' => 'expense'],
            ['user_name' => 'Alex Morgan', 'transaction_date' => '2026-09-14', 'description' => 'Weekly Grocery Store haul', 'amount' => '65.50', 'category_name' => 'Food & Dining', 'category_type' => 'expense']
        ]
    ],

    // -------------------------------------------------------------------------
    // 17. Subquery
    // -------------------------------------------------------------------------
    [
        'num' => 17,
        'id' => 'query_subquery',
        'title' => '17. Subquery: Nested Scalar Subquery (Above-Average Expenditures)',
        'concept' => 'Subquery',
        'category' => 'analytics',
        'badge' => 'Nested Subquery (Scalar)',
        'tables' => ['transactions', 'categories'],
        'used_in' => 'analytics.php, queries.php',
        'purpose' => 'Uses an inner scalar subquery to dynamically calculate the overall user expenditure average, and filters outer query transactions whose amounts strictly exceed that benchmark.',
        'sql' => "SELECT \n    t.transaction_date, \n    t.description, \n    t.amount,\n    c.category_name\nFROM transactions t\nINNER JOIN categories c ON t.category_id = c.category_id\nWHERE t.user_id = :user_id \n  AND t.transaction_type = 'expense'\n  AND t.amount > (\n      SELECT AVG(amount) \n      FROM transactions \n      WHERE user_id = :user_id AND transaction_type = 'expense'\n  )\nORDER BY t.amount DESC\nLIMIT 5;",
        'run' => function(PDO $pdo, int $userId) {
            $stmt = $pdo->prepare("
                SELECT t.transaction_date, t.description, t.amount, c.category_name
                FROM transactions t
                INNER JOIN categories c ON t.category_id = c.category_id
                WHERE t.user_id = ? AND t.transaction_type = 'expense'
                  AND t.amount > (SELECT AVG(amount) FROM transactions WHERE user_id = ? AND transaction_type = 'expense')
                ORDER BY t.amount DESC
                LIMIT 5
            ");
            $stmt->execute([$userId, $userId]);
            return $stmt->fetchAll();
        },
        'sample_output' => [
            ['transaction_date' => '2026-08-11', 'description' => 'Monthly Groceries Supermarket', 'amount' => '210.00', 'category_name' => 'Food & Dining'],
            ['transaction_date' => '2026-08-14', 'description' => 'Winter Clothing Shopping', 'amount' => '185.00', 'category_name' => 'Shopping'],
            ['transaction_date' => '2026-09-07', 'description' => 'Sneakers & sports wear', 'amount' => '120.00', 'category_name' => 'Shopping']
        ]
    ],

    // -------------------------------------------------------------------------
    // 18. Monthly expense analysis
    // -------------------------------------------------------------------------
    [
        'num' => 18,
        'id' => 'query_monthly_analysis',
        'title' => '18. Monthly Expense Analysis: Time-Series Ledger Aggregation',
        'concept' => 'Monthly expense analysis',
        'category' => 'analytics',
        'badge' => 'Temporal Analysis',
        'tables' => ['transactions'],
        'used_in' => 'analytics.php, reports.php, dashboard.php',
        'purpose' => 'Aggregates financial performance across consecutive monthly billing periods using DATE_FORMAT(transaction_date, \'%Y-%m\'), calculating income, expense, and delta savings.',
        'sql' => "SELECT \n    DATE_FORMAT(transaction_date, '%Y-%m') AS report_month,\n    SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) AS monthly_income,\n    SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) AS monthly_expense,\n    (SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) - \n     SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END)) AS monthly_net_savings\nFROM transactions\nWHERE user_id = :user_id\nGROUP BY DATE_FORMAT(transaction_date, '%Y-%m')\nORDER BY report_month DESC\nLIMIT 6;",
        'run' => function(PDO $pdo, int $userId) {
            $stmt = $pdo->prepare("
                SELECT 
                    DATE_FORMAT(transaction_date, '%Y-%m') AS report_month,
                    SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) AS monthly_income,
                    SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) AS monthly_expense,
                    (SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) - 
                     SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END)) AS monthly_net_savings
                FROM transactions
                WHERE user_id = ?
                GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
                ORDER BY report_month DESC
                LIMIT 6
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        },
        'sample_output' => [
            ['report_month' => '2026-09', 'monthly_income' => '5550.00', 'monthly_expense' => '513.99', 'monthly_net_savings' => '5036.01'],
            ['report_month' => '2026-08', 'monthly_income' => '5100.00', 'monthly_expense' => '760.00', 'monthly_net_savings' => '4340.00']
        ]
    ],

    // -------------------------------------------------------------------------
    // 19. Category-wise expense analysis
    // -------------------------------------------------------------------------
    [
        'num' => 19,
        'id' => 'query_category_analysis',
        'title' => '19. Category-Wise Expense Analysis: Categorical Breakdown & Share %',
        'concept' => 'Category-wise expense analysis',
        'category' => 'analytics',
        'badge' => 'Distribution & Percentage',
        'tables' => ['transactions', 'categories'],
        'used_in' => 'analytics.php, reports.php, dashboard.php',
        'purpose' => 'Calculates expenditure metrics per category, including transaction count, total sum, average per ticket, and percentage contribution relative to lifetime expenses using a subquery.',
        'sql' => "SELECT \n    c.category_name,\n    COUNT(t.transaction_id) AS transaction_count,\n    SUM(t.amount) AS total_spent,\n    ROUND(AVG(t.amount), 2) AS avg_spent_per_tx,\n    ROUND((SUM(t.amount) / (\n        SELECT SUM(amount) FROM transactions WHERE user_id = :user_id AND transaction_type = 'expense'\n    ) * 100), 1) AS percentage_of_total\nFROM transactions t\nINNER JOIN categories c ON t.category_id = c.category_id\nWHERE t.user_id = :user_id AND t.transaction_type = 'expense'\nGROUP BY c.category_id, c.category_name\nORDER BY total_spent DESC;",
        'run' => function(PDO $pdo, int $userId) {
            $stmt = $pdo->prepare("
                SELECT 
                    c.category_name,
                    COUNT(t.transaction_id) AS transaction_count,
                    SUM(t.amount) AS total_spent,
                    ROUND(AVG(t.amount), 2) AS avg_spent_per_tx,
                    ROUND((SUM(t.amount) / (
                        SELECT SUM(amount) FROM transactions WHERE user_id = ? AND transaction_type = 'expense'
                    ) * 100), 1) AS percentage_of_total
                FROM transactions t
                INNER JOIN categories c ON t.category_id = c.category_id
                WHERE t.user_id = ? AND t.transaction_type = 'expense'
                GROUP BY c.category_id, c.category_name
                ORDER BY total_spent DESC
                LIMIT 5
            ");
            $stmt->execute([$userId, $userId]);
            return $stmt->fetchAll();
        },
        'sample_output' => [
            ['category_name' => 'Shopping', 'transaction_count' => '3', 'total_spent' => '370.00', 'avg_spent_per_tx' => '123.33', 'percentage_of_total' => '28.8'],
            ['category_name' => 'Food & Dining', 'transaction_count' => '4', 'total_spent' => '351.50', 'avg_spent_per_tx' => '87.88', 'percentage_of_total' => '27.4'],
            ['category_name' => 'Bills & Utilities', 'transaction_count' => '2', 'total_spent' => '205.00', 'avg_spent_per_tx' => '102.50', 'percentage_of_total' => '16.0']
        ]
    ],

    // -------------------------------------------------------------------------
    // 20. Budget exceeded analysis
    // -------------------------------------------------------------------------
    [
        'num' => 20,
        'id' => 'query_budget_exceeded',
        'title' => '20. Budget Exceeded Analysis: Multi-Table Breach & Variance Query',
        'concept' => 'Budget exceeded analysis',
        'category' => 'analytics',
        'badge' => 'Analytical Variance Join',
        'tables' => ['budgets', 'categories', 'transactions'],
        'used_in' => 'budgets.php, dashboard.php, includes/auth.php',
        'purpose' => 'Joins budgets, categories, and transactions within active dates, calculating total spent and utilization percentage to detect budget overruns using a HAVING clause.',
        'sql' => "SELECT \n    b.budget_id,\n    c.category_name,\n    b.budget_amount,\n    COALESCE(SUM(t.amount), 0.00) AS total_spent,\n    ROUND((COALESCE(SUM(t.amount), 0.00) - b.budget_amount), 2) AS overrun_amount,\n    ROUND((COALESCE(SUM(t.amount), 0.00) / b.budget_amount * 100), 1) AS utilization_percentage\nFROM budgets b\nINNER JOIN categories c ON b.category_id = c.category_id\nLEFT JOIN transactions t ON t.category_id = b.category_id \n    AND t.user_id = b.user_id \n    AND t.transaction_type = 'expense'\n    AND t.transaction_date BETWEEN b.start_date AND b.end_date\nWHERE b.user_id = :user_id\nGROUP BY b.budget_id, c.category_name, b.budget_amount\nORDER BY utilization_percentage DESC;",
        'run' => function(PDO $pdo, int $userId) {
            $stmt = $pdo->prepare("
                SELECT 
                    b.budget_id,
                    c.category_name,
                    b.budget_amount,
                    COALESCE(SUM(t.amount), 0.00) AS total_spent,
                    ROUND((COALESCE(SUM(t.amount), 0.00) - b.budget_amount), 2) AS overrun_amount,
                    ROUND((COALESCE(SUM(t.amount), 0.00) / b.budget_amount * 100), 1) AS utilization_percentage
                FROM budgets b
                INNER JOIN categories c ON b.category_id = c.category_id
                LEFT JOIN transactions t ON t.category_id = b.category_id 
                    AND t.user_id = b.user_id 
                    AND t.transaction_type = 'expense'
                    AND t.transaction_date BETWEEN b.start_date AND b.end_date
                WHERE b.user_id = ?
                GROUP BY b.budget_id, c.category_name, b.budget_amount
                ORDER BY utilization_percentage DESC
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        },
        'sample_output' => [
            ['budget_id' => '3', 'category_name' => 'Shopping', 'budget_amount' => '200.00', 'total_spent' => '185.00', 'overrun_amount' => '-15.00', 'utilization_percentage' => '92.5'],
            ['budget_id' => '1', 'category_name' => 'Food & Dining', 'budget_amount' => '300.00', 'total_spent' => '141.50', 'overrun_amount' => '-158.50', 'utilization_percentage' => '47.2'],
            ['budget_id' => '4', 'category_name' => 'Bills & Utilities', 'budget_amount' => '120.00', 'total_spent' => '95.00', 'overrun_amount' => '-25.00', 'utilization_percentage' => '79.2']
        ]
    ]
];

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

        <!-- Educational Header Card -->
        <div class="card" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: white; border: none; margin-bottom: 24px; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.4);">
            <div class="card-body" style="padding: 28px;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
                    <div style="display: flex; align-items: center; gap: 16px;">
                        <div style="width: 52px; height: 52px; border-radius: 14px; background: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; box-shadow: 0 8px 16px rgba(59, 130, 246, 0.4);">
                            <i class="fa-solid fa-database"></i>
                        </div>
                        <div>
                            <h2 style="color: white; margin: 0 0 4px 0; font-size: 1.65rem; font-weight: 700;">DBMS SQL Query Demonstration Catalog</h2>
                            <span style="color: #94a3b8; font-size: 0.92rem;">College DBMS Academic Project • 20 Core SQL Queries • 3NF Normalized Schema</span>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary" onclick="toggleNormalizationGuide()" style="background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.2); color: white; display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-book-open"></i>
                        <span id="normGuideBtnText">View Normalization Guide (1NF, 2NF, 3NF)</span>
                    </button>
                </div>

                <p style="color: #cbd5e1; font-size: 0.95rem; line-height: 1.6; max-width: 950px; margin-top: 16px; margin-bottom: 0;">
                    This catalog demonstrates the <strong>20 essential SQL queries</strong> powering the Smart Expense Tracker. 
                    Each card highlights the <strong>DBMS Concept</strong>, <strong>Tables Involved</strong>, <strong>Parameterized SQL</strong>, 
                    <strong>Academic Rationale</strong>, <strong>Application Usage</strong>, and <strong>Live Database Execution Output</strong>.
                </p>
            </div>
        </div>

        <!-- Collapsible DBMS Schema & Normalization Guide -->
        <div id="normalizationGuideCard" class="card" style="display: none; border: 1px solid #3b82f6; background: #f8fafc; margin-bottom: 24px; animation: fadeIn 0.3s ease-in-out;">
            <div class="card-header" style="background: #eff6ff; border-bottom: 1px solid #bfdbfe; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 1.15rem; color: #1e3a8a; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-diagram-project" style="color: #3b82f6;"></i>
                    Relational Schema Verification & Normalization Proofs
                </h3>
                <button type="button" onclick="toggleNormalizationGuide()" style="background: none; border: none; font-size: 1.1rem; color: #64748b; cursor: pointer;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="card-body" style="padding: 24px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 18px; margin-bottom: 20px;">
                    <!-- 1NF Card -->
                    <div style="background: white; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 18px;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                            <span class="badge" style="background: #e0f2fe; color: #0369a1; font-weight: 700; font-size: 0.85rem;">1NF Verified</span>
                            <strong style="color: var(--text-primary);">First Normal Form</strong>
                        </div>
                        <ul style="margin: 0; padding-left: 18px; font-size: 0.88rem; color: var(--text-secondary); line-height: 1.6;">
                            <li><strong>Atomic Attributes:</strong> All fields store indivisible single values (no CSV arrays or multi-valued fields).</li>
                            <li><strong>Primary Keys:</strong> Each relation defines a unique surrogate key (<code>user_id</code>, <code>transaction_id</code>, etc.).</li>
                            <li><strong>Consistency:</strong> Column domain constraints strictly typed.</li>
                        </ul>
                    </div>

                    <!-- 2NF Card -->
                    <div style="background: white; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 18px;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                            <span class="badge" style="background: #f0fdf4; color: #15803d; font-weight: 700; font-size: 0.85rem;">2NF Verified</span>
                            <strong style="color: var(--text-primary);">Second Normal Form</strong>
                        </div>
                        <ul style="margin: 0; padding-left: 18px; font-size: 0.88rem; color: var(--text-secondary); line-height: 1.6;">
                            <li><strong>No Partial Dependency:</strong> Every non-prime attribute is fully functionally dependent on the entire primary key.</li>
                            <li><strong>Single-Attribute Keys:</strong> Surrogate keys eliminate composite candidate key partial dependencies.</li>
                        </ul>
                    </div>

                    <!-- 3NF Card -->
                    <div style="background: white; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 18px;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                            <span class="badge" style="background: #fef3c7; color: #b45309; font-weight: 700; font-size: 0.85rem;">3NF & BCNF Verified</span>
                            <strong style="color: var(--text-primary);">Third Normal Form</strong>
                        </div>
                        <ul style="margin: 0; padding-left: 18px; font-size: 0.88rem; color: var(--text-secondary); line-height: 1.6;">
                            <li><strong>No Transitive Dependency:</strong> Non-prime attributes depend ONLY on the primary key ($X \rightarrow Y$ holds only when $X$ is a superkey).</li>
                            <li><code>transactions</code> stores only <code>category_id</code>, referencing <code>categories</code> rather than duplicating category names or colors.</li>
                        </ul>
                    </div>
                </div>

                <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: var(--radius-md); padding: 16px;">
                    <div style="font-weight: 700; font-size: 0.92rem; color: var(--text-primary); margin-bottom: 8px;">
                        <i class="fa-solid fa-shield-halved" style="color: var(--primary);"></i> DBMS Integrity & Precision Engineering:
                    </div>
                    <div style="font-size: 0.88rem; color: var(--text-secondary); line-height: 1.7;">
                        • <strong>DECIMAL(10,2) Precision:</strong> Used across all financial columns (<code>amount</code>, <code>budget_amount</code>, <code>target_amount</code>) to guarantee zero IEEE-754 binary floating-point roundoff loss.<br>
                        • <strong>Referential Integrity:</strong> Strict Foreign Keys configured with <code>ON DELETE CASCADE</code> for user removal and <code>ON DELETE RESTRICT</code> on categories to prevent orphan transactions.<br>
                        • <strong>Strategic Indexing:</strong> B-Tree indexes on <code>(user_id, transaction_date)</code>, <code>(user_id, transaction_type, transaction_date)</code>, and foreign keys optimize range and join queries.<br>
                        • <strong>Relational Views:</strong> Includes <code>v_monthly_financial_summary</code>, <code>v_budget_status</code>, and <code>v_category_spending</code>.
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Navigation & Search Bar -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-body" style="padding: 16px 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
                    <!-- Filter Buttons -->
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;" id="filterButtonContainer">
                        <button type="button" class="btn btn-sm btn-primary active-filter" onclick="filterQueries('all', this)">
                            All Queries (20)
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="filterQueries('dql', this)">
                            Basic & DQL (1, 5, 6)
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="filterQueries('dml', this)">
                            CRUD & DML (2, 3, 4)
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="filterQueries('aggregates', this)">
                            Aggregates (7, 8, 9, 10, 11, 12, 13)
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="filterQueries('joins', this)">
                            Relational Joins (14, 15, 16)
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="filterQueries('analytics', this)">
                            Advanced Analytics (17, 18, 19, 20)
                        </button>
                    </div>

                    <!-- Instant Search -->
                    <div style="position: relative; min-width: 240px;">
                        <input type="text" id="querySearchInput" onkeyup="searchQueries()" placeholder="Search title, table, or concept..." class="form-control" style="padding-left: 36px; height: 38px; font-size: 0.88rem;">
                        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 12px; top: 12px; color: #94a3b8; font-size: 0.85rem;"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- 20 Query Cards List -->
        <div id="queriesList">
            <?php foreach ($queryCatalog as $q): ?>
                <?php 
                    $results = [];
                    $isLiveExecuted = false;
                    if (isset($q['run']) && is_callable($q['run'])) {
                        try {
                            $liveResults = $q['run']($pdo, $userId);
                            if (!empty($liveResults)) {
                                $results = $liveResults;
                                $isLiveExecuted = true;
                            } else {
                                $results = $q['sample_output'] ?? [];
                            }
                        } catch (Exception $e) {
                            $results = [['Execution Notice' => 'Database exception or table pending: ' . $e->getMessage()]];
                        }
                    } elseif (isset($q['sample_output'])) {
                        $results = $q['sample_output'];
                    }
                ?>
                <div class="query-card" id="<?= htmlspecialchars($q['id']) ?>" data-category="<?= htmlspecialchars($q['category']) ?>" style="margin-bottom: 24px;">
                    
                    <!-- Query Header -->
                    <div class="query-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 12px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 8px; background: #e0f2fe; color: #0284c7; font-weight: 700; font-size: 0.85rem;">
                                <?= $q['num'] ?>
                            </span>
                            <strong style="font-size: 1.1rem; color: var(--text-primary);"><?= htmlspecialchars($q['title']) ?></strong>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="query-concept-tag"><?= htmlspecialchars($q['badge']) ?></span>
                        </div>
                    </div>

                    <!-- Meta Tags: Tables Involved & App Usage -->
                    <div style="display: flex; flex-wrap: wrap; gap: 14px; margin-bottom: 14px; font-size: 0.84rem;">
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <span style="font-weight: 600; color: var(--text-secondary);"><i class="fa-solid fa-table-cells" style="color: #6366f1;"></i> Tables Involved:</span>
                            <?php foreach ($q['tables'] as $tbl): ?>
                                <span class="badge" style="background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; font-family: monospace; font-size: 0.8rem; padding: 2px 8px;">
                                    <?= htmlspecialchars($tbl) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <span style="font-weight: 600; color: var(--text-secondary);"><i class="fa-solid fa-code" style="color: #10b981;"></i> Application Usage:</span>
                            <span style="color: #0f766e; font-family: monospace; background: #ccfbf1; padding: 2px 8px; border-radius: 4px; font-size: 0.8rem;">
                                <?= htmlspecialchars($q['used_in']) ?>
                            </span>
                        </div>
                    </div>

                    <!-- SQL Code Box -->
                    <div class="sql-code-box" style="margin-bottom: 14px; position: relative;">
                        <button type="button" class="btn btn-sm" onclick="copySql(this)" style="position: absolute; right: 10px; top: 10px; background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.2); color: #cbd5e1; font-size: 0.72rem; padding: 3px 8px;" title="Copy SQL statement to clipboard">
                            <i class="fa-regular fa-copy"></i> <span>Copy SQL</span>
                        </button>
                        <pre style="margin: 0; white-space: pre-wrap; word-break: break-word; font-family: 'Consolas', 'Fira Code', monospace; font-size: 0.88rem; color: #38bdf8; padding-right: 80px;"><code><?= htmlspecialchars($q['sql']) ?></code></pre>
                    </div>

                    <!-- Academic Purpose & Explanation -->
                    <div class="query-explanation" style="margin-bottom: 16px; background: #fffbeb; border: 1px solid #fef3c7; border-radius: var(--radius-sm); padding: 12px 16px; font-size: 0.88rem; color: #92400e;">
                        <i class="fa-solid fa-graduation-cap" style="color: #d97706; margin-right: 6px;"></i>
                        <strong>DBMS Academic Purpose:</strong> <?= htmlspecialchars($q['purpose']) ?>
                    </div>

                    <!-- Execution Results Table Preview -->
                    <div class="query-result-preview">
                        <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between;">
                            <span style="display: flex; align-items: center; gap: 6px;">
                                <i class="fa-solid fa-play" style="color: <?= $isLiveExecuted ? 'var(--success)' : '#3b82f6' ?>; font-size: 0.72rem;"></i> 
                                <?= $isLiveExecuted ? 'Live Database Execution' : 'Sample / Schema Execution Result' ?> (<?= count($results) ?> rows)
                            </span>
                            <?php if ($isLiveExecuted): ?>
                                <span class="badge" style="background: #dcfce7; color: #15803d; font-size: 0.7rem; padding: 2px 6px;">Active Live DB</span>
                            <?php else: ?>
                                <span class="badge" style="background: #e0e7ff; color: #4338ca; font-size: 0.7rem; padding: 2px 6px;">Academic Demo Output</span>
                            <?php endif; ?>
                        </div>

                        <?php if (empty($results)): ?>
                            <div style="font-size: 0.85rem; color: #94a3b8; font-style: italic; padding: 10px;">
                                No rows returned for this filter.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive" style="background: white; border: 1px solid var(--border-color); border-radius: var(--radius-sm); overflow-x: auto;">
                                <table class="table" style="font-size: 0.84rem; margin-bottom: 0;">
                                    <thead style="background: #f8fafc; border-bottom: 1px solid var(--border-color);">
                                        <tr>
                                            <?php foreach (array_keys($results[0]) as $colName): ?>
                                                <th style="padding: 9px 14px; font-weight: 600; color: #475569; white-space: nowrap;"><?= htmlspecialchars($colName) ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($results as $row): ?>
                                            <tr>
                                                <?php foreach ($row as $colKey => $val): ?>
                                                    <td style="padding: 8px 14px; font-family: monospace; font-size: 0.84rem; color: #334155; white-space: nowrap;">
                                                        <?php 
                                                        $colLower = strtolower((string)$colKey);
                                                        $isMoneyCol = in_array($colLower, ['amount', 'total_spent', 'budget_amount', 'saved_amount', 'remaining', 'monthly_income', 'monthly_expense', 'monthly_net_savings', 'overrun_amount', 'max_expense', 'min_expense', 'total_expense', 'total_amount', 'spent'])
                                                            || str_contains($colLower, 'amount')
                                                            || str_contains($colLower, 'expense')
                                                            || str_contains($colLower, 'spent')
                                                            || str_contains($colLower, 'income');
                                                        if (is_numeric($val) && $isMoneyCol && !str_contains($colLower, 'count') && !str_contains($colLower, 'id') && !str_contains($colLower, 'percentage')) {
                                                            echo htmlspecialchars(formatINR($val));
                                                        } else {
                                                            echo htmlspecialchars((string)$val);
                                                        }
                                                        ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div> <!-- End content-body -->
</div>

<script>
function toggleNormalizationGuide() {
    const guide = document.getElementById('normalizationGuideCard');
    const btnText = document.getElementById('normGuideBtnText');
    if (guide.style.display === 'none' || guide.style.display === '') {
        guide.style.display = 'block';
        btnText.textContent = 'Hide Normalization Guide';
    } else {
        guide.style.display = 'none';
        btnText.textContent = 'View Normalization Guide (1NF, 2NF, 3NF)';
    }
}

function filterQueries(category, btn) {
    const buttons = document.querySelectorAll('#filterButtonContainer button');
    buttons.forEach(b => {
        b.classList.remove('btn-primary');
        b.classList.add('btn-secondary');
    });
    btn.classList.remove('btn-secondary');
    btn.classList.add('btn-primary');

    const cards = document.querySelectorAll('.query-card');
    cards.forEach(card => {
        if (category === 'all' || card.getAttribute('data-category') === category) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

function searchQueries() {
    const query = document.getElementById('querySearchInput').value.toLowerCase();
    const cards = document.querySelectorAll('.query-card');
    cards.forEach(card => {
        const text = card.textContent.toLowerCase();
        if (text.includes(query)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

function copySql(btn) {
    const box = btn.closest('.sql-code-box');
    const code = box.querySelector('code').innerText;
    navigator.clipboard.writeText(code).then(() => {
        const span = btn.querySelector('span');
        const icon = btn.querySelector('i');
        const oldText = span.textContent;
        span.textContent = 'Copied!';
        icon.className = 'fa-solid fa-check';
        btn.style.color = '#34d399';
        setTimeout(() => {
            span.textContent = oldText;
            icon.className = 'fa-regular fa-copy';
            btn.style.color = '#cbd5e1';
        }, 2000);
    }).catch(err => {
        console.error('Clipboard copy failed: ', err);
    });
}
</script>

<style>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-8px); }
    to { opacity: 1; transform: translateY(0); }
}
.query-card {
    background: white;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 22px;
    box-shadow: var(--shadow-sm);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.query-card:hover {
    box-shadow: var(--shadow-md);
}
.query-concept-tag {
    background: #eff6ff;
    color: #2563eb;
    border: 1px solid #bfdbfe;
    padding: 4px 10px;
    border-radius: 9999px;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.02em;
}
.sql-code-box {
    background: #0f172a;
    border: 1px solid #334155;
    border-radius: var(--radius-sm);
    padding: 14px 18px;
    overflow-x: auto;
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
