<?php

include 'constant.php';
include 'session.php';

$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$isEdit = $editId > 0;

/* Popup mode */
$isPopup = isset($_GET['popup']) && (int) $_GET['popup'] === 1;

/* Return-to-sender (dormant) */
$return_to = isset($_GET['return'])
    ? preg_replace('/[^a-z0-9_\-\.]/i', '', $_GET['return'])
    : '';

requirePermission($isEdit ? 'lorry.edit' : 'lorry.create');

$pageTitle = $isEdit ? 'Edit Lorry | Billing Portal' : 'Add Lorry | Billing Portal';

$error     = '';
$errorList = [];

/* Popup result holders */
$popup_saved_id    = 0;
$popup_saved_label = '';

$old = [
    'branch_id'    => isAdmin() ? 0 : (int) ($_SESSION['branch_id'] ?? 0),
    'lorry_number' => '',
    'lorry_type'   => '',
    'capacity'     => '',
    'owner_name'   => '',
    'address'      => '',
    'city'         => '',
    'state'        => '',
    'pincode'      => '',
    'active'       => 1,
];

/* LOAD (Edit mode) */
if ($isEdit) {

    $res = mysqli_query($conn, "SELECT * FROM lorry WHERE id = $editId LIMIT 1");

    if (!$res || mysqli_num_rows($res) !== 1) {
        if ($isPopup) {
            echo "<p class='text-danger p-3'>Lorry not found.</p>";
            exit;
        }
        header("Location: lorries-list.php");
        exit;
    }

    $row = mysqli_fetch_assoc($res);

    $old['branch_id']    = (int) ($row['branch_id'] ?? 0);
    $old['lorry_number'] = $row['lorry_number'] ?? '';
    $old['lorry_type']   = $row['lorry_type']   ?? '';
    $old['capacity']     = $row['capacity']     ?? '';
    $old['owner_name']   = $row['owner_name']   ?? '';
    $old['address']      = $row['address']      ?? '';
    $old['city']         = $row['city']         ?? '';
    $old['state']        = $row['state']        ?? '';
    $old['pincode']      = $row['pincode']      ?? '';
    $old['active']       = (int) ($row['active'] ?? 1);
}

/* Branch dropdown */
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

