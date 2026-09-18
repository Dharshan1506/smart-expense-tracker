# Smart Expense Tracker - DBMS College Project

A professional, fintech-style personal finance web application built for a **DBMS College Project** using **PHP 8+**, **MySQL**, **HTML5**, **CSS3**, and **JavaScript** (Chart.js), designed specifically for the **XAMPP** environment.

---

## Technology Stack

- **Frontend**: HTML5, Modern CSS3 (Fintech Dashboard Theme), Vanilla JavaScript, FontAwesome 6, Chart.js 4.4
- **Backend**: PHP 8+ (PDO with Parameterized Prepared Statements)
- **Database**: MySQL (phpMyAdmin)
- **Web Server**: Apache (XAMPP)

---

## Key Features

1. **User Authentication**:
   - Registration with password hashing (`password_hash` with BCRYPT).
   - Secure sign-in with `password_verify()` and session fixation protection.
   - CSRF protection on all forms.
   - Session access guards preventing unauthorized dashboard access.

2. **Fintech Dashboard**:
   - Financial KPI cards: Total Income, Total Expenses, Current Balance, Total Savings, Monthly Expenses.
   - Rule-based Smart Spending Insights banner.
   - Interactive Chart.js graphs: Income vs. Expense and Category Spending Doughnut.
   - Real-time budget progress indicators with warning alerts.
   - Recent transactions ledger with category badges.

3. **Transaction Management**:
   - Record Income and Expense entries with dynamic category selection.
   - Search transactions by description.
   - Multi-criteria filtering by category, transaction type, and date range.
   - Multi-column sorting (Date Newest/Oldest, Amount Highest/Lowest).
   - Edit and delete transactions with referential integrity.

4. **Categories**:
   - 11 pre-seeded default categories: Food & Dining, Travel & Transport, Shopping, Bills & Utilities, Education, Entertainment, Healthcare, Other Expense, Salary, Freelance, Other Income.
   - Support for custom user-created categories with custom icons and color pickers.
   - Usage statistics tracking transaction counts and total volume per category.

5. **Budget Monitoring**:
   - Establish periodic category spending caps with start and end dates.
   - Real-time spent vs. remaining budget calculations.
   - Threshold warnings when spending reaches $\ge 80\%$ or exceeds $100\%$.

6. **Savings Goals Tracker**:
   - Define milestone targets (e.g., Emergency Fund, Vacation, Gadgets).
   - Deposit and contribute funds with visual progress tracking.

7. **Spending Analytics**:
   - Daily spending velocity (last 14 days line/area chart).
   - 12-month historical cashflow bar charts.
   - Category portfolio distribution table with visual percentage indicators.
   - Identification of highest spending category and monthly average expense.

8. **Reports & Exports**:
   - Filterable statements: Monthly Expense, Category Breakdown, Income, Budget Variance, and Savings Goals.
   - Printable formatted views (`window.print()`).
   - One-click CSV spreadsheet export.

9. **DBMS SQL Query Demonstration (`queries.php`)**:
   - Dedicated academic showcase displaying **20 essential SQL queries** matching college syllabus requirements:
     1. `Basic SELECT`
     2. `INSERT`
     3. `UPDATE`
     4. `DELETE`
     5. `WHERE`
     6. `ORDER BY`
     7. `GROUP BY`
     8. `HAVING`
     9. `SUM`
     10. `AVG`
     11. `COUNT`
     12. `MAX`
     13. `MIN`
     14. `INNER JOIN`
     15. `LEFT JOIN`
     16. `Multiple-table JOIN`
     17. `Subquery`
     18. `Monthly expense analysis`
     19. `Category-wise expense analysis`
     20. `Budget exceeded analysis`
   - Each query displays: **Query Title**, **SQL Code**, **Purpose & Rationale**, **Tables Involved**, and **Live / Example Result**.
   - Interactive on-page **Normalization Guide** proving **1NF, 2NF, and 3NF** compliance, B-tree indexes, views, and `DECIMAL(10,2)` monetary precision.

---

## Directory Structure

```
smart-expense-tracker/
│
├── index.php                 # Landing page & redirection
├── login.php                 # Secure login with demo prefill
├── register.php              # Account registration
├── logout.php                # Session destruction
├── dashboard.php             # Fintech dashboard
├── transactions.php          # Transactions ledger with search & filters
├── add_transaction.php       # Record income/expense
├── edit_transaction.php      # Edit existing transaction
├── delete_transaction.php    # Secure POST-based deletion
├── categories.php            # Category management & usage
├── budgets.php               # Budget limit monitor & warnings
├── savings.php               # Savings goals & contribution tracker
├── analytics.php             # Daily, monthly & trend charts
├── reports.php               # Reports generator & CSV export
├── queries.php               # DBMS SQL Query demonstration page
├── profile.php               # User settings & password change
│
├── config/
│   └── database.php          # PDO connection & error handling
│
├── includes/
│   ├── auth.php              # Auth guards, CSRF, flash messages & insights engine
│   ├── header.php            # HTML head & stylesheets
│   ├── sidebar.php           # Collapsible sidebar navigation
│   ├── navbar.php            # Top header bar with quick balance
│   └── footer.php            # Scripts & Chart.js CDN
│
├── api/
│   ├── dashboard_data.php    # JSON endpoint for dashboard
│   ├── analytics_data.php    # JSON endpoint for trends
│   └── transaction_data.php  # JSON endpoint for transactions
│
├── assets/
│   ├── css/
│   │   └── style.css         # Fintech styles & responsive layout
│   └── js/
│       ├── dashboard.js      # Dashboard charts
│       ├── analytics.js      # Analytics charts
│       └── validation.js     # Form validation
│
└── database/
    └── expense_tracker.sql   # Normalized schema, constraints & seed data
```

---

## Installation & Setup Guide (XAMPP)

### Step 1: Start XAMPP Services
1. Launch the **XAMPP Control Panel**.
2. Click **Start** next to **Apache**.
3. Click **Start** next to **MySQL**.

### Step 2: Import the Database in phpMyAdmin
1. Open your web browser and go to:
   ```
   http://localhost/phpmyadmin/
   ```
2. In the top navigation bar, click on **Import**.
3. Click **Choose File** / **Browse** and select:
   ```
   C:\xampp\htdocs\smart-expense-tracker\database\expense_tracker.sql
   ```
4. Scroll down and click **Import** (or **Go**).
5. The database `expense_tracker` and all 6 relational tables with sample records will be created automatically.

### Step 3: Verify Database Configuration
The database settings in `config/database.php` are pre-configured for default XAMPP credentials:
```php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'expense_tracker');
define('DB_USER', 'root');
define('DB_PASS', '');
```
If your MySQL has a password, update `DB_PASS` accordingly.

### Step 4: Open the Application
Navigate to the application in your browser:
```
http://localhost/smart-expense-tracker/
```

---

## Demo Credentials

A demo account is pre-seeded with rich sample transactions, budgets, and savings goals for immediate evaluation:

- **Email**: `demo@example.com`
- **Password**: `password123`

*(On the login screen, you can also click the **"Fill Demo"** button to automatically populate these credentials).*
