<?php
/**
 * Database Configuration using PHP Data Objects (PDO)
 * Default credentials for XAMPP Apache + MySQL
 */

declare(strict_types=1);

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'expense_tracker');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

function getDBConnection(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Check if database does not exist
            die('
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Database Setup Required | Smart Expense Tracker</title>
                <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
                <style>
                    body { font-family: "Plus Jakarta Sans", sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
                    .card { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 36px; max-width: 600px; width: 100%; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
                    h1 { margin-top: 0; color: #38bdf8; font-size: 1.6rem; display: flex; align-items: center; gap: 10px; }
                    .alert { background: rgba(239, 68, 68, 0.15); border-left: 4px solid #ef4444; padding: 14px; border-radius: 6px; margin: 18px 0; color: #fca5a5; font-size: 0.9rem; word-break: break-word; }
                    ol { line-height: 1.8; color: #cbd5e1; padding-left: 20px; }
                    code { background: #0f172a; color: #38bdf8; padding: 3px 8px; border-radius: 4px; font-size: 0.9em; }
                    .btn { display: inline-block; background: #3b82f6; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; margin-top: 15px; }
                </style>
            </head>
            <body>
                <div class="card">
                    <h1>⚠️ Database Connection Issue</h1>
                    <div class="alert">
                        <strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '
                    </div>
                    <p>To run the Smart Expense Tracker on XAMPP, please follow these simple setup steps:</p>
                    <ol>
                        <li>Open the <strong>XAMPP Control Panel</strong> and click <strong>Start</strong> next to <strong>Apache</strong> and <strong>MySQL</strong>.</li>
                        <li>Open your browser and navigate to <code>http://localhost/phpmyadmin/</code>.</li>
                        <li>Click <strong>Import</strong> in the top menu.</li>
                        <li>Choose file: <code>database/expense_tracker.sql</code> from this project directory.</li>
                        <li>Click <strong>Import</strong> (Go) at the bottom to create the database and seed demo data.</li>
                    </ol>
                    <a href="" class="btn" onclick="location.reload(); return false;">🔄 Retry Connection</a>
                </div>
            </body>
            </html>
            ');
        }
    }

    return $pdo;
}