/* SUBMIT */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['popup_mode']) && (int) $_POST['popup_mode'] === 1) {
        $isPopup = true;
    }

    $old['branch_id']    = (int) ($_POST['branch_id'] ?? 0);
    $old['lorry_number'] = strtoupper(trim($_POST['lorry_number'] ?? ''));
    $old['lorry_type']   = trim($_POST['lorry_type']   ?? '');
    $old['capacity']     = trim($_POST['capacity']     ?? '');
    $old['owner_name']   = trim($_POST['owner_name']   ?? '');
    $old['address']      = trim($_POST['address']      ?? '');
    $old['city']         = trim($_POST['city']         ?? '');
    $old['state']        = trim($_POST['state']        ?? '');
    $old['pincode']      = trim($_POST['pincode']      ?? '');
    $old['active']       = isset($_POST['active']) ? (int) $_POST['active'] : 1;

    if (isset($_POST['return_to'])) {
        $return_to = preg_replace('/[^a-z0-9_\-\.]/i', '', $_POST['return_to']);
    }

    /* Validate */
    if ($old['branch_id'] <= 0) {
        $errorList[] = 'Please select a branch.';
    }

    if ($old['lorry_number'] === '') {
        $errorList[] = 'Lorry number is required.';
    } elseif (!preg_match('/^[A-Z0-9\-\s]{4,15}$/', $old['lorry_number'])) {
        $errorList[] = 'Invalid lorry number. Use letters, digits, hyphen, or space (4-15 chars).';
    }

    if ($old['pincode'] !== '' && !preg_match('/^[0-9]{6}$/', $old['pincode'])) {
        $errorList[] = 'Pincode must be exactly 6 digits.';
    }

    if (strlen($old['address']) > 255) {
        $errorList[] = 'Address is too long (max 255).';
    }

    /* Duplicate check */
    if (empty($errorList) && $old['lorry_number'] !== '') {
        $ln_safe = mysqli_real_escape_string($conn, $old['lorry_number']);
        $sqlDup = "SELECT id FROM lorry WHERE UPPER(lorry_number) = UPPER('$ln_safe')";
        if ($isEdit) {
            $sqlDup .= " AND id <> $editId";
        }
        $sqlDup .= " LIMIT 1";
        $dup = mysqli_query($conn, $sqlDup);
        if ($dup && mysqli_num_rows($dup) > 0) {
            $errorList[] = 'A lorry with this number already exists.';
        }
    }

    /* Insert or Update */
    if (empty($errorList)) {

        $esc = function ($v) use ($conn) {
            return mysqli_real_escape_string($conn, $v);
        };

        $branch_v       = (int) $old['branch_id'];
        $lorry_number_v = "'" . $esc($old['lorry_number']) . "'";
        $lorry_type_v   = $old['lorry_type'] !== '' ? "'" . $esc($old['lorry_type']) . "'" : "NULL";
        $capacity_v     = $old['capacity']   !== '' ? "'" . $esc($old['capacity'])   . "'" : "NULL";
        $owner_name_v   = $old['owner_name'] !== '' ? "'" . $esc($old['owner_name']) . "'" : "NULL";
        $address_v      = $old['address']    !== '' ? "'" . $esc($old['address'])    . "'" : "NULL";
        $city_v         = $old['city']       !== '' ? "'" . $esc($old['city'])       . "'" : "NULL";
        $state_v        = $old['state']      !== '' ? "'" . $esc($old['state'])      . "'" : "NULL";
        $pincode_v      = $old['pincode']    !== '' ? "'" . $esc($old['pincode'])    . "'" : "NULL";
        $active_v       = (int) $old['active'];

        if ($isEdit) {

            $sql = "UPDATE lorry SET
                        branch_id    = $branch_v,
                        lorry_number = $lorry_number_v,
                        lorry_type   = $lorry_type_v,
                        capacity     = $capacity_v,
                        owner_name   = $owner_name_v,
                        address      = $address_v,
                        city         = $city_v,
                        state        = $state_v,
                        pincode      = $pincode_v,
                        active       = $active_v
                    WHERE id = $editId";

            if (mysqli_query($conn, $sql)) {
                if ($isPopup) {
                    $popup_saved_id    = $editId;
                    $popup_saved_label = $old['lorry_number'];
                } else {
                    header("Location: lorries-list.php?msg=updated");
                    exit;
                }
            } else {
                $errorList[] = 'Failed to update lorry: ' . mysqli_error($conn);
            }
        } else {

            $sql = "INSERT INTO lorry
                        (branch_id, lorry_number, lorry_type, capacity, owner_name,
                         address, city, state, pincode, active)
                    VALUES
                        ($branch_v, $lorry_number_v, $lorry_type_v, $capacity_v, $owner_name_v,
                         $address_v, $city_v, $state_v, $pincode_v, $active_v)";

            if (mysqli_query($conn, $sql)) {

                $newId = mysqli_insert_id($conn);

                if ($isPopup) {
                    $popup_saved_id    = $newId;
                    $popup_saved_label = $old['lorry_number'];
                } elseif ($return_to !== '') {
                    $sep = (strpos($return_to, '?') !== false) ? '&' : '?';
                    header("Location: $return_to{$sep}new_lorry_id=$newId&msg=added");
                    exit;
                } else {
                    header("Location: lorries-list.php?msg=added");
                    exit;
                }
            } else {
                $errorList[] = 'Failed to save lorry: ' . mysqli_error($conn);
            }
        }
    }

    if (!empty($errorList)) {
        $error = implode('<br>', array_map(function ($e) {
            return '&bull; ' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8');
        }, $errorList));
    }
}

