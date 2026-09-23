<?php

include 'constant.php';
include 'session.php';

$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$isEdit = $editId > 0;

requirePermission($isEdit ? 'branch.edit' : 'branch.create');

$pageTitle = $isEdit ? 'Edit Branch | Billing Portal' : 'Add Branch | Billing Portal';

/* =========================================================
   FORM STATE
   ========================================================= */
$error     = '';
$errorList = [];

$old = [
    'branch_name' => '',
    'branch_code' => '',
    'address'     => '',
    'city'        => '',
    'state'       => '',
    'pincode'     => '',
    'phone'       => '',
    'email'       => '',
    'active'      => 1,
];

/* =========================================================
   LOAD (Edit mode)
   ========================================================= */
if ($isEdit) {

    $res = mysqli_query($conn, "SELECT * FROM branch WHERE id = $editId LIMIT 1");

    if (!$res || mysqli_num_rows($res) !== 1) {
        header("Location: branches-list.php");
        exit;
    }

    $row = mysqli_fetch_assoc($res);

    foreach ($old as $k => $v) {
        $old[$k] = $row[$k] ?? $v;
    }
    $old['active'] = (int) ($row['active'] ?? 1);
}

/* =========================================================
   SUBMIT
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $old['branch_name'] = trim($_POST['branch_name'] ?? '');
    $old['branch_code'] = strtoupper(trim($_POST['branch_code'] ?? ''));
    $old['address']     = trim($_POST['address'] ?? '');
    $old['city']        = trim($_POST['city'] ?? '');
    $old['state']       = trim($_POST['state'] ?? '');
    $old['pincode']     = trim($_POST['pincode'] ?? '');
    $old['phone']       = trim($_POST['phone'] ?? '');
    $old['email']       = trim($_POST['email'] ?? '');
    $old['active']      = isset($_POST['active']) ? (int) $_POST['active'] : 1;

    /* Validate */
    if ($old['branch_name'] === '') {
        $errorList[] = 'Branch name is required.';
    }

    if ($old['branch_code'] === '') {
        $errorList[] = 'Branch code is required.';
    } elseif (!preg_match('/^[A-Z0-9]{2,10}$/', $old['branch_code'])) {
        $errorList[] = 'Branch code must be 2-10 uppercase letters or digits (e.g. KOL, MUM, DEL01).';
    }

    if ($old['pincode'] !== '' && !preg_match('/^[0-9]{6}$/', $old['pincode'])) {
        $errorList[] = 'Pincode must be exactly 6 digits.';
    }

    if ($old['phone'] !== '' && !preg_match('/^[0-9+\-\s]{6,15}$/', $old['phone'])) {
        $errorList[] = 'Invalid phone number.';
    }

    if ($old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errorList[] = 'Invalid email address.';
    }

    /* Duplicate checks */
    if (empty($errorList)) {

        $name_safe = mysqli_real_escape_string($conn, $old['branch_name']);
        $code_safe = mysqli_real_escape_string($conn, $old['branch_code']);

        $sqlDup = "SELECT id, branch_name, branch_code FROM branch
                   WHERE (UPPER(branch_name) = UPPER('$name_safe')
                          OR UPPER(branch_code) = UPPER('$code_safe'))";

        if ($isEdit) {
            $sqlDup .= " AND id <> $editId";
        }
        $sqlDup .= " LIMIT 1";

        $dup = mysqli_query($conn, $sqlDup);

        if ($dup && mysqli_num_rows($dup) > 0) {
            $dupRow = mysqli_fetch_assoc($dup);
            if (strcasecmp($dupRow['branch_name'], $old['branch_name']) === 0) {
                $errorList[] = 'A branch with this name already exists.';
            } else {
                $errorList[] = 'A branch with this code already exists.';
            }
        }
    }

    /* Insert or Update */
    if (empty($errorList)) {

        $esc = function ($v) use ($conn) {
            return mysqli_real_escape_string($conn, $v);
        };

        $branch_name_v = "'" . $esc($old['branch_name']) . "'";
        $branch_code_v = "'" . $esc($old['branch_code']) . "'";
        $address_v     = $old['address'] !== '' ? "'" . $esc($old['address']) . "'" : "NULL";
        $city_v        = $old['city']    !== '' ? "'" . $esc($old['city'])    . "'" : "NULL";
        $state_v       = $old['state']   !== '' ? "'" . $esc($old['state'])   . "'" : "NULL";
        $pincode_v     = $old['pincode'] !== '' ? "'" . $esc($old['pincode']) . "'" : "NULL";
        $phone_v       = $old['phone']   !== '' ? "'" . $esc($old['phone'])   . "'" : "NULL";
        $email_v       = $old['email']   !== '' ? "'" . $esc($old['email'])   . "'" : "NULL";
        $active_v      = (int) $old['active'];

        if ($isEdit) {

            $sql = "UPDATE branch SET
                        branch_name = $branch_name_v,
                        branch_code = $branch_code_v,
                        address     = $address_v,
                        city        = $city_v,
                        state       = $state_v,
                        pincode     = $pincode_v,
                        phone       = $phone_v,
                        email       = $email_v,
                        active      = $active_v
                    WHERE id = $editId";

            if (mysqli_query($conn, $sql)) {
                header("Location: branches-list.php?msg=updated");
                exit;
            } else {
                $errorList[] = 'Failed to update branch: ' . mysqli_error($conn);
            }
        } else {

            $sql = "INSERT INTO branch
                        (branch_name, branch_code, address, city, state,
                         pincode, phone, email, active)
                    VALUES
                        ($branch_name_v, $branch_code_v, $address_v, $city_v, $state_v,
                         $pincode_v, $phone_v, $email_v, $active_v)";

            if (mysqli_query($conn, $sql)) {
                header("Location: branches-list.php?msg=added");
                exit;
            } else {
                $errorList[] = 'Failed to save branch: ' . mysqli_error($conn);
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
                            <i class="fas <?= $isEdit ? 'fa-building' : 'fa-plus-circle' ?> mr-2"></i>
                            <?= $isEdit ? 'Edit Branch' : 'Add Branch' ?>
                        </h1>

                        <a href="branches-list.php"
                            class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
                            <i class="fas fa-arrow-left fa-sm text-white-50 mr-1"></i>
                            Back to Branches
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

                    <form id="branchForm" action="" method="POST" novalidate>

                        <input type="hidden" name="active" value="<?= (int) $old['active'] ?>">

                        <!-- BRANCH DETAILS -->
                        <div class="card shadow mb-4">

                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-building mr-1"></i>
                                    Branch Details
                                </h6>
                            </div>

                            <div class="card-body">

                                <div class="row">

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="branch_name">
                                                Branch Name <span class="text-danger">*</span>
                                            </label>
                                            <input
                                                type="text"
                                                id="branch_name"
                                                name="branch_name"
                                                class="form-control"
                                                maxlength="100"
                                                placeholder="Kolkata Main Branch"
                                                value="<?= htmlspecialchars($old['branch_name'], ENT_QUOTES, 'UTF-8') ?>">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="branch_code">
                                                Branch Code <span class="text-danger">*</span>
                                            </label>
                                            <input
                                                type="text"
                                                id="branch_code"
                                                name="branch_code"
                                                class="form-control text-uppercase"
                                                maxlength="10"
                                                placeholder="KOL"
                                                value="<?= htmlspecialchars($old['branch_code'], ENT_QUOTES, 'UTF-8') ?>">
                                            <small class="form-text text-muted">
                                                Short code used in trip and invoice numbers (e.g. KOL, MUM, DEL01)
                                            </small>
                                        </div>
                                    </div>

                                </div>

                            </div>

                        </div>

                        <!-- CONTACT & LOCATION -->
                        <div class="card shadow mb-4">

                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-map-marker-alt mr-1"></i>
                                    Contact &amp; Location
                                </h6>
                            </div>

                            <div class="card-body">

                                <div class="row">

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="phone">Phone</label>
                                            <input
                                                type="text"
                                                id="phone"
                                                name="phone"
                                                class="form-control"
                                                maxlength="15"
                                                placeholder="9876543210"
                                                value="<?= htmlspecialchars($old['phone'], ENT_QUOTES, 'UTF-8') ?>">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="email">Email</label>
                                            <input
                                                type="text"
                                                id="email"
                                                name="email"
                                                class="form-control"
                                                maxlength="100"
                                                placeholder="branch@example.com"
                                                value="<?= htmlspecialchars($old['email'], ENT_QUOTES, 'UTF-8') ?>">
                                        </div>
                                    </div>

                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="address">Address</label>
                                            <input
                                                type="text"
                                                id="address"
                                                name="address"
                                                class="form-control"
                                                maxlength="255"
                                                placeholder="Street address"
                                                value="<?= htmlspecialchars($old['address'], ENT_QUOTES, 'UTF-8') ?>">
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-group mb-0">
                                            <label for="city">City</label>
                                            <input
                                                type="text"
                                                id="city"
                                                name="city"
                                                class="form-control"
                                                maxlength="50"
                                                value="<?= htmlspecialchars($old['city'], ENT_QUOTES, 'UTF-8') ?>">
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-group mb-0">
                                            <label for="state">State</label>
                                            <input
                                                type="text"
                                                id="state"
                                                name="state"
                                                class="form-control"
                                                maxlength="50"
                                                value="<?= htmlspecialchars($old['state'], ENT_QUOTES, 'UTF-8') ?>">
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-group mb-0">
                                            <label for="pincode">Pincode</label>
                                            <input
                                                type="text"
                                                id="pincode"
                                                name="pincode"
                                                class="form-control"
                                                maxlength="6"
                                                placeholder="6 digits"
                                                value="<?= htmlspecialchars($old['pincode'], ENT_QUOTES, 'UTF-8') ?>">
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

                                    <?php if ($isEdit): ?>
                                        <a href="branches-list.php" class="btn btn-secondary">
                                            <i class="fas fa-times mr-1"></i>
                                            Cancel
                                        </a>
                                    <?php else: ?>
                                        <a href="branch.php" class="btn btn-secondary">
                                            <i class="fas fa-redo mr-1"></i>
                                            Clear
                                        </a>
                                    <?php endif; ?>

                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-check mr-1"></i>
                                        <?= $isEdit ? 'Update Branch' : 'Save Branch' ?>
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