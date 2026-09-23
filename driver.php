<?php

include 'constant.php';
include 'session.php';

/* =========================================================
   DETERMINE MODE
   ========================================================= */
$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$isEdit = $editId > 0;

/* Popup mode */
$isPopup = isset($_GET['popup']) && (int) $_GET['popup'] === 1;

/* Return-to-sender (dormant) */
$return_to = isset($_GET['return'])
    ? preg_replace('/[^a-z0-9_\-\.]/i', '', $_GET['return'])
    : '';

requirePermission($isEdit ? 'driver.edit' : 'driver.create');

$pageTitle = $isEdit ? 'Edit Driver | Billing Portal' : 'Add Driver | Billing Portal';

/* =========================================================
   FORM STATE
   ========================================================= */
$error     = '';
$errorList = [];

$popup_saved_id    = 0;
$popup_saved_label = '';

$old = [
    'driver_name'    => '',
    'phone'          => '',
    'license_no'     => '',
    'license_expiry' => '',
    'address'        => '',
    'city'           => '',
    'state'          => '',
    'pincode'        => '',
    'branch_id'      => isAdmin() ? 0 : (int) ($_SESSION['branch_id'] ?? 0),
    'active'         => 1,
];

/* =========================================================
   LOAD (Edit mode)
   ========================================================= */
