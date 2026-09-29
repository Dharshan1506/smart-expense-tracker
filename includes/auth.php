<?php
/**
 * Authentication, Session Guards, Security & Helper Functions
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

/**
 * Check if the user is currently authenticated
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Enforce authentication on protected pages
 */
function require_login(): void {
    if (!is_logged_in()) {
        set_flash('warning', 'Please sign in to access your financial dashboard.');
        header('Location: login.php');
        exit;
    }
}

/**
 * Get current logged in user details
 */
function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'    => (int)$_SESSION['user_id'],
        'name'  => $_SESSION['user_name'] ?? 'User',
        'email' => $_SESSION['user_email'] ?? ''
    ];
}

/**
 * Set a session flash alert message (types: success, danger, warning, info)
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash_message'] = [
        'type'    => $type,
        'message' => $message
    ];
}

/**
 * Retrieve and clear the session flash message
 */
function get_flash(): ?array {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

/**
 * Generate CSRF Token for form security
 */
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token
 */
function verify_csrf_token(?string $token): bool {
    if (!$token || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize string inputs to prevent XSS
 */
function sanitize(string $data): string {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Reusable Indian Rupee (INR) formatting with Indian numbering system (Lakhs, Crores)
 * e.g. ₹500, ₹1,500, ₹18,500, ₹50,000, ₹1,00,000, ₹10,00,000, ₹1,00,00,000
 */
function formatINR(float|int|string $amount, bool $forceDecimals = true): string {
    $num = (float)$amount;
    $isNegative = $num < 0;
    $absNum = abs($num);
    $parts = explode('.', number_format($absNum, 2, '.', ''));
    $intPart = $parts[0];
    $decPart = $parts[1] ?? '00';

    if (strlen($intPart) > 3) {
        $lastThree = substr($intPart, -3);
        $remaining = substr($intPart, 0, -3);
        $chunks = [];
        while (strlen($remaining) > 2) {
            $chunks[] = substr($remaining, -2);
            $remaining = substr($remaining, 0, -2);
        }
        if (strlen($remaining) > 0) {
            $chunks[] = $remaining;
        }
        $formattedInt = implode(',', array_reverse($chunks)) . ',' . $lastThree;
    } else {
        $formattedInt = $intPart;
    }

    $res = ($isNegative ? '-' : '') . '₹' . $formattedInt;
    if ($forceDecimals || (int)$decPart > 0) {
        $res .= '.' . $decPart;
    }
    return $res;
}

/**
 * Standardize currency formatting using Indian Rupees (INR)
 */
function format_currency(float|int|string $amount, bool $forceDecimals = true): string {
    return formatINR($amount, $forceDecimals);
}

/**
 * Format dates cleanly (e.g. Sep 12, 2026)
 */
function format_date(string $date): string {
    $timestamp = strtotime($date);
    return $timestamp ? date('M d, Y', $timestamp) : $date;
}

/**
 * Relative time ago (e.g. "2 hours ago")
 */
function time_ago(string $datetime): string {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' mins ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M d, Y', $time);
}

/**
 * Generate and evaluate rule-based smart insights for the user
 */
function get_smart_insights(PDO $pdo, int $userId): array {
    $insights = [];

    // 1. Highest spending category this month
    $stmt = $pdo->prepare("
        SELECT c.category_name, SUM(t.amount) as total_spent
        FROM transactions t
        INNER JOIN categories c ON t.category_id = c.category_id
        WHERE t.user_id = ? AND t.transaction_type = 'expense'
          AND MONTH(t.transaction_date) = MONTH(CURRENT_DATE())
          AND YEAR(t.transaction_date) = YEAR(CURRENT_DATE())
        GROUP BY c.category_id, c.category_name
        ORDER BY total_spent DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $highestCat = $stmt->fetch();
    if ($highestCat && (float)$highestCat['total_spent'] > 0) {
        $insights[] = [
            'type' => 'info',
            'icon' => 'fa-fire',
            'title' => 'Highest Spending Category',
            'message' => 'Your highest spending category this month is <strong>' . htmlspecialchars($highestCat['category_name']) . '</strong> with <strong>' . format_currency($highestCat['total_spent']) . '</strong> spent.'
        ];
    }

    // 2. Budget threshold alerts (exceeded or > 80%)
    $stmt = $pdo->prepare("
        SELECT b.budget_amount, c.category_name, 
               COALESCE(SUM(t.amount), 0) AS total_spent
        FROM budgets b
        INNER JOIN categories c ON b.category_id = c.category_id
        LEFT JOIN transactions t ON t.category_id = b.category_id 
            AND t.user_id = b.user_id 
            AND t.transaction_type = 'expense'
            AND t.transaction_date BETWEEN b.start_date AND b.end_date
        WHERE b.user_id = ? 
          AND CURRENT_DATE() BETWEEN b.start_date AND b.end_date
        GROUP BY b.budget_id, b.budget_amount, c.category_name
    ");
    $stmt->execute([$userId]);
    $budgets = $stmt->fetchAll();

    foreach ($budgets as $b) {
        $spent = (float)$b['total_spent'];
        $budget = (float)$b['budget_amount'];
        if ($budget > 0) {
            $pct = round(($spent / $budget) * 100);
            if ($pct >= 100) {
                $insights[] = [
                    'type' => 'danger',
                    'icon' => 'fa-triangle-exclamation',
                    'title' => 'Budget Exceeded!',
                    'message' => 'You have exceeded your <strong>' . htmlspecialchars($b['category_name']) . '</strong> budget by ' . format_currency($spent - $budget) . ' (' . $pct . '% used).'
                ];
            } elseif ($pct >= 80) {
                $insights[] = [
                    'type' => 'warning',
                    'icon' => 'fa-bell',
                    'title' => 'Budget Warning',
                    'message' => 'Your <strong>' . htmlspecialchars($b['category_name']) . '</strong> budget is at ' . $pct . '% (' . format_currency($spent) . ' of ' . format_currency($budget) . ').'
                ];
            }
        }
    }

    // 3. Month-over-Month Expense comparison
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN MONTH(transaction_date) = MONTH(CURRENT_DATE()) AND YEAR(transaction_date) = YEAR(CURRENT_DATE()) THEN amount ELSE 0 END) as cur_month_exp,
            SUM(CASE WHEN MONTH(transaction_date) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) AND YEAR(transaction_date) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH) THEN amount ELSE 0 END) as prev_month_exp
        FROM transactions
        WHERE user_id = ? AND transaction_type = 'expense'
    ");
    $stmt->execute([$userId]);
    $comp = $stmt->fetch();

    $curExp = (float)($comp['cur_month_exp'] ?? 0);
    $prevExp = (float)($comp['prev_month_exp'] ?? 0);

    if ($prevExp > 0) {
        $delta = (($curExp - $prevExp) / $prevExp) * 100;
        if ($delta > 0) {
            $insights[] = [
                'type' => 'warning',
                'icon' => 'fa-chart-line-up',
                'title' => 'Expenses Increased',
                'message' => 'Your expenses increased by <strong>' . round($delta, 1) . '%</strong> compared to last month (' . format_currency($curExp) . ' vs ' . format_currency($prevExp) . ').'
            ];
        } else {
            $insights[] = [
                'type' => 'success',
                'icon' => 'fa-chart-line-down',
                'title' => 'Expenses Reduced!',
                'message' => 'Great job! Your expenses are <strong>' . abs(round($delta, 1)) . '% lower</strong> than last month.'
            ];
        }
    }

    // 4. Comparison with Average Monthly Expense
    $stmt = $pdo->prepare("
        SELECT AVG(monthly_total) as avg_monthly
        FROM (
            SELECT SUM(amount) as monthly_total
            FROM transactions
            WHERE user_id = ? AND transaction_type = 'expense'
            GROUP BY YEAR(transaction_date), MONTH(transaction_date)
        ) AS sub
    ");
    $stmt->execute([$userId]);
    $avgData = $stmt->fetch();
    $avgMonthly = (float)($avgData['avg_monthly'] ?? 0);

    if ($avgMonthly > 0 && $curExp > 0) {
        if ($curExp > $avgMonthly) {
            $insights[] = [
                'type' => 'warning',
                'icon' => 'fa-gauge-high',
                'title' => 'Above Average Spending',
                'message' => 'Current spending (' . format_currency($curExp) . ') is higher than your overall monthly average of <strong>' . format_currency($avgMonthly) . '</strong>.'
            ];
        } else {
            $insights[] = [
                'type' => 'success',
                'icon' => 'fa-shield-check',
                'title' => 'Disciplined Spending',
                'message' => 'Current spending (' . format_currency($curExp) . ') is well below your historical monthly average of <strong>' . format_currency($avgMonthly) . '</strong>.'
            ];
        }
    }

    // 5. Savings rate analysis for current month
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) as cur_income,
            SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) as cur_expense
        FROM transactions
        WHERE user_id = ? 
          AND MONTH(transaction_date) = MONTH(CURRENT_DATE())
          AND YEAR(transaction_date) = YEAR(CURRENT_DATE())
    ");
    $stmt->execute([$userId]);
    $cashflow = $stmt->fetch();
    $curIncome = (float)($cashflow['cur_income'] ?? 0);

    if ($curIncome > 0) {
        $saved = $curIncome - $curExp;
        $savingsRate = round(($saved / $curIncome) * 100);
        if ($savingsRate >= 20) {
            $insights[] = [
                'type' => 'success',
                'icon' => 'fa-piggy-bank',
                'title' => 'Healthy Savings Rate',
                'message' => 'Your savings rate this month is <strong>' . $savingsRate . '%</strong> (' . format_currency($saved) . ' saved out of ' . format_currency($curIncome) . ').'
            ];
        }
    }

    return $insights;
}

/**
 * Get aggregated financial summary for the currently logged-in user
 * Enforces correct Net Balance formula: Net Balance = Total Income - Total Expenses - Total Savings
 */
function get_user_financial_summary(PDO $pdo, int $userId): array {
    // 1. Total Income & Expenses
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0) AS total_income,
            COALESCE(SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END), 0) AS total_expense
        FROM transactions 
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $totals = $stmt->fetch();

    $totalIncome = (float)($totals['total_income'] ?? 0);
    $totalExpense = (float)($totals['total_expense'] ?? 0);

    // 2. Total Savings (Accumulated in Savings Goals)
    $stmtSavings = $pdo->prepare("SELECT COALESCE(SUM(saved_amount), 0) FROM savings_goals WHERE user_id = ?");
    $stmtSavings->execute([$userId]);
    $totalSavings = (float)($stmtSavings->fetchColumn() ?: 0.0);

    // 3. Net Balance = Income - Expense - Savings
    $netBalance = $totalIncome - $totalExpense - $totalSavings;

    // 4. Savings Rate
    $savingsRate = ($totalIncome > 0) ? max(0, round((($totalIncome - $totalExpense) / $totalIncome) * 100, 2)) : 0.0;

    return [
        'total_income'  => $totalIncome,
        'total_expense' => $totalExpense,
        'total_savings' => $totalSavings,
        'net_balance'   => $netBalance,
        'savings_rate'  => $savingsRate
    ];
}

