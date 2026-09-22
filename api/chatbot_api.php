<?php
/**
 * Real AI Chatbot Backend API Endpoint - Ask Me Help
 * Connects to Gemini API server-side with an intelligent domain expert fallback
 * 
 * SECURITY:
 * Never exposes the Gemini API key to client browsers.
 * Endpoint: POST api/chatbot_api.php
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/ai_config.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

// Parse incoming payload
$inputData = [];
$rawInput = file_get_contents('php://input');
if (!empty($rawInput)) {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $inputData = $decoded;
    } else {
        parse_str($rawInput, $parsed);
        if (is_array($parsed) && !empty($parsed['message'])) {
            $inputData['message'] = $parsed['message'];
        }
    }
}
$message = trim((string)($inputData['message'] ?? $inputData['prompt'] ?? $_POST['message'] ?? ''));

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Question cannot be empty.']);
    exit;
}

$user = current_user();
$userName = $user ? htmlspecialchars($user['name']) : 'there';

// Required System Prompt
$systemPrompt = "You are Ask Me Help, the AI assistant for ExpenseIQ Smart Expense Tracker. Answer questions about the application, its features, DBMS concepts, PHP, MySQL, XAMPP, transactions, budgets, analytics and reports. Give clear and concise answers based on the actual application. All currency amounts should be in Indian Rupees (₹).";

$apiKey = defined('GEMINI_API_KEY') ? constant('GEMINI_API_KEY') : '';
$reply = '';
$source = 'local_intelligence';

// Query Gemini if API key is present
if (!empty($apiKey)) {
    $url = GEMINI_API_URL . '?key=' . urlencode($apiKey);

    $payload = [
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => $systemPrompt . "\n\nUser Question: " . $message]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.4,
            'maxOutputTokens' => 650,
            'topP' => 0.9
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response !== false && $httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            $reply = trim($data['candidates'][0]['content']['parts'][0]['text']);
            $source = 'gemini';
        }
    }
}

// If Gemini is unconfigured or failed, use our comprehensive contextual intelligent engine
if (empty($reply)) {
    $reply = generateIntelligentResponse($message, $userName);
    $source = 'intelligent_engine';
}

echo json_encode([
    'success' => true,
    'reply' => $reply,
    'source' => $source
]);
exit;

/**
 * High-precision domain knowledge engine for ExpenseIQ
 */
