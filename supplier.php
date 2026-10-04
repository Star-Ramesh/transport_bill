<?php

include 'constant.php';
include 'session.php';

$editId  = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$isEdit  = $editId > 0;

$isPopup = isset($_GET['popup']) && (int) $_GET['popup'] === 1;

requirePermission($isEdit ? 'supplier.edit' : 'supplier.create');

$pageTitle = $isEdit ? 'Edit Supplier | Billing Portal' : 'Add Supplier | Billing Portal';

/* =========================================================
   FORM STATE
   ========================================================= */
$error     = '';
$errorList = [];

$popup_saved_id    = 0;
$popup_saved_label = '';

$old = [
    'supplier_name' => '',
    'supplier_type' => '',
    'phone'         => '',
    'address'       => '',
    'active'        => 1,
];

/* =========================================================
   LOAD (Edit mode)
   ========================================================= */
if ($isEdit) {

    $res = mysqli_query($conn, "SELECT * FROM supplier WHERE id = $editId LIMIT 1");

    if (!$res || mysqli_num_rows($res) !== 1) {
        if ($isPopup) {
            echo "<p class='text-danger p-3'>Supplier not found.</p>";
            exit;
        }
        header("Location: suppliers-list.php");
        exit;
    }

    $row = mysqli_fetch_assoc($res);

    $old['supplier_name'] = $row['supplier_name'] ?? '';
    $old['supplier_type'] = $row['supplier_type'] ?? '';
    $old['phone']         = $row['phone']         ?? '';
    $old['address']       = $row['address']       ?? '';
    $old['active']        = (int) ($row['active'] ?? 1);
}

/* =========================================================
   SUPPLIER TYPES
   ========================================================= */
$supplierTypes = [
    'Diesel Pump',
    'Mechanic',
    'Spare Parts',
    'Toll',
    'Labour',
    'Typing',
    'Packing',
    'Truck Supplier',
    'Other',
];

/* =========================================================
   SUBMIT
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['popup_mode']) && (int) $_POST['popup_mode'] === 1) {
        $isPopup = true;
    }

    $old['supplier_name'] = trim($_POST['supplier_name'] ?? '');
    $old['supplier_type'] = trim($_POST['supplier_type'] ?? '');
    $old['phone']         = trim($_POST['phone']         ?? '');
    $old['address']       = trim($_POST['address']       ?? '');
    $old['active']        = isset($_POST['active']) ? (int) $_POST['active'] : 1;

    /* Validate */
    if ($old['supplier_name'] === '') {
        $errorList[] = 'Supplier name is required.';
    } elseif (strlen($old['supplier_name']) > 100) {
        $errorList[] = 'Supplier name is too long (max 100).';
    }

    if ($old['supplier_type'] !== '' && !in_array($old['supplier_type'], $supplierTypes, true)) {
        $errorList[] = 'Invalid supplier type.';
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

        $name_v   = "'" . $esc($old['supplier_name']) . "'";
        $type_v   = $old['supplier_type'] !== '' ? "'" . $esc($old['supplier_type']) . "'" : "NULL";
        $phone_v  = $old['phone']         !== '' ? "'" . $esc($old['phone'])         . "'" : "NULL";
        $addr_v   = $old['address']       !== '' ? "'" . $esc($old['address'])       . "'" : "NULL";
        $active_v = (int) $old['active'];

        if ($isEdit) {

            $sql = "UPDATE supplier SET
                        supplier_name = $name_v,
                        supplier_type = $type_v,
                        phone         = $phone_v,
                        address       = $addr_v,
                        active        = $active_v
                    WHERE id = $editId";

            if (mysqli_query($conn, $sql)) {
                if ($isPopup) {
                    $popup_saved_id    = $editId;
                    $popup_saved_label = $old['supplier_name'];
                } else {
                    header("Location: suppliers-list.php?msg=updated");
                    exit;
                }
            } else {
                $errorList[] = 'Failed to update supplier: ' . mysqli_error($conn);
            }
        } else {

            $sql = "INSERT INTO supplier
                        (supplier_name, supplier_type, phone, address, active)
                    VALUES
                        ($name_v, $type_v, $phone_v, $addr_v, $active_v)";

            if (mysqli_query($conn, $sql)) {

                $newId = mysqli_insert_id($conn);

                if ($isPopup) {
                    $popup_saved_id    = $newId;
                    $popup_saved_label = $old['supplier_name'];
                } else {
                    header("Location: suppliers-list.php?msg=added");
                    exit;
                }
            } else {
                $errorList[] = 'Failed to save supplier: ' . mysqli_error($conn);
            }
        }
    }

    if (!empty($errorList)) {
        $error = implode('<br>', array_map(function ($e) {
            return '&bull; ' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8');
        }, $errorList));
    }
}

/* =========================================================
   POPUP MODE — postMessage on success
   ========================================================= */
$popupPostScript = '';
if ($isPopup && $popup_saved_id > 0) {
    $safeId    = (int) $popup_saved_id;
    $safeLabel = json_encode($popup_saved_label, JSON_UNESCAPED_UNICODE);
    $popupPostScript = <<<JS
        <script>
            (function () {
                try {
                    window.parent.postMessage({
                        type:  'master-saved',
                        entity: 'supplier',
                        id:    $safeId,
                        label: $safeLabel
                    }, '*');
                } catch (e) {}
            })();
        </script>
JS;
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

    <?php if ($isPopup): ?>
        <style>
            body {
                background: #fff;
            }

            #wrapper {
                display: block;
            }

            #content-wrapper {
                margin-left: 0 !important;
            }

            .container-fluid {
                padding: 1rem 1.25rem;
            }
        </style>
    <?php endif; ?>

