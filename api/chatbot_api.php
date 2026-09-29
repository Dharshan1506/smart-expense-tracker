<?php
/**
 * Real AI Chatbot Backend API Endpoint - Ask Me Help
 * Hybrid AI + MySQL Database Architecture for ExpenseIQ
 * 
 * SECURITY:
 * Always uses $_SESSION['user_id'] for logged-in financial queries.
 * Never exposes database credentials or API keys to client browsers.
 * Uses prepared statements for all database operations.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/ai_config.php';

if (isset($_SERVER['REQUEST_METHOD'])) {
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
    $history = $inputData['history'] ?? [];

    if (empty($message)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Question cannot be empty.']);
        exit;
    }

    // Require authenticated user
    $user = current_user();
    if (!$user) {
        echo json_encode([
            'success' => true,
            'reply'   => "Please sign in to access your ExpenseIQ account and view your personal financial information!",
            'source'  => 'auth_guard'
        ]);
        exit;
    }

    $userId = (int)$user['id'];
    $userName = htmlspecialchars($user['name']);
    $pdo = getDBConnection();

    // Process query using Hybrid AI + Database Intent Engine
    $result = processAskMeQuery($pdo, $userId, $userName, $message, $history);

    echo json_encode([
        'success' => true,
        'reply'   => $result['reply'],
        'source'  => $result['source']
    ]);
    exit;
}

/**
 * Main Hybrid AI Query Processor
 */
function processAskMeQuery(PDO $pdo, int $userId, string $userName, string $query, array $history): array {
    $qLower = strtolower(trim($query));
    $qClean = preg_replace('/[^\w\s]/u', ' ', $qLower);
    $qClean = (string)preg_replace('/\s+/', ' ', (string)$qClean);

    // 1. Technical / Implementation Query Refusal (Section 17 rule)
    if (isTechnicalQuery($qLower, $qClean)) {
        return [
            'reply'  => "I'm Ask Me Help, your ExpenseIQ user assistant. I can help you understand and use the ExpenseIQ application, including your expenses, income, budgets, savings, analytics and financial information.",
            'source' => 'technical_scope'
        ];
    }

    // 2. Financial Database Intent Handler (Queries MySQL for live user data)
    $dbAnswer = handleFinancialDatabaseIntent($pdo, $userId, $userName, $query, $qLower, $qClean);
    if ($dbAnswer !== null) {
        return [
            'reply'  => $dbAnswer,
            'source' => 'database_intent'
        ];
    }

    // 3. Application Guidance & Feature Handler
    $appAnswer = handleAppGuideIntent($qLower, $qClean, $userName);
    if ($appAnswer !== null) {
        return [
            'reply'  => $appAnswer,
            'source' => 'app_guide'
        ];
    }

    // 4. AI Generator / Intelligent Fallback for General Questions & Personal Finance Advice
    $aiResult = getAIGeneratedResponse($pdo, $userId, $userName, $query, $history);
    return [
        'reply'  => $aiResult['reply'],
        'source' => $aiResult['source']
    ];
}

/**
 * Check if the question asks for technical implementation, code, storage, or server details
 */
function isTechnicalQuery(string $q, string $clean): bool {
    $techTerms = [
        'how does php', 'how does mysql', 'how does xampp', 'php code', 'mysql query',
        'sql query', 'backend implementation', 'api implementation', 'server configuration',
        'database connection', 'chatbot implementation', 'foreign key', 'primary key',
        'normalization', '3nf', 'show me the php', 'show me the code', 'where is the code',
        'how is the api connected', 'how does login work', 'how does php execute',
        'database setup', 'tables used', 'tables related', 'crud', 'subquery', 'join query',
        'database structure', 'prepared statement code', 'database connection code',
        'where are my expenses stored', 'where is my data stored', 'how are transactions stored',
        'database storage'
    ];

    foreach ($techTerms as $term) {
        if (str_contains($q, $term)) {
            return true;
        }
    }
    return false;
}

/**
 * Helper: Check if string contains any of the provided needles
 */
function containsAny(string $haystack, array $needles): bool {
    foreach ($needles as $needle) {
        if (str_contains($haystack, $needle)) {
            return true;
        }
    }
    return false;
}

/**
 * Dynamically find category referenced in question
 */
