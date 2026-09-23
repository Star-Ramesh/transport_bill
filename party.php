<?php

include 'constant.php';
include 'session.php';

/* =========================================================
   DETERMINE MODE — Add or Edit
   ========================================================= */
$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$isEdit = $editId > 0;

/* Popup mode — rendered inside an iframe from trip.php */
$isPopup = isset($_GET['popup']) && (int) $_GET['popup'] === 1;

/* Return-to-sender (kept — dormant unless ?return= is present) */
$return_to = isset($_GET['return'])
    ? preg_replace('/[^a-z0-9_\-\.]/i', '', $_GET['return'])
    : '';

requirePermission($isEdit ? 'party.edit' : 'party.create');

$pageTitle = $isEdit ? 'Edit Party | Billing Portal' : 'Add Party | Billing Portal';

/* =========================================================
   FORM STATE
   ========================================================= */
$error     = '';
$errorList = [];

/* Popup result holders — declared up front */
$popup_saved_id    = 0;
$popup_saved_label = '';

$old = [
    'branch_id'  => 0,
    'gstin'      => '',
    'legal_name' => '',
    'trade_name' => '',
    'phone'      => '',
    'email'      => '',
    'address'    => '',
    'city'       => '',
    'state'      => '',
    'pincode'    => '',
    'status'     => '',
    'active'     => 1,
];

/* =========================================================
   LOAD (Edit mode)
   ========================================================= */
