<?php
/**
 * User Registration Page - ExpenseIQ Premium Fintech SaaS
 * Full-Screen Split Layout with 3D Floating Financial Elements & Password Strength Meter
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    // Validate CSRF
    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Security validation failed. Please refresh the page and try again.';
    }

    // Validate fields
    if (empty($name)) {
        $errors[] = 'Full Name is required.';
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters in length.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Password and Confirmation Password do not match.';
    }

    if (empty($errors)) {
        try {
            $pdo = getDBConnection();

            // Check if email already exists
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'This email address is already registered. Please sign in instead.';
            } else {
                // Hash password securely with BCRYPT
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

                $insertStmt = $pdo->prepare("INSERT INTO users (name, email, password, created_at) VALUES (?, ?, ?, NOW())");
                $insertStmt->execute([$name, $email, $hashedPassword]);
                $newUserId = (int)$pdo->lastInsertId();

                // Auto sign-in new user
                session_regenerate_id(true);
                $_SESSION['user_id'] = $newUserId;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;

                set_flash('success', 'Account created successfully! Welcome to ExpenseIQ, ' . htmlspecialchars($name) . '.');
                header('Location: dashboard.php');
                exit;
            }
        } catch (Exception $e) {
            $errors[] = 'Registration failed due to a database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Free Account | ExpenseIQ - Modern Fintech SaaS</title>
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
            background: radial-gradient(circle, rgba(16, 185, 129, 0.22) 0%, transparent 70%);
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
            background: linear-gradient(135deg, #34d399 0%, #38bdf8 50%, #818cf8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-subtitle {
            font-size: 0.95rem;
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 32px;
        }

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
            border-left: 4px solid #10b981;
            animation: floatSlow 5.5s ease-in-out infinite 1s;
        }

        .card-badge-pill {
            font-size: 0.72rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: var(--radius-full);
        }

        .auth-form-pane {
            padding: 44px 42px;
            background: rgba(10, 16, 30, 0.85);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-pane-header {
            margin-bottom: 24px;
        }

        .form-pane-title {
            font-size: 1.7rem;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.03em;
            margin-bottom: 6px;
        }

        .strength-meter-bar {
            height: 5px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 9999px;
            overflow: hidden;
            margin-top: 8px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 4px;
        }

        .strength-segment {
            height: 100%;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 9999px;
            transition: background-color 0.3s ease;
        }

        .strength-label {
            font-size: 0.72rem;
            color: var(--text-muted);
            margin-top: 5px;
            display: flex;
            justify-content: space-between;
            font-weight: 600;
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
                Smart Financial Control & <span class="hero-title-accent">Autonomous Wealth</span>
            </h2>
            <p class="hero-subtitle">
                Join thousands optimizing their personal cashflow with automated category budgets, intelligent spending warnings, and 3NF relational data design in Indian Rupees (₹).
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
                            <div style="font-size: 0.72rem; text-transform: uppercase; color: #94a3b8; font-weight: 700; letter-spacing: 0.05em;">Monthly Inflow</div>
                            <strong style="font-size: 1.22rem; color: #ffffff; font-family: var(--font-mono); font-weight: 800;">+ ₹50,000</strong>
                        </div>
                    </div>
                    <span class="card-badge-pill" style="background: rgba(59, 130, 246, 0.2); color: #93c5fd; border: 1px solid rgba(59, 130, 246, 0.35);">
                        Verified
                    </span>
                </div>

                <!-- 2. Expenses Card -->
                <div class="card-3d-item card-expense-3d">
                    <div style="display: flex; align-items: center; gap: 14px;">
                        <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(244, 63, 94, 0.2); color: #fb7185; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                            <i class="fa-solid fa-shield-halved"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.72rem; text-transform: uppercase; color: #94a3b8; font-weight: 700; letter-spacing: 0.05em;">Cap Target</div>
                            <strong style="font-size: 1.22rem; color: #f87171; font-family: var(--font-mono); font-weight: 800;">- ₹18,500</strong>
                        </div>
                    </div>
                    <span class="card-badge-pill" style="background: rgba(244, 63, 94, 0.2); color: #fca5a5; border: 1px solid rgba(244, 63, 94, 0.35);">
                        Controlled
                    </span>
                </div>

                <!-- 3. Balance Card -->
                <div class="card-3d-item card-balance-3d">
                    <div style="display: flex; align-items: center; gap: 14px;">
                        <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(16, 185, 129, 0.2); color: #34d399; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                            <i class="fa-solid fa-piggy-bank"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.72rem; text-transform: uppercase; color: #94a3b8; font-weight: 700; letter-spacing: 0.05em;">Net Balance</div>
                            <strong style="font-size: 1.22rem; color: #34d399; font-family: var(--font-mono); font-weight: 800;">₹31,500</strong>
                        </div>
                    </div>
                    <span class="card-badge-pill" style="background: rgba(16, 185, 129, 0.2); color: #6ee7b7; border: 1px solid rgba(16, 185, 129, 0.35);">
                        63% Saved
                    </span>
                </div>
            </div>
        </div>

        <!-- Left Hero Footer -->
        <div style="display: flex; justify-content: space-between; font-size: 0.76rem; color: #64748b; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.06);">
            <span><i class="fa-solid fa-lock" style="color: #10b981; margin-right: 5px;"></i> BCRYPT Hashed Passwords</span>
            <span>PHP 8 &bull; MySQL 8 &bull; PDO</span>
        </div>
    </div>

    <!-- RIGHT SIDE: Create Account Panel -->
    <div class="auth-form-pane">
        <div class="form-pane-header">
            <h1 class="form-pane-title">Create Account</h1>
            <p style="font-size: 0.88rem; color: var(--text-secondary); margin: 0;">
                Get started in seconds with intelligent personal finance tracking
            </p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="flash-alert flash-danger">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div>
                    <?php foreach ($errors as $err): ?>
                        <div><?= htmlspecialchars($err) ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Create Account Form -->
        <form action="register.php" method="POST" id="registerForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

            <!-- Full Name -->
            <div class="form-group">
                <label class="form-label" for="regName">Full Name</label>
                <div class="input-icon-wrapper">
                    <i class="fa-solid fa-user"></i>
                    <input type="text" name="name" id="regName" required value="<?= htmlspecialchars($name) ?>" placeholder="Alex Morgan" class="form-control" autocomplete="name" autofocus>
                </div>
            </div>

            <!-- Email -->
            <div class="form-group">
                <label class="form-label" for="regEmail">Email Address</label>
                <div class="input-icon-wrapper">
                    <i class="fa-solid fa-envelope"></i>
                    <input type="email" name="email" id="regEmail" required value="<?= htmlspecialchars($email) ?>" placeholder="alex.morgan@example.com" class="form-control" autocomplete="email">
                </div>
            </div>

            <!-- Password with Strength Meter -->
            <div class="form-group">
                <label class="form-label" for="regPassword">Password</label>
                <div class="input-icon-wrapper">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="password" id="regPassword" required placeholder="Minimum 6 characters" class="form-control" style="padding-right: 42px;" autocomplete="new-password" oninput="checkPasswordStrength(this.value)">
                    <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('regPassword', this)" title="Show / Hide Password" aria-label="Toggle password visibility">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
                <!-- Dynamic Strength Meter -->
                <div class="strength-meter-bar" id="strengthBar">
                    <div class="strength-segment" id="seg1"></div>
                    <div class="strength-segment" id="seg2"></div>
                    <div class="strength-segment" id="seg3"></div>
                    <div class="strength-segment" id="seg4"></div>
                </div>
                <div class="strength-label">
                    <span id="strengthText">Enter at least 6 characters</span>
                    <span id="strengthScore"></span>
                </div>
            </div>

            <!-- Confirm Password -->
            <div class="form-group">
                <label class="form-label" for="regConfirmPassword">Confirm Password</label>
                <div class="input-icon-wrapper">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="confirm_password" id="regConfirmPassword" required placeholder="Repeat your password" class="form-control" style="padding-right: 42px;" autocomplete="new-password">
                    <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('regConfirmPassword', this)" title="Show / Hide Password" aria-label="Toggle password visibility">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn-fintech-gradient" style="margin-top: 6px;">
                <i class="fa-solid fa-user-plus"></i>
                <span>Create Free Account</span>
            </button>
        </form>

        <div style="text-align: center; margin-top: 24px; font-size: 0.88rem; color: var(--text-secondary);">
            Already have an account? 
            <a href="login.php" style="color: #60a5fa; font-weight: 700; margin-left: 4px; text-decoration: none;">
                Sign In &rarr;
            </a>
        </div>
    </div>
</div>

<script src="assets/js/validation.js"></script>
<script>
function checkPasswordStrength(password) {
    const seg1 = document.getElementById('seg1');
    const seg2 = document.getElementById('seg2');
    const seg3 = document.getElementById('seg3');
    const seg4 = document.getElementById('seg4');
    const text = document.getElementById('strengthText');

    let score = 0;
    if (password.length >= 6) score++;
    if (password.length >= 8) score++;
    if (/[A-Z]/.test(password) && /[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;

    [seg1, seg2, seg3, seg4].forEach(s => s.style.backgroundColor = 'rgba(255, 255, 255, 0.08)');

    if (score === 0) {
        text.textContent = 'Too short (min 6 characters)';
        text.style.color = '#ef4444';
    } else if (score === 1) {
        seg1.style.backgroundColor = '#ef4444';
        text.textContent = 'Weak password';
        text.style.color = '#f87171';
    } else if (score === 2) {
        seg1.style.backgroundColor = '#f59e0b';
        seg2.style.backgroundColor = '#f59e0b';
        text.textContent = 'Fair password';
        text.style.color = '#fbbf24';
    } else if (score === 3) {
        seg1.style.backgroundColor = '#3b82f6';
        seg2.style.backgroundColor = '#3b82f6';
        seg3.style.backgroundColor = '#3b82f6';
        text.textContent = 'Good password';
        text.style.color = '#60a5fa';
    } else {
        seg1.style.backgroundColor = '#10b981';
        seg2.style.backgroundColor = '#10b981';
        seg3.style.backgroundColor = '#10b981';
        seg4.style.backgroundColor = '#10b981';
        text.textContent = 'Strong password! 🔒';
        text.style.color = '#34d399';
    }
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