if ($isEdit) {

    $res = mysqli_query($conn, "SELECT * FROM driver WHERE id = $editId LIMIT 1");

    if (!$res || mysqli_num_rows($res) !== 1) {
        if ($isPopup) {
            echo "<p class='text-danger p-3'>Driver not found.</p>";
            exit;
        }
        header("Location: drivers-list.php");
        exit;
    }

    $row = mysqli_fetch_assoc($res);

    $old['driver_name']    = $row['driver_name']    ?? '';
    $old['phone']          = $row['phone']          ?? '';
    $old['license_no']     = $row['license_no']     ?? '';
    $old['license_expiry'] = $row['license_expiry'] ?? '';
    $old['address']        = $row['address']        ?? '';
    $old['city']           = $row['city']           ?? '';
    $old['state']          = $row['state']          ?? '';
    $old['pincode']        = $row['pincode']        ?? '';
    $old['branch_id']      = (int) ($row['branch_id'] ?? 0);
    $old['active']         = (int) ($row['active']    ?? 1);
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
   SUBMIT
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['popup_mode']) && (int) $_POST['popup_mode'] === 1) {
        $isPopup = true;
    }

    $old['driver_name']    = trim($_POST['driver_name']    ?? '');
    $old['phone']          = trim($_POST['phone']          ?? '');
    $old['license_no']     = strtoupper(trim($_POST['license_no'] ?? ''));
    $old['license_expiry'] = trim($_POST['license_expiry'] ?? '');
    $old['address']        = trim($_POST['address']        ?? '');
    $old['city']           = trim($_POST['city']           ?? '');
    $old['state']          = trim($_POST['state']          ?? '');
    $old['pincode']        = trim($_POST['pincode']        ?? '');
    $old['branch_id']      = (int) ($_POST['branch_id'] ?? 0);
    $old['active']         = isset($_POST['active']) ? (int) $_POST['active'] : 1;

    if (isset($_POST['return_to'])) {
        $return_to = preg_replace('/[^a-z0-9_\-\.]/i', '', $_POST['return_to']);
    }

    /* Validate */
    if ($old['driver_name'] === '') {
        $errorList[] = 'Driver name is required.';
    } elseif (strlen($old['driver_name']) > 100) {
        $errorList[] = 'Driver name is too long (max 100).';
    }

    if ($old['phone'] !== '' && !preg_match('/^[0-9+\-\s]{6,15}$/', $old['phone'])) {
        $errorList[] = 'Invalid phone number. Use 6-15 digits.';
    }

    if ($old['license_no'] !== '' && !preg_match('/^[A-Z0-9\-\/\s]{5,30}$/', $old['license_no'])) {
        $errorList[] = 'Invalid license number. Use letters, digits, hyphen, slash, space (5-30 chars).';
    }

    if ($old['license_expiry'] !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $old['license_expiry']);
        if (!$d || $d->format('Y-m-d') !== $old['license_expiry']) {
            $errorList[] = 'Invalid license expiry date.';
        }
    }

    if ($old['pincode'] !== '' && !preg_match('/^[0-9]{6}$/', $old['pincode'])) {
        $errorList[] = 'Pincode must be exactly 6 digits.';
    }

    if ($old['branch_id'] <= 0) {
        $errorList[] = 'Please select a branch.';
    }

    /* Duplicate license */
    if ($old['license_no'] !== '' && empty($errorList)) {
        $lic_safe = mysqli_real_escape_string($conn, $old['license_no']);
        $sqlDup = "SELECT id FROM driver WHERE UPPER(license_no) = UPPER('$lic_safe')";
        if ($isEdit) {
            $sqlDup .= " AND id <> $editId";
        }
        $sqlDup .= " LIMIT 1";
        $dup = mysqli_query($conn, $sqlDup);
        if ($dup && mysqli_num_rows($dup) > 0) {
            $errorList[] = 'Another driver already has this license number.';
        }
    }

    /* Insert or Update */
    if (empty($errorList)) {

        $esc = function ($v) use ($conn) {
            return mysqli_real_escape_string($conn, $v);
        };

        $name_v    = "'" . $esc($old['driver_name']) . "'";
        $phone_v   = $old['phone']          !== '' ? "'" . $esc($old['phone'])          . "'" : "NULL";
        $license_v = $old['license_no']     !== '' ? "'" . $esc($old['license_no'])     . "'" : "NULL";
        $lic_exp_v = $old['license_expiry'] !== '' ? "'" . $esc($old['license_expiry']) . "'" : "NULL";
        $address_v = $old['address']        !== '' ? "'" . $esc($old['address'])        . "'" : "NULL";
        $city_v    = $old['city']           !== '' ? "'" . $esc($old['city'])           . "'" : "NULL";
        $state_v   = $old['state']          !== '' ? "'" . $esc($old['state'])          . "'" : "NULL";
        $pincode_v = $old['pincode']        !== '' ? "'" . $esc($old['pincode'])        . "'" : "NULL";
        $branch_v  = (int) $old['branch_id'];
        $active_v  = (int) $old['active'];

        if ($isEdit) {

            $sql = "UPDATE driver SET
                        driver_name    = $name_v,
                        phone          = $phone_v,
                        license_no     = $license_v,
                        license_expiry = $lic_exp_v,
                        address        = $address_v,
                        city           = $city_v,
                        state          = $state_v,
                        pincode        = $pincode_v,
                        branch_id      = $branch_v,
                        active         = $active_v
                    WHERE id = $editId";

            if (mysqli_query($conn, $sql)) {
                if ($isPopup) {
                    $popup_saved_id    = $editId;
                    $popup_saved_label = $old['driver_name'];
                } else {
                    header("Location: drivers-list.php?msg=updated");
                    exit;
                }
            } else {
                $errorList[] = 'Failed to update driver: ' . mysqli_error($conn);
            }
        } else {

            $sql = "INSERT INTO driver
                        (driver_name, phone, license_no, license_expiry,
                         address, city, state, pincode, branch_id, active)
                    VALUES
                        ($name_v, $phone_v, $license_v, $lic_exp_v,
                         $address_v, $city_v, $state_v, $pincode_v, $branch_v, $active_v)";

            if (mysqli_query($conn, $sql)) {

                $newId = mysqli_insert_id($conn);

                if ($isPopup) {
                    $popup_saved_id    = $newId;
                    $popup_saved_label = $old['driver_name'];
                } elseif ($return_to !== '') {
                    $sep = (strpos($return_to, '?') !== false) ? '&' : '?';
                    header("Location: $return_to{$sep}new_driver_id=$newId&msg=added");
                    exit;
                } else {
                    header("Location: drivers-list.php?msg=added");
                    exit;
                }
            } else {
                $errorList[] = 'Failed to save driver: ' . mysqli_error($conn);
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
                        entity: 'driver',
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

            <form id="driverForm" action="" method="POST" novalidate>

                <input type="hidden" name="active" value="<?= (int) $old['active'] ?>">
                <input type="hidden" name="return_to" value="<?= htmlspecialchars($return_to, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="popup_mode" value="1">

                <div class="card shadow-sm mb-3">
                    <div class="card-header py-2">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-id-card mr-1"></i> Driver Details
                        </h6>
                    </div>
                    <div class="card-body">

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="driver_name">Driver Name <span class="text-danger">*</span></label>
                                <input type="text" id="driver_name" name="driver_name"
                                    class="form-control" maxlength="100"
                                    placeholder="Enter driver name"
                                    value="<?= htmlspecialchars($old['driver_name'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div class="form-group col-md-6">
                                <label for="phone">Phone</label>
                                <input type="text" id="phone" name="phone"
                                    class="form-control" maxlength="15"
                                    placeholder="Enter phone number"
                                    value="<?= htmlspecialchars($old['phone'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="license_no">License Number</label>
                                <input type="text" id="license_no" name="license_no"
                                    class="form-control text-uppercase" maxlength="30"
                                    placeholder="Enter license number"
                                    value="<?= htmlspecialchars($old['license_no'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div class="form-group col-md-6">
                                <label for="license_expiry">License Expiry Date</label>
                                <input type="date" id="license_expiry" name="license_expiry"
                                    class="form-control"
                                    value="<?= htmlspecialchars($old['license_expiry'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="branch_id">Branch <span class="text-danger">*</span></label>
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

                        <div class="form-group">
                            <label for="address">Address</label>
                            <input type="text" id="address" name="address"
                                class="form-control" maxlength="255"
                                placeholder="Enter address"
                                value="<?= htmlspecialchars($old['address'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-4 mb-md-0">
                                <label for="city">City</label>
                                <input type="text" id="city" name="city"
                                    class="form-control" maxlength="50"
                                    placeholder="Enter city"
                                    value="<?= htmlspecialchars($old['city'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="form-group col-md-4 mb-md-0">
                                <label for="state">State</label>
                                <input type="text" id="state" name="state"
                                    class="form-control" maxlength="50"
                                    placeholder="Enter state"
                                    value="<?= htmlspecialchars($old['state'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="form-group col-md-4 mb-0">
                                <label for="pincode">Pincode</label>
                                <input type="text" id="pincode" name="pincode"
                                    class="form-control" maxlength="6"
                                    placeholder="Enter 6-digit pincode"
                                    value="<?= htmlspecialchars($old['pincode'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
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
                            <?= $isEdit ? 'Update Driver' : 'Save Driver' ?>
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
                            entity: 'driver'
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
                                <i class="fas <?= $isEdit ? 'fa-user-edit' : 'fa-user-plus' ?> mr-2"></i>
                                <?= $isEdit ? 'Edit Driver' : 'Add Driver' ?>
                            </h1>
                            <a href="drivers-list.php"
                                class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
                                <i class="fas fa-arrow-left fa-sm text-white-50 mr-1"></i>
                                Back to Drivers
                            </a>
                        </div>

                        <?php if ($error !== ''): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle mr-1"></i>
                                <strong>Please fix the following:</strong>
                                <div class="mt-2"><?= $error ?></div>
                                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                            </div>
                        <?php endif; ?>

                        <form id="driverForm" action="" method="POST" novalidate>

                            <input type="hidden" name="active" value="<?= (int) $old['active'] ?>">
                            <input type="hidden" name="return_to" value="<?= htmlspecialchars($return_to, ENT_QUOTES, 'UTF-8') ?>">

                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="fas fa-id-card mr-1"></i> Driver Details
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="driver_name">Driver Name <span class="text-danger">*</span></label>
                                                <input type="text" id="driver_name" name="driver_name"
                                                    class="form-control" maxlength="100"
                                                    placeholder="Enter driver name"
                                                    value="<?= htmlspecialchars($old['driver_name'], ENT_QUOTES, 'UTF-8') ?>">
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

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="license_no">License Number</label>
                                                <input type="text" id="license_no" name="license_no"
                                                    class="form-control text-uppercase" maxlength="30"
                                                    placeholder="Enter license number"
                                                    value="<?= htmlspecialchars($old['license_no'], ENT_QUOTES, 'UTF-8') ?>">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="license_expiry">License Expiry Date</label>
                                                <input type="date" id="license_expiry" name="license_expiry"
                                                    class="form-control"
                                                    value="<?= htmlspecialchars($old['license_expiry'], ENT_QUOTES, 'UTF-8') ?>">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="branch_id">Branch <span class="text-danger">*</span></label>
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

                                    </div>
                                </div>
                            </div>

                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="fas fa-map-marker-alt mr-1"></i> Address
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="address">Address</label>
                                                <input type="text" id="address" name="address"
                                                    class="form-control" maxlength="255"
                                                    placeholder="Enter address"
                                                    value="<?= htmlspecialchars($old['address'], ENT_QUOTES, 'UTF-8') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group mb-0">
                                                <label for="city">City</label>
                                                <input type="text" id="city" name="city"
                                                    class="form-control" maxlength="50"
                                                    placeholder="Enter city"
                                                    value="<?= htmlspecialchars($old['city'], ENT_QUOTES, 'UTF-8') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group mb-0">
                                                <label for="state">State</label>
                                                <input type="text" id="state" name="state"
                                                    class="form-control" maxlength="50"
                                                    placeholder="Enter state"
                                                    value="<?= htmlspecialchars($old['state'], ENT_QUOTES, 'UTF-8') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group mb-0">
                                                <label for="pincode">Pincode</label>
                                                <input type="text" id="pincode" name="pincode"
                                                    class="form-control" maxlength="6"
                                                    placeholder="Enter 6-digit pincode"
                                                    value="<?= htmlspecialchars($old['pincode'], ENT_QUOTES, 'UTF-8') ?>">
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
                                            <a href="drivers-list.php" class="btn btn-secondary">
                                                <i class="fas fa-times mr-1"></i> Cancel
                                            </a>
                                        <?php else: ?>
                                            <a href="driver.php" class="btn btn-secondary">
                                                <i class="fas fa-redo mr-1"></i> Clear
                                            </a>
                                        <?php endif; ?>
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-check mr-1"></i>
                                            <?= $isEdit ? 'Update Driver' : 'Save Driver' ?>
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