# Smart Expense Tracker: Complete Project Walkthrough & DBMS Master Report

**Course Project**: Relational Database Management Systems (DBMS)  
**Implementation**: PHP 8 (Native PDO) + MySQL 8.0 (InnoDB) on Apache (XAMPP)  
**Frontend Architecture**: Modern Fintech Responsive UI • Chart.js 4.4 • Vanilla CSS  
**Database Normalization**: Third Normal Form (3NF Satisfied)  
**Security Model**: 100% Prepared Statements (SQLi Immunity), Bcrypt Hashing, CSRF Tokens  

---

## 1. Executive Summary

The **Smart Expense Tracker** is a comprehensive personal finance web application built to fulfill college DBMS academic requirements while offering a clean, professional fintech user interface.

The application allows users to:
1. Securely register, authenticate, and manage their personal profile.
2. Record inbound cashflows (income) and outbound expenditures (expenses) across classified taxonomy categories.
3. Establish periodic budget thresholds and automatically monitor overruns via threshold warnings.
4. Set milestone savings targets and deposit funds toward them.
5. Generate dynamic financial statements and export reports to CSV.
6. Explore a live interactive catalog of **20 essential relational SQL queries**.

---

## 2. Technology Stack & Tool Selection Rationale

| Tool / Technology | What It Does | Why Specifically Selected Over Alternatives |
|---|---|---|
| **PHP 8 (Strict Types)** | Core backend engine and HTML compiler | Native execution on Apache without complex build setups or daemon runtimes (unlike Node.js or Spring Boot). Enables rapid server-side rendering and strict typing via `declare(strict_types=1)`. |
| **MySQL 8 (InnoDB)** | Relational Database Management System | Full ACID compliance, strict foreign key referential integrity (`ON DELETE CASCADE` / `RESTRICT`), B-tree indexes, and virtual views (`v_budget_status`, `v_category_spending`). |
| **PHP Data Objects (PDO)** | Database abstraction and query execution | Configured with `PDO::ATTR_EMULATE_PREPARES => false`. This forces native prepared statement compilation in MySQL, eliminating SQL injection at the protocol level. |
| **Bcrypt (`PASSWORD_BCRYPT`)** | One-way password hashing | Adaptive work factor with cryptographic salting prevents brute-force, dictionary, and rainbow-table attacks (unlike legacy MD5/SHA1). |
| **Chart.js 4.4 (CDN)** | Client-side visual charting engine | Lightweight HTML5 canvas library that renders smooth area line charts, grouped cashflow bars, and category doughnut charts with zero server overhead. |
| **FontAwesome 6.5.1** | Vector icon taxonomy | Standardized financial iconography (wallets, cards, badges) without static image assets. |
| **Vanilla CSS (Design Tokens)** | Design system and layout engine (`style.css`) | Avoids bulky CSS frameworks (Bootstrap/Tailwind) that add dependencies or require node-sass/postcss compilers. Custom properties provide a high-end Stripe/Mercury aesthetic. |
| **XAMPP (Apache + MySQL)** | Local execution stack on Windows | Industry-standard university DBMS evaluation environment. Easily deployed across any machine with Apache and phpMyAdmin. |

---

## 3. End-to-End Request-Response Lifecycle

```
[Browser Client]
       │
       ▼ (1. HTTP Request GET/POST)
[Apache Web Server :80]
       │
       ▼ (2. Dispatches to target .php file)
[PHP 8 Execution Engine]
       │
       ├─► (Session Gate: session_start() -> check $_SESSION['user_id'])
       │     └─► If unauthenticated, redirect 302 to login.php
       │
       ├─► (CSRF Validation: verify_csrf_token() on POST forms)
       │
       ├─► (Database Connection: getDBConnection() in config/database.php)
       │     │
       │     ▼ (3. Native Prepared Statements via PDO)
       │   [MySQL 8 Relational Engine :3306]
       │     │
       │     ▼ (4. Relational Result Sets returned to PHP)
       │
       └─► (5. PHP formats currencies/dates & injects data into DOM + window.Data)
       │
       ▼ (6. HTTP 200 Response)
[Browser Client: HTML + CSS + Chart.js hydration]
```

---

