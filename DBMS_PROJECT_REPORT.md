# Smart Expense Tracker - DBMS Academic Project Report

**Project Title:** Smart Expense Tracker (Personal Finance & Ledger Management System)  
**Database Management System:** MySQL 8.0+ / MariaDB (InnoDB Storage Engine)  
**Backend Framework:** PHP 8+ (PDO Prepared Statements)  
**Target Architecture:** Normalized Relational Database System (RDBMS)

---

## 1. System Overview & Relational Architecture

The **Smart Expense Tracker** is a secure, normalized, multi-user relational database system engineered to model, record, classify, and analyze personal financial cash flows. The database is built on the **InnoDB** storage engine to guarantee full **ACID** (Atomicity, Consistency, Isolation, Durability) properties, strict foreign key enforcement, transaction boundaries, and row-level locking.

```
+-----------------------------------------------------------------------------------+
|                               RELATIONAL ENTITY MAP                               |
+-----------------------------------------------------------------------------------+
|                                                                                   |
|    +-------------------+                  1:N                 +-----------------+ |
|    |       users       |------------------------------------->|  savings_goals  | |
|    +-------------------+                                      +-----------------+ |
|       | 1           | 1                                                           |
|       |             |                                                             |
|       | 1:N         | 1:N                                     +-----------------+ |
|       |             +---------------------------------------->|     alerts      | |
|       v                                                       +-----------------+ |
|    +-------------------+       1:N        +-----------------+                     |
|    |    categories     |----------------->|     budgets     |                     |
|    +-------------------+                  +-----------------+                     |
|       | 1                                          ^ 1                            |
|       |                                            |                              |
|       | 1:N                                        | N                            |
|       v                                            |                              |
|    +-----------------------------------------------+                              |
|    |                               transactions                                   |
|    +------------------------------------------------------------------------------+
+-----------------------------------------------------------------------------------+
```

---

## 2. Relational Schema & Table Specifications

### 2.1 Table: `users`
Represents registered system users and authentication credentials.
* **Primary Key:** `user_id` (INT UNSIGNED, Auto-Increment)
* **Candidate Key:** `email` (VARCHAR 150, UNIQUE)
* **Attributes:**
  - `user_id`: Unique surrogate identifier.
  - `name`: Full name (VARCHAR(100), NOT NULL).
  - `email`: User electronic mail address (VARCHAR(150), NOT NULL, UNIQUE).
  - `password`: Secure BCRYPT password hash (VARCHAR(255), NOT NULL).
  - `created_at`: Row creation timestamp (TIMESTAMP, NOT NULL, DEFAULT CURRENT_TIMESTAMP).
  - `updated_at`: Last modification timestamp (TIMESTAMP, NOT NULL, DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP).

### 2.2 Table: `categories`
Hierarchical taxonomy for categorizing financial income and expenses. Supports both system-wide default categories (`user_id IS NULL`) and user-defined custom categories.
* **Primary Key:** `category_id` (INT UNSIGNED, Auto-Increment)
* **Foreign Key:** `user_id` $\rightarrow$ `users(user_id)` ON DELETE CASCADE ON UPDATE CASCADE
* **Composite Candidate Key:** `uq_user_category(user_id, category_name, category_type)`
* **Attributes:**
  - `category_id`: Unique surrogate identifier.
  - `user_id`: Owning user ID (NULL for global default categories).
  - `category_name`: Human-readable classification title (VARCHAR(80), NOT NULL).
  - `category_type`: Classification domain (`income` or `expense`, ENUM, NOT NULL).
  - `icon`: FontAwesome glyph representation (VARCHAR(50), NOT NULL, DEFAULT 'fa-tag').
  - `color`: Hexadecimal color code (VARCHAR(20), NOT NULL, DEFAULT '#4f46e5').
  - `created_at` / `updated_at`: Audit timestamps.

