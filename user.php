<?php

include 'constant.php';
include 'session.php';

$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$isEdit = $editId > 0;

requirePermission($isEdit ? 'user.edit' : 'user.create');

$pageTitle = $isEdit ? 'Edit User | Billing Portal' : 'Add User | Billing Portal';

$error     = '';
$errorList = [];

$old = [
    'username'  => '',
    'full_name' => '',
    'branch_id' => 0,
    'role_id'   => 0,
    'active'    => 1,
];

/* =========================================================
   LOAD (Edit mode)
   ========================================================= */
if ($isEdit) {

    $res = mysqli_query($conn, "SELECT * FROM `user` WHERE id = $editId LIMIT 1");

    if (!$res || mysqli_num_rows($res) !== 1) {
        header("Location: users-list.php");
        exit;
    }

    $row = mysqli_fetch_assoc($res);

    $old['username']  = $row['username']  ?? '';
    $old['full_name'] = $row['full_name'] ?? '';
    $old['branch_id'] = (int) ($row['branch_id'] ?? 0);
    $old['role_id']   = (int) ($row['role_id']   ?? 0);
    $old['active']    = (int) ($row['active']    ?? 1);
}

/* =========================================================
   DROPDOWN DATA
   ========================================================= */
$branches = [];
$resB = mysqli_query($conn, "SELECT id, branch_name FROM branch WHERE active = 1 ORDER BY branch_name");
if ($resB) {
    while ($b = mysqli_fetch_assoc($resB)) {
        $branches[] = $b;
    }
}

$roles = [];
$resR = mysqli_query($conn, "SELECT id, role_name FROM role WHERE active = 1 ORDER BY id");
if ($resR) {
    while ($r = mysqli_fetch_assoc($resR)) {
        $roles[] = $r;
    }
}

