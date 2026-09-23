<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* Auth check */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

/* isAdmin() */
if (!function_exists('isAdmin')) {
    function isAdmin()
    {
        return (int) ($_SESSION['role_id'] ?? 0) === 1;
    }
}

/* hasPermission() */
if (!function_exists('hasPermission')) {
    function hasPermission($permKey)
    {
        if (isAdmin()) return true;
        $perms = $_SESSION['permissions'] ?? [];
        return in_array($permKey, $perms, true);
    }
}

/* requireAdmin() — kept for compatibility */
if (!function_exists('requireAdmin')) {
    function requireAdmin()
    {
        if (!isAdmin()) {
            header("Location: index.php");
            exit();
        }
    }
}

/* requirePermission() */
if (!function_exists('requirePermission')) {
    function requirePermission($permKey)
    {
        if (!hasPermission($permKey)) {
            header("Location: index.php");
            exit();
        }
    }
}
