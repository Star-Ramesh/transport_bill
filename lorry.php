<?php

include 'constant.php';
include 'session.php';

$editId  = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$isEdit  = $editId > 0;

$isPopup = isset($_GET['popup']) && (int) $_GET['popup'] === 1;

$return_to = isset($_GET['return'])
    ? preg_replace('/[^a-z0-9_\-\.]/i', '', $_GET['return'])
    : '';

requirePermission($isEdit ? 'lorry.edit' : 'lorry.create');

$pageTitle = $isEdit ? 'Edit Truck | Billing Portal' : 'Add Truck | Billing Portal';

$error     = '';
$errorList = [];

$popup_saved_id    = 0;
$popup_saved_label = '';

/* Truck type list — single source of truth */
$types = ['Open', 'Container', 'Trailer', 'Tanker', 'Flatbed', 'Refrigerated', 'Other'];

$old = [
    'lorry_number'        => '',
    'lorry_type'          => '',
    'ownership_type'      => -1,
    'supplier_id'         => 0,
    'supplier_name'       => '',
    'preferred_driver_id' => 0,
    'capacity'            => '',
    'address'             => '',
    'city'                => '',
    'state'               => '',
    'pincode'             => '',
    'active'              => 1,
];

if ($isEdit) {
    $res = mysqli_query($conn, "SELECT * FROM lorry WHERE id = $editId LIMIT 1");
    if (!$res || mysqli_num_rows($res) !== 1) {
        if ($isPopup) { echo "<p class='text-danger p-3'>Truck not found.</p>"; exit; }
        header("Location: lorries-list.php"); exit;
    }

    $row = mysqli_fetch_assoc($res);
    $old['lorry_number']        = $row['lorry_number'] ?? '';
    $old['lorry_type']          = $row['lorry_type']   ?? '';
    $old['ownership_type']      = (int) ($row['ownership_type'] ?? 0);
    $old['supplier_id']         = (int) ($row['supplier_id'] ?? 0);
    $old['preferred_driver_id'] = (int) ($row['preferred_driver_id'] ?? 0);
    $old['capacity']            = $row['capacity']     ?? '';
    $old['address']             = $row['address']      ?? '';
    $old['city']                = $row['city']         ?? '';
    $old['state']               = $row['state']        ?? '';
    $old['pincode']             = $row['pincode']      ?? '';
    $old['active']              = (int) ($row['active'] ?? 1);

    if ($old['ownership_type'] === 1 && $old['supplier_id'] > 0) {
        $supRow = mysqli_query($conn, "SELECT supplier_name FROM supplier WHERE id = " . (int) $old['supplier_id'] . " LIMIT 1");
        if ($supRow && mysqli_num_rows($supRow) === 1) {
            $sr = mysqli_fetch_assoc($supRow);
            $old['supplier_name'] = $sr['supplier_name'];
        }
    }
}

$suppliers = [];
$resSup = mysqli_query($conn, "SELECT id, supplier_name, phone, address FROM supplier WHERE active = 1 ORDER BY supplier_name");
if ($resSup) while ($s = mysqli_fetch_assoc($resSup)) $suppliers[] = $s;

