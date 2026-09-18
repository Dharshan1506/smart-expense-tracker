<?php
/**
 * User Registration Page
 * Modern Fintech Redesign with Client & Server-side Validation & Instant Account Provisioning
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

    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    }

    if (empty($name) || strlen($name) < 2) {
        $errors[] = 'Please enter your full name (at least 2 characters).';
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
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
                // Hash password securely
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

                $insertStmt = $pdo->prepare("INSERT INTO users (name, email, password, created_at) VALUES (?, ?, ?, NOW())");
                $insertStmt->execute([$name, $email, $hashedPassword]);
                $newUserId = (int)$pdo->lastInsertId();

                // Add welcoming alert
                $alertStmt = $pdo->prepare("INSERT INTO alerts (user_id, message, alert_type, created_at) VALUES (?, ?, 'success', NOW())");
                $alertStmt->execute([$newUserId, 'Welcome to ExpenseIQ! Start by adding your first transaction or creating a monthly budget limit.']);

                // Auto sign-in
                session_regenerate_id(true);
                $_SESSION['user_id'] = $newUserId;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;

                set_flash('success', 'Account created successfully! Welcome to your dashboard, ' . htmlspecialchars($name) . '.');
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
    <title>Create Free Account | Smart Expense Tracker</title>
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
            max-width: 480px;
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
    </style>
</head>
<body>

<div class="auth-container">
    <div class="auth-card">
        <div style="text-align: center; margin-bottom: 24px;">
            <div class="auth-logo">
                <i class="fa-solid fa-user-plus"></i>
            </div>
            <h1 style="font-size: 1.55rem; font-weight: 800; color: var(--text-primary); margin-bottom: 4px; letter-spacing: -0.02em;">
                Create Account
            </h1>
            <p style="font-size: 0.88rem; color: var(--text-secondary); margin: 0;">
                Get started with your Smart Expense Tracker
            </p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="flash-alert flash-danger" style="display: block;">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <strong>Please resolve the following:</strong>
                </div>
                <ul style="margin: 0; padding-left: 20px; font-size: 0.84rem; line-height: 1.5;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST" id="registerForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

            <div class="form-group">
                <label class="form-label" for="regName">Full Name</label>
                <div class="input-icon-wrapper">
                    <i class="fa-solid fa-user"></i>
                    <input type="text" name="name" id="regName" required value="<?= htmlspecialchars($name) ?>" placeholder="Alex Morgan" class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="regEmail">Email Address</label>
                <div class="input-icon-wrapper">
                    <i class="fa-solid fa-envelope"></i>
                    <input type="email" name="email" id="regEmail" required value="<?= htmlspecialchars($email) ?>" placeholder="alex@example.com" class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="regPassword">Password (minimum 6 characters)</label>
                <div class="input-icon-wrapper">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="password" id="regPassword" required minlength="6" placeholder="••••••••••••" class="form-control" style="padding-right: 40px;">
                    <button type="button" onclick="togglePasswordVisibility('regPassword', this)" style="position: absolute; right: 12px; background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 0.9rem;" title="Show/Hide Password">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="regConfirmPassword">Confirm Password</label>
                <div class="input-icon-wrapper">
                    <i class="fa-solid fa-shield-check"></i>
                    <input type="password" name="confirm_password" id="regConfirmPassword" required placeholder="••••••••••••" class="form-control" style="padding-right: 40px;">
                    <button type="button" onclick="togglePasswordVisibility('regConfirmPassword', this)" style="position: absolute; right: 12px; background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 0.9rem;" title="Show/Hide Password">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 11px; font-size: 0.95rem; margin-top: 10px;">
                <i class="fa-solid fa-user-check"></i> Create Account & Launch
            </button>
        </form>

        <div style="text-align: center; margin-top: 24px; font-size: 0.88rem; color: var(--text-secondary);">
            Already have an account? 
            <a href="login.php" style="color: var(--brand-primary); font-weight: 700;">Sign In</a>
        </div>

        <div style="margin-top: 22px; padding-top: 18px; border-top: 1px solid var(--border-color); text-align: center;">
            <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">
                <i class="fa-solid fa-database"></i> College DBMS Academic Project • MySQL 3NF
            </span>
        </div>
    </div>
</div>

<script>
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
