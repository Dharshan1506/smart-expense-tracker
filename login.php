<?php
/**
 * User Login Page
 * Modern Fintech Redesign with Secure Session Authentication & 1-Click Demo Fill
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
        $error = 'Invalid security token. Please refresh and try again.';
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

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | Smart Expense Tracker</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #090d16 0%, #0f172a 50%, #1e1b4b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .auth-container {
            width: 100%;
            max-width: 440px;
        }
        .auth-card {
            background: #ffffff;
            border-radius: var(--radius-xl);
            padding: 40px 36px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .auth-logo {
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, #3b82f6, #6366f1);
            border-radius: var(--radius-lg);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.4rem;
            box-shadow: 0 8px 18px rgba(59, 130, 246, 0.35);
            margin-bottom: 16px;
        }
        .demo-pill-btn {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            padding: 11px 16px;
            border-radius: var(--radius-md);
            font-size: 0.82rem;
            color: #334155;
            cursor: pointer;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: var(--transition);
        }
        .demo-pill-btn:hover {
            background: #eff6ff;
            border-color: #3b82f6;
            color: #1e40af;
        }
    </style>
</head>
<body>

<div class="auth-container">
    <div class="auth-card">
        <div style="text-align: center; margin-bottom: 24px;">
            <div class="auth-logo">
                <i class="fa-solid fa-wallet"></i>
            </div>
            <h1 style="font-size: 1.55rem; font-weight: 800; color: var(--text-primary); margin-bottom: 4px; letter-spacing: -0.02em;">
                Welcome Back
            </h1>
            <p style="font-size: 0.88rem; color: var(--text-secondary); margin: 0;">
                Sign in to your ExpenseIQ Financial Dashboard
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

        <!-- 1-Click Demo Account Fast Fill -->
        <div class="demo-pill-btn" onclick="fillDemo()" id="demoFillBtn" title="Click to autofill pre-seeded demonstration credentials">
            <div style="display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-wand-magic-sparkles" style="color: #3b82f6;"></i>
                <span style="font-weight: 700;">Demo Account Fill</span>
            </div>
            <span style="font-size: 0.75rem; background: #e0e7ff; color: #4338ca; padding: 2px 8px; border-radius: var(--radius-full); font-weight: 700;">
                demo@example.com
            </span>
        </div>

        <form action="login.php" method="POST" id="loginForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

            <div class="form-group">
                <label class="form-label" for="loginEmail">Email Address</label>
                <div class="input-icon-wrapper">
                    <i class="fa-solid fa-envelope"></i>
                    <input type="email" name="email" id="loginEmail" required value="<?= htmlspecialchars($email) ?>" placeholder="alex.morgan@example.com" class="form-control">
                </div>
            </div>

            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <label class="form-label" for="loginPassword" style="margin-bottom: 0;">Password</label>
                    <span style="font-size: 0.78rem; color: var(--text-muted);">password123</span>
                </div>
                <div class="input-icon-wrapper">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="password" id="loginPassword" required placeholder="••••••••••••" class="form-control" style="padding-right: 40px;">
                    <button type="button" onclick="togglePasswordVisibility('loginPassword', this)" style="position: absolute; right: 12px; background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 0.9rem;" title="Show/Hide Password">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 11px; font-size: 0.95rem; margin-top: 10px;">
                <i class="fa-solid fa-arrow-right-to-bracket"></i> Sign In to Dashboard
            </button>
        </form>

        <div style="text-align: center; margin-top: 24px; font-size: 0.88rem; color: var(--text-secondary);">
            Don't have an account? 
            <a href="register.php" style="color: var(--brand-primary); font-weight: 700;">Create Account</a>
        </div>

        <div style="margin-top: 22px; padding-top: 18px; border-top: 1px solid var(--border-color); text-align: center;">
            <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">
                <i class="fa-solid fa-database"></i> College DBMS Academic Project • MySQL 3NF
            </span>
        </div>
    </div>
</div>

<script>
function fillDemo() {
    document.getElementById('loginEmail').value = 'demo@example.com';
    document.getElementById('loginPassword').value = 'password123';
    const btn = document.getElementById('demoFillBtn');
    btn.style.background = '#dcfce7';
    btn.style.borderColor = '#86efac';
    btn.innerHTML = '<span style="color: #15803d; font-weight: 700;"><i class="fa-solid fa-check"></i> Demo Credentials Injected!</span>';
    setTimeout(() => {
        btn.style.background = '#f8fafc';
        btn.style.borderColor = '#cbd5e1';
        btn.innerHTML = '<div style="display: flex; align-items: center; gap: 8px;"><i class="fa-solid fa-wand-magic-sparkles" style="color: #3b82f6;"></i><span style="font-weight: 700;">Demo Account Fill</span></div><span style="font-size: 0.75rem; background: #e0e7ff; color: #4338ca; padding: 2px 8px; border-radius: 9999px; font-weight: 700;">demo@example.com</span>';
    }, 2500);
}

function togglePasswordVisibility(id, btn) {
    const input = document.getElementById(id);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>

</body>
</html>