### 2.3 Table: `transactions`
The core transactional ledger (Fact table) capturing individual monetary flows.
* **Primary Key:** `transaction_id` (INT UNSIGNED, Auto-Increment)
* **Foreign Keys:**
  - `user_id` $\rightarrow$ `users(user_id)` ON DELETE CASCADE ON UPDATE CASCADE
  - `category_id` $\rightarrow$ `categories(category_id)` ON DELETE RESTRICT ON UPDATE CASCADE
* **Attributes:**
  - `transaction_id`: Unique surrogate identifier.
  - `user_id`: Reference to the user who executed the transaction.
  - `category_id`: Reference to the assigned taxonomy category.
  - `amount`: Exact monetary amount (**DECIMAL(10,2)**, NOT NULL).
  - `transaction_type`: `income` or `expense` (ENUM, NOT NULL).
  - `description`: Narrative description (VARCHAR(255), NOT NULL).
  - `transaction_date`: Effective transaction date (DATE, NOT NULL).
  - `created_at` / `updated_at`: Audit timestamps.

### 2.4 Table: `budgets`
Allocated expenditure limits for specific categories across defined date ranges.
* **Primary Key:** `budget_id` (INT UNSIGNED, Auto-Increment)
* **Foreign Keys:**
  - `user_id` $\rightarrow$ `users(user_id)` ON DELETE CASCADE ON UPDATE CASCADE
  - `category_id` $\rightarrow$ `categories(category_id)` ON DELETE CASCADE ON UPDATE CASCADE
* **Composite Candidate Key:** `uq_user_category_period(user_id, category_id, start_date, end_date)`
* **Attributes:**
  - `budget_id`: Unique surrogate identifier.
  - `user_id`: Tenant reference.
  - `category_id`: Target expense category reference.
  - `budget_amount`: Budget ceiling (**DECIMAL(10,2)**, NOT NULL).
  - `start_date` / `end_date`: Time window for budget enforcement (DATE, NOT NULL).
  - `created_at` / `updated_at`: Audit timestamps.

### 2.5 Table: `savings_goals`
Milestone-driven savings accumulations.
* **Primary Key:** `goal_id` (INT UNSIGNED, Auto-Increment)
* **Foreign Key:** `user_id` $\rightarrow$ `users(user_id)` ON DELETE CASCADE ON UPDATE CASCADE
* **Attributes:**
  - `goal_id`: Unique surrogate identifier.
  - `user_id`: Tenant reference.
  - `goal_name`: Milestone title (VARCHAR(120), NOT NULL).
  - `target_amount`: Target milestone goal (**DECIMAL(10,2)**, NOT NULL).
  - `saved_amount`: Currently accumulated deposit (**DECIMAL(10,2)**, NOT NULL, DEFAULT 0.00).
  - `target_date`: Target target completion deadline (DATE, NOT NULL).
  - `created_at` / `updated_at`: Audit timestamps.

### 2.6 Table: `alerts`
System-generated financial intelligence events and budget overrun notifications.
* **Primary Key:** `alert_id` (INT UNSIGNED, Auto-Increment)
* **Foreign Key:** `user_id` $\rightarrow$ `users(user_id)` ON DELETE CASCADE ON UPDATE CASCADE
* **Attributes:**
  - `alert_id`: Unique surrogate identifier.
  - `user_id`: Recipient user ID.
  - `message`: Textual notification details (TEXT, NOT NULL).
  - `alert_type`: Classification (`warning`, `danger`, `info`, `success`, ENUM, NOT NULL).
  - `is_read`: Boolean flag (TINYINT(1), NOT NULL, DEFAULT 0).
  - `created_at`: Creation timestamp.

---

## 3. Relational Normalization Proof (1NF, 2NF, 3NF, BCNF)

### 3.1 First Normal Form (1NF)
A relation $R$ is in 1NF if and only if:
1. Every attribute domain contains only atomic (indivisible) scalar values.
2. There are no repeating groups, comma-separated lists, or nested arrays.
3. Each record possesses a well-defined primary key.