## 4. Complete Walkthrough of All 25 PHP Files

### Module A: Core Configuration & Security Guards

1. **`config/database.php`**:
   * Singleton PDO connection to MySQL (`expense_tracker`).
   * Configures `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, and `EMULATE_PREPARES => false`.
   * Includes a styled troubleshooting page with recovery instructions if MySQL is offline.

2. **`includes/auth.php`**:
   * Central security hub.
   * Provides `require_login()`, `is_logged_in()`, and `current_user()`.
   * Manages CSRF tokens (`generate_csrf_token()`, `verify_csrf_token()`).
   * Provides formatters: `format_currency()`, `format_date()`, `time_ago()`.
   * Houses `get_smart_insights()`, which runs 5 SQL queries to evaluate monthly trends, budget overruns, and savings ratios.

3. **`includes/header.php`**:
   * HTML `<head>` template with meta tags, *Plus Jakarta Sans* font, FontAwesome, and `assets/css/style.css`.

4. **`includes/navbar.php`**:
   * Sticky top navigation bar.
   * Calculates live user net balance (`Income - Expense`) and provides the universal `+ Add Transaction` modal trigger.

5. **`includes/sidebar.php`**:
   * Responsive navigation sidebar and mobile slide-out drawer with active page indicators and sign-out confirmation modal.

6. **`includes/footer.php`**:
   * Global footer containing the universal **Quick Add Transaction Modal** and the custom **Confirmation Dialog Modal** (`#confirmDialogModal`).
   * Loads Chart.js 4.4 and manages drawer backdrop dismissals and auto-dismissing flash alerts.

---

### Module B: Entry, Authentication & User Lifecycle

7. **`index.php`**:
   * Public entry router. Redirects logged-in users to `dashboard.php`; displays feature presentation to guests.

8. **`login.php`**:
   * Authenticates users via `password_verify()`.
   * Regenerates session IDs against fixation attacks (`session_regenerate_id(true)`).
   * Includes a 1-click **Demo Account Fill** button (`demo@example.com` / `password123`).

9. **`register.php`**:
   * Handles user registration with email uniqueness checks, input bounds validation, Bcrypt password hashing, and welcome alert seeding.

10. **`logout.php`**:
    * Destroys user session, deletes session cookies, and redirects to `login.php`.

---

### Module C: Core Financial Ledger

11. **`dashboard.php`**:
    * 3-tier financial dashboard:
      * **Top**: Total Income, Total Expenses, Net Balance, Total Savings.
      * **Middle**: 6-month monthly expense velocity area chart and income vs. expense grouped bar chart.
      * **Bottom**: Category doughnut chart, 6 most recent transactions, budget threshold progress bars, and smart insights cards.

12. **`transactions.php`**:
    * Full financial ledger with search keyword filtering, category dropdown, transaction type toggle, date range selection, and sorting.

13. **`add_transaction.php`**:
    * Handles adding income and expenses. If an expense is added, automatically checks active budgets and generates an alert if exceeded.

14. **`edit_transaction.php`**:
    * Modifies existing ledger entries. Checks `transaction_id = ? AND user_id = ?` to prevent Insecure Direct Object References (IDOR).

15. **`delete_transaction.php`**:
    * Deletes transactions via POST and CSRF validation with user ownership checks.

---

### Module D: Budgets, Categories, Savings & Reports

16. **`budgets.php`**:
    * Creates and monitors monthly budget limits. Uses `LEFT JOIN` on transactions to compute percentage utilization and applies color coding (Green <80%, Amber ≥80%, Red ≥100%).

17. **`categories.php`**:
    * Manages system default categories (`user_id IS NULL`) and user-custom categories. Displays total volume and transaction count per category.

18. **`savings.php`**:
    * Tracks milestone financial targets. Computes progress percentages and supports atomic fund deposits via SQL addition.

19. **`analytics.php`**:
    * Analyzes spending velocity over 14 days, computes average monthly expenditure using nested subqueries, and shows category allocations.

20. **`reports.php`**:
    * Statement generator for Monthly, Category, Income, Budget, and Savings statements with one-click CSV export and print view.