if ($isEdit) {

    $res = mysqli_query($conn, "SELECT * FROM party WHERE id = $editId LIMIT 1");

    if (!$res || mysqli_num_rows($res) !== 1) {
        if ($isPopup) {
            echo "<p class='text-danger p-3'>Party not found.</p>";
            exit;
        }
        header("Location: parties-list.php");
        exit;
    }

    $row = mysqli_fetch_assoc($res);

    $old['branch_id']  = (int) ($row['branch_id'] ?? 0);
    $old['gstin']      = $row['gstin']      ?? '';
    $old['legal_name'] = $row['legal_name'] ?? '';
    $old['trade_name'] = $row['trade_name'] ?? '';
    $old['phone']      = $row['phone']      ?? '';
    $old['email']      = $row['email']      ?? '';
    $old['address']    = $row['address']    ?? '';
    $old['city']       = $row['city']       ?? '';
    $old['state']      = $row['state']      ?? '';
    $old['pincode']    = $row['pincode']    ?? '';
    $old['status']     = $row['status']     ?? '';
    $old['active']     = (int) ($row['active'] ?? 1);
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
   HANDLE SUBMIT
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* Popup mode may come in via POST as a hidden field */
    if (isset($_POST['popup_mode']) && (int) $_POST['popup_mode'] === 1) {
        $isPopup = true;
    }

    $old['branch_id']  = (int) ($_POST['branch_id'] ?? 0);
    $old['gstin']      = strtoupper(trim($_POST['gstin']      ?? ''));
    $old['legal_name'] = trim($_POST['legal_name'] ?? '');
    $old['trade_name'] = trim($_POST['trade_name'] ?? '');
    $old['phone']      = trim($_POST['phone']      ?? '');
    $old['email']      = trim($_POST['email']      ?? '');
    $old['address']    = trim($_POST['address']    ?? '');
    $old['city']       = trim($_POST['city']       ?? '');
    $old['state']      = trim($_POST['state']      ?? '');
    $old['pincode']    = trim($_POST['pincode']    ?? '');
    $old['status']     = trim($_POST['status']     ?? '');
    $old['active']     = isset($_POST['active']) ? (int) $_POST['active'] : 1;

    /* Return-to-sender: prefer posted value over GET */
    if (isset($_POST['return_to'])) {
        $return_to = preg_replace('/[^a-z0-9_\-\.]/i', '', $_POST['return_to']);
    }

    /* Validate */
    if ($old['branch_id'] <= 0) {
        $errorList[] = 'Please select a branch.';
    }

    if ($old['legal_name'] === '') {
        $errorList[] = 'Legal name is required.';
    }

    if ($old['gstin'] !== '') {
        $gstPattern = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/';
        if (!preg_match($gstPattern, $old['gstin'])) {
            $errorList[] = 'Invalid GSTIN format. Expected 15 characters like 27AAAAA0000A1Z5.';
        }
    }

    if ($old['phone'] !== '' && !preg_match('/^[0-9+\-\s]{6,15}$/', $old['phone'])) {
        $errorList[] = 'Invalid phone number. Use 6-15 digits.';
    }

    if ($old['email'] !== '') {
        if (
            !filter_var($old['email'], FILTER_VALIDATE_EMAIL)
            || !preg_match('/^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i', $old['email'])
        ) {
            $errorList[] = 'Invalid email address. Expected format: name@domain.com';
        }
    }

    if ($old['pincode'] !== '' && !preg_match('/^[0-9]{6}$/', $old['pincode'])) {
        $errorList[] = 'Pincode must be exactly 6 digits.';
    }

    if (strlen($old['trade_name']) > 255) {
        $errorList[] = 'Trade name is too long (max 255).';
    }
    if (strlen($old['address']) > 500) {
        $errorList[] = 'Address is too long (max 500).';
    }

    /* Duplicate GSTIN check */
    if (empty($errorList) && $old['gstin'] !== '') {

        $gstin_safe = mysqli_real_escape_string($conn, $old['gstin']);

        $sqlDup = "SELECT id FROM party
                   WHERE UPPER(gstin) = UPPER('$gstin_safe')";

        if ($isEdit) {
            $sqlDup .= " AND id <> $editId";
        }

        $sqlDup .= " LIMIT 1";

        $dup = mysqli_query($conn, $sqlDup);

        if (!$dup) {
            $errorList[] = 'Database error: ' . mysqli_error($conn);
        } elseif (mysqli_num_rows($dup) > 0) {
            $errorList[] = 'A party with this GSTIN already exists.';
        }
    }

    /* Insert or Update */
    if (empty($errorList)) {

        $esc = function ($v) use ($conn) {
            return mysqli_real_escape_string($conn, $v);
        };

        $branch_v     = (int) $old['branch_id'];
        $gstin_v      = $old['gstin']      !== '' ? "'" . $esc($old['gstin'])      . "'" : "NULL";
        $trade_name_v = $old['trade_name'] !== '' ? "'" . $esc($old['trade_name']) . "'" : "NULL";
        $address_v    = $old['address']    !== '' ? "'" . $esc($old['address'])    . "'" : "NULL";
        $city_v       = $old['city']       !== '' ? "'" . $esc($old['city'])       . "'" : "NULL";
        $state_v      = $old['state']      !== '' ? "'" . $esc($old['state'])      . "'" : "NULL";
        $pincode_v    = $old['pincode']    !== '' ? "'" . $esc($old['pincode'])    . "'" : "NULL";
        $phone_v      = $old['phone']      !== '' ? "'" . $esc($old['phone'])      . "'" : "NULL";
        $email_v      = $old['email']      !== '' ? "'" . $esc($old['email'])      . "'" : "NULL";
        $status_v     = $old['status']     !== '' ? "'" . $esc($old['status'])     . "'" : "NULL";
        $legal_name_v = "'" . $esc($old['legal_name']) . "'";
        $active_v     = (int) $old['active'];

        if ($isEdit) {

            $sql = "UPDATE party SET
                        branch_id  = $branch_v,
                        gstin      = $gstin_v,
                        legal_name = $legal_name_v,
                        trade_name = $trade_name_v,
                        address    = $address_v,
                        city       = $city_v,
                        state      = $state_v,
                        pincode    = $pincode_v,
                        phone      = $phone_v,
                        email      = $email_v,
                        status     = $status_v,
                        active     = $active_v
                    WHERE id = $editId";

            if (mysqli_query($conn, $sql)) {
                if ($isPopup) {
                    $popup_saved_id    = $editId;
                    $popup_saved_label = $old['legal_name'];
                } else {
                    header("Location: parties-list.php?msg=updated");
                    exit;
                }
            } else {
                $errorList[] = 'Failed to update party: ' . mysqli_error($conn);
            }
        } else {

            $sql = "INSERT INTO party
                        (branch_id, gstin, legal_name, trade_name, address, city, state,
                         pincode, phone, email, status, active)
                    VALUES
                        ($branch_v, $gstin_v, $legal_name_v, $trade_name_v, $address_v,
                         $city_v, $state_v, $pincode_v, $phone_v, $email_v,
                         $status_v, $active_v)";

            if (mysqli_query($conn, $sql)) {

                $newId = mysqli_insert_id($conn);

                if ($isPopup) {
                    $popup_saved_id    = $newId;
                    $popup_saved_label = $old['legal_name'];
                } elseif ($return_to !== '') {
                    $sep = (strpos($return_to, '?') !== false) ? '&' : '?';
                    header("Location: $return_to{$sep}new_party_id=$newId&msg=added");
                    exit;
                } else {
                    header("Location: parties-list.php?msg=added");
                    exit;
                }
            } else {
                $errorList[] = 'Failed to save party: ' . mysqli_error($conn);
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
   POPUP MODE — send postMessage to parent on success
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
                        entity: 'party',
                        id:    $safeId,
                        label: $safeLabel
                    }, '*');
                } catch (e) {
                    console.error('postMessage failed', e);
                }
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

        <!-- =========================================================
         POPUP MODE — no chrome, form only
         ========================================================= -->
        <div class="container-fluid">

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

            <div id="gstAlert"></div>

            <!-- GST VERIFICATION -->
            <div class="card shadow-sm mb-3">
                <div class="card-header py-2 d-flex align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-search mr-1"></i>
                        GST Verification
                    </h6>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="no_gst">
                        <label class="custom-control-label" for="no_gst">
                            Party has no GSTIN
                        </label>
                    </div>
                </div>
                <div class="card-body py-3">

                    <div class="form-group mb-0">
                        <label for="gstin">GST Number</label>
                        <div class="input-group">
                            <input
                                type="text"
                                id="gstin"
                                name="gstin"
                                form="partyForm"
                                class="form-control text-uppercase"
                                maxlength="15"
                                autocomplete="off"
                                placeholder="Enter GSTIN"
                                value="<?= htmlspecialchars($old['gstin'], ENT_QUOTES, 'UTF-8') ?>">

                            <div class="input-group-append">
                                <button type="button" id="fetchGst" class="btn btn-primary">
                                    <span id="fetchText">
                                        <i class="fas fa-search mr-1"></i>
                                        Fetch Details
                                    </span>
                                    <span id="fetchLoading" class="d-none">
                                        <span class="spinner-border spinner-border-sm mr-1"></span>
                                        Fetching...
                                    </span>
                                </button>

                                <button type="button" id="changeGst"
                                    class="btn btn-outline-secondary d-none">
                                    <i class="fas fa-times mr-1"></i>
                                    Change GSTIN
                                </button>
                            </div>
                        </div>

                        <small class="form-text text-muted" id="gstHelpText">
                            Enter GSTIN to automatically fill the registered party details.
                        </small>
                    </div>

                    <div id="gstResult" class="d-none mt-3">
                        <div class="alert alert-light border mb-0 py-2">
                            <div class="row small">
                                <div class="col-md-4 mb-1 mb-md-0">
                                    <strong>GSTIN:</strong>
                                    <span id="resultGstin">-</span>
                                </div>
                                <div class="col-md-4 mb-1 mb-md-0">
                                    <strong>Status:</strong>
                                    <span id="resultStatus" class="badge badge-success">-</span>
                                </div>
                                <div class="col-md-4">
                                    <strong>Taxpayer Type:</strong>
                                    <span id="resultType">-</span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- PARTY FORM -->
            <form id="partyForm" action="" method="POST" novalidate>

                <input type="hidden" name="status" id="gst_status"
                    value="<?= htmlspecialchars($old['status'], ENT_QUOTES, 'UTF-8') ?>">

                <input type="hidden" name="active" value="<?= (int) $old['active'] ?>">

                <input type="hidden" name="return_to"
                    value="<?= htmlspecialchars($return_to, ENT_QUOTES, 'UTF-8') ?>">

                <input type="hidden" name="popup_mode" value="1">

                <!-- PARTY DETAILS -->
                <div class="card shadow-sm mb-3">
                    <div class="card-header py-2">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-user mr-1"></i>
                            Party Details
                        </h6>
                    </div>
                    <div class="card-body">

                        <div class="form-row">
                            <div class="form-group col-md-6">
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

                            <div class="form-group col-md-6">
                                <label for="legal_name">
                                    Legal Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="legal_name" name="legal_name"
                                    class="form-control" maxlength="255"
                                    placeholder="Enter legal name"
                                    value="<?= htmlspecialchars($old['legal_name'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="trade_name">Trade Name</label>
                                <input type="text" id="trade_name" name="trade_name"
                                    class="form-control" maxlength="255"
                                    placeholder="Enter trade name"
                                    value="<?= htmlspecialchars($old['trade_name'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div class="form-group col-md-6">
                                <label for="phone">Phone</label>
                                <input type="text" id="phone" name="phone"
                                    class="form-control" maxlength="15"
                                    placeholder="Enter phone number"
                                    value="<?= htmlspecialchars($old['phone'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="text" id="email" name="email"
                                class="form-control" maxlength="150"
                                placeholder="Enter email address"
                                value="<?= htmlspecialchars($old['email'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" rows="2"
                                class="form-control" maxlength="500"
                                placeholder="Enter address"><?= htmlspecialchars($old['address'], ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-4 mb-md-0">
                                <label for="city">City</label>
                                <input type="text" id="city" name="city"
                                    class="form-control" maxlength="100"
                                    placeholder="Enter city"
                                    value="<?= htmlspecialchars($old['city'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="form-group col-md-4 mb-md-0">
                                <label for="state">State</label>
                                <input type="text" id="state" name="state"
                                    class="form-control" maxlength="100"
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

                <!-- ACTION BAR -->
                <div class="d-flex align-items-center justify-content-between flex-wrap mb-2">

                    <div class="text-muted small mb-2 mb-sm-0">
                        <i class="fas fa-info-circle mr-1"></i>
                        Fields marked <span class="text-danger">*</span> are required.
                    </div>

                    <div>
                        <button type="button" class="btn btn-secondary" id="popupCancelBtn">
                            <i class="fas fa-times mr-1"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-success" id="saveParty">
                            <i class="fas fa-check mr-1"></i>
                            <?= $isEdit ? 'Update Party' : 'Save Party' ?>
                        </button>
                    </div>

                </div>

            </form>

        </div>

        <script src="vendor/jquery/jquery.min.js"></script>
        <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
        <script src="js/party.js"></script>

        <script>
            $(function() {
                $(document).on('click', '#popupCancelBtn', function() {
                    try {
                        window.parent.postMessage({
                            type: 'master-cancelled',
                            entity: 'party'
                        }, '*');
                    } catch (e) {
                        console.error('postMessage failed', e);
                    }
                });
            });
        </script>

        <?= $popupPostScript ?>

    <?php else: ?>

        <!-- =========================================================
         NORMAL MODE — full app chrome
         ========================================================= -->
        <div id="wrapper">

            <?php include 'layout/sidebar.php'; ?>

            <div id="content-wrapper" class="d-flex flex-column">

                <div id="content">

                    <?php include 'layout/topbar.php'; ?>

                    <div class="container-fluid">

                        <div class="d-sm-flex align-items-center justify-content-between mb-4">
                            <h1 class="h3 mb-0 text-gray-800">
                                <i class="fas <?= $isEdit ? 'fa-user-edit' : 'fa-user-plus' ?> mr-2"></i>
                                <?= $isEdit ? 'Edit Party' : 'Add Party' ?>
                            </h1>

                            <a href="parties-list.php"
                                class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
                                <i class="fas fa-arrow-left fa-sm text-white-50 mr-1"></i>
                                Back to Parties
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

                        <div id="gstAlert"></div>

                        <!-- GST VERIFICATION -->
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-search mr-1"></i>
                                    GST Verification
                                </h6>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="no_gst">
                                    <label class="custom-control-label" for="no_gst">
                                        Party has no GSTIN
                                    </label>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="form-group mb-0">
                                    <label for="gstin">GST Number</label>
                                    <div class="input-group">
                                        <input type="text" id="gstin" name="gstin" form="partyForm"
                                            class="form-control text-uppercase" maxlength="15"
                                            autocomplete="off" placeholder="Enter GSTIN"
                                            value="<?= htmlspecialchars($old['gstin'], ENT_QUOTES, 'UTF-8') ?>">
                                        <div class="input-group-append">
                                            <button type="button" id="fetchGst" class="btn btn-primary">
                                                <span id="fetchText">
                                                    <i class="fas fa-search mr-1"></i>
                                                    Fetch Details
                                                </span>
                                                <span id="fetchLoading" class="d-none">
                                                    <span class="spinner-border spinner-border-sm mr-1"></span>
                                                    Fetching...
                                                </span>
                                            </button>
                                            <button type="button" id="changeGst"
                                                class="btn btn-outline-secondary d-none">
                                                <i class="fas fa-times mr-1"></i>
                                                Change GSTIN
                                            </button>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted" id="gstHelpText">
                                        Enter GSTIN to automatically fill the registered party details.
                                    </small>
                                </div>
                                <div id="gstResult" class="d-none mt-3">
                                    <div class="alert alert-light border mb-0">
                                        <div class="row">
                                            <div class="col-md-4 mb-2 mb-md-0">
                                                <strong>GSTIN:</strong>
                                                <span id="resultGstin">-</span>
                                            </div>
                                            <div class="col-md-4 mb-2 mb-md-0">
                                                <strong>Status:</strong>
                                                <span id="resultStatus" class="badge badge-success">-</span>
                                            </div>
                                            <div class="col-md-4">
                                                <strong>Taxpayer Type:</strong>
                                                <span id="resultType">-</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- PARTY FORM -->
                        <form id="partyForm" action="" method="POST" novalidate>

                            <input type="hidden" name="status" id="gst_status"
                                value="<?= htmlspecialchars($old['status'], ENT_QUOTES, 'UTF-8') ?>">

                            <input type="hidden" name="active" value="<?= (int) $old['active'] ?>">

                            <input type="hidden" name="return_to"
                                value="<?= htmlspecialchars($return_to, ENT_QUOTES, 'UTF-8') ?>">

                            <!-- PARTY DETAILS -->
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="fas fa-user mr-1"></i>
                                        Party Details
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
                                                <label for="legal_name">
                                                    Legal Name <span class="text-danger">*</span>
                                                </label>
                                                <input type="text" id="legal_name" name="legal_name"
                                                    class="form-control" maxlength="255"
                                                    placeholder="Enter legal name"
                                                    value="<?= htmlspecialchars($old['legal_name'], ENT_QUOTES, 'UTF-8') ?>">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="trade_name">Trade Name</label>
                                                <input type="text" id="trade_name" name="trade_name"
                                                    class="form-control" maxlength="255"
                                                    placeholder="Enter trade name"
                                                    value="<?= htmlspecialchars($old['trade_name'], ENT_QUOTES, 'UTF-8') ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- CONTACT -->
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="fas fa-address-book mr-1"></i>
                                        Contact Information
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="phone">Mobile Number</label>
                                                <input type="text" id="phone" name="phone"
                                                    class="form-control" maxlength="15"
                                                    placeholder="Enter mobile number"
                                                    value="<?= htmlspecialchars($old['phone'], ENT_QUOTES, 'UTF-8') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="email">Email Address</label>
                                                <input type="text" id="email" name="email"
                                                    class="form-control" maxlength="150"
                                                    placeholder="Enter email address"
                                                    value="<?= htmlspecialchars($old['email'], ENT_QUOTES, 'UTF-8') ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ADDRESS -->
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="fas fa-map-marker-alt mr-1"></i>
                                        Address
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="address">Registered Address</label>
                                                <textarea id="address" name="address" rows="3"
                                                    class="form-control" maxlength="500"
                                                    placeholder="Enter address"><?= htmlspecialchars($old['address'], ENT_QUOTES, 'UTF-8') ?></textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="city">City / Locality</label>
                                                <input type="text" id="city" name="city"
                                                    class="form-control" maxlength="100"
                                                    placeholder="Enter city"
                                                    value="<?= htmlspecialchars($old['city'], ENT_QUOTES, 'UTF-8') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="state">State</label>
                                                <input type="text" id="state" name="state"
                                                    class="form-control" maxlength="100"
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

                            <!-- ACTION BAR -->
                            <div class="card shadow mb-4">
                                <div class="card-body py-3 d-flex align-items-center justify-content-between flex-wrap">
                                    <div class="text-muted small mb-2 mb-sm-0">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Fields marked <span class="text-danger">*</span> are required.
                                    </div>
                                    <div>
                                        <?php if ($isEdit): ?>
                                            <a href="parties-list.php" class="btn btn-secondary">
                                                <i class="fas fa-times mr-1"></i>
                                                Cancel
                                            </a>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-secondary" id="clearParty">
                                                <i class="fas fa-redo mr-1"></i>
                                                Clear
                                            </button>
                                        <?php endif; ?>

                                        <button type="submit" class="btn btn-success" id="saveParty">
                                            <i class="fas fa-check mr-1"></i>
                                            <?= $isEdit ? 'Update Party' : 'Save Party' ?>
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

        <script src="js/party.js"></script>

    <?php endif; ?>

</body>

</html>