* **Proof for Smart Expense Tracker:**
  - In `transactions`, columns such as `amount`, `transaction_date`, and `transaction_type` contain singular atomic values. Dates are formatted as ISO-8601 SQL dates, and categories are linked via scalar foreign key integers.
  - In `categories`, icon and color are stored as distinct scalar strings, not composite or serialized structures.
  - Every table has an unambiguous primary key (`user_id`, `category_id`, `transaction_id`, etc.).
  - **Conclusion:** The database strictly satisfies **1NF**.

### 3.2 Second Normal Form (2NF)
A relation $R$ is in 2NF if and only if:
1. $R$ is in 1NF.
2. Every non-prime attribute is fully functionally dependent on the primary key (no partial dependencies on any candidate key).

* **Proof for Smart Expense Tracker:**
  - A partial dependency can only arise when a primary key is composite ($K = \{A, B\}$ and $A \rightarrow C$ where $C$ is non-prime).
  - In our schema, **all relations utilize single-attribute surrogate primary keys** (`user_id`, `category_id`, `transaction_id`, `budget_id`, `goal_id`, `alert_id`).
  - Because no primary key consists of multiple attributes, partial functional dependency is mathematically impossible.
  - **Conclusion:** The database strictly satisfies **2NF**.

### 3.3 Third Normal Form (3NF)
A relation $R$ is in 3NF if and only if:
1. $R$ is in 2NF.
2. There are no transitive functional dependencies ($X \rightarrow Y$ where $Y$ is a non-prime attribute and $X$ is not a superkey).

* **Proof for Smart Expense Tracker:**
  - In `transactions`, we store `category_id` (foreign key) rather than denormalizing `category_name`, `category_type`, or `color`.
    - If `category_name` were stored directly in `transactions`, we would have:  
      $\text{transaction\_id} \rightarrow \text{category\_id}$ and $\text{category\_id} \rightarrow \text{category\_name}$, creating a transitive dependency.
    - By decoupling `categories` into its own relation, attributes of a category depend solely on `category_id`.
  - Similarly, `transactions` stores only `user_id`, referencing `users`, preventing user email or password hashes from being transitively dependent on `transaction_id`.
  - In `budgets`, spending calculations are computed dynamically via SQL joins rather than storing redundant running totals that could become inconsistent.
  - **Conclusion:** The database strictly satisfies **3NF (and Boyce-Codd Normal Form - BCNF)**.

---

## 4. Integrity Constraints, Data Types & Indexing Strategy

### 4.1 Monetary Precision: DECIMAL(10,2) vs FLOAT
In computer systems, `FLOAT` and `DOUBLE` adhere to the **IEEE-754 binary floating-point** standard, representing fractions as binary approximations (e.g. $0.1_{10} = 0.000110011..._2$). Over repetitive summing operations, this produces precision loss and catastrophic rounding errors (e.g. `₹0.01` discrepancy in bank balances).
* The Smart Expense Tracker strictly implements **`DECIMAL(10, 2)`** across all monetary columns (`amount`, `budget_amount`, `target_amount`, `saved_amount`), storing currency as exact base-10 fixed-point numerical values.

### 4.2 Referential Integrity & Cascade Semantics
1. **User Deletion (`ON DELETE CASCADE`):** If a user deletes their account, all associated child records (`transactions`, `budgets`, `savings_goals`, `alerts`, custom `categories`) are automatically cascaded and expunged to prevent dangling records.
2. **Category Protection (`ON DELETE RESTRICT`):** The foreign key `fk_transactions_category` enforces `ON DELETE RESTRICT`. A category cannot be deleted if transactions reference it, preserving historical ledger integrity and preventing audit trail destruction.