21. **`queries.php`**:
    * Academic DBMS demonstration catalog of all 20 SQL queries with 1-click "Copy SQL", live database outputs, and normalization documentation.

22. **`profile.php`**:
    * Displays account telemetry (User ID, Account Age, Total Transactions, Balance) and handles name and password updates.

---

### Module E: Asynchronous REST API Endpoints

23. **`api/dashboard_data.php`**:
    * Returns live financial totals and category arrays in JSON format for authenticated users (401 guard).

24. **`api/analytics_data.php`**:
    * Returns 12-month aggregated cash flow arrays in JSON format for charting engines.

25. **`api/transaction_data.php`**:
    * Returns filtered transaction objects bounded by `$limit = max(1, min((int)..., 100))`.

---

## 5. Relational Database Design & 3NF Normalization Proof

The MySQL schema (`database/expense_tracker.sql`) satisfies **Third Normal Form (3NF)**:

* **1NF (Atomic Attributes & Unique Keys)**:
  Every column holds scalar values. Money is stored as `DECIMAL(10,2)`. Every table has an explicit surrogate primary key (`user_id`, `transaction_id`, `category_id`, `budget_id`, `goal_id`, `alert_id`).
* **2NF (No Partial Dependency)**:
  All tables use single-attribute primary keys. Non-key attributes are fully functionally dependent on the entire primary key.
* **3NF (No Transitive Dependency)**:
  `transactions` references `category_id` (foreign key) but does not store `category_name` or `icon`. These attributes are resolved via `JOIN`, eliminating data redundancy and update anomalies.

### Relational Views

1. **`v_monthly_financial_summary`**: Aggregates income, expense, and net savings per user per month.
2. **`v_budget_status`**: Calculates budget utilization percentage and status (`ON_TRACK`, `WARNING`, `EXCEEDED`).
3. **`v_category_spending`**: Computes category-wise counts, sums, averages, and extremes.

---

## 6. Catalog of 20 Relational DBMS Queries

| # | DBMS Concept | SQL Functionality | Application Location |
|---|---|---|---|
| 1 | Basic SELECT | Attribute projection | `profile.php` |
| 2 | INSERT | Tuple insertion with FK constraints | `add_transaction.php` |
| 3 | UPDATE | Mutation with user ID predicate | `edit_transaction.php` |
| 4 | DELETE | Referential tuple removal | `delete_transaction.php` |
| 5 | WHERE Clause | 30-day date boundary filtering | `transactions.php` |
| 6 | ORDER BY | Multi-attribute sorting | `transactions.php` |
| 7 | GROUP BY | Aggregation by transaction type | `dashboard.php` |
| 8 | HAVING Clause | Group filter on `SUM(amount) > 100` | `queries.php` |
| 9 | SUM Function | Lifetime income/expense summation | `dashboard.php` |
| 10 | AVG Function | Average expense per transaction | `queries.php` |
| 11 | COUNT Function | Transaction frequency counting | `categories.php` |
| 12 | MAX Function | Highest single transaction | `queries.php` |
| 13 | MIN Function | Smallest single transaction | `queries.php` |
| 14 | INNER JOIN | Join transactions with categories | `transactions.php` |
| 15 | LEFT JOIN | Join categories including unused ones | `categories.php` |
| 16 | Multi-Table JOIN | Join transactions, users, and categories | `queries.php` |
| 17 | Subquery | Correlated subquery for above-average spending | `queries.php` |
| 18 | Monthly Trend | Group by Year-Month (`DATE_FORMAT`) | `analytics.php` |
| 19 | Category Share | Subquery calculation of portfolio percentage | `analytics.php` |
| 20 | Budget Exceeded | Overrun calculation with LEFT JOIN | `budgets.php` |

---

## 7. XAMPP Setup & Testing Quick Reference

1. **Start Apache & MySQL** in XAMPP Control Panel.
2. **Import Database**:
   * Open `http://localhost/phpmyadmin/`.
   * Click **Import** → Choose `database/expense_tracker.sql` → Click **Go**.
3. **Open Application**:
   * URL: `http://localhost/smart-expense-tracker/`
4. **Sign In**:
   * Email: `demo@example.com`
   * Password: `password123`
   * *(Or click "Demo Account Fill" on the login screen)*