function findCategoryInQuery(PDO $pdo, int $userId, string $q): ?array {
    $stmt = $pdo->prepare("SELECT category_id, category_name, category_type FROM categories WHERE user_id IS NULL OR user_id = ?");
    $stmt->execute([$userId]);
    $categories = $stmt->fetchAll();

    $aliasMap = [
        'food' => ['food', 'dining', 'groceries', 'grocery', 'restaurant', 'eat', 'swiggy', 'zomato', 'snack', 'snacks', 'lunch', 'dinner', 'brunch', 'cafe'],
        'travel' => ['travel', 'transport', 'commute', 'uber', 'train', 'bus', 'fuel', 'petrol', 'flight', 'cab', 'subway'],
        'shopping' => ['shopping', 'clothes', 'clothing', 'apparel', 'buy', 'mall', 'amazon', 'flipkart', 'sneakers', 'shoes', 'mouse'],
        'bills' => ['bills', 'utilities', 'electricity', 'internet', 'water', 'recharge', 'power', 'broadband'],
        'education' => ['education', 'books', 'course', 'school', 'college', 'tuition', 'study', 'certification'],
        'entertainment' => ['entertainment', 'movie', 'cinema', 'games', 'gaming', 'netflix', 'weekend', 'concert', 'theater'],
        'healthcare' => ['healthcare', 'health', 'pharmacy', 'medicine', 'doctor', 'clinic', 'hospital', 'vitamins']
    ];

    foreach ($categories as $cat) {
        $cNameLower = strtolower($cat['category_name']);
        if (str_contains($q, $cNameLower)) {
            return $cat;
        }
        foreach ($aliasMap as $mainKey => $aliases) {
            if (str_contains($cNameLower, $mainKey)) {
                foreach ($aliases as $alias) {
                    if (preg_match('/\b' . preg_quote($alias, '/') . '\b/i', $q)) {
                        return $cat;
                    }
                }
            }
        }
    }
    return null;
}

/**
 * Financial Database Intent Router
 */