### 4.3 Strategic B-Tree Indexes
| Index Identifier | Target Relation | Indexed Attributes | Query Optimization Justification |
|---|---|---|---|
| `idx_transactions_user_date` | `transactions` | `(user_id, transaction_date)` | Speeds up chronological sorting, ledger pagination, and date range filters (`WHERE transaction_date BETWEEN ...`). |
| `idx_transactions_user_type_date`| `transactions` | `(user_id, transaction_type, transaction_date)` | Covering index for monthly income vs. expense aggregations and dashboard KPI summaries. |
| `idx_transactions_category` | `transactions` | `(category_id)` | Accelerates equi-joins between transactions and categories. |
| `idx_budgets_user_dates` | `budgets` | `(user_id, start_date, end_date)` | Speeds up active budget period lookups (`CURDATE() BETWEEN start_date AND end_date`). |
| `idx_savings_user_date` | `savings_goals` | `(user_id, target_date)` | Speeds up milestone deadline tracking and upcoming target queries. |
| `idx_alerts_user_read` | `alerts` | `(user_id, is_read)` | Accelerates fetching unread alerts for navigation badges. |

---

## 5. Standard Relational Database Views

The schema includes 3 predefined relational views:

1. **`v_monthly_financial_summary`**:  
   Encapsulates monthly income, expense, and delta savings per user using `DATE_FORMAT(transaction_date, '%Y-%m')`.
2. **`v_budget_status`**:  
   Performs multi-table aggregation across `budgets`, `categories`, and `transactions` to evaluate budget utilization percentages, remaining amounts, and overage status (`ON_TRACK`, `WARNING`, `EXCEEDED`).
3. **`v_category_spending`**:  
   Pre-aggregates total expenditures, transaction counts, average per ticket, maximum, and minimum spending per category.

---

## 6. Catalog of 20 Essential SQL Queries

The application implements and executes 20 SQL queries demonstrating the complete breadth of the college DBMS syllabus:

### Query 1: Basic SELECT (Projection)
* **Title:** User Account & Profile Retrieval
* **Relational Concept:** Basic SELECT / Attribute Projection ($\pi$)
* **Tables Involved:** `users`
* **Application Location:** `profile.php`, `includes/auth.php`, `login.php`
* **SQL:**
  ```sql
  SELECT user_id, name, email, created_at, updated_at
  FROM users
  WHERE user_id = :user_id;
  ```

### Query 2: INSERT (Tuple Insertion)
* **Title:** Financial Transaction Ledger Entry (DML)
* **Relational Concept:** INSERT INTO
* **Tables Involved:** `transactions`, `users`, `categories`
* **Application Location:** `add_transaction.php`
* **SQL:**
  ```sql
  INSERT INTO transactions (
      user_id, category_id, amount, transaction_type, description, transaction_date, created_at
  ) VALUES (
      :user_id, 1, 45.50, 'expense', 'Grocery haul at supermarket', CURDATE(), NOW()
  );
  ```

### Query 3: UPDATE (Tuple Mutation)
* **Title:** Record Modification with Primary Key Predicate
* **Relational Concept:** UPDATE with WHERE
* **Tables Involved:** `transactions`
* **Application Location:** `edit_transaction.php`
* **SQL:**
  ```sql
  UPDATE transactions
  SET amount = 58.75, description = 'Supermarket grocery and household items'
  WHERE transaction_id = 1 AND user_id = :user_id;
  ```

### Query 4: DELETE (Tuple Deletion)
* **Title:** Referential Record Removal with Owner Validation
* **Relational Concept:** DELETE with WHERE
* **Tables Involved:** `transactions`
* **Application Location:** `delete_transaction.php`
* **SQL:**
  ```sql
  DELETE FROM transactions
  WHERE transaction_id = :transaction_id AND user_id = :user_id;
  ```

