<?php

include 'constant.php';
include 'session.php';

$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$isEdit = $editId > 0;

requirePermission($isEdit ? 'vendor.edit' : 'vendor.create');

$pageTitle = $isEdit ? 'Edit Vendor | Billing Portal' : 'Add Vendor | Billing Portal';

/* =========================================================
   FORM STATE
   ========================================================= */
$error     = '';
$errorList = [];

$old = [
    'branch_id'   => isAdmin() ? 0 : (int) ($_SESSION['branch_id'] ?? 0),
    'vendor_name' => '',
    'vendor_type' => '',
    'phone'       => '',
    'address'     => '',
    'active'      => 1,
];

/* =========================================================
   LOAD (Edit mode)
   ========================================================= */
if ($isEdit) {

    $res = mysqli_query($conn, "SELECT * FROM vendor WHERE id = $editId LIMIT 1");

    if (!$res || mysqli_num_rows($res) !== 1) {
        header("Location: vendors-list.php");
        exit;
    }

    $row = mysqli_fetch_assoc($res);

    $old['branch_id']   = (int) ($row['branch_id'] ?? 0);
    $old['vendor_name'] = $row['vendor_name'] ?? '';
    $old['vendor_type'] = $row['vendor_type'] ?? '';
    $old['phone']       = $row['phone']       ?? '';
    $old['address']     = $row['address']     ?? '';
    $old['active']      = (int) ($row['active'] ?? 1);
}

/* =========================================================
   BRANCH DROPDOWN
   ========================================================= */
$branches = [];
$resB = mysqli_query(
    $conn,
    "SELECT id, branch_name FROM branch WHERE active = 1 ORDER BY branch_name"
);
if ($resB) {
    while ($b = mysqli_fetch_assoc($resB)) {
        $branches[] = $b;
    }
}

/* =========================================================
   VENDOR TYPES
   ========================================================= */
$vendorTypes = ['Diesel Pump', 'Mechanic', 'Spare Parts', 'Toll', 'Labour', 'Typing', 'Packing', 'Other'];

/* =========================================================
   SUBMIT
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $old['branch_id']   = (int) ($_POST['branch_id'] ?? 0);
    $old['vendor_name'] = trim($_POST['vendor_name'] ?? '');
    $old['vendor_type'] = trim($_POST['vendor_type'] ?? '');
    $old['phone']       = trim($_POST['phone']       ?? '');
    $old['address']     = trim($_POST['address']     ?? '');
    $old['active']      = isset($_POST['active']) ? (int) $_POST['active'] : 1;

    /* Validate */
    if ($old['branch_id'] <= 0) {
        $errorList[] = 'Please select a branch.';
    }

    if ($old['vendor_name'] === '') {
        $errorList[] = 'Vendor name is required.';
    } elseif (strlen($old['vendor_name']) > 100) {
        $errorList[] = 'Vendor name is too long (max 100).';
    }

    if ($old['vendor_type'] !== '' && !in_array($old['vendor_type'], $vendorTypes, true)) {
        $errorList[] = 'Invalid vendor type.';
    }

    if ($old['phone'] !== '' && !preg_match('/^[0-9+\-\s]{6,15}$/', $old['phone'])) {
        $errorList[] = 'Invalid phone number. Use 6-15 digits.';
    }

    if (strlen($old['address']) > 255) {
        $errorList[] = 'Address is too long (max 255).';
    }

    /* Insert or Update */
    if (empty($errorList)) {

        $esc = function ($v) use ($conn) {
            return mysqli_real_escape_string($conn, $v);
        };

        $branch_v = (int) $old['branch_id'];
        $name_v   = "'" . $esc($old['vendor_name']) . "'";
        $type_v   = $old['vendor_type'] !== '' ? "'" . $esc($old['vendor_type']) . "'" : "NULL";
        $phone_v  = $old['phone']       !== '' ? "'" . $esc($old['phone'])       . "'" : "NULL";
        $addr_v   = $old['address']     !== '' ? "'" . $esc($old['address'])     . "'" : "NULL";
        $active_v = (int) $old['active'];

        if ($isEdit) {

            $sql = "UPDATE vendor SET
                        branch_id   = $branch_v,
                        vendor_name = $name_v,
                        vendor_type = $type_v,
                        phone       = $phone_v,
                        address     = $addr_v,
                        active      = $active_v
                    WHERE id = $editId";

            if (mysqli_query($conn, $sql)) {
                header("Location: vendors-list.php?msg=updated");
                exit;
            } else {
                $errorList[] = 'Failed to update vendor: ' . mysqli_error($conn);
            }
        } else {

            $sql = "INSERT INTO vendor
                        (branch_id, vendor_name, vendor_type, phone, address, active)
                    VALUES
                        ($branch_v, $name_v, $type_v, $phone_v, $addr_v, $active_v)";

            if (mysqli_query($conn, $sql)) {
                header("Location: vendors-list.php?msg=added");
                exit;
            } else {
                $errorList[] = 'Failed to save vendor: ' . mysqli_error($conn);
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
                            <i class="fas <?= $isEdit ? 'fa-edit' : 'fa-plus-circle' ?> mr-2"></i>
                            <?= $isEdit ? 'Edit Vendor' : 'Add Vendor' ?>
                        </h1>
                        <a href="vendors-list.php"
                            class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
                            <i class="fas fa-arrow-left fa-sm text-white-50 mr-1"></i>
                            Back to Vendors
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

                    <form id="vendorForm" action="" method="POST" novalidate>

                        <input type="hidden" name="active" value="<?= (int) $old['active'] ?>">

                        <!-- VENDOR DETAILS -->
                        <div class="card shadow mb-4">

                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-store mr-1"></i>
                                    Vendor Details
                                </h6>
                            </div>

                            <div class="card-body">

                                <div class="row">

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
                                            <label for="vendor_name">
                                                Vendor Name <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" id="vendor_name" name="vendor_name"
                                                class="form-control" maxlength="100"
                                                placeholder="Enter vendor name"
                                                value="<?= htmlspecialchars($old['vendor_name'], ENT_QUOTES, 'UTF-8') ?>">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="vendor_type">Vendor Type</label>
                                            <select id="vendor_type" name="vendor_type" class="form-control">
                                                <option value="">— Select —</option>
                                                <?php foreach ($vendorTypes as $t): ?>
                                                    <option value="<?= $t ?>"
                                                        <?= $old['vendor_type'] === $t ? 'selected' : '' ?>>
                                                        <?= $t ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="phone">Phone</label>
                                            <input type="text" id="phone" name="phone"
                                                class="form-control" maxlength="15"
                                                placeholder="Enter phone number"
                                                value="<?= htmlspecialchars($old['phone'], ENT_QUOTES, 'UTF-8') ?>">
                                        </div>
                                    </div>

                                    <div class="col-md-12">
                                        <div class="form-group mb-0">
                                            <label for="address">Address</label>
                                            <textarea id="address" name="address" rows="2"
                                                class="form-control" maxlength="255"
                                                placeholder="Enter full address"><?= htmlspecialchars($old['address'], ENT_QUOTES, 'UTF-8') ?></textarea>
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
                                        <a href="vendors-list.php" class="btn btn-secondary">
                                            <i class="fas fa-times mr-1"></i>Cancel
                                        </a>
                                    <?php else: ?>
                                        <a href="vendor.php" class="btn btn-secondary">
                                            <i class="fas fa-redo mr-1"></i>Clear
                                        </a>
                                    <?php endif; ?>

                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-check mr-1"></i>
                                        <?= $isEdit ? 'Update Vendor' : 'Save Vendor' ?>
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