/* =========================================================
   SUBMIT
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $old['username']  = strtolower(trim($_POST['username']  ?? ''));
    $old['full_name'] = trim($_POST['full_name'] ?? '');
    $old['branch_id'] = (int) ($_POST['branch_id'] ?? 0);
    $old['role_id']   = (int) ($_POST['role_id']   ?? 0);
    $old['active']    = isset($_POST['active']) ? (int) $_POST['active'] : 1;

    $password        = $_POST['password']         ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    /* Validate */
    if ($old['username'] === '') {
        $errorList[] = 'Username is required.';
    } elseif (!preg_match('/^[a-z0-9_\.]{3,30}$/', $old['username'])) {
        $errorList[] = 'Username must be 3-30 chars (a-z, 0-9, _, .)';
    }

    if ($old['full_name'] === '') {
        $errorList[] = 'Full name is required.';
    }

    if ($old['branch_id'] <= 0) {
        $errorList[] = 'Please select a branch.';
    }

    if ($old['role_id'] <= 0) {
        $errorList[] = 'Please select a role.';
    }

    /* Password rules */
    if (!$isEdit) {
        // Add mode: password required
        if ($password === '') {
            $errorList[] = 'Password is required.';
        } elseif (strlen($password) < 6) {
            $errorList[] = 'Password must be at least 6 characters.';
        } elseif ($password !== $passwordConfirm) {
            $errorList[] = 'Password and confirmation do not match.';
        }
    } else {
        // Edit mode: password optional, but if given must be valid
        if ($password !== '') {
            if (strlen($password) < 6) {
                $errorList[] = 'New password must be at least 6 characters.';
            } elseif ($password !== $passwordConfirm) {
                $errorList[] = 'New password and confirmation do not match.';
            }
        }
    }

    /* Duplicate username */
    if (empty($errorList)) {

        $u_safe = mysqli_real_escape_string($conn, $old['username']);

        $sqlDup = "SELECT id FROM `user` WHERE LOWER(username) = LOWER('$u_safe')";
        if ($isEdit) {
            $sqlDup .= " AND id <> $editId";
        }
        $sqlDup .= " LIMIT 1";

        $dup = mysqli_query($conn, $sqlDup);
        if ($dup && mysqli_num_rows($dup) > 0) {
            $errorList[] = 'This username is already taken.';
        }
    }

    /* Insert / Update */
    if (empty($errorList)) {

        $esc = function ($v) use ($conn) {
            return mysqli_real_escape_string($conn, $v);
        };

        $username_v  = "'" . $esc($old['username'])  . "'";
        $full_name_v = "'" . $esc($old['full_name']) . "'";
        $branch_v    = (int) $old['branch_id'];
        $role_v      = (int) $old['role_id'];
        $active_v    = (int) $old['active'];

        if ($isEdit) {

            $sql = "UPDATE `user` SET
                        username  = $username_v,
                        full_name = $full_name_v,
                        branch_id = $branch_v,
                        role_id   = $role_v,
                        active    = $active_v";

            if ($password !== '') {
                $pass_v = "'" . $esc($password) . "'";
                $sql .= ", password = $pass_v";
            }

            $sql .= " WHERE id = $editId";

            if (mysqli_query($conn, $sql)) {
                header("Location: users-list.php?msg=updated");
                exit;
            } else {
                $errorList[] = 'Failed to update user: ' . mysqli_error($conn);
            }
        } else {

            $pass_v = "'" . $esc($password) . "'";

            $sql = "INSERT INTO `user`
                        (username, password, full_name, branch_id, role_id, active)
                    VALUES
                        ($username_v, $pass_v, $full_name_v, $branch_v, $role_v, $active_v)";

            if (mysqli_query($conn, $sql)) {
                header("Location: users-list.php?msg=added");
                exit;
            } else {
                $errorList[] = 'Failed to save user: ' . mysqli_error($conn);
            }
        }
    }

    if (!empty($errorList)) {
        $error = implode('<br>', array_map(function ($e) {
            return '&bull; ' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8');
        }, $errorList));
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

                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">
                            <i class="fas <?= $isEdit ? 'fa-user-edit' : 'fa-user-plus' ?> mr-2"></i>
                            <?= $isEdit ? 'Edit User' : 'Add User' ?>
                        </h1>
                        <a href="users-list.php"
                            class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
                            <i class="fas fa-arrow-left fa-sm text-white-50 mr-1"></i>
                            Back to Users
                        </a>
                    </div>

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle mr-1"></i>
                            <strong>Please fix the following:</strong>
                            <div class="mt-2"><?= $error ?></div>
                            <button type="button" class="close" data-dismiss="alert">
                                <span>&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <form id="userForm" action="" method="POST" novalidate>

                        <input type="hidden" name="active" value="<?= (int) $old['active'] ?>">

                        <!-- USER DETAILS -->
                        <div class="card shadow mb-4">

                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-user mr-1"></i>
                                    User Details
                                </h6>
                            </div>

                            <div class="card-body">

                                <div class="row">

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="full_name">
                                                Full Name <span class="text-danger">*</span>
                                            </label>
                                            <input
                                                type="text"
                                                id="full_name"
                                                name="full_name"
                                                class="form-control"
                                                maxlength="100"
                                                placeholder="e.g. Ramesh Kumar"
                                                value="<?= htmlspecialchars($old['full_name'], ENT_QUOTES, 'UTF-8') ?>">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="username">
                                                Username <span class="text-danger">*</span>
                                            </label>
                                            <input
                                                type="text"
                                                id="username"
                                                name="username"
                                                class="form-control"
                                                maxlength="30"
                                                placeholder="e.g. ramesh"
                                                autocomplete="off"
                                                value="<?= htmlspecialchars($old['username'], ENT_QUOTES, 'UTF-8') ?>">
                                            <small class="form-text text-muted">
                                                Lowercase letters, numbers, _ and . only
                                            </small>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="branch_id">
                                                Branch <span class="text-danger">*</span>
                                            </label>
                                            <select id="branch_id" name="branch_id" class="form-control">
                                                <option value="">— Select Branch —</option>
                                                <?php foreach ($branches as $b): ?>
                                                    <option value="<?= (int) $b['id'] ?>"
                                                        <?= $old['branch_id'] === (int) $b['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($b['branch_name'], ENT_QUOTES, 'UTF-8') ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="role_id">
                                                Role <span class="text-danger">*</span>
                                            </label>
                                            <select id="role_id" name="role_id" class="form-control">
                                                <option value="">— Select Role —</option>
                                                <?php foreach ($roles as $r): ?>
                                                    <option value="<?= (int) $r['id'] ?>"
                                                        <?= $old['role_id'] === (int) $r['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($r['role_name'], ENT_QUOTES, 'UTF-8') ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                </div>

                            </div>

                        </div>

                        <!-- PASSWORD -->
                        <div class="card shadow mb-4">

                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-lock mr-1"></i>
                                    <?= $isEdit ? 'Change Password (optional)' : 'Set Password' ?>
                                </h6>
                            </div>

                            <div class="card-body">

                                <div class="row">

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="password">
                                                Password <?= $isEdit ? '' : '<span class="text-danger">*</span>' ?>
                                            </label>
                                            <input
                                                type="password"
                                                id="password"
                                                name="password"
                                                class="form-control"
                                                autocomplete="new-password"
                                                minlength="6"
                                                placeholder="<?= $isEdit ? 'Leave blank to keep current' : 'Minimum 6 characters' ?>">
                                            <small class="form-text text-muted">
                                                <?= $isEdit
                                                    ? 'Leave blank to keep the current password.'
                                                    : 'Minimum 6 characters.' ?>
                                            </small>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="password_confirm">
                                                Confirm Password <?= $isEdit ? '' : '<span class="text-danger">*</span>' ?>
                                            </label>
                                            <input
                                                type="password"
                                                id="password_confirm"
                                                name="password_confirm"
                                                class="form-control"
                                                autocomplete="new-password"
                                                minlength="6"
                                                placeholder="Re-enter password">
                                        </div>
                                    </div>

                                </div>

                            </div>

                        </div>

                        <!-- ACTION BAR -->
                        <div class="card shadow mb-4">

                            <div class="card-body py-3 d-flex align-items-center justify-content-between flex-wrap">

                                <div class="text-muted small mb-2 mb-sm-0">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Fields marked <span class="text-danger">*</span> are required.
                                </div>

                                <div>
                                    <a href="users-list.php" class="btn btn-secondary">
                                        <i class="fas fa-times mr-1"></i>
                                        Cancel
                                    </a>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-check mr-1"></i>
                                        <?= $isEdit ? 'Update User' : 'Save User' ?>
                                    </button>
                                </div>

                            </div>

                        </div>

                    </form>

                </div>

            </div>

            <?php include 'layout/footer.php'; ?>

        </div>

    </div>

</body>

</html>