### Query 5: WHERE (Relational Selection)
* **Title:** Multi-Predicate Filtering (Date Range & Classification)
* **Relational Concept:** WHERE Clause / Relational Selection ($\sigma$)
* **Tables Involved:** `transactions`
* **Application Location:** `transactions.php`, `reports.php`
* **SQL:**
  ```sql
  SELECT transaction_id, description, amount, transaction_date, transaction_type
  FROM transactions
  WHERE user_id = :user_id 
    AND transaction_type = 'expense' 
    AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
  ORDER BY transaction_date DESC
  LIMIT 5;
  ```

### Query 6: ORDER BY (Sorting)
* **Title:** Multi-Attribute Spending Sorting & Ranking
* **Relational Concept:** ORDER BY (Ascending / Descending)
* **Tables Involved:** `transactions`
* **Application Location:** `transactions.php`, `dashboard.php`
* **SQL:**
  ```sql
  SELECT transaction_date, description, amount, transaction_type
  FROM transactions
  WHERE user_id = :user_id
  ORDER BY amount DESC, transaction_date DESC
  LIMIT 5;
  ```

### Query 7: GROUP BY (Partitioning)
* **Title:** Relation Partitioning & Volume Aggregation
* **Relational Concept:** GROUP BY
* **Tables Involved:** `transactions`
* **Application Location:** `dashboard.php`, `analytics.php`
* **SQL:**
  ```sql
  SELECT 
      transaction_type,
      COUNT(transaction_id) AS total_records,
      SUM(amount) AS total_volume
  FROM transactions
  WHERE user_id = :user_id
  GROUP BY transaction_type;
  ```

### Query 8: HAVING (Post-Aggregation Filter)
* **Title:** Post-Aggregation Group Filtering on Aggregated Spending
* **Relational Concept:** HAVING Clause
* **Tables Involved:** `transactions`, `categories`
* **Application Location:** `analytics.php`, `queries.php`
* **SQL:**
  ```sql
  SELECT 
      c.category_name,
      COUNT(t.transaction_id) AS transaction_count,
      SUM(t.amount) AS total_spent
  FROM transactions t
  INNER JOIN categories c ON t.category_id = c.category_id
  WHERE t.user_id = :user_id AND t.transaction_type = 'expense'
  GROUP BY c.category_id, c.category_name
  HAVING SUM(t.amount) > 100.00
  ORDER BY total_spent DESC;
  ```

### Query 9: SUM (Financial Accumulation)
* **Title:** Lifetime Financial Summation & Net Balance Computation
* **Relational Concept:** Aggregate SUM()
* **Tables Involved:** `transactions`
* **Application Location:** `dashboard.php`, `reports.php`
* **SQL:**
  ```sql
  SELECT 
      SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) AS total_income,
      SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) AS total_expenses,
      (SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) - 
       SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END)) AS net_savings
  FROM transactions
  WHERE user_id = :user_id;
  ```

### Query 10: AVG (Mean Metrics)
* **Title:** Arithmetic Mean Expenditure per Transaction
* **Relational Concept:** Aggregate AVG()
* **Tables Involved:** `transactions`
* **Application Location:** `analytics.php`, `reports.php`
* **SQL:**
  ```sql
  SELECT 
      ROUND(AVG(amount), 2) AS average_expense_per_transaction,
      COUNT(transaction_id) AS total_expense_count
  FROM transactions
  WHERE user_id = :user_id AND transaction_type = 'expense';
  ```

### Query 11: COUNT (Cardinality Measure)
* **Title:** Transaction Cardinality & Category Frequency
* **Relational Concept:** Aggregate COUNT()
* **Tables Involved:** `transactions`, `categories`
* **Application Location:** `categories.php`, `analytics.php`
* **SQL:**
  ```sql
  SELECT 
      c.category_name,
      COUNT(t.transaction_id) AS transaction_frequency
  FROM categories c
  INNER JOIN transactions t ON c.category_id = t.category_id
  WHERE t.user_id = :user_id
  GROUP BY c.category_id, c.category_name
  ORDER BY transaction_frequency DESC;
  ```