$drivers = [];
$resDrv = mysqli_query($conn, "SELECT id, driver_name, phone FROM driver WHERE active = 1 ORDER BY driver_name");
if ($resDrv) while ($d = mysqli_fetch_assoc($resDrv)) $drivers[] = $d;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['popup_mode']) && (int) $_POST['popup_mode'] === 1) $isPopup = true;

    $old['lorry_number']        = strtoupper(trim($_POST['lorry_number'] ?? ''));
    $old['lorry_type']          = trim($_POST['lorry_type']   ?? '');
    $old['ownership_type']      = isset($_POST['ownership_type']) ? (int) $_POST['ownership_type'] : -1;
    $old['supplier_id']         = (int) ($_POST['supplier_id'] ?? 0);
    $old['supplier_name']       = trim($_POST['supplier_name'] ?? '');
    $old['preferred_driver_id'] = (int) ($_POST['preferred_driver_id'] ?? 0);
    $old['capacity']            = trim($_POST['capacity']     ?? '');
    $old['address']             = trim($_POST['address']      ?? '');
    $old['city']                = trim($_POST['city']         ?? '');
    $old['state']               = trim($_POST['state']        ?? '');
    $old['pincode']             = trim($_POST['pincode']      ?? '');
    $old['active']              = isset($_POST['active']) ? (int) $_POST['active'] : 1;

    if (isset($_POST['return_to'])) $return_to = preg_replace('/[^a-z0-9_\-\.]/i', '', $_POST['return_to']);
    if ($old['ownership_type'] !== 1 && $old['ownership_type'] !== 0) $old['ownership_type'] = -1;

    if ($old['ownership_type'] === 0) {
        $old['supplier_id']    = 0;
        $old['supplier_name']  = '';
        $old['address']        = '';
        $old['city']           = '';
        $old['state']          = '';
        $old['pincode']        = '';
    }

    if ($old['ownership_type'] === -1) $errorList[] = 'Please select the truck ownership (Own or 3rd Party).';

    if ($old['lorry_number'] === '') {
        $errorList[] = 'Truck number is required.';
    } elseif (!preg_match('/^[A-Z0-9\-\s]{4,15}$/', $old['lorry_number'])) {
        $errorList[] = 'Invalid truck number.';
    }

    if ($old['ownership_type'] === 1 && $old['supplier_name'] === '' && $old['supplier_id'] <= 0) {
        $errorList[] = 'Please enter or select a supplier for the 3rd-party truck.';
    }
    if ($old['pincode'] !== '' && !preg_match('/^[0-9]{6}$/', $old['pincode'])) $errorList[] = 'Pincode must be 6 digits.';

    if (empty($errorList) && $old['lorry_number'] !== '') {
        $ln_safe = mysqli_real_escape_string($conn, $old['lorry_number']);
        $sqlDup = "SELECT id FROM lorry WHERE UPPER(lorry_number) = UPPER('$ln_safe')";
        if ($isEdit) $sqlDup .= " AND id <> $editId";
        $sqlDup .= " LIMIT 1";
        $dup = mysqli_query($conn, $sqlDup);
        if ($dup && mysqli_num_rows($dup) > 0) $errorList[] = 'A truck with this number already exists.';
    }

    if (empty($errorList)) {
        mysqli_begin_transaction($conn);
        try {
            $esc = function ($v) use ($conn) { return mysqli_real_escape_string($conn, $v); };

            if ($old['ownership_type'] === 1) {
                if ($old['supplier_id'] > 0) {
                    $chk = mysqli_query($conn, "SELECT id FROM supplier WHERE id = " . (int) $old['supplier_id'] . " LIMIT 1");
                    if (!$chk || mysqli_num_rows($chk) !== 1) $old['supplier_id'] = 0;
                }
                if ($old['supplier_id'] <= 0 && $old['supplier_name'] !== '') {
                    $sn_safe = $esc($old['supplier_name']);
                    $chk = mysqli_query($conn, "SELECT id FROM supplier WHERE UPPER(supplier_name) = UPPER('$sn_safe') LIMIT 1");
                    if ($chk && mysqli_num_rows($chk) === 1) {
                        $cr = mysqli_fetch_assoc($chk);
                        $old['supplier_id'] = (int) $cr['id'];
                    } else {
                        $new_sn_v = "'" . $esc($old['supplier_name']) . "'";
                        $parts = [];
                        if ($old['address'] !== '') $parts[] = $old['address'];
                        if ($old['city']    !== '') $parts[] = $old['city'];
                        if ($old['state']   !== '') $parts[] = $old['state'];
                        if ($old['pincode'] !== '') $parts[] = $old['pincode'];
                        $packed = implode(', ', $parts);
                        $new_addr_v = $packed !== '' ? "'" . $esc($packed) . "'" : "NULL";
                        $sqlInsSup = "INSERT INTO supplier (supplier_name, supplier_type, address, active) VALUES ($new_sn_v, 'Truck Supplier', $new_addr_v, 1)";
                        if (!mysqli_query($conn, $sqlInsSup)) throw new Exception('Failed to create supplier: ' . mysqli_error($conn));
                        $old['supplier_id'] = (int) mysqli_insert_id($conn);
                    }
                }
                if ($old['supplier_id'] <= 0) throw new Exception('Could not resolve supplier.');
            }

            $lorry_number_v  = "'" . $esc($old['lorry_number']) . "'";
            $lorry_type_v    = $old['lorry_type'] !== '' ? "'" . $esc($old['lorry_type']) . "'" : "NULL";
            $ownership_v     = (int) $old['ownership_type'];
            $supplier_v      = (int) $old['supplier_id'] > 0 ? (int) $old['supplier_id'] : "NULL";
            $pref_driver_v   = (int) $old['preferred_driver_id'] > 0 ? (int) $old['preferred_driver_id'] : "NULL";
            $capacity_v      = $old['capacity'] !== '' ? "'" . $esc($old['capacity']) . "'" : "NULL";
            $owner_name_v    = ($old['ownership_type'] === 1 && $old['supplier_name'] !== '') ? "'" . $esc($old['supplier_name']) . "'" : "NULL";
            $address_v       = $old['address'] !== '' ? "'" . $esc($old['address']) . "'" : "NULL";
            $city_v          = $old['city']    !== '' ? "'" . $esc($old['city'])    . "'" : "NULL";
            $state_v         = $old['state']   !== '' ? "'" . $esc($old['state'])   . "'" : "NULL";
            $pincode_v       = $old['pincode'] !== '' ? "'" . $esc($old['pincode']) . "'" : "NULL";
            $active_v        = (int) $old['active'];

            if ($isEdit) {
                $sql = "UPDATE lorry SET lorry_number=$lorry_number_v, lorry_type=$lorry_type_v, ownership_type=$ownership_v, supplier_id=$supplier_v, preferred_driver_id=$pref_driver_v, capacity=$capacity_v, owner_name=$owner_name_v, address=$address_v, city=$city_v, state=$state_v, pincode=$pincode_v, active=$active_v WHERE id = $editId";
                if (!mysqli_query($conn, $sql)) throw new Exception('Failed to update truck: ' . mysqli_error($conn));
                $savedId = $editId;
            } else {
                $created_by = (int) ($_SESSION['user_id'] ?? 0);
                $sql = "INSERT INTO lorry (lorry_number, lorry_type, ownership_type, supplier_id, preferred_driver_id, capacity, owner_name, address, city, state, pincode, active, created_by) VALUES ($lorry_number_v, $lorry_type_v, $ownership_v, $supplier_v, $pref_driver_v, $capacity_v, $owner_name_v, $address_v, $city_v, $state_v, $pincode_v, $active_v, $created_by)";
                if (!mysqli_query($conn, $sql)) throw new Exception('Failed to save truck: ' . mysqli_error($conn));
                $savedId = (int) mysqli_insert_id($conn);
            }

            mysqli_commit($conn);

            if ($isPopup) {
                $popup_saved_id = $savedId;
                $popup_saved_label = $old['lorry_number'];
            } else {
                if ($isEdit) { header("Location: lorries-list.php?msg=updated"); exit; }
                elseif ($return_to !== '') {
                    $sep = (strpos($return_to, '?') !== false) ? '&' : '?';
                    header("Location: $return_to{$sep}new_lorry_id=$savedId&msg=added");
                    exit;
                } else { header("Location: lorries-list.php?msg=added"); exit; }
            }
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errorList[] = $e->getMessage();
        }
    }

    if (!empty($errorList)) {
        $error = implode('<br>', array_map(function ($e) {
            return '&bull; ' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8');
        }, $errorList));
    }
}

