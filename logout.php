<?php
/**
 * User Logout Page
 * Completely clears and destroys user session
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

// Unset all session variables
$_SESSION = [];

// Destroy session cookie if set
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Start a fresh session just to show the logout flash message
session_start();
set_flash('info', 'You have been successfully logged out.');

header('Location: login.php');
exit;
