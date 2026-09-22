<?php
/**
 * Application Entry Point / Landing Page - ExpenseIQ Fintech SaaS
 * Redirects to dashboard if logged in, or displays high-end fintech introduction
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'ExpenseIQ - Intelligent Financial Management & Analytics';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ExpenseIQ | Intelligent Personal Finance SaaS & DBMS Showcase</title>
    <!-- Google Fonts: Plus Jakarta Sans & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome 6 Icons CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Design System Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .landing-hero {
            min-height: 100vh;
            background: #050811;
            color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 60px 24px;
            position: relative;
            overflow: hidden;
        }

        .hero-glow-1 {
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(37, 99, 235, 0.22) 0%, transparent 70%);
            top: 15%;
            left: 50%;
            transform: translate(-50%, -50%);
            pointer-events: none;
        }

        .hero-glow-2 {
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.18) 0%, transparent 70%);
            bottom: 10%;
            right: 15%;
            pointer-events: none;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(30, 48, 80, 0.6);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(59, 130, 246, 0.35);
            padding: 8px 20px;
            border-radius: var(--radius-full);
            font-size: 0.85rem;
            color: #93c5fd;
            font-weight: 700;
            margin-bottom: 28px;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.2);
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            letter-spacing: -0.035em;
            line-height: 1.15;
            max-width: 860px;
            margin-bottom: 22px;
            background: linear-gradient(135deg, #ffffff 30%, #93c5fd 80%, #a78bfa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-subtitle {
            font-size: 1.15rem;
            color: var(--text-secondary);
            max-width: 680px;
            line-height: 1.65;
            margin-bottom: 40px;
        }

        .hero-actions {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            justify-content: center;
            margin-bottom: 60px;
        }

        .btn-hero-primary {
            background: var(--gradient-primary);
            color: white;
            padding: 15px 34px;
            font-size: 1.05rem;
            font-weight: 700;
            border-radius: var(--radius-md);
            box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.6);
            transition: var(--transition);
        }

        .btn-hero-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 32px -5px rgba(37, 99, 235, 0.75);
        }

        .btn-hero-outline {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #f8fafc;
            padding: 15px 30px;
            font-size: 1.05rem;
            font-weight: 600;
            border-radius: var(--radius-md);
            transition: var(--transition);
        }

        .btn-hero-outline:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        /* 3D Floating Demo Cards Strip */
        .hero-demo-cards {
            display: flex;
            gap: 18px;
            max-width: 920px;
            width: 100%;
            margin-bottom: 60px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .demo-kpi-card {
            flex: 1;
            min-width: 240px;
            background: rgba(14, 21, 38, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            padding: 20px;
            text-align: left;
            box-shadow: var(--shadow-lg);
            backdrop-filter: blur(16px);
            transition: var(--transition);
        }

        .demo-kpi-card:hover {
            transform: translateY(-4px);
            border-color: rgba(59, 130, 246, 0.4);
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 22px;
            max-width: 1100px;
            width: 100%;
            text-align: left;
        }

        .feature-box {
            background: rgba(14, 21, 38, 0.65);
            border: 1px solid rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(14px);
            padding: 28px;
            border-radius: var(--radius-lg);
            transition: var(--transition);
        }

        .feature-box:hover {
            background: rgba(20, 31, 54, 0.8);
            border-color: rgba(59, 130, 246, 0.3);
            transform: translateY(-4px);
        }

        .feature-icon {
            font-size: 1.8rem;
            margin-bottom: 16px;
            color: #38bdf8;
        }

        .feature-title {
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: #f8fafc;
        }

        .feature-desc {
            font-size: 0.9rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.3rem;
            }
        }
    </style>
</head>
<body>
    <div class="landing-hero">
        <div class="hero-glow-1"></div>
        <div class="hero-glow-2"></div>

        <div class="hero-badge">
            <i class="fa-solid fa-graduation-cap" style="color: #60a5fa;"></i> Academic DBMS Project &bull; PHP 8 + MySQL + 3NF
        </div>

        <h1 class="hero-title">
            Intelligent Spending Analytics & <br>Autonomous Wealth Management
        </h1>

        <p class="hero-subtitle">
            Take total command of your finances with real-time Indian Rupee (₹) telemetry, automated budget threshold alerts, and 20 live relational DBMS query demonstrations.
        </p>

        <div class="hero-actions">
            <a href="login.php" class="btn btn-hero-primary">
                <i class="fa-solid fa-right-to-bracket"></i> Launch Dashboard
            </a>
            <a href="register.php" class="btn btn-hero-outline">
                <i class="fa-solid fa-user-plus"></i> Create Free Account
            </a>
            <a href="queries.php" class="btn btn-hero-outline" style="border-color: rgba(6, 182, 212, 0.4); color: #38bdf8;">
                <i class="fa-solid fa-database"></i> Explore 20 SQL Queries
            </a>
        </div>

        <!-- 3D Floating Demo KPI Cards -->
        <div class="hero-demo-cards">
            <div class="demo-kpi-card" style="border-left: 4px solid #3b82f6;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 0.74rem; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Monthly Inflow</span>
                    <i class="fa-solid fa-arrow-trend-up" style="color: #60a5fa;"></i>
                </div>
                <div style="font-size: 1.55rem; font-weight: 800; font-family: var(--font-mono); color: #ffffff;">+ ₹50,000.00</div>
                <div style="font-size: 0.74rem; color: #93c5fd; margin-top: 4px;">Primary Salary & Investments</div>
            </div>

            <div class="demo-kpi-card" style="border-left: 4px solid #f43f5e;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 0.74rem; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Monthly Outflow</span>
                    <i class="fa-solid fa-arrow-trend-down" style="color: #fb7185;"></i>
                </div>
                <div style="font-size: 1.55rem; font-weight: 800; font-family: var(--font-mono); color: #f87171;">- ₹18,500.00</div>
                <div style="font-size: 0.74rem; color: #fca5a5; margin-top: 4px;">Controlled Category Caps</div>
            </div>

            <div class="demo-kpi-card" style="border-left: 4px solid #06b6d4;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 0.74rem; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Net Balance</span>
                    <i class="fa-solid fa-scale-balanced" style="color: #38bdf8;"></i>
                </div>
                <div style="font-size: 1.55rem; font-weight: 800; font-family: var(--font-mono); color: #38bdf8;">₹31,500.00</div>
                <div style="font-size: 0.74rem; color: #6ee7b7; margin-top: 4px;">63% Savings Velocity</div>
            </div>
        </div>

        <div class="feature-grid">
            <div class="feature-box">
                <div class="feature-icon"><i class="fa-solid fa-brain"></i></div>
                <div class="feature-title">Smart Financial Insights</div>
                <div class="feature-desc">Autonomous rule-based and AI telemetry calculates spending velocity, daily burn rate, and threshold warnings.</div>
            </div>
            <div class="feature-box">
                <div class="feature-icon"><i class="fa-solid fa-chart-pie"></i></div>
                <div class="feature-title">Fintech SaaS Analytics</div>
                <div class="feature-desc">Interactive Chart.js visualizations for income vs expense comparisons, monthly trends, and category distribution.</div>
            </div>
            <div class="feature-box">
                <div class="feature-icon"><i class="fa-solid fa-shield-halved"></i></div>
                <div class="feature-title">Relational 3NF Integrity</div>
                <div class="feature-desc">Third Normal Form normalized MySQL schema enforcing foreign keys, prepared statements, and ACID guarantees.</div>
            </div>
        </div>
    </div>

    <!-- Floating Ask Me Help AI Chatbot -->
    <?php require_once __DIR__ . '/includes/chatbot.php'; ?>
</body>
</html>
