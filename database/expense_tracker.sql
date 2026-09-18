-- ==========================================================
-- DBMS Project: Smart Expense Tracker Relational Database Script
-- Academic DBMS Schema Specification with Relational Normalization (1NF, 2NF, 3NF)
-- Compatible with MySQL 8.0+ / MariaDB 10.4+ (XAMPP phpMyAdmin)
-- Storage Engine: InnoDB (ACID compliant with Foreign Key Enforcement)
-- Character Set: utf8mb4 / utf8mb4_unicode_ci
-- ==========================================================

CREATE DATABASE IF NOT EXISTS `expense_tracker` 
DEFAULT CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `expense_tracker`;

-- Disable foreign key checks for safe re-creation of relations
SET FOREIGN_KEY_CHECKS = 0;

-- Drop dependent views if existing
DROP VIEW IF EXISTS `v_monthly_financial_summary`;
DROP VIEW IF EXISTS `v_budget_status`;
DROP VIEW IF EXISTS `v_category_spending`;

-- Drop relational tables in reverse dependency order
DROP TABLE IF EXISTS `alerts`;
DROP TABLE IF EXISTS `savings_goals`;
DROP TABLE IF EXISTS `budgets`;
DROP TABLE IF EXISTS `transactions`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------
-- 1. Table: users
-- Entity: User Account & Authentication
-- Normalization: 3NF Satisfied (Atomic attributes, single PK, no transitive dependencies)
-- ----------------------------------------------------------
CREATE TABLE `users` (
    `user_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `uq_users_email` UNIQUE (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 2. Table: categories
-- Entity: Income & Expense Classification Taxonomy
-- Supports System Defaults (user_id IS NULL) and User Custom Categories
-- Normalization: 3NF Satisfied (Composite UNIQUE on user_id, category_name, category_type)
-- ----------------------------------------------------------
CREATE TABLE `categories` (
    `category_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NULL, -- NULL denotes global system category
    `category_name` VARCHAR(80) NOT NULL,
    `category_type` ENUM('income', 'expense') NOT NULL,
    `icon` VARCHAR(50) NOT NULL DEFAULT 'fa-tag',
    `color` VARCHAR(20) NOT NULL DEFAULT '#4f46e5',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_categories_user` 
        FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`user_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `uq_user_category` 
        UNIQUE (`user_id`, `category_name`, `category_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Performance Indexes for categories
CREATE INDEX `idx_categories_user` ON `categories` (`user_id`);
CREATE INDEX `idx_categories_type` ON `categories` (`category_type`);

-- ----------------------------------------------------------
-- 3. Table: transactions
-- Entity: Core Financial Ledger (Fact Table)
-- Normalization: 3NF Satisfied (Foreign keys reference users and categories; exact DECIMAL precision)
-- ----------------------------------------------------------
CREATE TABLE `transactions` (
    `transaction_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `transaction_type` ENUM('income', 'expense') NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `transaction_date` DATE NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_transactions_user` 
        FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`user_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_transactions_category` 
        FOREIGN KEY (`category_id`) 
        REFERENCES `categories` (`category_id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Performance Indexes for transactions
CREATE INDEX `idx_transactions_user_date` ON `transactions` (`user_id`, `transaction_date`);
CREATE INDEX `idx_transactions_category` ON `transactions` (`category_id`);
CREATE INDEX `idx_transactions_user_type_date` ON `transactions` (`user_id`, `transaction_type`, `transaction_date`);
CREATE INDEX `idx_transactions_amount` ON `transactions` (`amount`);

-- ----------------------------------------------------------
-- 4. Table: budgets
-- Entity: Periodic Category-Wise Expenditure Thresholds
-- Normalization: 3NF Satisfied (Composite UNIQUE on user_id, category_id, start_date, end_date)
-- ----------------------------------------------------------
CREATE TABLE `budgets` (
    `budget_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    `budget_amount` DECIMAL(10,2) NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_budgets_user` 
        FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`user_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_budgets_category` 
        FOREIGN KEY (`category_id`) 
        REFERENCES `categories` (`category_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `uq_user_category_period` 
        UNIQUE (`user_id`, `category_id`, `start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Performance Indexes for budgets
CREATE INDEX `idx_budgets_user_dates` ON `budgets` (`user_id`, `start_date`, `end_date`);
CREATE INDEX `idx_budgets_category` ON `budgets` (`category_id`);

-- ----------------------------------------------------------
-- 5. Table: savings_goals
-- Entity: User Financial Target Savings
-- Normalization: 3NF Satisfied (Decimal currency precision with non-negative constraints)
-- ----------------------------------------------------------
CREATE TABLE `savings_goals` (
    `goal_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `goal_name` VARCHAR(120) NOT NULL,
    `target_amount` DECIMAL(10,2) NOT NULL,
    `saved_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `target_date` DATE NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_savings_user` 
        FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`user_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Performance Indexes for savings_goals
CREATE INDEX `idx_savings_user_date` ON `savings_goals` (`user_id`, `target_date`);

-- ----------------------------------------------------------
-- 6. Table: alerts
-- Entity: System-Generated Alerts & Financial Threshold Warnings
-- Normalization: 3NF Satisfied
-- ----------------------------------------------------------
CREATE TABLE `alerts` (
    `alert_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `message` TEXT NOT NULL,
    `alert_type` ENUM('warning', 'danger', 'info', 'success') NOT NULL DEFAULT 'info',
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_alerts_user` 
        FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`user_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Performance Indexes for alerts
CREATE INDEX `idx_alerts_user_read` ON `alerts` (`user_id`, `is_read`);

-- ==========================================================
-- RELATIONAL VIEWS (DBMS Encapsulation & Query Optimization)
-- ==========================================================

-- View 1: Monthly Financial Summary per User
CREATE OR REPLACE VIEW `v_monthly_financial_summary` AS
SELECT 
    `user_id`,
    DATE_FORMAT(`transaction_date`, '%Y-%m') AS `report_month`,
    SUM(CASE WHEN `transaction_type` = 'income' THEN `amount` ELSE 0 END) AS `monthly_income`,
    SUM(CASE WHEN `transaction_type` = 'expense' THEN `amount` ELSE 0 END) AS `monthly_expense`,
    (SUM(CASE WHEN `transaction_type` = 'income' THEN `amount` ELSE 0 END) - 
     SUM(CASE WHEN `transaction_type` = 'expense' THEN `amount` ELSE 0 END)) AS `monthly_net_savings`,
    COUNT(`transaction_id`) AS `total_transactions`
FROM `transactions`
GROUP BY `user_id`, DATE_FORMAT(`transaction_date`, '%Y-%m');

-- View 2: Active Budget Health & Overrun Monitoring
CREATE OR REPLACE VIEW `v_budget_status` AS
SELECT 
    b.`budget_id`,
    b.`user_id`,
    b.`category_id`,
    c.`category_name`,
    b.`budget_amount`,
    b.`start_date`,
    b.`end_date`,
    COALESCE(SUM(t.`amount`), 0.00) AS `total_spent`,
    (b.`budget_amount` - COALESCE(SUM(t.`amount`), 0.00)) AS `remaining_budget`,
    ROUND((COALESCE(SUM(t.`amount`), 0.00) / b.`budget_amount` * 100), 2) AS `utilization_pct`,
    CASE 
        WHEN COALESCE(SUM(t.`amount`), 0.00) > b.`budget_amount` THEN 'EXCEEDED'
        WHEN (COALESCE(SUM(t.`amount`), 0.00) / b.`budget_amount`) >= 0.80 THEN 'WARNING'
        ELSE 'ON_TRACK'
    END AS `status`
FROM `budgets` b
INNER JOIN `categories` c ON b.`category_id` = c.`category_id`
LEFT JOIN `transactions` t ON t.`category_id` = b.`category_id` 
    AND t.`user_id` = b.`user_id` 
    AND t.`transaction_type` = 'expense'
    AND t.`transaction_date` BETWEEN b.`start_date` AND b.`end_date`
GROUP BY b.`budget_id`, b.`user_id`, b.`category_id`, c.`category_name`, b.`budget_amount`, b.`start_date`, b.`end_date`;

-- View 3: Category Spending Aggregation
CREATE OR REPLACE VIEW `v_category_spending` AS
SELECT 
    t.`user_id`,
    c.`category_id`,
    c.`category_name`,
    c.`category_type`,
    c.`icon`,
    c.`color`,
    COUNT(t.`transaction_id`) AS `transaction_count`,
    SUM(t.`amount`) AS `total_amount`,
    ROUND(AVG(t.`amount`), 2) AS `avg_amount`,
    MAX(t.`amount`) AS `max_amount`,
    MIN(t.`amount`) AS `min_amount`
FROM `categories` c
INNER JOIN `transactions` t ON t.`category_id` = c.`category_id`
GROUP BY t.`user_id`, c.`category_id`, c.`category_name`, c.`category_type`, c.`icon`, c.`color`;

-- ==========================================================
-- SEED DATA: Default System Categories
-- ==========================================================
INSERT INTO `categories` (`category_id`, `user_id`, `category_name`, `category_type`, `icon`, `color`) VALUES
(1, NULL, 'Food & Dining', 'expense', 'fa-utensils', '#ef4444'),
(2, NULL, 'Travel & Transport', 'expense', 'fa-plane', '#3b82f6'),
(3, NULL, 'Shopping', 'expense', 'fa-bag-shopping', '#ec4899'),
(4, NULL, 'Bills & Utilities', 'expense', 'fa-receipt', '#f59e0b'),
(5, NULL, 'Education', 'expense', 'fa-graduation-cap', '#8b5cf6'),
(6, NULL, 'Entertainment', 'expense', 'fa-film', '#06b6d4'),
(7, NULL, 'Healthcare', 'expense', 'fa-heart-pulse', '#10b981'),
(8, NULL, 'Salary', 'income', 'fa-money-bill-wave', '#10b981'),
(9, NULL, 'Freelance', 'income', 'fa-laptop-code', '#6366f1'),
(10, NULL, 'Other Expense', 'expense', 'fa-wallet', '#64748b'),
(11, NULL, 'Other Income', 'income', 'fa-wallet', '#0ea5e9');

-- ==========================================================
-- SEED DATA: Demo User Account
-- Login: demo@example.com | Password: password123
-- Hash generated using PHP password_hash('password123', PASSWORD_BCRYPT)
-- ==========================================================
INSERT INTO `users` (`user_id`, `name`, `email`, `password`, `created_at`) VALUES
(1, 'Alex Morgan', 'demo@example.com', '$2y$10$6thg1jnhp7GixBHYZrFReekz76iU0sEIK1zLYaPTRIp7htttgy/IG', NOW());

-- ==========================================================
-- SEED DATA: Demo Transactions (Ledger Fact Tuples)
-- ==========================================================
INSERT INTO `transactions` (`user_id`, `category_id`, `amount`, `transaction_type`, `description`, `transaction_date`, `created_at`) VALUES
-- Current Month Income
(1, 8, 4500.00, 'income', 'Monthly Engineering Salary', CURDATE() - INTERVAL 12 DAY, NOW()),
(1, 9, 850.00, 'income', 'Full-stack Web Dev Client Project', CURDATE() - INTERVAL 5 DAY, NOW()),
(1, 11, 200.00, 'income', 'Stock Dividend Payout', CURDATE() - INTERVAL 2 DAY, NOW()),

-- Current Month Expenses
(1, 1, 65.50, 'expense', 'Weekly Grocery Store haul', CURDATE() - INTERVAL 1 DAY, NOW()),
(1, 1, 24.00, 'expense', 'Cafe Brunch with colleagues', CURDATE() - INTERVAL 3 DAY, NOW()),
(1, 2, 45.00, 'expense', 'Subway & Train Monthly Pass', CURDATE() - INTERVAL 10 DAY, NOW()),
(1, 2, 18.50, 'expense', 'Uber ride to city center', CURDATE() - INTERVAL 4 DAY, NOW()),
(1, 3, 120.00, 'expense', 'Sneakers & sports wear', CURDATE() - INTERVAL 8 DAY, NOW()),
(1, 4, 95.00, 'expense', 'Electricity & High-speed Internet bill', CURDATE() - INTERVAL 11 DAY, NOW()),
(1, 5, 49.99, 'expense', 'Database Systems Certification Course', CURDATE() - INTERVAL 7 DAY, NOW()),
(1, 6, 28.00, 'expense', 'Cinema ticket & snacks', CURDATE() - INTERVAL 6 DAY, NOW()),
(1, 7, 35.00, 'expense', 'Pharmacy & Vitamin supplements', CURDATE() - INTERVAL 9 DAY, NOW()),
(1, 1, 52.00, 'expense', 'Italian Dinner with family', CURDATE() - INTERVAL 2 DAY, NOW()),
(1, 3, 65.00, 'expense', 'Desk organizer and wireless mouse', CURDATE(), NOW()),

-- Previous Month Historical Records (for Trends & Comparative Analysis)
(1, 8, 4500.00, 'income', 'Previous Month Salary', CURDATE() - INTERVAL 42 DAY, NOW()),
(1, 9, 600.00, 'income', 'UI/UX Design Contract', CURDATE() - INTERVAL 38 DAY, NOW()),
(1, 1, 210.00, 'expense', 'Monthly Groceries Supermarket', CURDATE() - INTERVAL 35 DAY, NOW()),
(1, 2, 60.00, 'expense', 'Commute & Fuel Expenses', CURDATE() - INTERVAL 34 DAY, NOW()),
(1, 3, 185.00, 'expense', 'Winter Clothing Shopping', CURDATE() - INTERVAL 32 DAY, NOW()),
(1, 4, 110.00, 'expense', 'Water, Power and Broadband', CURDATE() - INTERVAL 40 DAY, NOW()),
(1, 5, 80.00, 'expense', 'Academic Books and References', CURDATE() - INTERVAL 36 DAY, NOW()),
(1, 6, 75.00, 'expense', 'Concert & Weekend Amusement', CURDATE() - INTERVAL 31 DAY, NOW()),
(1, 7, 40.00, 'expense', 'Health Checkup Clinic', CURDATE() - INTERVAL 33 DAY, NOW());

-- ==========================================================
-- SEED DATA: Demo Budgets
-- ==========================================================
INSERT INTO `budgets` (`user_id`, `category_id`, `budget_amount`, `start_date`, `end_date`, `created_at`) VALUES
(1, 1, 300.00, DATE_FORMAT(CURDATE(), '%Y-%m-01'), LAST_DAY(CURDATE()), NOW()),
(1, 2, 100.00, DATE_FORMAT(CURDATE(), '%Y-%m-01'), LAST_DAY(CURDATE()), NOW()),
(1, 3, 200.00, DATE_FORMAT(CURDATE(), '%Y-%m-01'), LAST_DAY(CURDATE()), NOW()),
(1, 4, 120.00, DATE_FORMAT(CURDATE(), '%Y-%m-01'), LAST_DAY(CURDATE()), NOW()),
(1, 6, 80.00,  DATE_FORMAT(CURDATE(), '%Y-%m-01'), LAST_DAY(CURDATE()), NOW());

-- ==========================================================
-- SEED DATA: Demo Savings Goals
-- ==========================================================
INSERT INTO `savings_goals` (`user_id`, `goal_name`, `target_amount`, `saved_amount`, `target_date`, `created_at`) VALUES
(1, 'Emergency Fund Reserve', 5000.00, 3200.00, CURDATE() + INTERVAL 180 DAY, NOW()),
(1, 'New M3 MacBook Pro', 2200.00, 1450.00, CURDATE() + INTERVAL 90 DAY, NOW()),
(1, 'Summer Vacation Trip', 1200.00, 600.00, CURDATE() + INTERVAL 120 DAY, NOW());

-- ==========================================================
-- SEED DATA: Demo Alerts
-- ==========================================================
INSERT INTO `alerts` (`user_id`, `message`, `alert_type`, `is_read`, `created_at`) VALUES
(1, 'Smart Alert: Your Food & Dining spending has reached 47% of your monthly budget.', 'info', 0, NOW()),
(1, 'Budget Warning: Shopping expenses are at 92.5% of your allocated budget (₹185.00 of ₹200.00).', 'warning', 0, NOW()),
(1, 'Positive Milestone: You have saved 25% more this month compared with last month!', 'success', 0, NOW());