### Query 12: MAX (Peak Detection)
* **Title:** Peak Expenditure Identification
* **Relational Concept:** Aggregate MAX()
* **Tables Involved:** `transactions`
* **Application Location:** `analytics.php`, `reports.php`
* **SQL:**
  ```sql
  SELECT 
      MAX(amount) AS peak_expense_amount,
      COUNT(transaction_id) AS sample_size
  FROM transactions
  WHERE user_id = :user_id AND transaction_type = 'expense';
  ```

### Query 13: MIN (Lowest Non-Zero Detection)
* **Title:** Lowest Non-Zero Expense Detection
* **Relational Concept:** Aggregate MIN()
* **Tables Involved:** `transactions`
* **Application Location:** `analytics.php`, `reports.php`
* **SQL:**
  ```sql
  SELECT 
      MIN(amount) AS minimum_expense_amount
  FROM transactions
  WHERE user_id = :user_id AND transaction_type = 'expense';
  ```

### Query 14: INNER JOIN (Equi-Join)
* **Title:** Equi-Join Linking Transactions with Categories
* **Relational Concept:** INNER JOIN ($\bowtie$)
* **Tables Involved:** `transactions`, `categories`
* **Application Location:** `transactions.php`, `dashboard.php`, `analytics.php`
* **SQL:**
  ```sql
  SELECT 
      t.transaction_id,
      t.transaction_date,
      t.description,
      t.amount,
      c.category_name,
      c.category_type
  FROM transactions t
  INNER JOIN categories c ON t.category_id = c.category_id
  WHERE t.user_id = :user_id
  ORDER BY t.transaction_date DESC
  LIMIT 5;
  ```

### Query 15: LEFT JOIN (Outer Join)
* **Title:** Outer Join Preserving Unused Categories with COALESCE
* **Relational Concept:** LEFT OUTER JOIN ($\leftouterjoin$)
* **Tables Involved:** `categories`, `transactions`
* **Application Location:** `categories.php`, `budgets.php`
* **SQL:**
  ```sql
  SELECT 
      c.category_name,
      c.category_type,
      COUNT(t.transaction_id) AS total_transactions,
      COALESCE(SUM(t.amount), 0.00) AS total_spent
  FROM categories c
  LEFT JOIN transactions t ON t.category_id = c.category_id AND t.user_id = :user_id
  WHERE c.user_id IS NULL OR c.user_id = :user_id
  GROUP BY c.category_id, c.category_name, c.category_type
  ORDER BY total_spent DESC
  LIMIT 6;
  ```

### Query 16: Multiple-table JOIN (3-Table Join)
* **Title:** 3-Relation Equi-Join (Users, Transactions, Categories)
* **Relational Concept:** Multi-Table Equi-Join
* **Tables Involved:** `users`, `transactions`, `categories`
* **Application Location:** `reports.php`, `queries.php`
* **SQL:**
  ```sql
  SELECT 
      u.name AS user_name,
      t.transaction_date,
      t.description,
      t.amount,
      c.category_name,
      c.category_type
  FROM transactions t
  INNER JOIN users u ON t.user_id = u.user_id
  INNER JOIN categories c ON t.category_id = c.category_id
  WHERE t.user_id = :user_id
  ORDER BY t.transaction_date DESC
  LIMIT 5;
  ```

### Query 17: Subquery (Nested Scalar)
* **Title:** Nested Scalar Subquery (Above-Average Expenditures)
* **Relational Concept:** Nested Subquery in WHERE
* **Tables Involved:** `transactions`, `categories`
* **Application Location:** `analytics.php`, `queries.php`
* **SQL:**
  ```sql
  SELECT 
      t.transaction_date, 
      t.description, 
      t.amount,
      c.category_name
  FROM transactions t
  INNER JOIN categories c ON t.category_id = c.category_id
  WHERE t.user_id = :user_id 
    AND t.transaction_type = 'expense'
    AND t.amount > (
        SELECT AVG(amount) 
        FROM transactions 
        WHERE user_id = :user_id AND transaction_type = 'expense'
    )
  ORDER BY t.amount DESC
  LIMIT 5;
  ```

