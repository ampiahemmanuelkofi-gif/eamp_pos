<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

function checkLogin()
{
    if (!isLoggedIn()) {
        header("Location: /eamp_pos/modules/auth/login.php");
        exit();
    }
}

function hasPermission($required_role)
{
    if (!isLoggedIn())
        return false;
    $user_role = $_SESSION['user_role'] ?? '';

    // simple hierarchy: admin > manager > cashier
    $hierarchy = ['cashier' => 1, 'manager' => 2, 'admin' => 3];
    return ($hierarchy[$user_role] ?? 0) >= ($hierarchy[$required_role] ?? 0);
}

function sanitizeInput($input)
{
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input);
    return $input;
}
?>