<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* Auth check */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

/* =========================================================
   REFRESH USER CONTEXT
   ---------------------------------------------------------
   The user's branch_id, role_id, and active flag may have
   been changed by an Admin since this user logged in.
   We re-read them from the DB on every page load so the
   session stays in sync.

   This also force-logs-out users who were deactivated.
   ========================================================= */
if (!empty($GLOBALS['conn'])) {
    $__sid = (int) $_SESSION['user_id'];

    $__res = mysqli_query(
        $GLOBALS['conn'],
        "SELECT id, username, full_name, branch_id, role_id, active
         FROM `user` WHERE id = $__sid LIMIT 1"
    );

    if ($__res && mysqli_num_rows($__res) === 1) {

        $__row = mysqli_fetch_assoc($__res);

        /* Force logout if the account was deactivated */
        if ((int) $__row['active'] !== 1) {
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            header("Location: login.php");
            exit();
        }

        /* Keep session data in sync with the DB */
        $_SESSION['username']  = $__row['username'];
        $_SESSION['full_name'] = $__row['full_name'] ?: $__row['username'];
        $_SESSION['branch_id'] = (int) ($__row['branch_id'] ?? 0);

        /* If the role changed, reload permissions too */
        $__newRoleId = (int) ($__row['role_id'] ?? 0);
        if ($__newRoleId !== (int) ($_SESSION['role_id'] ?? 0)) {

            $_SESSION['role_id'] = $__newRoleId;

            $__permissions = [];
            if ($__newRoleId > 0) {
                $__permRes = mysqli_query(
                    $GLOBALS['conn'],
                    "SELECT p.perm_key
                     FROM role_permission rp
                     JOIN permission p ON p.id = rp.permission_id
                     WHERE rp.role_id = $__newRoleId"
                );
                if ($__permRes) {
                    while ($__p = mysqli_fetch_assoc($__permRes)) {
                        $__permissions[] = $__p['perm_key'];
                    }
                }
            }
            $_SESSION['permissions'] = $__permissions;
        }
    } else {
        /* User row was deleted — force logout */
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        header("Location: login.php");
        exit();
    }
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