/**
 * Dynamically calculate the Financial Health Score (0-100) based on actual database data
 * Transparent rule-based system across 5 weighted factors:
 * 1. Savings Rate (Max 30 pts)
 * 2. Expense-to-Income Ratio (Max 25 pts)
 * 3. Budget Utilization & Overruns (Max 20 pts)
 * 4. Net Balance Liquidity (Max 15 pts)
 * 5. Savings Goal Progress (Max 10 pts)
 */
function calculate_financial_health_score(PDO $pdo, int $userId): array {
    $summary = get_user_financial_summary($pdo, $userId);
    $income = $summary['total_income'];
    $expense = $summary['total_expense'];
    $savings = $summary['total_savings'];
    $netBalance = $summary['net_balance'];
    $savingsRate = $summary['savings_rate'];

    // Factor 1: Savings Rate (Max 30 pts)
    $savingsRatePts = 0;
    if ($savingsRate >= 30) $savingsRatePts = 30;
    elseif ($savingsRate >= 20) $savingsRatePts = 25;
    elseif ($savingsRate >= 10) $savingsRatePts = 18;
    elseif ($savingsRate > 0) $savingsRatePts = 10;
    else $savingsRatePts = 0;

    // Factor 2: Expense-to-Income Ratio (Max 25 pts)
    $expRatio = ($income > 0) ? ($expense / $income) * 100 : 100;
    $expRatioPts = 0;
    if ($income > 0) {
        if ($expRatio <= 50) $expRatioPts = 25;
        elseif ($expRatio <= 70) $expRatioPts = 20;
        elseif ($expRatio <= 85) $expRatioPts = 15;
        elseif ($expRatio <= 100) $expRatioPts = 5;
        else $expRatioPts = 0;
    } else {
        $expRatioPts = ($expense == 0) ? 15 : 0;
    }

    // Factor 3: Budget Utilization (Max 20 pts)
    $stmtBudgets = $pdo->prepare("
        SELECT b.budget_amount, COALESCE(SUM(t.amount), 0) as total_spent
        FROM budgets b
        LEFT JOIN transactions t ON t.category_id = b.category_id 
            AND t.user_id = b.user_id 
            AND t.transaction_type = 'expense'
            AND t.transaction_date BETWEEN b.start_date AND b.end_date
        WHERE b.user_id = ? AND CURRENT_DATE() BETWEEN b.start_date AND b.end_date
        GROUP BY b.budget_id, b.budget_amount
    ");
    $stmtBudgets->execute([$userId]);
    $userBudgets = $stmtBudgets->fetchAll();

    $budgetPts = 15; // default neutral if no active budgets
    if (!empty($userBudgets)) {
        $overBudgetCount = 0;
        $totalPctSum = 0;
        foreach ($userBudgets as $b) {
            $limit = (float)$b['budget_amount'];
            $spent = (float)$b['total_spent'];
            $pct = ($limit > 0) ? ($spent / $limit) * 100 : 0;
            $totalPctSum += $pct;
            if ($pct > 100) $overBudgetCount++;
        }
        $avgPct = $totalPctSum / count($userBudgets);
        if ($overBudgetCount == 0 && $avgPct <= 80) $budgetPts = 20;
        elseif ($overBudgetCount == 0 && $avgPct <= 95) $budgetPts = 16;
        elseif ($overBudgetCount == 0 && $avgPct <= 100) $budgetPts = 12;
        else $budgetPts = max(0, 10 - ($overBudgetCount * 5));
    }

    // Factor 4: Net Balance Liquidity (Max 15 pts)
    $liquidityPts = 0;
    if ($netBalance > 0) $liquidityPts = 15;
    elseif ($netBalance == 0) $liquidityPts = 5;
    else $liquidityPts = 0;

    // Factor 5: Savings Goal Progress (Max 10 pts)
    $stmtGoals = $pdo->prepare("SELECT target_amount, saved_amount FROM savings_goals WHERE user_id = ?");
    $stmtGoals->execute([$userId]);
    $goals = $stmtGoals->fetchAll();

    $goalPts = 5; // default if no active goals
    if (!empty($goals)) {
        $goalPctSum = 0;
        foreach ($goals as $g) {
            $target = (float)$g['target_amount'];
            $saved = (float)$g['saved_amount'];
            $pct = ($target > 0) ? min(100, ($saved / $target) * 100) : 0;
            $goalPctSum += $pct;
        }
        $avgGoalPct = $goalPctSum / count($goals);
        $goalPts = min(10, (int)round($avgGoalPct / 10));
    }

    $rawScore = $savingsRatePts + $expRatioPts + $budgetPts + $liquidityPts + $goalPts;
    $finalScore = max(0, min(100, (int)round($rawScore)));

    // Determine Interpretation Label
    if ($finalScore >= 80) $label = 'Excellent';
    elseif ($finalScore >= 60) $label = 'Good';
    elseif ($finalScore >= 40) $label = 'Moderate';
    elseif ($finalScore >= 20) $label = 'Needs Attention';
    else $label = 'Critical';

    return [
        'score'          => $finalScore,
        'label'          => $label,
        'savings_rate'   => $savingsRate,
        'expense_ratio'  => round($expRatio, 1),
        'net_balance'    => $netBalance,
        'total_income'   => $income,
        'total_expenses' => $expense,
        'total_savings'  => $savings
    ];
}

/**
 * Server-side budget validation function
 * Prevents transaction insertion/update if adding the expense would exceed the user's category budget
 */
function validate_expense_against_budget(
    PDO $pdo,
    int $userId,
    int $categoryId,
    float $newAmount,
    string $transactionDate,
    ?int $excludeTransactionId = null
): array {
    // 1. Fetch active budget for user, category and date
    $stmt = $pdo->prepare("
        SELECT b.budget_id, b.budget_amount, b.start_date, b.end_date, c.category_name
        FROM budgets b
        INNER JOIN categories c ON b.category_id = c.category_id
        WHERE b.user_id = ? AND b.category_id = ?
          AND ? BETWEEN b.start_date AND b.end_date
        LIMIT 1
    ");
    $stmt->execute([$userId, $categoryId, $transactionDate]);
    $budget = $stmt->fetch();

    if (!$budget) {
        // No budget configured for this category and timeframe
        return ['blocked' => false];
    }

    $budgetAmount = (float)$budget['budget_amount'];
    $categoryName = (string)$budget['category_name'];
    $startDate = (string)$budget['start_date'];
    $endDate = (string)$budget['end_date'];

    // 2. Calculate current total spent in this category for the budget period
    $querySpent = "
        SELECT COALESCE(SUM(amount), 0) as total_spent
        FROM transactions
        WHERE user_id = ? AND category_id = ? AND transaction_type = 'expense'
          AND transaction_date BETWEEN ? AND ?
    ";
    $params = [$userId, $categoryId, $startDate, $endDate];

    if ($excludeTransactionId !== null && $excludeTransactionId > 0) {
        $querySpent .= " AND transaction_id != ?";
        $params[] = $excludeTransactionId;
    }

    $stmtSpent = $pdo->prepare($querySpent);
    $stmtSpent->execute($params);
    $alreadySpent = (float)($stmtSpent->fetchColumn() ?: 0.0);

    $remaining = $budgetAmount - $alreadySpent;
    $newTotal = $alreadySpent + $newAmount;

    // 3. Check if new total exceeds budget limit
    if ($newTotal > $budgetAmount) {
        $maxSpend = max(0, $remaining);
        $blockMessage = "⚠️ Expense Blocked\n"
            . "Your " . $categoryName . " budget is " . format_currency($budgetAmount) . ".\n"
            . "You have already spent " . format_currency($alreadySpent) . ".\n"
            . "You can spend only " . format_currency($maxSpend) . " more.\n"
            . "This " . format_currency($newAmount) . " expense cannot be added because it exceeds your budget.";

        return [
            'blocked'          => true,
            'message'          => $blockMessage,
            'category_name'    => $categoryName,
            'budget_amount'    => $budgetAmount,
            'already_spent'    => $alreadySpent,
            'remaining'        => $maxSpend,
            'attempted_amount' => $newAmount
        ];
    }

    // 4. Budget not exceeded, check for warning thresholds
    $pct = ($budgetAmount > 0) ? round(($newTotal / $budgetAmount) * 100) : 0;
    $remAfter = max(0, $budgetAmount - $newTotal);
    $warningMsg = null;

    if ($newTotal == $budgetAmount) {
        $warningMsg = "🚨 " . $categoryName . " budget fully used.";
    } elseif ($pct >= 80) {
        $warningMsg = "⚠️ You have used " . $pct . "% of your " . $categoryName . " budget. Only " . format_currency($remAfter) . " remaining.";
    }

    return [
        'blocked'       => false,
        'warning'       => $warningMsg,
        'category_name' => $categoryName,
        'budget_amount' => $budgetAmount,
        'already_spent' => $alreadySpent,
        'new_total'     => $newTotal,
        'utilization'   => $pct
    ];
}