</head>

<body id="page-top">

    <?php if ($isPopup): ?>

        <div class="container-fluid">

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle mr-1"></i>
                    <strong>Please fix the following:</strong>
                    <div class="mt-2"><?= $error ?></div>
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            <?php endif; ?>

            <form id="supplierForm" action="" method="POST" novalidate>

                <input type="hidden" name="active" value="<?= (int) $old['active'] ?>">
                <input type="hidden" name="popup_mode" value="1">

                <div class="card shadow-sm mb-3">
                    <div class="card-header py-2">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-truck-loading mr-1"></i> Supplier Details
                        </h6>
                    </div>
                    <div class="card-body">

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="supplier_name">Supplier Name <span class="text-danger">*</span></label>
                                <input type="text" id="supplier_name" name="supplier_name"
                                    class="form-control" maxlength="100"
                                    placeholder="Enter supplier name"
                                    value="<?= htmlspecialchars($old['supplier_name'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div class="form-group col-md-6">
                                <label for="supplier_type">Supplier Type</label>
                                <select id="supplier_type" name="supplier_type" class="form-control">
                                    <option value="">— Select —</option>
                                    <?php foreach ($supplierTypes as $t): ?>
                                        <option value="<?= $t ?>"
                                            <?= $old['supplier_type'] === $t ? 'selected' : '' ?>>
                                            <?= $t ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input type="text" id="phone" name="phone"
                                class="form-control" maxlength="15"
                                placeholder="Enter phone number"
                                value="<?= htmlspecialchars($old['phone'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="form-group mb-0">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" rows="2"
                                class="form-control" maxlength="255"
                                placeholder="Enter full address"><?= htmlspecialchars($old['address'], ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>

                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between flex-wrap mb-2">
                    <div class="text-muted small mb-2 mb-sm-0">
                        <i class="fas fa-info-circle mr-1"></i>
                        Fields marked <span class="text-danger">*</span> are required.
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary" id="popupCancelBtn">
                            <i class="fas fa-times mr-1"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check mr-1"></i>
                            <?= $isEdit ? 'Update Supplier' : 'Save Supplier' ?>
                        </button>
                    </div>
                </div>

            </form>

        </div>

        <script src="vendor/jquery/jquery.min.js"></script>
        <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

        <script>
            $(function() {
                $(document).on('click', '#popupCancelBtn', function() {
                    try {
                        window.parent.postMessage({
                            type: 'master-cancelled',
                            entity: 'supplier'
                        }, '*');
                    } catch (e) {}
                });
            });
        </script>

        <?= $popupPostScript ?>

    <?php else: ?>

        <div id="wrapper">

            <?php include 'layout/sidebar.php'; ?>

            <div id="content-wrapper" class="d-flex flex-column">

                <div id="content">

                    <?php include 'layout/topbar.php'; ?>

                    <div class="container-fluid">

                        <div class="d-sm-flex align-items-center justify-content-between mb-4">
                            <h1 class="h3 mb-0 text-gray-800">
                                <i class="fas <?= $isEdit ? 'fa-edit' : 'fa-plus-circle' ?> mr-2"></i>
                                <?= $isEdit ? 'Edit Supplier' : 'Add Supplier' ?>
                            </h1>
                            <a href="suppliers-list.php"
                                class="d-inline-block btn btn-sm btn-secondary shadow-sm">
                                <i class="fas fa-arrow-left fa-sm text-white-50 mr-1"></i>
                                Back to Suppliers
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

                        <form id="supplierForm" action="" method="POST" novalidate>

                            <input type="hidden" name="active" value="<?= (int) $old['active'] ?>">

                            <div class="card shadow mb-4">

                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="fas fa-truck-loading mr-1"></i>
                                        Supplier Details
                                    </h6>
                                </div>

                                <div class="card-body">

                                    <div class="row">

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="supplier_name">
                                                    Supplier Name <span class="text-danger">*</span>
                                                </label>
                                                <input type="text" id="supplier_name" name="supplier_name"
                                                    class="form-control" maxlength="100"
                                                    placeholder="Enter supplier name"
                                                    value="<?= htmlspecialchars($old['supplier_name'], ENT_QUOTES, 'UTF-8') ?>">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="supplier_type">Supplier Type</label>
                                                <select id="supplier_type" name="supplier_type" class="form-control">
                                                    <option value="">— Select —</option>
                                                    <?php foreach ($supplierTypes as $t): ?>
                                                        <option value="<?= $t ?>"
                                                            <?= $old['supplier_type'] === $t ? 'selected' : '' ?>>
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

                            <div class="card shadow mb-4">

                                <div class="card-body py-3 d-flex align-items-center justify-content-between flex-wrap">

                                    <div class="text-muted small mb-2 mb-sm-0">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Fields marked <span class="text-danger">*</span> are required.
                                    </div>

                                    <div>
                                        <?php if ($isEdit): ?>
                                            <a href="suppliers-list.php" class="btn btn-secondary">
                                                <i class="fas fa-times mr-1"></i>Cancel
                                            </a>
                                        <?php else: ?>
                                            <a href="supplier.php" class="btn btn-secondary">
                                                <i class="fas fa-redo mr-1"></i>Clear
                                            </a>
                                        <?php endif; ?>

                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-check mr-1"></i>
                                            <?= $isEdit ? 'Update Supplier' : 'Save Supplier' ?>
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

    <?php endif; ?>

</body>

</html>