/* Popup postMessage */
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
                        entity: 'lorry',
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

            <form id="lorryForm" action="" method="POST" novalidate>

                <input type="hidden" name="active" value="<?= (int) $old['active'] ?>">
                <input type="hidden" name="return_to" value="<?= htmlspecialchars($return_to, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="popup_mode" value="1">

                <div class="card shadow mb-3">
                    <div class="card-header py-2">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-truck mr-1"></i> Lorry Details
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">

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

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="lorry_number">Lorry Number <span class="text-danger">*</span></label>
                                    <input type="text" id="lorry_number" name="lorry_number"
                                        class="form-control text-uppercase" maxlength="15"
                                        placeholder="Enter lorry number"
                                        value="<?= htmlspecialchars($old['lorry_number'], ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="lorry_type">Lorry Type</label>
                                    <select id="lorry_type" name="lorry_type" class="form-control">
                                        <option value="">— Select —</option>
                                        <?php
                                        $types = ['Open', 'Container', 'Trailer', 'Tanker', 'Flatbed', 'Refrigerated', 'Other'];
                                        foreach ($types as $t):
                                        ?>
                                            <option value="<?= $t ?>" <?= $old['lorry_type'] === $t ? 'selected' : '' ?>>
                                                <?= $t ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="capacity">Capacity</label>
                                    <input type="text" id="capacity" name="capacity"
                                        class="form-control" maxlength="15"
                                        placeholder="Enter capacity"
                                        value="<?= htmlspecialchars($old['capacity'], ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="owner_name">Owner Name</label>
                                    <input type="text" id="owner_name" name="owner_name"
                                        class="form-control" maxlength="100"
                                        placeholder="Enter owner name"
                                        value="<?= htmlspecialchars($old['owner_name'], ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="address">Address</label>
                                    <input type="text" id="address" name="address"
                                        class="form-control" maxlength="255"
                                        placeholder="Enter address"
                                        value="<?= htmlspecialchars($old['address'], ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="city">City</label>
                                    <input type="text" id="city" name="city"
                                        class="form-control" maxlength="50"
                                        placeholder="Enter city"
                                        value="<?= htmlspecialchars($old['city'], ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-group">
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
                            <?= $isEdit ? 'Update Lorry' : 'Save Lorry' ?>
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
                            entity: 'lorry'
                        }, '*');
                    } catch (e) {}
                });
            });
        </script>

        <?= $popupPostScript ?>

    <?php else: ?>

        <!-- NORMAL MODE — unchanged -->
        <div id="wrapper">
            <?php include 'layout/sidebar.php'; ?>
            <div id="content-wrapper" class="d-flex flex-column">
                <div id="content">
                    <?php include 'layout/topbar.php'; ?>
                    <div class="container-fluid">

                        <div class="d-sm-flex align-items-center justify-content-between mb-4">
                            <h1 class="h3 mb-0 text-gray-800">
                                <i class="fas <?= $isEdit ? 'fa-truck-moving' : 'fa-truck' ?> mr-2"></i>
                                <?= $isEdit ? 'Edit Lorry' : 'Add Lorry' ?>
                            </h1>
                            <a href="lorries-list.php"
                                class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
                                <i class="fas fa-arrow-left fa-sm text-white-50 mr-1"></i>
                                Back to Lorries
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

                        <form id="lorryForm" action="" method="POST" novalidate>

                            <input type="hidden" name="active" value="<?= (int) $old['active'] ?>">
                            <input type="hidden" name="return_to" value="<?= htmlspecialchars($return_to, ENT_QUOTES, 'UTF-8') ?>">

                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="fas fa-truck mr-1"></i> Lorry Details
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">

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

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="lorry_number">Lorry Number <span class="text-danger">*</span></label>
                                                <input type="text" id="lorry_number" name="lorry_number"
                                                    class="form-control text-uppercase" maxlength="15"
                                                    placeholder="Enter lorry number"
                                                    value="<?= htmlspecialchars($old['lorry_number'], ENT_QUOTES, 'UTF-8') ?>">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="lorry_type">Lorry Type</label>
                                                <select id="lorry_type" name="lorry_type" class="form-control">
                                                    <option value="">— Select —</option>
                                                    <?php
                                                    $types = ['Open', 'Container', 'Trailer', 'Tanker', 'Flatbed', 'Refrigerated', 'Other'];
                                                    foreach ($types as $t):
                                                    ?>
                                                        <option value="<?= $t ?>" <?= $old['lorry_type'] === $t ? 'selected' : '' ?>>
                                                            <?= $t ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="capacity">Capacity</label>
                                                <input type="text" id="capacity" name="capacity"
                                                    class="form-control" maxlength="15"
                                                    placeholder="Enter capacity"
                                                    value="<?= htmlspecialchars($old['capacity'], ENT_QUOTES, 'UTF-8') ?>">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="owner_name">Owner Name</label>
                                                <input type="text" id="owner_name" name="owner_name"
                                                    class="form-control" maxlength="100"
                                                    placeholder="Enter owner name"
                                                    value="<?= htmlspecialchars($old['owner_name'], ENT_QUOTES, 'UTF-8') ?>">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
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
                                            <a href="lorries-list.php" class="btn btn-secondary">
                                                <i class="fas fa-times mr-1"></i>Cancel
                                            </a>
                                        <?php else: ?>
                                            <a href="lorry.php" class="btn btn-secondary">
                                                <i class="fas fa-redo mr-1"></i>Clear
                                            </a>
                                        <?php endif; ?>
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-check mr-1"></i>
                                            <?= $isEdit ? 'Update Lorry' : 'Save Lorry' ?>
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