### Query 18: Monthly Expense Analysis
* **Title:** Time-Series Ledger Aggregation by Year-Month
* **Relational Concept:** Temporal Aggregation (`DATE_FORMAT`)
* **Tables Involved:** `transactions`
* **Application Location:** `analytics.php`, `reports.php`, `dashboard.php`
* **SQL:**
  ```sql
  SELECT 
      DATE_FORMAT(transaction_date, '%Y-%m') AS report_month,
      SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) AS monthly_income,
      SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) AS monthly_expense,
      (SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) - 
       SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END)) AS monthly_net_savings
  FROM transactions
  WHERE user_id = :user_id
  GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
  ORDER BY report_month DESC
  LIMIT 6;
  ```

### Query 19: Category-Wise Expense Analysis
* **Title:** Categorical Breakdown & Portfolio Percentage Share
* **Relational Concept:** Categorical Aggregation with Subquery Share Calculation
* **Tables Involved:** `transactions`, `categories`
* **Application Location:** `analytics.php`, `reports.php`, `dashboard.php`
* **SQL:**
  ```sql
  SELECT 
      c.category_name,
      COUNT(t.transaction_id) AS transaction_count,
      SUM(t.amount) AS total_spent,
      ROUND(AVG(t.amount), 2) AS avg_spent_per_tx,
      ROUND((SUM(t.amount) / (
          SELECT SUM(amount) FROM transactions WHERE user_id = :user_id AND transaction_type = 'expense'
      ) * 100), 1) AS percentage_of_total
  FROM transactions t
  INNER JOIN categories c ON t.category_id = c.category_id
  WHERE t.user_id = :user_id AND t.transaction_type = 'expense'
  GROUP BY c.category_id, c.category_name
  ORDER BY total_spent DESC;
  ```

### Query 20: Budget Exceeded Analysis
* **Title:** Multi-Table Threshold Breach & Variance Detection
* **Relational Concept:** Analytical Cross-Table Variance Join with Date Bounds & HAVING
* **Tables Involved:** `budgets`, `categories`, `transactions`
* **Application Location:** `budgets.php`, `dashboard.php`, `includes/auth.php`
* **SQL:**
  ```sql
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
  WHERE b.user_id = :user_id
  GROUP BY b.budget_id, c.category_name, b.budget_amount
  ORDER BY utilization_percentage DESC;
  ```

---

## 7. Conclusion & Viva-Voce Key Points

1. **Why InnoDB?**  
   Provides ACID compliance, transactions (`BEGIN`, `COMMIT`, `ROLLBACK`), foreign key constraint validation, and eliminates table-level locking in multi-user concurrent environments.
2. **Why DECIMAL(10,2) instead of FLOAT?**  
   Financial applications require exact numeric storage. `FLOAT` uses IEEE 754 floating-point binary representation which causes precision decay and rounding errors. `DECIMAL(10,2)` is an exact numeric type.
3. **What Normal Form is the database in?**  
   The schema is in **3NF (and BCNF)** because all attributes are atomic (1NF), all non-key attributes are fully dependent on single-attribute primary keys (2NF), and there are no transitive dependencies between non-key attributes (3NF).
4. **How is referential integrity protected?**  
   Child records are cascaded on user deletion (`ON DELETE CASCADE`), while categories with attached transactions are protected against orphan creation (`ON DELETE RESTRICT`).
5. **How are queries optimized?**  
   Targeted compound B-Tree indexes on `(user_id, transaction_date)` and `(user_id, transaction_type, transaction_date)` eliminate full-table scans.