function handleFinancialDatabaseIntent(PDO $pdo, int $userId, string $userName, string $rawQuery, string $q, string $clean): ?string {
    
    // 1. Balance / Net Available Balance Questions
    // Examples: "What is my balance?", "How much money do I have left?", "Net balance?", "Available balance?", "Enoda balance evlo?"
    if (containsAny($q, ['balance', 'available balance', 'net balance', 'money left', 'money do i have left', 'how much can i spend', 'enoda balance', 'my balance'])) {
        $summary = get_user_financial_summary($pdo, $userId);
        return "Your current **Net Available Balance** is **" . format_currency($summary['net_balance']) . "**.\n\n"
             . "Here is your current live summary:\n"
             . "💰 **Total Income:** " . format_currency($summary['total_income']) . "\n"
             . "💸 **Total Expenses:** " . format_currency($summary['total_expense']) . "\n"
             . "🏦 **Total Savings:** " . format_currency($summary['total_savings']) . "\n"
             . "💵 **Net Available Balance:** **" . format_currency($summary['net_balance']) . "**";
    }

    $matchedCategory = findCategoryInQuery($pdo, $userId, $q);

    // 2. Budget Questions (Prioritized over general category expenses)
    // Examples: "What is my budget?", "What is my Food budget?", "How much is left in my Food budget?", "How much Food budget is remaining?", "Did I exceed any budget?"
    if (containsAny($q, ['budget'])) {
        // Specific category budget query
        if ($matchedCategory !== null) {
            $stmt = $pdo->prepare("
                SELECT b.budget_amount, COALESCE(SUM(t.amount), 0) as total_spent
                FROM budgets b
                LEFT JOIN transactions t ON t.category_id = b.category_id 
                    AND t.user_id = b.user_id 
                    AND t.transaction_type = 'expense'
                    AND t.transaction_date BETWEEN b.start_date AND b.end_date
                WHERE b.user_id = ? AND b.category_id = ?
                  AND CURRENT_DATE() BETWEEN b.start_date AND b.end_date
                GROUP BY b.budget_id, b.budget_amount
            ");
            $stmt->execute([$userId, $matchedCategory['category_id']]);
            $bData = $stmt->fetch();

            $catName = htmlspecialchars($matchedCategory['category_name']);
            if ($bData) {
                $limit = (float)$bData['budget_amount'];
                $spent = (float)$bData['total_spent'];
                $rem = max(0, $limit - $spent);
                $pct = ($limit > 0) ? round(($spent / $limit) * 100) : 0;

                return "Your **" . $catName . "** budget is **" . format_currency($limit) . "**. You have spent **" . format_currency($spent) . "**, so **" . format_currency($rem) . "** remains. You have used **" . $pct . "%**.";
            } else {
                return "You do not have an active budget established for **" . $catName . "**. You can create one on the **Budgets** page!";
            }
        }

        // Exceeded budget check
        if (containsAny($q, ['exceed', 'exceeded', 'over budget', 'finished', 'almost finished'])) {
            $stmt = $pdo->prepare("
                SELECT c.category_name, b.budget_amount, COALESCE(SUM(t.amount), 0) as total_spent
                FROM budgets b
                INNER JOIN categories c ON b.category_id = c.category_id
                LEFT JOIN transactions t ON t.category_id = b.category_id 
                    AND t.user_id = b.user_id 
                    AND t.transaction_type = 'expense'
                    AND t.transaction_date BETWEEN b.start_date AND b.end_date
                WHERE b.user_id = ? AND CURRENT_DATE() BETWEEN b.start_date AND b.end_date
                GROUP BY b.budget_id, b.budget_amount, c.category_name
            ");
            $stmt->execute([$userId]);
            $allBudgets = $stmt->fetchAll();

            $exceededList = [];
            $warningList = [];
            foreach ($allBudgets as $b) {
                $limit = (float)$b['budget_amount'];
                $spent = (float)$b['total_spent'];
                if ($spent > $limit) {
                    $exceededList[] = "• **" . htmlspecialchars($b['category_name']) . ":** Spent " . format_currency($spent) . " (Limit: " . format_currency($limit) . ")";
                } elseif ($limit > 0 && ($spent / $limit) >= 0.8) {
                    $pct = round(($spent / $limit) * 100);
                    $warningList[] = "• **" . htmlspecialchars($b['category_name']) . ":** " . $pct . "% used (" . format_currency($spent) . " of " . format_currency($limit) . ")";
                }
            }

            if (!empty($exceededList)) {
                return "⚠️ You have exceeded the following budget(s):\n\n" . implode("\n", $exceededList);
            } elseif (!empty($warningList)) {
                return "None of your budgets have been exceeded yet, but the following are near their limit:\n\n" . implode("\n", $warningList);
            } else {
                return "None of your active budgets have been exceeded! You are fully on track.";
            }
        }

        // General list of active budgets
        $stmt = $pdo->prepare("
            SELECT c.category_name, b.budget_amount, COALESCE(SUM(t.amount), 0) as total_spent
            FROM budgets b
            INNER JOIN categories c ON b.category_id = c.category_id
            LEFT JOIN transactions t ON t.category_id = b.category_id 
                AND t.user_id = b.user_id 
                AND t.transaction_type = 'expense'
                AND t.transaction_date BETWEEN b.start_date AND b.end_date
            WHERE b.user_id = ? AND CURRENT_DATE() BETWEEN b.start_date AND b.end_date
            GROUP BY b.budget_id, b.budget_amount, c.category_name
        ");
        $stmt->execute([$userId]);
        $allBudgets = $stmt->fetchAll();

        if (!empty($allBudgets)) {
            $lines = "";
            foreach ($allBudgets as $b) {
                $limit = (float)$b['budget_amount'];
                $spent = (float)$b['total_spent'];
                $rem = max(0, $limit - $spent);
                $pct = ($limit > 0) ? round(($spent / $limit) * 100) : 0;
                $lines .= "• **" . htmlspecialchars($b['category_name']) . ":** Budget " . format_currency($limit) . " | Spent " . format_currency($spent) . " | Remaining " . format_currency($rem) . " (" . $pct . "% used)\n";
            }
            return "Here is your active budget status:\n\n" . $lines;
        } else {
            return "You don't have any active budgets established right now. You can create one under **Budgets** in the navigation menu!";
        }
    }

    // 3. Salary Questions
    // Examples: "How much is my salary?", "What is my salary this month?", "How much salary did I receive?", "Salary evlo?", "Indha month salary evlo?"
    if (containsAny($q, ['salary'])) {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(t.amount), 0)
            FROM transactions t
            INNER JOIN categories c ON t.category_id = c.category_id
            WHERE t.user_id = ? AND t.transaction_type = 'income'
              AND LOWER(c.category_name) LIKE '%salary%'
              AND MONTH(t.transaction_date) = MONTH(CURRENT_DATE())
              AND YEAR(t.transaction_date) = YEAR(CURRENT_DATE())
        ");
        $stmt->execute([$userId]);
        $salaryAmount = (float)$stmt->fetchColumn();

        if ($salaryAmount > 0) {
            return "You have received **" . format_currency($salaryAmount) . "** in Salary this month.";
        } else {
            return "You haven't recorded any Salary income for this month yet.";
        }
    }

    // 4. Other Income Questions
    // Examples: "How much other income?", "How much freelance income?", "How much income apart from salary?"
    if (containsAny($q, ['other income', 'freelance', 'apart from salary', 'other than salary', 'extra income'])) {
        $stmt = $pdo->prepare("
            SELECT c.category_name, COALESCE(SUM(t.amount), 0) as total
            FROM transactions t
            INNER JOIN categories c ON t.category_id = c.category_id
            WHERE t.user_id = ? AND t.transaction_type = 'income'
              AND LOWER(c.category_name) NOT LIKE '%salary%'
              AND MONTH(t.transaction_date) = MONTH(CURRENT_DATE())
              AND YEAR(t.transaction_date) = YEAR(CURRENT_DATE())
            GROUP BY c.category_id, c.category_name
        ");
        $stmt->execute([$userId]);
        $otherIncomes = $stmt->fetchAll();

        if (!empty($otherIncomes)) {
            $totalOther = 0;
            $lines = "";
            foreach ($otherIncomes as $oi) {
                $totalOther += (float)$oi['total'];
                $lines .= "• **" . htmlspecialchars($oi['category_name']) . ":** " . format_currency($oi['total']) . "\n";
            }
            return "You have received a total of **" . format_currency($totalOther) . "** in non-salary income this month:\n\n" . $lines;
        } else {
            return "You haven't recorded any non-salary income for this month.";
        }
    }

    // 5. General Income Questions
    // Examples: "What is my income?", "How much did I earn this month?", "How much income did I receive?", "What's my monthly income?"
    if (containsAny($q, ['income', 'how much did i earn', 'earned this month', 'income evlo'])) {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0)
            FROM transactions
            WHERE user_id = ? AND transaction_type = 'income'
              AND MONTH(transaction_date) = MONTH(CURRENT_DATE())
              AND YEAR(transaction_date) = YEAR(CURRENT_DATE())
        ");
        $stmt->execute([$userId]);
        $totalIncome = (float)$stmt->fetchColumn();

        return "Your total income for this month is **" . format_currency($totalIncome) . "**.";
    }

    // 6. Highest & Lowest Spending Categories
    // Examples: "Which category did I spend the most on?", "What is my highest expense category?", "Where am I spending the most?", "Which category costs me the most?"
    if (containsAny($q, ['spend the most', 'spent the most', 'highest expense', 'highest spending', 'spending the most', 'costs me the most', 'top category', 'max expense', 'highest category'])) {
        $stmt = $pdo->prepare("
            SELECT c.category_name, SUM(t.amount) as total
            FROM transactions t
            INNER JOIN categories c ON t.category_id = c.category_id
            WHERE t.user_id = ? AND t.transaction_type = 'expense'
              AND MONTH(t.transaction_date) = MONTH(CURRENT_DATE())
              AND YEAR(t.transaction_date) = YEAR(CURRENT_DATE())
            GROUP BY c.category_id, c.category_name
            ORDER BY total DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $top = $stmt->fetch();

        if ($top && (float)$top['total'] > 0) {
            return "Your highest spending category this month is **" . htmlspecialchars($top['category_name']) . "** with a total of **" . format_currency($top['total']) . "** spent.";
        } else {
            return "No category expenses have been recorded for this month yet.";
        }
    }

    if (containsAny($q, ['spend the least', 'spent the least', 'lowest expense', 'lowest spending', 'least category', 'min expense'])) {
        $stmt = $pdo->prepare("
            SELECT c.category_name, SUM(t.amount) as total
            FROM transactions t
            INNER JOIN categories c ON t.category_id = c.category_id
            WHERE t.user_id = ? AND t.transaction_type = 'expense'
              AND MONTH(t.transaction_date) = MONTH(CURRENT_DATE())
              AND YEAR(t.transaction_date) = YEAR(CURRENT_DATE())
            GROUP BY c.category_id, c.category_name
            ORDER BY total ASC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $least = $stmt->fetch();

        if ($least && (float)$least['total'] > 0) {
            return "Your lowest spending category this month is **" . htmlspecialchars($least['category_name']) . "** with **" . format_currency($least['total']) . "** spent.";
        } else {
            return "No category expenses recorded for this month yet.";
        }
    }

    // 7. Category-Specific Expense Questions
    // Examples: "How much did I spend on Food?", "Food expense?", "How much for food?", "Food ku evlo spend panniruka?", "Food ku evlo selavu?"
    if ($matchedCategory !== null && (containsAny($q, ['spend', 'spent', 'expense', 'selavu', 'for', 'ku', 'cost', 'outflow', 'food', 'travel', 'entertainment', 'shopping', 'bills', 'education', 'healthcare']) || count(explode(' ', $q)) <= 4)) {
        $catId = (int)$matchedCategory['category_id'];
        $catName = htmlspecialchars($matchedCategory['category_name']);

        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(t.amount), 0)
            FROM transactions t
            WHERE t.user_id = ? AND t.category_id = ? AND t.transaction_type = 'expense'
              AND MONTH(t.transaction_date) = MONTH(CURRENT_DATE())
              AND YEAR(t.transaction_date) = YEAR(CURRENT_DATE())
        ");
        $stmt->execute([$userId, $catId]);
        $catSpent = (float)$stmt->fetchColumn();

        if ($catSpent > 0) {
            return "You have spent **" . format_currency($catSpent) . "** on **" . $catName . "** this month.";
        } else {
            return "You haven't recorded any **" . $catName . "** expenses this month.";
        }
    }

    // 8. Total / Monthly Expense Questions (Current Month / Past Months)
    // Examples: "How much did I spend this month?", "What is my total expense?", "Show my September expenses", "What was my expense in August?"
    if (containsAny($q, ['how much did i spend', 'total expense', 'how much have i spent', 'what are my expenses', 'total expenses', 'spend this month', 'spent this month', 'indha month expense', 'how much money did i spend', 'expenses in', 'spent in', 'expenses last month', 'spent last month', 'selavu evlo'])) {
        
        $targetMonth = (int)date('m');
        $targetYear = (int)date('Y');
        $monthLabel = "this month";

        $monthsList = [
            'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4,
            'may' => 5, 'june' => 6, 'july' => 7, 'august' => 8,
            'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12
        ];

        foreach ($monthsList as $mName => $mNum) {
            if (str_contains($q, $mName)) {
                $targetMonth = $mNum;
                $monthLabel = "in " . ucfirst($mName);
                if ($targetMonth > (int)date('m')) {
                    $targetYear = (int)date('Y') - 1;
                    $monthLabel .= " " . $targetYear;
                }
                break;
            }
        }

        if (str_contains($q, 'last month') || str_contains($q, 'previous month')) {
            $dt = new DateTime('first day of last month');
            $targetMonth = (int)$dt->format('m');
            $targetYear = (int)$dt->format('Y');
            $monthLabel = "last month (" . $dt->format('F Y') . ")";
        }

        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0)
            FROM transactions
            WHERE user_id = ? AND transaction_type = 'expense'
              AND MONTH(transaction_date) = ? AND YEAR(transaction_date) = ?
        ");
        $stmt->execute([$userId, $targetMonth, $targetYear]);
        $totalSpent = (float)$stmt->fetchColumn();

        if ($monthLabel === "this month") {
            return "You have spent **" . format_currency($totalSpent) . "** this month.";
        } else {
            return "You spent **" . format_currency($totalSpent) . "** " . $monthLabel . ".";
        }
    }

    // 9. Savings Questions
    // Examples: "How much have I saved?", "What are my savings?", "What is my savings progress?", "Savings evlo?"
    if (containsAny($q, ['savings', 'have i saved', 'what are my savings', 'savings goal', 'need to save', 'savings progress'])) {
        $summary = get_user_financial_summary($pdo, $userId);

        $stmt = $pdo->prepare("SELECT goal_name, target_amount, saved_amount, target_date FROM savings_goals WHERE user_id = ?");
        $stmt->execute([$userId]);
        $goals = $stmt->fetchAll();

        $goalLines = "";
        foreach ($goals as $g) {
            $target = (float)$g['target_amount'];
            $saved = (float)$g['saved_amount'];
            $rem = max(0, $target - $saved);
            $pct = ($target > 0) ? round(($saved / $target) * 100) : 0;
            $goalLines .= "• **" . htmlspecialchars($g['goal_name']) . ":** Saved " . format_currency($saved) . " of " . format_currency($target) . " target (" . $pct . "% complete, " . format_currency($rem) . " remaining)\n";
        }

        return "Your total accumulated savings across all goals is **" . format_currency($summary['total_savings']) . "**.\n\n"
             . ($goalLines ? "Savings Goals Progress:\n" . $goalLines : "You can set up new savings targets on the **Savings** page!");
    }

    // 10. Financial Health Questions
    // Examples: "What is my financial health?", "What's my financial health score?", "Why is my score low?"
    if (containsAny($q, ['financial health', 'health score', 'score low', 'improve my financial health', 'score change'])) {
        $health = calculate_financial_health_score($pdo, $userId);

        return "Your current **Financial Health Score** is **" . $health['score'] . " / 100** (" . $health['label'] . ").\n\n"
             . "Score Breakdown:\n"
             . "• **Savings Rate:** " . $health['savings_rate'] . "%\n"
             . "• **Expense-to-Income Ratio:** " . $health['expense_ratio'] . "%\n"
             . "• **Net Available Balance:** " . format_currency($health['net_balance']) . "\n\n"
             . "💡 **Tips to Improve:** Keep your monthly expenses below 50% of income, avoid budget overruns, and maintain regular savings contributions!";
    }

    // 11. Transaction Queries
    // Examples: "What did I spend today?", "What did I spend yesterday?", "Show my recent expenses", "What was my last expense?"
    if (containsAny($q, ['spent today', 'spent yesterday', 'recent expenses', 'last expense', 'recent transactions', 'how many transactions', 'my transactions'])) {
        if (str_contains($q, 'today')) {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = ? AND transaction_type = 'expense' AND transaction_date = CURRENT_DATE()");
            $stmt->execute([$userId]);
            $todayAmt = (float)$stmt->fetchColumn();
            return "You have spent **" . format_currency($todayAmt) . "** today.";
        }

        if (str_contains($q, 'yesterday')) {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = ? AND transaction_type = 'expense' AND transaction_date = CURRENT_DATE() - INTERVAL 1 DAY");
            $stmt->execute([$userId]);
            $yestAmt = (float)$stmt->fetchColumn();
            return "You spent **" . format_currency($yestAmt) . "** yesterday.";
        }

        if (str_contains($q, 'how many transactions')) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = ?");
            $stmt->execute([$userId]);
            $count = (int)$stmt->fetchColumn();
            return "You have recorded a total of **" . $count . "** transactions in ExpenseIQ.";
        }

        $stmt = $pdo->prepare("
            SELECT t.amount, t.transaction_type, t.description, t.transaction_date, c.category_name
            FROM transactions t
            INNER JOIN categories c ON t.category_id = c.category_id
            WHERE t.user_id = ?
            ORDER BY t.transaction_date DESC, t.transaction_id DESC
            LIMIT 5
        ");
        $stmt->execute([$userId]);
        $txs = $stmt->fetchAll();

        if (!empty($txs)) {
            $lines = "";
            foreach ($txs as $t) {
                $icon = $t['transaction_type'] === 'income' ? '🟢' : '🔴';
                $sign = $t['transaction_type'] === 'income' ? '+' : '-';
                $lines .= $icon . " **" . htmlspecialchars($t['description']) . "** (" . htmlspecialchars($t['category_name']) . "): " . $sign . format_currency($t['amount']) . " on " . format_date($t['transaction_date']) . "\n";
            }
            return "Here are your 5 most recent transactions:\n\n" . $lines;
        } else {
            return "You haven't recorded any transactions yet.";
        }
    }

    // 12. Expense Blocked Reason
    if (containsAny($q, ['blocked', 'why was my expense blocked', 'cannot add expense', 'budget limit'])) {
        return "ExpenseIQ enforces **Server-Side Budget Limits** in PHP:\n\n"
             . "When you record an expense, PHP checks if `Current Category Spending + New Expense` will exceed your allocated budget ceiling. If it does, the expense is **BLOCKED** and not inserted into MySQL to prevent overspending.\n\n"
             . "To resolve this, adjust your budget ceiling under **Budgets** or enter an expense amount within your remaining limit.";
    }

    return null;
}

/**
 * Handle Application Usage & Feature Questions
 */
function handleAppGuideIntent(string $qLower, string $qClean, string $userName): ?string {
    if (containsAny($qLower, ['how do i add an expense', 'add expense', 'record expense'])) {
        return "To record an expense in ExpenseIQ:\n\n"
             . "1. Click **'+ Add Expense'** on your Dashboard or open **Transactions**.\n"
             . "2. Choose an expense category (e.g., Food & Dining, Travel, Bills).\n"
             . "3. Enter the amount in ₹, date, and description.\n"
             . "4. Click **'Save Transaction'**. Server-side budget validation checks your budget ceiling before saving!";
    }

    if (containsAny($qLower, ['how do i add income', 'add income', 'record income'])) {
        return "To record income:\n\n"
             . "1. Click **'+ Add Income'** on your Dashboard or open **Transactions**.\n"
             . "2. Select an income category (e.g., Salary, Freelance, Other Income).\n"
             . "3. Enter the amount in ₹, date, and description.\n"
             . "4. Click **'Save Transaction'**. Your Net Available Balance will update immediately!";
    }

    if (containsAny($qLower, ['create a budget', 'add a budget', 'how do i create a budget', 'set budget'])) {
        return "To set up a budget:\n\n"
             . "1. Navigate to **Budgets** from the sidebar menu.\n"
             . "2. Click **'Set New Budget'**.\n"
             . "3. Select the expense category and set your monthly allocation limit in ₹.\n"
             . "4. Save the budget. ExpenseIQ will track your utilization in real-time!";
    }

    if (containsAny($qLower, ['edit a transaction', 'edit expense', 'edit income', 'modify transaction'])) {
        return "To edit a transaction:\n\n"
             . "1. Open the **Transactions** page.\n"
             . "2. Locate the transaction row you want to change.\n"
             . "3. Click the **Edit** icon (pencil) on the right.\n"
             . "4. Update the amount, category, or note, then click **'Update Transaction'**.";
    }

    if (containsAny($qLower, ['delete an expense', 'delete transaction', 'remove expense'])) {
        return "To delete a transaction:\n\n"
             . "1. Open the **Transactions** page.\n"
             . "2. Click the **Delete** (trash icon) button on the transaction row.\n"
             . "3. Confirm deletion in the popup dialog. Your balance and budget totals will recalculate automatically!";
    }

    if (containsAny($qLower, ['create a savings goal', 'add savings goal', 'savings target'])) {
        return "To create a savings goal:\n\n"
             . "1. Open **Savings Goals** from the sidebar menu.\n"
             . "2. Click **'Create Savings Goal'**.\n"
             . "3. Enter your goal name (e.g., Emergency Fund), target amount, and target date.\n"
             . "4. Add savings contributions whenever you deposit money toward your goal!";
    }

    if (containsAny($qLower, ['dashboard show', 'what does the dashboard show'])) {
        return "The ExpenseIQ **Dashboard** displays your real-time financial overview:\n\n"
             . "• **Net Available Balance, Total Income, Total Expenses, Total Savings**\n"
             . "• **Financial Health Score** & breakdown\n"
             . "• **Interactive Monthly Cash Flow & Category Spending Charts**\n"
             . "• **Budget Progress Bars & Smart AI Alerts**\n"
             . "• **Recent Ledger Transactions**";
    }

    if (containsAny($qLower, ['analytics page', 'what is the analytics page'])) {
        return "The **Analytics** page provides deep insights into your spending habits:\n\n"
             . "• Category-wise expense breakdown charts\n"
             . "• Month-over-month trend comparisons\n"
             . "• Average daily spending metrics\n"
             . "• Income vs. Expense ratio analysis";
    }

    if (containsAny($qLower, ['reports page', 'what is the reports page'])) {
        return "The **Reports** page allows you to generate and export financial summaries:\n\n"
             . "• Filter transactions by custom date range, type, or category\n"
             . "• Export complete financial reports for tax and personal auditing";
    }

    if (containsAny($qLower, ['ask me help', 'what is ask me help'])) {
        return "I am **Ask Me Help**, your personal AI financial assistant for ExpenseIQ!\n\n"
             . "I connect directly to your live database context to answer questions about your balance, expenses, budgets, savings goals, financial health score, and app features.";
    }

    if (containsAny($qLower, ['how do i use this application', 'how to use', 'use this app'])) {
        return "Welcome to **ExpenseIQ**! Here is how to get started:\n\n"
             . "1. **Track Income & Expenses:** Use **'+ Add Expense'** and **'+ Add Income'** buttons.\n"
             . "2. **Set Category Budgets:** Go to **Budgets** to assign monthly limits and prevent overspending.\n"
             . "3. **Set Savings Goals:** Track your targets on the **Savings Goals** page.\n"
             . "4. **Monitor Health & Analytics:** Review your **Financial Health Score** on the Dashboard and analyze spending on the **Analytics** page!\n"
             . "5. **Ask Me Help:** Type any question in this chat window anytime!";
    }

    return null;
}

/**
 * AI Generation with Live Context Injection & Intelligent Fallback
 */
function getAIGeneratedResponse(PDO $pdo, int $userId, string $userName, string $query, array $history): array {
    $apiKey = defined('GEMINI_API_KEY') ? constant('GEMINI_API_KEY') : '';

    // Fetch user context for AI prompt
    $summary = get_user_financial_summary($pdo, $userId);
    $health = calculate_financial_health_score($pdo, $userId);

    // Fetch top category spending
    $stmtTopCat = $pdo->prepare("
        SELECT c.category_name, SUM(t.amount) as total_spent
        FROM transactions t
        INNER JOIN categories c ON t.category_id = c.category_id
        WHERE t.user_id = ? AND t.transaction_type = 'expense'
          AND MONTH(t.transaction_date) = MONTH(CURRENT_DATE())
          AND YEAR(t.transaction_date) = YEAR(CURRENT_DATE())
        GROUP BY c.category_id, c.category_name
        ORDER BY total_spent DESC
    ");
    $stmtTopCat->execute([$userId]);
    $topCategories = $stmtTopCat->fetchAll();

    $userContextStr = "USER LIVE FINANCIAL CONTEXT:
- Name: {$userName}
- Total Income: " . format_currency($summary['total_income']) . "
- Total Expenses: " . format_currency($summary['total_expense']) . "
- Total Savings: " . format_currency($summary['total_savings']) . "
- Net Available Balance: " . format_currency($summary['net_balance']) . "
- Financial Health Score: " . $health['score'] . " / 100 (" . $health['label'] . ")";

    // 1. Try Gemini REST API if Key is present
    if (!empty($apiKey)) {
        $url = GEMINI_API_URL . '?key=' . urlencode($apiKey);

        $systemPrompt = "You are Ask Me Help, the real-time AI assistant for ExpenseIQ Smart Expense Tracker.
You help users understand personal finance, budgeting, savings, and ExpenseIQ application features naturally.
All currency values must be formatted in Indian Rupees (₹).
Net Balance Formula: Net Balance = Total Income − Total Expenses − Total Savings.

" . $userContextStr . "

RULES:
- Answer naturally in a warm, conversational, helpful tone.
- Keep responses concise with markdown bolding and bullet points.
- DO NOT answer technical implementation questions (PHP code, MySQL queries, server configuration, XAMPP, database connection code). If asked technical implementation questions, respond exactly: 'I\'m Ask Me Help, your ExpenseIQ user assistant. I can help you understand and use the ExpenseIQ application, including your expenses, income, budgets, savings, analytics and financial information.'";

        $contents = [
            ['role' => 'user', 'parts' => [['text' => "SYSTEM INSTRUCTION:\n" . $systemPrompt]]],
            ['role' => 'model', 'parts' => [['text' => "Understood. I am Ask Me Help, the AI assistant for ExpenseIQ. I will answer user questions naturally using their live financial context."]]]
        ];

        if (is_array($history)) {
            foreach (array_slice($history, -4) as $h) {
                $role = ($h['sender'] ?? '') === 'user' ? 'user' : 'model';
                $text = trim((string)($h['text'] ?? ''));
                if (!empty($text)) {
                    $contents[] = ['role' => $role, 'parts' => [['text' => $text]]];
                }
            }
        }

        $contents[] = ['role' => 'user', 'parts' => [['text' => $query]]];

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.4,
                'maxOutputTokens' => 600,
                'topP' => 0.9
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response !== false && $httpCode === 200) {
            $data = json_decode($response, true);
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                $replyText = trim($data['candidates'][0]['content']['parts'][0]['text']);
                if (!empty($replyText)) {
                    return ['reply' => $replyText, 'source' => 'gemini'];
                }
            }
        }
    }

    // 2. Intelligent PHP Local Fallback Generator for General Personal Finance Advice
    $qLower = strtolower($query);

    if (str_contains($qLower, 'why should i create a budget') || str_contains($qLower, 'why budget')) {
        return [
            'reply'  => "Creating a budget in ExpenseIQ helps you control your spending, avoid living paycheck to paycheck, and build savings faster.\n\n"
                      . "ExpenseIQ even enforces your category budgets server-side, preventing overspending before it happens!",
            'source' => 'intelligent_fallback'
        ];
    }

    if (str_contains($qLower, 'how can i save more') || str_contains($qLower, 'save more money') || str_contains($qLower, 'tips to save')) {
        $topCatName = !empty($topCategories) ? htmlspecialchars($topCategories[0]['category_name']) : "Food & Dining";
        $topCatAmt = !empty($topCategories) ? format_currency($topCategories[0]['total_spent']) : "₹0.00";

        return [
            'reply'  => "Here are 3 actionable ways to save more using ExpenseIQ:\n\n"
                      . "1. **Review your highest spending category:** Your top expense category this month is **" . $topCatName . "** (" . $topCatAmt . "). Try setting a budget limit for it.\n"
                      . "2. **Establish Category Budgets:** Assign realistic spending limits under **Budgets**.\n"
                      . "3. **Set Savings Goals:** Automate contributions toward specific goals right on your **Savings Goals** page!",
            'source' => 'intelligent_fallback'
        ];
    }

    if (str_contains($qLower, 'difference between income and expense') || str_contains($qLower, 'income vs expense')) {
        return [
            'reply'  => "• **Income:** Money flowing into your account (e.g., Salary, Freelance project, Investment dividends).\n"
                      . "• **Expense:** Money spent on goods or services (e.g., Groceries, Rent, Transportation, Entertainment).\n\n"
                      . "In ExpenseIQ, your **Net Available Balance** is calculated as: **Total Income − Total Expenses − Total Savings**.",
            'source' => 'intelligent_fallback'
        ];
    }

    if (str_contains($qLower, 'example of how this project works') || str_contains($qLower, 'how this project works')) {
        return [
            'reply'  => "ExpenseIQ operates as a complete personal finance ecosystem:\n\n"
                      . "1. You log your income and daily expenses.\n"
                      . "2. ExpenseIQ automatically updates your **Net Available Balance** and **Financial Health Score**.\n"
                      . "3. Category budgets validate your transactions to prevent overspending.\n"
                      . "4. Interactive charts on the Analytics page visualize your spending breakdown!",
            'source' => 'intelligent_fallback'
        ];
    }

    if (preg_match('/^(hi|hello|hey|greetings|good morning|good evening|namaste)/i', $qLower)) {
        return [
            'reply'  => "Hello {$userName}! 👋 I am **Ask Me Help**, your ExpenseIQ AI Assistant.\n\n"
                      . "Ask me anything about your balance, expenses, budgets, savings progress, or financial health!",
            'source' => 'intelligent_fallback'
        ];
    }

    // Default warm natural assistant response
    return [
        'reply'  => "I am **Ask Me Help**, your AI assistant for ExpenseIQ.\n\n"
                  . "I can assist you with:\n"
                  . "• *'How much did I spend this month?'*\n"
                  . "• *'How much did I spend on Food?'*\n"
                  . "• *'What is my balance?'*\n"
                  . "• *'What is my Food budget?'*\n"
                  . "• *'What is my financial health score?'*\n"
                  . "• *'How do I add an expense?'*",
        'source' => 'intelligent_fallback'
    ];
}
