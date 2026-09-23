<?php

include 'constant.php';
include 'session.php';

$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$isEdit = $editId > 0;

requirePermission($isEdit ? 'role.edit' : 'role.create');

$pageTitle = $isEdit ? 'Edit Role | Billing Portal' : 'Add Role | Billing Portal';

/* =========================================================
   FORM STATE
   ========================================================= */
$error     = '';
$errorList = [];

$old = [
    'role_name' => '',
    'role_desc' => '',
    'active'    => 1,
];

$isSystemRole = false;

/* =========================================================
   LOAD (Edit mode)
   ========================================================= */
if ($isEdit) {

    $res = mysqli_query($conn, "SELECT * FROM role WHERE id = $editId LIMIT 1");

    if (!$res || mysqli_num_rows($res) !== 1) {
        header("Location: roles-list.php");
        exit;
    }

    $row = mysqli_fetch_assoc($res);

    $old['role_name'] = $row['role_name'] ?? '';
    $old['role_desc'] = $row['role_desc'] ?? '';
    $old['active']    = (int) ($row['active'] ?? 1);

    // Admin role (id=1) is a system role — flag it
    $isSystemRole = ($editId === 1);
}

/* =========================================================
   SUBMIT
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $old['role_name'] = trim($_POST['role_name'] ?? '');
    $old['role_desc'] = trim($_POST['role_desc'] ?? '');
    $old['active']    = isset($_POST['active']) ? (int) $_POST['active'] : 1;

    /* Validate */
    if ($old['role_name'] === '') {
        $errorList[] = 'Role name is required.';
    } elseif (strlen($old['role_name']) > 50) {
        $errorList[] = 'Role name is too long (max 50 characters).';
    }

    if (strlen($old['role_desc']) > 255) {
        $errorList[] = 'Description is too long (max 255 characters).';
    }

    /* Duplicate name check */
    if (empty($errorList)) {

        $name_safe = mysqli_real_escape_string($conn, $old['role_name']);

        $sqlDup = "SELECT id FROM role WHERE UPPER(role_name) = UPPER('$name_safe')";
        if ($isEdit) {
            $sqlDup .= " AND id <> $editId";
        }
        $sqlDup .= " LIMIT 1";

        $dup = mysqli_query($conn, $sqlDup);
        if ($dup && mysqli_num_rows($dup) > 0) {
            $errorList[] = 'A role with this name already exists.';
        }
    }

    /* Insert or Update */
    if (empty($errorList)) {

        $esc = function ($v) use ($conn) {
            return mysqli_real_escape_string($conn, $v);
        };

        $name_v   = "'" . $esc($old['role_name']) . "'";
        $desc_v   = $old['role_desc'] !== '' ? "'" . $esc($old['role_desc']) . "'" : "NULL";
        $active_v = (int) $old['active'];

        if ($isEdit) {

            // Safety: cannot disable Admin
            if ($editId === 1) {
                $active_v = 1;
            }

            $sql = "UPDATE role SET
                        role_name = $name_v,
                        role_desc = $desc_v,
                        active    = $active_v
                    WHERE id = $editId";

            if (mysqli_query($conn, $sql)) {
                header("Location: roles-list.php?msg=updated");
                exit;
            } else {
                $errorList[] = 'Failed to update role: ' . mysqli_error($conn);
            }
        } else {

            $sql = "INSERT INTO role (role_name, role_desc, active)
                    VALUES ($name_v, $desc_v, $active_v)";

            if (mysqli_query($conn, $sql)) {
                // Redirect to permissions page for the new role
                $newId = mysqli_insert_id($conn);
                header("Location: role-permissions.php?id=$newId");
                exit;
            } else {
                $errorList[] = 'Failed to save role: ' . mysqli_error($conn);
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

                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">

                        <h1 class="h3 mb-0 text-gray-800">
                            <i class="fas <?= $isEdit ? 'fa-user-shield' : 'fa-plus-circle' ?> mr-2"></i>
                            <?= $isEdit ? 'Edit Role' : 'Add Role' ?>
                        </h1>

                        <a href="roles-list.php"
                            class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
                            <i class="fas fa-arrow-left fa-sm text-white-50 mr-1"></i>
                            Back to Roles
                        </a>

                    </div>

                    <!-- Error alert -->
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

                    <!-- System role warning -->
                    <?php if ($isSystemRole): ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <i class="fas fa-shield-alt mr-1"></i>
                            <strong>System role.</strong>
                            The Admin role is used to bootstrap the system. Its name can be changed,
                            but it cannot be disabled.
                            <button type="button" class="close" data-dismiss="alert">
                                <span>&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <form id="roleForm" action="" method="POST" novalidate>

                        <input type="hidden" name="active" value="<?= (int) $old['active'] ?>">

                        <!-- ROLE DETAILS -->
                        <div class="card shadow mb-4">

                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-user-shield mr-1"></i>
                                    Role Details
                                </h6>
                            </div>

                            <div class="card-body">

                                <div class="row">

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="role_name">
                                                Role Name <span class="text-danger">*</span>
                                            </label>
                                            <input
                                                type="text"
                                                id="role_name"
                                                name="role_name"
                                                class="form-control"
                                                maxlength="50"
                                                placeholder="e.g. Dispatch Manager"
                                                value="<?= htmlspecialchars($old['role_name'], ENT_QUOTES, 'UTF-8') ?>">
                                            <small class="form-text text-muted">
                                                A short name like "Admin", "Operator", "Accountant"
                                            </small>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="role_desc">Description</label>
                                            <input
                                                type="text"
                                                id="role_desc"
                                                name="role_desc"
                                                class="form-control"
                                                maxlength="255"
                                                placeholder="What this role can do (short note)"
                                                value="<?= htmlspecialchars($old['role_desc'], ENT_QUOTES, 'UTF-8') ?>">
                                            <small class="form-text text-muted">
                                                Optional. Helps you remember the role's purpose.
                                            </small>
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
                                    <?php if ($isEdit): ?>
                                        After saving, you'll return to the roles list.
                                    <?php else: ?>
                                        After saving, you'll be taken to assign permissions to this role.
                                    <?php endif; ?>
                                </div>

                                <div>

                                    <a href="roles-list.php" class="btn btn-secondary">
                                        <i class="fas fa-times mr-1"></i>
                                        Cancel
                                    </a>

                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-check mr-1"></i>
                                        <?= $isEdit ? 'Update Role' : 'Save Role' ?>
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