$popupPostScript = '';
if ($isPopup && $popup_saved_id > 0) {
    $safeId    = (int) $popup_saved_id;
    $safeLabel = json_encode($popup_saved_label, JSON_UNESCAPED_UNICODE);
    $popupPostScript = <<<JS
        <script>
            (function () {
                try {
                    window.parent.postMessage({
                        type: 'master-saved', entity: 'lorry',
                        id: $safeId, label: $safeLabel
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
            body { background: #fff; }
            #wrapper { display: block; }
            #content-wrapper { margin-left: 0 !important; }
            .container-fluid { padding: 1rem 1.25rem; }
            @media (max-width: 575.98px) { .container-fluid { padding: .75rem; } }
        </style>
    <?php endif; ?>
    <style>
        .ownership-picker { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        @media (max-width: 575.98px) { .ownership-picker { grid-template-columns: 1fr; } }
        .ownership-option {
            display: flex; align-items: center; gap: 12px; padding: 14px 16px;
            border: 2px solid #e3e6f0; border-radius: 10px; background: #fff;
            cursor: pointer; transition: all .15s ease; margin: 0; user-select: none;
        }
        .ownership-option:hover { border-color: #bac8f3; background: #fafbff; }
        .ownership-option input[type="radio"] { width: 18px; height: 18px; margin: 0; cursor: pointer; flex-shrink: 0; accent-color: #4e73df; }
        .ownership-option .icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: #f2f4fa; color: #6b6d7d; font-size: 1.05rem; flex-shrink: 0; transition: all .15s ease; }
        .ownership-option .own-label { display: flex; flex-direction: column; gap: 2px; flex: 1 1 auto; min-width: 0; }
        .ownership-option .own-label strong { font-size: .95rem; font-weight: 700; color: #2c2e3e; line-height: 1.2; }
        .ownership-option .own-label span { font-size: .78rem; color: #7a7d8d; line-height: 1.2; }
        .ownership-option.checked { border-color: #4e73df; background: #f2f6ff; box-shadow: 0 0 0 3px rgba(78, 115, 223, .10); }
        .ownership-option.checked .icon { background: #4e73df; color: #fff; }
        .ownership-option.checked .own-label strong { color: #224abe; }
        .js-owner-block.own-hidden { display: none !important; }
        .ownership-error { border-color: #e74a3b !important; }
        .card { border: 1px solid #e3e6f0; }
        .card-header { background: #fbfcff; border-bottom: 1px solid #eaeef6; }
        .card-header h6 { font-size: .88rem; }
        .form-control, .custom-select { height: auto; padding: .5rem .75rem; font-size: .9rem; }
        select.form-control { height: auto; }
        .form-group label { font-size: .85rem; font-weight: 600; color: #3a3b45; margin-bottom: .35rem; }
        .form-text { font-size: .78rem; }
    </style>
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
            <input type="hidden" name="supplier_id" id="supplier_id" value="<?= (int) $old['supplier_id'] ?>">

            <div class="card shadow-sm mb-3">
                <div class="card-header py-2">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-user-shield mr-1"></i> Truck Ownership <span class="text-danger">*</span>
                    </h6>
                </div>
                <div class="card-body py-3">
                    <div class="ownership-picker js-ownership-picker <?= $old['ownership_type'] === -1 ? 'ownership-error' : '' ?>">
                        <label class="ownership-option js-own-option <?= $old['ownership_type'] === 0 ? 'checked' : '' ?>">
                            <input type="radio" name="ownership_type" value="0" class="js-ownership-radio" <?= $old['ownership_type'] === 0 ? 'checked' : '' ?>>
                            <span class="icon"><i class="fas fa-truck"></i></span>
                            <span class="own-label"><strong>Own Truck</strong><span>Company vehicle</span></span>
                        </label>
                        <label class="ownership-option js-third-option <?= $old['ownership_type'] === 1 ? 'checked' : '' ?>">
                            <input type="radio" name="ownership_type" value="1" class="js-ownership-radio" <?= $old['ownership_type'] === 1 ? 'checked' : '' ?>>
                            <span class="icon"><i class="fas fa-handshake"></i></span>
                            <span class="own-label"><strong>3rd Party</strong><span>Rented from supplier</span></span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header py-2">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-truck mr-1"></i> Vehicle Details</h6>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="lorry_number">Truck Number <span class="text-danger">*</span></label>
                            <input type="text" id="lorry_number" name="lorry_number" class="form-control text-uppercase" maxlength="15" placeholder="Enter truck number" value="<?= htmlspecialchars($old['lorry_number'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="lorry_type">Truck Type</label>
                            <select id="lorry_type" name="lorry_type" class="form-control">
                                <option value="">— Select —</option>
                                <?php foreach ($types as $t): ?>
                                    <option value="<?= $t ?>" <?= $old['lorry_type'] === $t ? 'selected' : '' ?>><?= $t ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row mb-0">
                        <div class="form-group col-md-6 mb-0">
                            <label for="capacity">Capacity</label>
                            <input type="text" id="capacity" name="capacity" class="form-control" maxlength="15" placeholder="Enter capacity" value="<?= htmlspecialchars($old['capacity'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="js-owner-block <?= $old['ownership_type'] === 1 ? '' : 'own-hidden' ?>">
                <div class="card shadow-sm mb-3">
                    <div class="card-header py-2">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-handshake mr-1"></i> Supplier</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="supplier_name_input">Supplier Name <span class="text-danger">*</span></label>
                            <input type="text" id="supplier_name_input" name="supplier_name" class="form-control" list="supplierNameList" autocomplete="off" maxlength="150" placeholder="Type or select supplier..." value="<?= htmlspecialchars($old['supplier_name'], ENT_QUOTES, 'UTF-8') ?>">
                            <small class="form-text text-muted">Pick from list to auto-fill, or type a new name to create one.</small>
                        </div>
                        <div class="form-group">
                            <label for="address">Address</label>
                            <input type="text" id="address" name="address" class="form-control" maxlength="255" placeholder="Enter address" value="<?= htmlspecialchars($old['address'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="form-row mb-0">
                            <div class="form-group col-md-4 mb-md-0">
                                <label for="city">City</label>
                                <input type="text" id="city" name="city" class="form-control" maxlength="50" placeholder="City" value="<?= htmlspecialchars($old['city'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="form-group col-md-4 mb-md-0">
                                <label for="state">State</label>
                                <input type="text" id="state" name="state" class="form-control" maxlength="50" placeholder="State" value="<?= htmlspecialchars($old['state'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="form-group col-md-4 mb-0">
                                <label for="pincode">Pincode</label>
                                <input type="text" id="pincode" name="pincode" class="form-control" maxlength="6" placeholder="6 digits" value="<?= htmlspecialchars($old['pincode'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header py-2">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-id-card mr-1"></i> Preferred Driver</h6>
                </div>
                <div class="card-body py-3">
                    <div class="form-group mb-0">
                        <select id="preferred_driver_id" name="preferred_driver_id" class="form-control">
                            <option value="">— None —</option>
                            <?php foreach ($drivers as $d): ?>
                                <option value="<?= (int) $d['id'] ?>" <?= $old['preferred_driver_id'] === (int) $d['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d['driver_name'], ENT_QUOTES, 'UTF-8') ?>
                                    <?= !empty($d['phone']) ? ' — ' . htmlspecialchars($d['phone'], ENT_QUOTES, 'UTF-8') : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Auto-selected when this truck is used on a trip.</small>
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center justify-content-between flex-wrap mb-2">
                <div class="text-muted small mb-2 mb-sm-0">
                    <i class="fas fa-info-circle mr-1"></i> Fields marked <span class="text-danger">*</span> are required.
                </div>
                <div>
                    <button type="button" class="btn btn-secondary" id="popupCancelBtn">
                        <i class="fas fa-times mr-1"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check mr-1"></i> <?= $isEdit ? 'Update Truck' : 'Save Truck' ?>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <datalist id="supplierNameList">
        <?php foreach ($suppliers as $s): ?>
            <option value="<?= htmlspecialchars($s['supplier_name'], ENT_QUOTES, 'UTF-8') ?>"></option>
        <?php endforeach; ?>
    </datalist>

    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <script>
        $(function() {
            function applyOwnershipUI(val) {
                $('.js-own-option').toggleClass('checked', String(val) === '0');
                $('.js-third-option').toggleClass('checked', String(val) === '1');
                var $ownerBlock = $('.js-owner-block');
                if (String(val) === '1') $ownerBlock.removeClass('own-hidden');
                else $ownerBlock.addClass('own-hidden');
                if (String(val) === '0' || String(val) === '1') $('.js-ownership-picker').removeClass('ownership-error');
            }

            $(document).on('change', '.js-ownership-radio', function() { applyOwnershipUI($(this).val()); });

            (function() {
                var cur = $('.js-ownership-radio:checked').val();
                if (cur !== undefined) applyOwnershipUI(cur);
                else $('.js-owner-block').addClass('own-hidden');
            })();

            var SUPPLIERS = <?= json_encode(array_map(function ($s) {
                                return ['id' => (int) $s['id'], 'name' => $s['supplier_name'], 'address' => $s['address'] ?? ''];
                            }, $suppliers), JSON_UNESCAPED_UNICODE) ?>;
            var SUP_BY_NAME = {};
            for (var i = 0; i < SUPPLIERS.length; i++) SUP_BY_NAME[SUPPLIERS[i].name.toLowerCase()] = SUPPLIERS[i];

            function syncSupplierFromName() {
                var typed = ($('#supplier_name_input').val() || '').trim();
                if (typed === '') { $('#supplier_id').val(''); return; }
                var match = SUP_BY_NAME[typed.toLowerCase()];
                if (match) {
                    $('#supplier_id').val(match.id);
                    if (match.address && !$('#address').val()) $('#address').val(match.address);
                } else { $('#supplier_id').val(''); }
            }

            $(document).on('input change', '#supplier_name_input', syncSupplierFromName);
            $(document).on('submit', '#lorryForm', syncSupplierFromName);

            $(document).on('click', '#popupCancelBtn', function() {
                try { window.parent.postMessage({ type: 'master-cancelled', entity: 'lorry' }, '*'); } catch (e) {}
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
                            <i class="fas <?= $isEdit ? 'fa-truck-moving' : 'fa-truck' ?> mr-2"></i>
                            <?= $isEdit ? 'Edit Truck' : 'Add Truck' ?>
                        </h1>
                        <a href="lorries-list.php" class="d-inline-block btn btn-sm btn-secondary shadow-sm">
                            <i class="fas fa-arrow-left fa-sm text-white-50 mr-1"></i> Back to Trucks
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
                        <input type="hidden" name="supplier_id" id="supplier_id" value="<?= (int) $old['supplier_id'] ?>">

                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-user-shield mr-1"></i> Truck Ownership <span class="text-danger">*</span>
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="ownership-picker js-ownership-picker <?= $old['ownership_type'] === -1 ? 'ownership-error' : '' ?>">
                                    <label class="ownership-option js-own-option <?= $old['ownership_type'] === 0 ? 'checked' : '' ?>">
                                        <input type="radio" name="ownership_type" value="0" class="js-ownership-radio" <?= $old['ownership_type'] === 0 ? 'checked' : '' ?>>
                                        <span class="icon"><i class="fas fa-truck"></i></span>
                                        <span class="own-label"><strong>Own Truck</strong><span>Company vehicle</span></span>
                                    </label>
                                    <label class="ownership-option js-third-option <?= $old['ownership_type'] === 1 ? 'checked' : '' ?>">
                                        <input type="radio" name="ownership_type" value="1" class="js-ownership-radio" <?= $old['ownership_type'] === 1 ? 'checked' : '' ?>>
                                        <span class="icon"><i class="fas fa-handshake"></i></span>
                                        <span class="own-label"><strong>3rd Party</strong><span>Rented from supplier</span></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-truck mr-1"></i> Vehicle Details</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="lorry_number">Truck Number <span class="text-danger">*</span></label>
                                            <input type="text" id="lorry_number" name="lorry_number" class="form-control text-uppercase" maxlength="15" placeholder="Enter truck number" value="<?= htmlspecialchars($old['lorry_number'], ENT_QUOTES, 'UTF-8') ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="lorry_type">Truck Type</label>
                                            <select id="lorry_type" name="lorry_type" class="form-control">
                                                <option value="">— Select —</option>
                                                <?php foreach ($types as $t): ?>
                                                    <option value="<?= $t ?>" <?= $old['lorry_type'] === $t ? 'selected' : '' ?>><?= $t ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-0">
                                            <label for="capacity">Capacity</label>
                                            <input type="text" id="capacity" name="capacity" class="form-control" maxlength="15" placeholder="Enter capacity" value="<?= htmlspecialchars($old['capacity'], ENT_QUOTES, 'UTF-8') ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="js-owner-block <?= $old['ownership_type'] === 1 ? '' : 'own-hidden' ?>">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-handshake mr-1"></i> Supplier</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="supplier_name_input">Supplier Name <span class="text-danger">*</span></label>
                                                <input type="text" id="supplier_name_input" name="supplier_name" class="form-control" list="supplierNameList" autocomplete="off" maxlength="150" placeholder="Type or select supplier..." value="<?= htmlspecialchars($old['supplier_name'], ENT_QUOTES, 'UTF-8') ?>">
                                                <small class="form-text text-muted">Pick from the list to auto-fill, or type a new name to create a supplier.</small>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="address">Address</label>
                                                <input type="text" id="address" name="address" class="form-control" maxlength="255" placeholder="Enter address" value="<?= htmlspecialchars($old['address'], ENT_QUOTES, 'UTF-8') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group mb-md-0">
                                                <label for="city">City</label>
                                                <input type="text" id="city" name="city" class="form-control" maxlength="50" placeholder="Enter city" value="<?= htmlspecialchars($old['city'], ENT_QUOTES, 'UTF-8') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group mb-md-0">
                                                <label for="state">State</label>
                                                <input type="text" id="state" name="state" class="form-control" maxlength="50" placeholder="Enter state" value="<?= htmlspecialchars($old['state'], ENT_QUOTES, 'UTF-8') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group mb-0">
                                                <label for="pincode">Pincode</label>
                                                <input type="text" id="pincode" name="pincode" class="form-control" maxlength="6" placeholder="6 digits" value="<?= htmlspecialchars($old['pincode'], ENT_QUOTES, 'UTF-8') ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-id-card mr-1"></i> Preferred Driver</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-0">
                                            <select id="preferred_driver_id" name="preferred_driver_id" class="form-control">
                                                <option value="">— None —</option>
                                                <?php foreach ($drivers as $d): ?>
                                                    <option value="<?= (int) $d['id'] ?>" <?= $old['preferred_driver_id'] === (int) $d['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($d['driver_name'], ENT_QUOTES, 'UTF-8') ?>
                                                        <?= !empty($d['phone']) ? ' — ' . htmlspecialchars($d['phone'], ENT_QUOTES, 'UTF-8') : '' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="form-text text-muted">Auto-selected as driver when this truck is used on a trip.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow mb-4">
                            <div class="card-body py-3 d-flex align-items-center justify-content-between flex-wrap">
                                <div class="text-muted small mb-2 mb-sm-0">
                                    <i class="fas fa-info-circle mr-1"></i> Fields marked <span class="text-danger">*</span> are required.
                                </div>
                                <div>
                                    <?php if ($isEdit): ?>
                                        <a href="lorries-list.php" class="btn btn-secondary"><i class="fas fa-times mr-1"></i>Cancel</a>
                                    <?php else: ?>
                                        <a href="lorry.php" class="btn btn-secondary"><i class="fas fa-redo mr-1"></i>Clear</a>
                                    <?php endif; ?>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-check mr-1"></i> <?= $isEdit ? 'Update Truck' : 'Save Truck' ?>
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

    <datalist id="supplierNameList">
        <?php foreach ($suppliers as $s): ?>
            <option value="<?= htmlspecialchars($s['supplier_name'], ENT_QUOTES, 'UTF-8') ?>"></option>
        <?php endforeach; ?>
    </datalist>

    <script>
        $(function() {
            function applyOwnershipUI(val) {
                $('.js-own-option').toggleClass('checked', String(val) === '0');
                $('.js-third-option').toggleClass('checked', String(val) === '1');
                var $ownerBlock = $('.js-owner-block');
                if (String(val) === '1') $ownerBlock.removeClass('own-hidden');
                else $ownerBlock.addClass('own-hidden');
                if (String(val) === '0' || String(val) === '1') $('.js-ownership-picker').removeClass('ownership-error');
            }

            $(document).on('change', '.js-ownership-radio', function() { applyOwnershipUI($(this).val()); });

            (function() {
                var cur = $('.js-ownership-radio:checked').val();
                if (cur !== undefined) applyOwnershipUI(cur);
                else $('.js-owner-block').addClass('own-hidden');
            })();

            var SUPPLIERS = <?= json_encode(array_map(function ($s) {
                                return ['id' => (int) $s['id'], 'name' => $s['supplier_name'], 'address' => $s['address'] ?? ''];
                            }, $suppliers), JSON_UNESCAPED_UNICODE) ?>;
            var SUP_BY_NAME = {};
            for (var i = 0; i < SUPPLIERS.length; i++) SUP_BY_NAME[SUPPLIERS[i].name.toLowerCase()] = SUPPLIERS[i];

            function syncSupplierFromName() {
                var typed = ($('#supplier_name_input').val() || '').trim();
                if (typed === '') { $('#supplier_id').val(''); return; }
                var match = SUP_BY_NAME[typed.toLowerCase()];
                if (match) {
                    $('#supplier_id').val(match.id);
                    if (match.address && !$('#address').val()) $('#address').val(match.address);
                } else { $('#supplier_id').val(''); }
            }

            $(document).on('input change', '#supplier_name_input', syncSupplierFromName);
            $(document).on('submit', '#lorryForm', syncSupplierFromName);
        });
    </script>

<?php endif; ?>

</body>
</html>