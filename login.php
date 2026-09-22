<?php
/**
 * User Login Page - ExpenseIQ Premium Fintech SaaS
 * Full-Screen Split Layout with 3D Floating Glassmorphism Financial Elements
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Please enter both your email address and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address format.';
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT user_id, name, email, password FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];

                if (!empty($_POST['remember_me'])) {
                    setcookie('remember_email', $email, time() + (86400 * 30), "/");
                } else {
                    if (isset($_COOKIE['remember_email'])) {
                        setcookie('remember_email', '', time() - 3600, "/");
                    }
                }

                set_flash('success', 'Welcome back, ' . htmlspecialchars($user['name']) . '!');
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Invalid email or password. Please verify your credentials.';
            }
        } catch (Exception $e) {
            $error = 'Database connection error: ' . $e->getMessage();
        }
    }
}

// Prefill email if cookie exists
if (empty($email) && isset($_COOKIE['remember_email'])) {
    $email = $_COOKIE['remember_email'];
}

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | ExpenseIQ - Intelligent Fintech Management</title>
    <!-- Google Fonts: Plus Jakarta Sans & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Main Design System Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .auth-page {
            min-height: 100vh;
            background: #050811;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            position: relative;
            overflow-x: hidden;
        }

        .auth-split-wrapper {
            width: 100%;
            max-width: 1180px;
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            background: rgba(14, 21, 38, 0.7);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: var(--radius-2xl);
            box-shadow: 0 35px 80px -20px rgba(0, 0, 0, 0.8), 0 0 50px -10px rgba(59, 130, 246, 0.25);
            overflow: hidden;
            position: relative;
            z-index: 10;
        }

        .auth-hero-pane {
            padding: 48px;
            background: linear-gradient(145deg, rgba(15, 23, 42, 0.9) 0%, rgba(8, 12, 22, 0.95) 100%);
            border-right: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .auth-hero-pane::before {
            content: '';
            position: absolute;
            top: -100px;
            left: -100px;
            width: 350px;
            height: 350px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.25) 0%, transparent 70%);
            pointer-events: none;
        }

        .auth-hero-pane::after {
            content: '';
            position: absolute;
            bottom: -80px;
            right: -80px;
            width: 320px;
            height: 320px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.2) 0%, transparent 70%);
            pointer-events: none;
        }

        .hero-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 28px;
        }

        .hero-brand-icon {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-md);
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: #ffffff;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.45);
        }

        .hero-brand-name {
            font-size: 1.45rem;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.03em;
        }

        .hero-title {
            font-size: 2.1rem;
            font-weight: 800;
            color: #ffffff;
            line-height: 1.25;
            letter-spacing: -0.03em;
            margin-bottom: 14px;
        }

        .hero-title-accent {
            background: linear-gradient(135deg, #60a5fa 0%, #a78bfa 50%, #38bdf8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-subtitle {
            font-size: 0.95rem;
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 32px;
        }

        /* 3D Floating Financial Cards Scene */
        .cards-scene-3d {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-bottom: 32px;
            perspective: 1200px;
        }

        .card-3d-item {
            background: rgba(20, 31, 54, 0.75);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 14px 30px -6px rgba(0, 0, 0, 0.6);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
        }

        .card-3d-item:hover {
            transform: translateX(6px) translateY(-2px);
            border-color: rgba(255, 255, 255, 0.25);
        }

        .card-income-3d {
            border-left: 4px solid #3b82f6;
            animation: floatSlow 5s ease-in-out infinite;
        }

        .card-expense-3d {
            border-left: 4px solid #f43f5e;
            animation: floatReverse 6s ease-in-out infinite 0.5s;
        }

        .card-balance-3d {
            border-left: 4px solid #06b6d4;
            animation: floatSlow 5.5s ease-in-out infinite 1s;
        }

        .card-badge-pill {
            font-size: 0.72rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: var(--radius-full);
        }

        /* Right Form Pane */
        .auth-form-pane {
            padding: 48px 42px;
            background: rgba(10, 16, 30, 0.85);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-pane-header {
            margin-bottom: 28px;
        }

        .form-pane-title {
            font-size: 1.7rem;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.03em;
            margin-bottom: 6px;
        }

        .demo-pill-btn {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(59, 130, 246, 0.12);
            border: 1px solid rgba(59, 130, 246, 0.3);
            padding: 10px 14px;
            border-radius: var(--radius-md);
            cursor: pointer;
            margin-bottom: 22px;
            transition: var(--transition);
        }

        .demo-pill-btn:hover {
            background: rgba(59, 130, 246, 0.2);
            border-color: rgba(59, 130, 246, 0.5);
            transform: translateY(-1px);
        }

        .password-toggle-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 0.92rem;
            transition: var(--transition);
        }

        .password-toggle-btn:hover {
            color: #ffffff;
        }

        .btn-fintech-gradient {
            background: linear-gradient(135deg, #1e40af 0%, #2563eb 50%, #7c3aed 100%);
            background-size: 200% 200%;
            color: #ffffff;
            font-weight: 700;
            padding: 13px 20px;
            border-radius: var(--radius-md);
            border: none;
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 0.98rem;
            cursor: pointer;
            box-shadow: 0 10px 25px -4px rgba(37, 99, 235, 0.5);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .btn-fintech-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 32px -4px rgba(37, 99, 235, 0.7);
            background-position: 100% 50%;
        }

        .forgot-link {
            color: #818cf8;
            font-size: 0.82rem;
            font-weight: 600;
            text-decoration: none;
        }

        .forgot-link:hover {
            color: #a5b4fc;
            text-decoration: underline;
        }

        @media (max-width: 960px) {
            .auth-split-wrapper {
                grid-template-columns: 1fr;
            }
            .auth-hero-pane {
                display: none;
            }
            .auth-form-pane {
                padding: 36px 24px;
            }
        }
    </style>
</head>
<body class="auth-page">

<div class="auth-split-wrapper">
    <!-- LEFT SIDE: 3D Visual Finance Scene -->
    <div class="auth-hero-pane">
        <div>
            <!-- Brand -->
            <div class="hero-brand">
                <div class="hero-brand-icon">
                    <i class="fa-solid fa-wallet"></i>
                </div>
                <div>
                    <div class="hero-brand-name">ExpenseIQ</div>
                </div>
                <span class="sidebar-brand-badge" style="margin-left: auto;">Fintech SaaS</span>
            </div>

            <!-- Title -->
            <h2 class="hero-title">
                Next-Gen Financial Analytics & <span class="hero-title-accent">Smart Ledger</span>
            </h2>
            <p class="hero-subtitle">
                Autonomous spending telemetry, dynamic category budgeting, and 3NF relational data integrity in Indian Rupees (₹).
            </p>

            <!-- 3D Floating Financial Cards -->
            <div class="cards-scene-3d">
                <!-- 1. Income Card -->
                <div class="card-3d-item card-income-3d">
                    <div style="display: flex; align-items: center; gap: 14px;">
                        <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(59, 130, 246, 0.2); color: #60a5fa; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                            <i class="fa-solid fa-arrow-trend-up"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.72rem; text-transform: uppercase; color: #94a3b8; font-weight: 700; letter-spacing: 0.05em;">Monthly Income</div>
                            <strong style="font-size: 1.22rem; color: #ffffff; font-family: var(--font-mono); font-weight: 800;">+ ₹50,000</strong>
                        </div>
                    </div>
                    <span class="card-badge-pill" style="background: rgba(59, 130, 246, 0.2); color: #93c5fd; border: 1px solid rgba(59, 130, 246, 0.35);">
                        +18.4%
                    </span>
                </div>

                <!-- 2. Expenses Card -->
                <div class="card-3d-item card-expense-3d">
                    <div style="display: flex; align-items: center; gap: 14px;">
                        <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(244, 63, 94, 0.2); color: #fb7185; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                            <i class="fa-solid fa-arrow-trend-down"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.72rem; text-transform: uppercase; color: #94a3b8; font-weight: 700; letter-spacing: 0.05em;">Monthly Expenses</div>
                            <strong style="font-size: 1.22rem; color: #f87171; font-family: var(--font-mono); font-weight: 800;">- ₹18,500</strong>
                        </div>
                    </div>
                    <span class="card-badge-pill" style="background: rgba(244, 63, 94, 0.2); color: #fca5a5; border: 1px solid rgba(244, 63, 94, 0.35);">
                        Controlled
                    </span>
                </div>

                <!-- 3. Available Balance Card -->
                <div class="card-3d-item card-balance-3d">
                    <div style="display: flex; align-items: center; gap: 14px;">
                        <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(6, 182, 212, 0.2); color: #38bdf8; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                            <i class="fa-solid fa-scale-balanced"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.72rem; text-transform: uppercase; color: #94a3b8; font-weight: 700; letter-spacing: 0.05em;">Available Balance</div>
                            <strong style="font-size: 1.22rem; color: #38bdf8; font-family: var(--font-mono); font-weight: 800;">₹31,500</strong>
                        </div>
                    </div>
                    <span class="card-badge-pill" style="background: rgba(6, 182, 212, 0.2); color: #7dd3fc; border: 1px solid rgba(6, 182, 212, 0.35);">
                        Net Surplus
                    </span>
                </div>
            </div>

            <!-- Mini Sparkline Graph Card -->
            <div style="background: rgba(14, 21, 38, 0.6); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: var(--radius-md); padding: 14px 18px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 0.76rem; color: #cbd5e1; font-weight: 600;"><i class="fa-solid fa-chart-line" style="color: #60a5fa; margin-right: 6px;"></i>Daily Liquidity Momentum</span>
                    <span style="font-size: 0.72rem; color: #34d399; font-weight: 700;">₹1,050/day Avg</span>
                </div>
                <svg viewBox="0 0 340 50" style="width: 100%; height: 50px; display: block;" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <linearGradient id="loginGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                            <stop offset="0%" stop-color="#3b82f6" />
                            <stop offset="50%" stop-color="#8b5cf6" />
                            <stop offset="100%" stop-color="#06b6d4" />
                        </linearGradient>
                        <linearGradient id="loginArea" x1="0%" y1="0%" x2="0%" y2="100%">
                            <stop offset="0%" stop-color="#3b82f6" stop-opacity="0.3" />
                            <stop offset="100%" stop-color="#3b82f6" stop-opacity="0.0" />
                        </linearGradient>
                    </defs>
                    <path d="M 0,40 Q 60,30 110,22 T 210,18 T 280,10 T 340,4 L 340,50 L 0,50 Z" fill="url(#loginArea)" />
                    <path d="M 0,40 Q 60,30 110,22 T 210,18 T 280,10 T 340,4" fill="none" stroke="url(#loginGrad)" stroke-width="2.5" stroke-linecap="round" />
                    <circle cx="110" cy="22" r="3.5" fill="#60a5fa" />
                    <circle cx="210" cy="18" r="3.5" fill="#c084fc" />
                    <circle cx="280" cy="10" r="3.5" fill="#38bdf8" />
                    <circle cx="340" cy="4" r="4" fill="#34d399" />
                </svg>
            </div>
        </div>

        <!-- Footer Note -->
        <div style="display: flex; justify-content: space-between; font-size: 0.76rem; color: #64748b; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.06);">
            <span><i class="fa-solid fa-lock" style="color: #10b981; margin-right: 5px;"></i> AES-256 Encrypted</span>
            <span>PHP 8 &bull; MySQL 8 &bull; 3NF</span>
        </div>
    </div>

    <!-- RIGHT SIDE: Premium Glass Login Panel -->
    <div class="auth-form-pane">
        <div class="form-pane-header">
            <h1 class="form-pane-title">Welcome back</h1>
            <p style="font-size: 0.88rem; color: var(--text-secondary); margin: 0;">
                Enter your credentials to access your financial command center
            </p>
        </div>

        <?php if ($flash): ?>
            <div class="flash-alert flash-<?= htmlspecialchars($flash['type']) ?>">
                <i class="fa-solid fa-circle-check"></i>
                <span><?= htmlspecialchars($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="flash-alert flash-danger">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- 1-Click Demo Fill Shortcut -->
        <div class="demo-pill-btn" onclick="fillDemoCredentials()" id="demoFillBtn" title="Auto-inject preloaded demo credentials">
            <div style="display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-wand-magic-sparkles" style="color: #60a5fa;"></i>
                <span style="font-weight: 700; color: #93c5fd; font-size: 0.84rem;">1-Click Demo Fill</span>
            </div>
            <span style="font-size: 0.74rem; background: rgba(59, 130, 246, 0.25); color: #93c5fd; padding: 3px 10px; border-radius: var(--radius-full); font-weight: 700; font-family: var(--font-mono);">
                demo@example.com
            </span>
        </div>

        <!-- Login Form -->
        <form action="login.php" method="POST" id="loginForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

            <!-- Email -->
            <div class="form-group">
                <label class="form-label" for="loginEmail">Email Address</label>
                <div class="input-icon-wrapper">
                    <i class="fa-solid fa-envelope"></i>
                    <input type="email" name="email" id="loginEmail" required value="<?= htmlspecialchars($email) ?>" placeholder="alex.morgan@example.com" class="form-control" autocomplete="email" autofocus>
                </div>
            </div>

            <!-- Password -->
            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 7px;">
                    <label class="form-label" for="loginPassword" style="margin-bottom: 0;">Password</label>
                    <a href="javascript:void(0)" onclick="openForgotModal()" class="forgot-link">Forgot password?</a>
                </div>
                <div class="input-icon-wrapper">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="password" id="loginPassword" required placeholder="••••••••••••" class="form-control" style="padding-right: 42px;" autocomplete="current-password">
                    <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('loginPassword', this)" title="Show / Hide Password" aria-label="Toggle password visibility">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>

            <!-- Remember Me -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin: 18px 0 24px;">
                <label style="display: flex; align-items: center; gap: 8px; font-size: 0.86rem; color: #94a3b8; cursor: pointer;">
                    <input type="checkbox" name="remember_me" value="1" <?= !empty($_COOKIE['remember_email']) ? 'checked' : '' ?> style="accent-color: #3b82f6; width: 16px; height: 16px;">
                    <span>Remember me on this device</span>
                </label>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn-fintech-gradient" id="signInBtn">
                <i class="fa-solid fa-arrow-right-to-bracket"></i>
                <span>Sign In</span>
            </button>
        </form>

        <div style="text-align: center; margin-top: 24px; font-size: 0.88rem; color: var(--text-secondary);">
            Don't have an account? 
            <a href="register.php" style="color: #60a5fa; font-weight: 700; margin-left: 4px; text-decoration: none;">
                Create Account &rarr;
            </a>
        </div>
    </div>
</div>

<!-- Forgot Password Modal -->
<div class="modal-backdrop" id="forgotModal" onclick="if(event.target===this) closeForgotModal()">
    <div class="modal-dialog" style="max-width: 440px;">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fa-solid fa-key" style="color: #60a5fa;"></i> Password Assistance
            </h3>
            <button type="button" class="modal-close" onclick="closeForgotModal()" aria-label="Close modal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <p style="font-size: 0.88rem; color: var(--text-secondary); line-height: 1.5; margin-bottom: 16px;">
                For evaluation in local XAMPP environments, pre-seeded demo account credentials are:
            </p>
            <div style="background: rgba(20, 31, 54, 0.8); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: var(--radius-md); padding: 14px; font-family: var(--font-mono); font-size: 0.86rem; color: #93c5fd; margin-bottom: 16px;">
                <div><strong>Email:</strong> demo@example.com</div>
                <div><strong>Password:</strong> password123</div>
            </div>
            <button type="button" class="btn btn-primary" onclick="fillDemoCredentials(); closeForgotModal();" style="width: 100%;">
                <i class="fa-solid fa-wand-magic-sparkles"></i> Auto-Fill Demo Credentials
            </button>
        </div>
    </div>
</div>

<script src="assets/js/validation.js"></script>
<script>
function fillDemoCredentials() {
    const emailField = document.getElementById('loginEmail');
    const passField = document.getElementById('loginPassword');
    if (emailField) emailField.value = 'demo@example.com';
    if (passField) passField.value = 'password123';
    
    const btn = document.getElementById('demoFillBtn');
    if (btn) {
        btn.style.background = 'rgba(16, 185, 129, 0.2)';
        btn.style.borderColor = 'rgba(16, 185, 129, 0.5)';
        btn.innerHTML = '<span style="color: #34d399; font-weight: 700; font-size: 0.85rem;"><i class="fa-solid fa-check"></i> Demo Credentials Injected!</span>';
        setTimeout(() => {
            btn.style.background = 'rgba(59, 130, 246, 0.12)';
            btn.style.borderColor = 'rgba(59, 130, 246, 0.3)';
            btn.innerHTML = '<div style="display: flex; align-items: center; gap: 8px;"><i class="fa-solid fa-wand-magic-sparkles" style="color: #60a5fa;"></i><span style="font-weight: 700; color: #93c5fd; font-size: 0.84rem;">1-Click Demo Fill</span></div><span style="font-size: 0.74rem; background: rgba(59, 130, 246, 0.25); color: #93c5fd; padding: 3px 10px; border-radius: var(--radius-full); font-weight: 700; font-family: var(--font-mono);">demo@example.com</span>';
        }, 2000);
    }
}

function openForgotModal() {
    document.getElementById('forgotModal').classList.add('show');
}

function closeForgotModal() {
    document.getElementById('forgotModal').classList.remove('show');
}

function togglePasswordVisibility(fieldId, btn) {
    const input = document.getElementById(fieldId);
    if (!input) return;
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    const icon = btn.querySelector('i');
    if (icon) {
        icon.className = isPassword ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
    }
}
</script>

<!-- Floating Ask Me Help AI Chatbot -->
<?php require_once __DIR__ . '/includes/chatbot.php'; ?>

</body>
</html>
