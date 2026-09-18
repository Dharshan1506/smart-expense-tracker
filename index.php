<?php
/**
 * Application Entry Point / Landing Page
 * Redirects to dashboard if logged in, or displays fintech introduction
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'Smart Expense Tracker - Welcome';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Expense Tracker | Intelligent Personal Finance & DBMS Showcase</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .landing-hero {
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
            color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 40px 20px;
            position: relative;
            overflow: hidden;
        }
        .hero-glow {
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(79, 70, 229, 0.3) 0%, transparent 70%);
            top: 20%;
            left: 50%;
            transform: translate(-50%, -50%);
            pointer-events: none;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 8px 18px;
            border-radius: 30px;
            font-size: 0.85rem;
            color: #93c5fd;
            font-weight: 600;
            margin-bottom: 24px;
        }
        .hero-title {
            font-size: 3.2rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.15;
            max-width: 800px;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #ffffff 30%, #93c5fd 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-subtitle {
            font-size: 1.15rem;
            color: #94a3b8;
            max-width: 650px;
            line-height: 1.6;
            margin-bottom: 36px;
        }
        .hero-actions {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            justify-content: center;
            margin-bottom: 50px;
        }
        .btn-hero-primary {
            background: linear-gradient(135deg, #4f46e5, #3b82f6);
            color: white;
            padding: 14px 32px;
            font-size: 1.05rem;
            font-weight: 700;
            border-radius: var(--radius-md);
            box-shadow: 0 10px 25px -5px rgba(79, 70, 229, 0.5);
        }
        .btn-hero-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px -5px rgba(79, 70, 229, 0.6);
        }
        .btn-hero-outline {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #f8fafc;
            padding: 14px 28px;
            font-size: 1.05rem;
            font-weight: 600;
            border-radius: var(--radius-md);
        }
        .btn-hero-outline:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
            max-width: 1050px;
            width: 100%;
            text-align: left;
        }
        .feature-box {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            padding: 24px;
            border-radius: var(--radius-lg);
            transition: var(--transition);
        }
        .feature-box:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-4px);
        }
        .feature-icon {
            font-size: 1.6rem;
            margin-bottom: 14px;
            color: #38bdf8;
        }
        .feature-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: #f8fafc;
        }
        .feature-desc {
            font-size: 0.88rem;
            color: #94a3b8;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="landing-hero">
        <div class="hero-glow"></div>
        <div class="hero-badge">
            <i class="fa-solid fa-graduation-cap"></i> DBMS College Project &bull; PHP 8 + MySQL + XAMPP
        </div>
        <h1 class="hero-title">Intelligent Spending Analytics & Budget Management</h1>
        <p class="hero-subtitle">
            Take total control of your personal finances with real-time income tracking, category budgets, automated spending alerts, and advanced SQL relational queries.
        </p>

        <div class="hero-actions">
            <a href="login.php" class="btn btn-hero-primary">
                <i class="fa-solid fa-right-to-bracket"></i> Launch Dashboard
            </a>
            <a href="register.php" class="btn btn-hero-outline">
                <i class="fa-solid fa-user-plus"></i> Create Free Account
            </a>
            <a href="queries.php" class="btn btn-hero-outline" style="border-color: #38bdf8; color: #38bdf8;">
                <i class="fa-solid fa-database"></i> Explore DBMS Queries
            </a>
        </div>

        <div class="feature-grid">
            <div class="feature-box">
                <div class="feature-icon"><i class="fa-solid fa-brain"></i></div>
                <div class="feature-title">Smart Spending Insights</div>
                <div class="feature-desc">Rule-based SQL computations calculate spending velocity, month-over-month variances, and budget warnings.</div>
            </div>
            <div class="feature-box">
                <div class="feature-icon"><i class="fa-solid fa-chart-pie"></i></div>
                <div class="feature-title">Fintech Analytics & Visuals</div>
                <div class="feature-desc">Interactive Chart.js data visualizations for income vs. expense, daily trends, and category shares.</div>
            </div>
            <div class="feature-box">
                <div class="feature-icon"><i class="fa-solid fa-shield-halved"></i></div>
                <div class="feature-title">Relational DBMS Integrity</div>
                <div class="feature-desc">Normalized MySQL architecture with foreign keys, prepared statements, and ACID guarantees.</div>
            </div>
        </div>
    </div>
</body>
</html>
