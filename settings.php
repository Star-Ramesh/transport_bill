<?php

include 'constant.php';
include 'session.php';

$pageTitle = 'Settings | Billing Portal';

$success = '';
$error   = '';

$userId = (int) ($_SESSION['user_id'] ?? 0);

/* =========================================================
   HANDLE PASSWORD CHANGE
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {

    $current  = $_POST['current_password'] ?? '';
    $new      = $_POST['new_password']     ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // 1. Basic validation
    if ($current === '' || $new === '' || $confirm === '') {
        $error = 'All three fields are required.';
    } elseif (strlen($new) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New password and confirmation do not match.';
    } elseif ($new === $current) {
        $error = 'New password must be different from current password.';
    } else {

        // 2. Load the user
        $res = mysqli_query($conn, "SELECT id, password FROM `user` WHERE id = $userId LIMIT 1");

        if (!$res || mysqli_num_rows($res) !== 1) {
            $error = 'User not found. Please log in again.';
        } else {

            $row = mysqli_fetch_assoc($res);
            $stored = $row['password'];

            // 3. Verify current password
            if ($current !== $stored) {
                $error = 'Current password is incorrect.';
            } else {

                // 4. Save new password
                $newSafe = mysqli_real_escape_string($conn, $new);

                $sql = "UPDATE `user` SET password = '$newSafe' WHERE id = $userId";

                if (mysqli_query($conn, $sql)) {
                    $success = 'Password changed successfully.';
                } else {
                    $error = 'Failed to update password: ' . mysqli_error($conn);
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title><?= $pageTitle ?></title>

    <?php include 'layout/header.php'; ?>

</head>

<body id="page-top">

    <div id="wrapper">

        <?php include 'layout/sidebar.php'; ?>

        <div id="content-wrapper" class="d-flex flex-column">

            <div id="content">

                <?php include 'layout/topbar.php'; ?>

                <div class="container-fluid">

                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">
                            <i class="fas fa-cogs mr-2"></i>Settings
                        </h1>
                    </div>

                    <!-- Success / error -->
                    <?php if ($success !== ''): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle mr-1"></i>
                            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                            <button type="button" class="close" data-dismiss="alert">
                                <span>&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle mr-1"></i>
                            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                            <button type="button" class="close" data-dismiss="alert">
                                <span>&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <div class="row">

                        <div class="col-lg-6">

                            <!-- Change Password Card -->
                            <div class="card shadow mb-4">

                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="fas fa-lock mr-1"></i>
                                        Change Password
                                    </h6>
                                </div>

                                <div class="card-body">

                                    <form method="POST" action="" novalidate>

                                        <input type="hidden" name="change_password" value="1">

                                        <div class="form-group">
                                            <label for="current_password">
                                                Current Password <span class="text-danger">*</span>
                                            </label>
                                            <input
                                                type="password"
                                                id="current_password"
                                                name="current_password"
                                                class="form-control"
                                                autocomplete="current-password"
                                                required>
                                        </div>

                                        <div class="form-group">
                                            <label for="new_password">
                                                New Password <span class="text-danger">*</span>
                                            </label>
                                            <input
                                                type="password"
                                                id="new_password"
                                                name="new_password"
                                                class="form-control"
                                                autocomplete="new-password"
                                                minlength="6"
                                                required>
                                            <small class="form-text text-muted">
                                                Minimum 6 characters.
                                            </small>
                                        </div>

                                        <div class="form-group">
                                            <label for="confirm_password">
                                                Confirm New Password <span class="text-danger">*</span>
                                            </label>
                                            <input
                                                type="password"
                                                id="confirm_password"
                                                name="confirm_password"
                                                class="form-control"
                                                autocomplete="new-password"
                                                minlength="6"
                                                required>
                                        </div>

                                        <hr>

                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-check mr-1"></i>
                                            Change Password
                                        </button>

                                        <button type="reset" class="btn btn-secondary">
                                            <i class="fas fa-redo mr-1"></i>
                                            Reset
                                        </button>

                                    </form>

                                </div>

                            </div>
                            <!-- End Change Password Card -->

                        </div>

                    </div>

                </div>

            </div>

            <?php include 'layout/footer.php'; ?>

        </div>

    </div>

</body>

</html>