function generateIntelligentResponse(string $query, string $userName): string {
    $q = strtolower(trim($query));

    // 1. Balance Calculation
    if (str_contains($q, 'balance') && (str_contains($q, 'calculate') || str_contains($q, 'how is') || str_contains($q, 'formula') || str_contains($q, 'net balance') || str_contains($q, 'what is'))) {
        return "Your net balance is calculated as **Total Income − Total Expenses**.\n\n"
            . "In the MySQL database, this is computed using an aggregate SQL query across the `transactions` table:\n"
            . "`SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) - SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END)`\n\n"
            . "A positive value indicates surplus liquidity in Indian Rupees (₹), while a negative value warns of an active cash deficit.";
    }

    // 2. Add Expense
    if (str_contains($q, 'add expense') || (str_contains($q, 'expense') && (str_contains($q, 'add') || str_contains($q, 'record') || str_contains($q, 'log') || str_contains($q, 'create')))) {
        return "To add an expense in ExpenseIQ:\n\n"
            . "1. Click the **'+ Add Transaction'** button in the top navigation bar or dashboard.\n"
            . "2. Ensure the classification radio is set to **'Expense'**.\n"
            . "3. Enter the amount in Indian Rupees (₹) (e.g., `1500.00`).\n"
            . "4. Select the category (e.g., Food & Dining, Bills, Transportation).\n"
            . "5. Enter a brief description and the date, then click **'Save Transaction'**.\n\n"
            . "The system deducts the expense from your Net Balance and updates any active category budgets automatically.";
    }

    // 3. Add Income
    if (str_contains($q, 'add income') || (str_contains($q, 'income') && (str_contains($q, 'add') || str_contains($q, 'record') || str_contains($q, 'receive')))) {
        return "To record new income in ExpenseIQ:\n\n"
            . "1. Click **'+ Add Transaction'** on your top navbar or dashboard.\n"
            . "2. Select the **'Income'** option.\n"
            . "3. Enter the amount in ₹ (e.g., `50000.00`).\n"
            . "4. Pick an income category (e.g. Salary, Freelance, Investment).\n"
            . "5. Add a description (e.g., 'Monthly Salary Credit') and submit.\n\n"
            . "Your Net Balance and monthly income analytics update instantly!";
    }

    // 4. Where are expenses stored (Database storage)
    if ((str_contains($q, 'where') || str_contains($q, 'which table') || str_contains($q, 'how')) && (str_contains($q, 'expense') || str_contains($q, 'transaction')) && (str_contains($q, 'store') || str_contains($q, 'save') || str_contains($q, 'table') || str_contains($q, 'database'))) {
        return "Your expenses are stored in the **`transactions`** table in your MySQL database.\n\n"
            . "Table schema attributes:\n"
            . "• `transaction_id`: Primary Key (AUTO_INCREMENT)\n"
            . "• `user_id`: Foreign Key referencing `users.user_id`\n"
            . "• `category_id`: Foreign Key referencing `categories.category_id`\n"
            . "• `amount`: Monetary value in DECIMAL(10,2)\n"
            . "• `transaction_type`: ENUM('income', 'expense') — set to `'expense'`\n"
            . "• `transaction_date`: DATE of occurrence\n"
            . "• `description`: VARCHAR(255) for transaction notes\n"
            . "• `created_at`: TIMESTAMP record timestamp";
    }

    // 5. What is a Foreign Key / Primary Key
    if (str_contains($q, 'foreign key') || str_contains($q, 'primary key') || str_contains($q, 'referential integrity')) {
        return "In relational database management systems (DBMS):\n\n"
            . "• **Primary Key (PK):** A column (or set of columns) that uniquely identifies each row in a table. In ExpenseIQ, `user_id` is the primary key of `users`, and `transaction_id` is the primary key of `transactions`.\n\n"
            . "• **Foreign Key (FK):** A column that establishes a link between two tables by referencing the Primary Key of another table. For example, `transactions.user_id` references `users.user_id`, and `transactions.category_id` references `categories.category_id`.\n\n"
            . "• **Referential Integrity:** Ensures that a transaction cannot exist without a valid user or category, preventing orphan records.";
    }

    // 6. How does XAMPP work
    if (str_contains($q, 'xampp') || str_contains($q, 'apache') || str_contains($q, 'mysql server')) {
        return "ExpenseIQ runs on the **XAMPP** local development stack:\n\n"
            . "• **Apache:** The HTTP web server (running on port 80/443). It processes client browser requests and executes PHP scripts.\n"
            . "• **MySQL/MariaDB:** The relational database server (running on port 3306). It stores tables (`users`, `categories`, `transactions`, `budgets`, `savings_goals`).\n"
            . "• **PHP 8+:** The server-side scripting language connecting Apache to MySQL using **PDO (PHP Data Objects)** with prepared statements for SQL injection prevention.\n\n"
            . "To run the app, ensure Apache and MySQL are started in the XAMPP Control Panel.";
    }

    // 7. What does the analytics page show
    if (str_contains($q, 'analytics') || str_contains($q, 'insight') || str_contains($q, 'chart')) {
        return "The **Analytics** page provides a commercial-grade financial intelligence overview:\n\n"
            . "• **Spending Trends:** A 6-month continuous area chart tracking expenditure velocity.\n"
            . "• **Income vs Expense Analysis:** Grouped comparisons showing your monthly cashflow margins.\n"
            . "• **Category Breakdown:** Doughnut chart and progress indicators showing where your funds go.\n"
            . "• **Financial Metrics:** Highest spending category, average monthly expense, daily burn rate, and savings rate.\n"
            . "• All visual charts are rendered dynamically using **Chart.js 4.4**.";
    }

    // 8. Budgets
    if (str_contains($q, 'budget')) {
        return "The **Budgets** module allows you to define spending ceilings per category:\n\n"
            . "• **Safe Zone (0% - 79%):** Displayed in emerald green.\n"
            . "• **Caution Zone (80% - 99%):** Amber indicator alerting you that you are near your limit.\n"
            . "• **Over Budget (100%+):** Pulsing red alert indicating an expenditure overrun.\n\n"
            . "Each budget links dynamically with the `transactions` table to calculate real-time percentage utilization.";
    }

    // 9. Savings Goals
    if (str_contains($q, 'saving') || str_contains($q, 'goal')) {
        return "The **Savings Goals** feature tracks progress toward major financial milestones:\n\n"
            . "• Set a target amount in ₹ (e.g. ₹1,00,000 for Emergency Fund or Vacation).\n"
            . "• Use the **'+ Deposit'** button on any goal card to log incremental contributions.\n"
            . "• Circular progress rings illustrate how close you are to reaching your target.";
    }

    // 10. SQL Queries / DBMS Showcase
    if (str_contains($q, 'sql') || str_contains($q, 'query') || str_contains($q, 'dbms') || str_contains($q, '3nf') || str_contains($q, 'normalization')) {
        return "ExpenseIQ includes a dedicated **DBMS Showcase** featuring **20 Core Relational Queries**:\n\n"
            . "• **DQL & Projections:** Basic `SELECT`, filtered queries, and date ranges.\n"
            . "• **DML:** Parameterized `INSERT`, `UPDATE`, and `DELETE`.\n"
            . "• **Joins:** `INNER JOIN` (transactions with categories) and `LEFT JOIN` (categories without spending).\n"
            . "• **Aggregations:** `GROUP BY`, `HAVING`, `SUM`, `AVG`, `COUNT`.\n"
            . "• **Advanced:** Correlated subqueries and window running totals.\n"
            . "• **3NF:** Full Third Normal Form proofs eliminating transitive dependencies across all 5 tables.\n\n"
            . "Visit the **'SQL Queries'** link in the sidebar to run all 20 live against MySQL!";
    }

    // 11. Reports
    if (str_contains($q, 'report') || str_contains($q, 'statement') || str_contains($q, 'csv') || str_contains($q, 'print')) {
        return "The **Reports** module allows you to generate customized financial statements:\n\n"
            . "• Filter transactions by custom date range, transaction type, or category.\n"
            . "• Review aggregated summary totals in Indian Rupees (₹).\n"
            . "• **Export to CSV** for external spreadsheet analysis or use browser **Print** for a clean, audit-ready financial statement.";
    }

    // 12. Greetings
    if (preg_match('/^(hi|hello|hey|good morning|good evening|namaste|greetings)/i', $q)) {
        return "Hello {$userName}! 👋 I am **Ask Me Help**, your ExpenseIQ AI Assistant.\n\n"
            . "How can I assist you today? You can ask me about:\n"
            . "• *'How do I add an expense?'*\n"
            . "• *'How is my balance calculated?'*\n"
            . "• *'Where are my expenses stored?'*\n"
            . "• *'What is a foreign key?'*\n"
            . "• *'How does XAMPP work?'*";
    }

    // Default Fallback
    return "I am **Ask Me Help**, the AI assistant for ExpenseIQ.\n\n"
        . "I can answer questions about the application, financial formulas, and database concepts. Try asking:\n"
        . "• *'How do I add an expense?'*\n"
        . "• *'How is my balance calculated?'*\n"
        . "• *'Where are my expenses stored?'*\n"
        . "• *'What is a foreign key?'*\n"
        . "• *'How does XAMPP work?'*\n"
        . "• *'What does the analytics page show?'*";
}
