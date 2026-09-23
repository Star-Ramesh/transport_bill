<?php

include 'session.php';
include 'constant.php';

requirePermission('trip.create');

$pageTitle = 'Add Trip | Billing Portal';

/* =========================================================
   LOAD DROPDOWN DATA (branch-scoped)
   ========================================================= */
$isAdminUser = isAdmin();
$myBranch    = (int) ($_SESSION['branch_id'] ?? 0);

/* Branches */
$branches = [];
$resB = mysqli_query(
    $conn,
    "SELECT id, branch_name, branch_code FROM branch WHERE active = 1 ORDER BY branch_name"
);
if ($resB) {
    while ($b = mysqli_fetch_assoc($resB)) {
        $branches[] = $b;
    }
}

/* Lorries */
$lorries = [];
$sqlL = "SELECT l.id, l.lorry_number, l.lorry_type
         FROM lorry l WHERE l.active = 1";
if (!$isAdminUser && $myBranch > 0) {
    $sqlL .= " AND l.branch_id = $myBranch";
}
$sqlL .= " ORDER BY l.lorry_number";
$resL = mysqli_query($conn, $sqlL);
if ($resL) {
    while ($r = mysqli_fetch_assoc($resL)) {
        $lorries[] = $r;
    }
}

/* Drivers */
$drivers = [];
$sqlD = "SELECT d.id, d.driver_name, d.phone
         FROM driver d WHERE d.active = 1";
if (!$isAdminUser && $myBranch > 0) {
    $sqlD .= " AND d.branch_id = $myBranch";
}
$sqlD .= " ORDER BY d.driver_name";
$resD = mysqli_query($conn, $sqlD);
if ($resD) {
    while ($r = mysqli_fetch_assoc($resD)) {
        $drivers[] = $r;
    }
}

/* Parties */
$parties = [];
$sqlP = "SELECT p.id, p.legal_name, p.trade_name
         FROM party p WHERE p.active = 1";
if (!$isAdminUser && $myBranch > 0) {
    $sqlP .= " AND p.branch_id = $myBranch";
}
$sqlP .= " ORDER BY p.legal_name";
$resP = mysqli_query($conn, $sqlP);
if ($resP) {
    while ($r = mysqli_fetch_assoc($resP)) {
        $parties[] = $r;
    }
}

/* =========================================================
   HANDLE SUBMIT
   ========================================================= */
$error     = '';
$errorList = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $lorry_id       = (int) ($_POST['lorry_id']       ?? 0);
    $driver_id      = (int) ($_POST['driver_id']      ?? 0);
    $from_branch_id = (int) ($_POST['from_branch_id'] ?? 0);
    $to_branch_id   = (int) ($_POST['to_branch_id']   ?? 0);
    $start_date     = trim($_POST['start_date']  ?? '');
    $trip_notes     = trim($_POST['trip_notes']  ?? '');

    $party_id       = (int) ($_POST['party_id']       ?? 0);
    $freight_amount = trim($_POST['freight_amount'] ?? '0');
    $advance_paid   = trim($_POST['advance_paid']   ?? '0');
    $party_notes    = trim($_POST['party_notes']    ?? '');

    $items = [];
    if (isset($_POST['items']) && is_array($_POST['items'])) {
        foreach ($_POST['items'] as $row) {
            $itm_name = trim($row['item_name'] ?? '');
            if ($itm_name === '') continue;

            $items[] = [
                'item_id'   => (int) ($row['item_id']  ?? 0),
                'item_name' => $itm_name,
                'unit'      => trim($row['unit']     ?? ''),
                'quantity'  => trim($row['quantity'] ?? '0'),
                'rate'      => trim($row['rate']     ?? '0'),
                'amount'    => trim($row['amount']   ?? '0'),
            ];
        }
    }

    /* Validate */
    if ($lorry_id  <= 0) $errorList[] = 'Please select a lorry.';
    if ($driver_id <= 0) $errorList[] = 'Please select a driver.';

    if ($from_branch_id <= 0) $errorList[] = 'Please select the "From" branch.';
    if ($to_branch_id   <= 0) $errorList[] = 'Please select the "To" branch.';

    if ($start_date === '') {
        $errorList[] = 'Start date is required.';
    } else {
        $d = DateTime::createFromFormat('Y-m-d', $start_date);
        if (!$d || $d->format('Y-m-d') !== $start_date) {
            $errorList[] = 'Invalid start date.';
        }
    }

    if ($party_id <= 0) $errorList[] = 'Please select a party.';
    if ($freight_amount === '' || !is_numeric($freight_amount) || (float) $freight_amount < 0) {
        $errorList[] = 'Freight amount must be a valid non-negative number.';
    }
    if ($advance_paid !== '' && (!is_numeric($advance_paid) || (float) $advance_paid < 0)) {
        $errorList[] = 'Advance paid must be a valid non-negative number.';
    }

    if (count($items) === 0) {
        $errorList[] = 'Add at least one item to the trip.';
    } else {
        foreach ($items as $i => $it) {
            $n = $i + 1;
            if ($it['quantity'] === '' || !is_numeric($it['quantity']) || (float) $it['quantity'] < 0) {
                $errorList[] = "Item #$n: quantity must be a valid number.";
            }
            if ($it['rate'] === '' || !is_numeric($it['rate']) || (float) $it['rate'] < 0) {
                $errorList[] = "Item #$n: rate must be a valid number.";
            }
        }
    }

    /* Save in a transaction */
    if (empty($errorList)) {

        /* Generate trip_no */
        $bc_res = mysqli_query(
            $conn,
            "SELECT branch_code FROM branch WHERE id = $from_branch_id LIMIT 1"
        );
        $branch_code = 'BR';
        if ($bc_res && mysqli_num_rows($bc_res) === 1) {
            $brow = mysqli_fetch_assoc($bc_res);
            $branch_code = $brow['branch_code'] !== ''
                ? strtoupper($brow['branch_code']) : 'BR';
        }

        $yymm   = date('ym', strtotime($start_date));
        $prefix = $branch_code . '-' . $yymm . '-';

        $prefix_safe = mysqli_real_escape_string($conn, $prefix);
        $cnt_res = mysqli_query(
            $conn,
            "SELECT COUNT(*) AS c FROM trip
             WHERE branch_id = $from_branch_id
               AND trip_no LIKE '$prefix_safe%'"
        );
        $next_seq = 1;
        if ($cnt_res) {
            $crow = mysqli_fetch_assoc($cnt_res);
            $next_seq = ((int) $crow['c']) + 1;
        }
        $trip_no = $prefix . str_pad($next_seq, 4, '0', STR_PAD_LEFT);

        $esc = function ($v) use ($conn) {
            return mysqli_real_escape_string($conn, $v);
        };

        $lorry_v      = $lorry_id  > 0 ? $lorry_id  : "NULL";
        $driver_v     = $driver_id > 0 ? $driver_id : "NULL";
        $trip_notes_v = $trip_notes !== '' ? "'" . $esc($trip_notes) . "'" : "NULL";
        $start_date_v = "'" . $esc($start_date) . "'";
        $end_date_v   = "NULL";
        $trip_no_v    = "'" . $esc($trip_no) . "'";
        $created_by_v = (int) ($_SESSION['user_id'] ?? 0);

        mysqli_begin_transaction($conn);

        try {
            /* Distance has been dropped from the table.
               End Date is present in the table but always NULL. */
            $sql_trip = "INSERT INTO trip
                            (trip_no, lorry_id, driver_id, from_branch_id, to_branch_id,
                             start_date, end_date, status, notes,
                             branch_id, created_by, active)
                         VALUES
                            ($trip_no_v, $lorry_v, $driver_v, $from_branch_id, $to_branch_id,
                             $start_date_v, $end_date_v, 'Scheduled', $trip_notes_v,
                             $from_branch_id, $created_by_v, 1)";

            if (!mysqli_query($conn, $sql_trip)) {
                throw new Exception('Failed to save trip: ' . mysqli_error($conn));
            }
            $new_trip_id = mysqli_insert_id($conn);

            $freight_v = "'" . number_format((float) $freight_amount, 2, '.', '') . "'";
            $advance_v = "'" . number_format((float) ($advance_paid !== '' ? $advance_paid : 0), 2, '.', '') . "'";
            $pnotes_v  = $party_notes !== '' ? "'" . $esc($party_notes) . "'" : "NULL";

            $sql_tp = "INSERT INTO trip_party
                          (trip_id, party_id, freight_amount, advance_paid, notes, active)
                       VALUES
                          ($new_trip_id, $party_id, $freight_v, $advance_v, $pnotes_v, 1)";

            if (!mysqli_query($conn, $sql_tp)) {
                throw new Exception('Failed to save party consignment: ' . mysqli_error($conn));
            }
            $new_trip_party_id = mysqli_insert_id($conn);

            foreach ($items as $it) {
                $it_name_v = "'" . $esc($it['item_name']) . "'";
                $it_unit_v = $it['unit'] !== '' ? "'" . $esc($it['unit']) . "'" : "NULL";
                $it_id_v   = $it['item_id'] > 0 ? (int) $it['item_id'] : "NULL";

                $qty  = (float) $it['quantity'];
                $rate = (float) $it['rate'];
                $amt  = $qty * $rate;

                $qty_v  = "'" . number_format($qty,  3, '.', '') . "'";
                $rate_v = "'" . number_format($rate, 2, '.', '') . "'";
                $amt_v  = "'" . number_format($amt,  2, '.', '') . "'";

                $sql_it = "INSERT INTO trip_item
                              (trip_party_id, item_id, item_name, unit,
                               quantity, rate, amount, active)
                           VALUES
                              ($new_trip_party_id, $it_id_v, $it_name_v, $it_unit_v,
                               $qty_v, $rate_v, $amt_v, 1)";

                if (!mysqli_query($conn, $sql_it)) {
                    throw new Exception('Failed to save item "' . $it['item_name'] . '": ' . mysqli_error($conn));
                }
            }

            mysqli_commit($conn);

            header("Location: trip-view.php?id=$new_trip_id&msg=added");
            exit;
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= $pageTitle ?></title>
    <?php include 'layout/header.php'; ?>
    <style>
        #itemsTable th {
            font-size: .75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #6e707e;
            background-color: #f8f9fc;
            white-space: nowrap;
            border-bottom: 2px solid #e3e6f0;
        }

        #itemsTable td {
            vertical-align: middle;
            padding: .5rem .4rem;
        }

        #itemsTable .form-control-sm {
            height: auto;
            padding: .3rem .5rem;
            font-size: .85rem;
        }

        .position-relative {
            position: relative;
        }

        .item-suggest {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            z-index: 1080;
            max-height: 220px;
            overflow-y: auto;
            margin-top: 2px;
            font-size: .85rem;
        }

        .item-suggest .list-group-item {
            cursor: pointer;
            padding: .35rem .6rem;
            border-left: 0;
            border-right: 0;
        }

        .item-suggest .list-group-item:first-child {
            border-top: 0;
        }

        .item-suggest .list-group-item:hover {
            background-color: #eef2ff;
        }

        .input-group-tight {
            display: flex;
            flex-wrap: nowrap;
            align-items: stretch;
        }

        .input-group-tight .form-control {
            flex: 1 1 auto;
            min-width: 0;
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }

        .input-group-tight .input-group-append {
            flex: 0 0 auto;
            display: flex;
        }

        .input-group-tight .input-group-append .btn {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
            padding-left: .65rem;
            padding-right: .65rem;
        }

        @media (max-width: 575.98px) {
            .input-group-tight {
                flex-wrap: wrap;
            }

            .input-group-tight .form-control {
                flex: 1 1 100%;
                border-top-right-radius: 0.35rem;
                border-bottom-right-radius: 0;
                border-bottom-left-radius: 0;
            }

            .input-group-tight .input-group-append {
                flex: 1 1 100%;
                width: 100%;
            }

            .input-group-tight .input-group-append .btn {
                width: 100%;
                border-top-left-radius: 0;
                border-top-right-radius: 0;
                border-bottom-left-radius: 0.35rem;
                border-bottom-right-radius: 0.35rem;
                padding: .35rem;
                font-size: .8rem;
            }
        }

        .items-grand-total {
            background-color: #f8f9fc;
            border-top: 2px solid #e3e6f0;
            padding: .75rem 1rem;
            font-size: 1rem;
            font-weight: 700;
            color: #2c2e3e;
        }

        #masterModal .modal-body {
            padding: 0;
        }

        #masterModalIframe {
            display: block;
            width: 100%;
            height: 78vh;
            border: 0;
            background: #fff;
        }

        @media (max-width: 575.98px) {
            #masterModalIframe {
                height: 85vh;
            }
        }
    </style>
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
                            <i class="fas fa-route mr-2"></i> Add Trip
                        </h1>
                        <a href="trips-list.php"
                            class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
                            <i class="fas fa-arrow-left fa-sm text-white-50 mr-1"></i>
                            Back to Trips
                        </a>
                    </div>

                    <div id="tripFlash"></div>

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle mr-1"></i>
                            <strong>Please fix the following:</strong>
                            <div class="mt-2"><?= $error ?></div>
                            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                        </div>
                    <?php endif; ?>

                    <form id="tripForm" action="" method="POST" novalidate>

                        <!-- ==================== TRIP DETAILS ==================== -->
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-truck-moving mr-1"></i> Trip Details
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">

                                    <!-- Lorry -->
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="lorry_input">Lorry <span class="text-danger">*</span></label>
                                            <div class="input-group-tight">
                                                <input type="text"
                                                    id="lorry_input"
                                                    class="form-control js-datalist-input"
                                                    list="lorryList"
                                                    data-target="lorry_id"
                                                    placeholder="Type or select lorry..."
                                                    autocomplete="off"
                                                    required>
                                                <input type="hidden" name="lorry_id" id="lorry_id" value="">
                                                <div class="input-group-append">
                                                    <a href="#" class="btn btn-outline-primary js-open-master"
                                                        data-entity="lorry" data-title="Add New Lorry"
                                                        title="Add new lorry">
                                                        <i class="fas fa-plus"></i>
                                                    </a>
                                                </div>
                                            </div>
                                            <datalist id="lorryList">
                                                <?php foreach ($lorries as $l):
                                                    $label = $l['lorry_number'];
                                                    if (!empty($l['lorry_type'])) {
                                                        $label .= ' — ' . $l['lorry_type'];
                                                    }
                                                ?>
                                                    <option data-id="<?= (int) $l['id'] ?>"
                                                        value="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>">
                                                    </option>
                                                <?php endforeach; ?>
                                            </datalist>
                                        </div>
                                    </div>

                                    <!-- Driver -->
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="driver_input">Driver <span class="text-danger">*</span></label>
                                            <div class="input-group-tight">
                                                <input type="text"
                                                    id="driver_input"
                                                    class="form-control js-datalist-input"
                                                    list="driverList"
                                                    data-target="driver_id"
                                                    placeholder="Type or select driver..."
                                                    autocomplete="off"
                                                    required>
                                                <input type="hidden" name="driver_id" id="driver_id" value="">
                                                <div class="input-group-append">
                                                    <a href="#" class="btn btn-outline-primary js-open-master"
                                                        data-entity="driver" data-title="Add New Driver"
                                                        title="Add new driver">
                                                        <i class="fas fa-plus"></i>
                                                    </a>
                                                </div>
                                            </div>
                                            <datalist id="driverList">
                                                <?php foreach ($drivers as $d):
                                                    $label = $d['driver_name'];
                                                    if (!empty($d['phone'])) {
                                                        $label .= ' — ' . $d['phone'];
                                                    }
                                                ?>
                                                    <option data-id="<?= (int) $d['id'] ?>"
                                                        value="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>">
                                                    </option>
                                                <?php endforeach; ?>
                                            </datalist>
                                        </div>
                                    </div>

                                    <!-- From -->
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="from_branch_input">
                                                From <span class="text-danger">*</span>
                                            </label>
                                            <input type="text"
                                                id="from_branch_input"
                                                class="form-control js-datalist-input"
                                                list="branchListFrom"
                                                data-target="from_branch_id"
                                                placeholder="Type or select branch..."
                                                autocomplete="off"
                                                required>
                                            <input type="hidden" name="from_branch_id" id="from_branch_id" value="">
                                            <datalist id="branchListFrom">
                                                <?php foreach ($branches as $b): ?>
                                                    <option data-id="<?= (int) $b['id'] ?>"
                                                        value="<?= htmlspecialchars($b['branch_name'], ENT_QUOTES, 'UTF-8') ?>">
                                                    </option>
                                                <?php endforeach; ?>
                                            </datalist>
                                        </div>
                                    </div>

                                    <!-- To -->
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="to_branch_input">
                                                To <span class="text-danger">*</span>
                                            </label>
                                            <input type="text"
                                                id="to_branch_input"
                                                class="form-control js-datalist-input"
                                                list="branchListTo"
                                                data-target="to_branch_id"
                                                placeholder="Type or select branch..."
                                                autocomplete="off"
                                                required>
                                            <input type="hidden" name="to_branch_id" id="to_branch_id" value="">
                                            <datalist id="branchListTo">
                                                <?php foreach ($branches as $b): ?>
                                                    <option data-id="<?= (int) $b['id'] ?>"
                                                        value="<?= htmlspecialchars($b['branch_name'], ENT_QUOTES, 'UTF-8') ?>">
                                                    </option>
                                                <?php endforeach; ?>
                                            </datalist>
                                        </div>
                                    </div>

                                    <!-- Start Date -->
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="start_date">
                                                Start Date <span class="text-danger">*</span>
                                            </label>
                                            <input type="date" id="start_date" name="start_date"
                                                class="form-control"
                                                value="<?= date('Y-m-d') ?>" required>
                                        </div>
                                    </div>

                                    <!-- Trip Notes -->
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="trip_notes">Trip Notes</label>
                                            <input type="text" id="trip_notes" name="trip_notes"
                                                class="form-control" maxlength="255"
                                                placeholder="Optional short trip note">
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>

                        <!-- ==================== PARTY CONSIGNMENT ==================== -->
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-user mr-1"></i> Party Consignment
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">

                                    <!-- Party -->
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="party_input">
                                                Party <span class="text-danger">*</span>
                                            </label>
                                            <div class="input-group-tight">
                                                <input type="text"
                                                    id="party_input"
                                                    class="form-control js-datalist-input"
                                                    list="partyList"
                                                    data-target="party_id"
                                                    placeholder="Type or select party..."
                                                    autocomplete="off"
                                                    required>
                                                <input type="hidden" name="party_id" id="party_id" value="">
                                                <div class="input-group-append">
                                                    <a href="#" class="btn btn-outline-primary js-open-master"
                                                        data-entity="party" data-title="Add New Party"
                                                        title="Add new party">
                                                        <i class="fas fa-plus"></i>
                                                    </a>
                                                </div>
                                            </div>
                                            <datalist id="partyList">
                                                <?php foreach ($parties as $p):
                                                    $label = $p['legal_name'];
                                                    if (!empty($p['trade_name'])) {
                                                        $label .= ' (' . $p['trade_name'] . ')';
                                                    }
                                                ?>
                                                    <option data-id="<?= (int) $p['id'] ?>"
                                                        value="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>">
                                                    </option>
                                                <?php endforeach; ?>
                                            </datalist>
                                        </div>
                                    </div>

                                    <!-- Freight -->
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="freight_amount">
                                                Freight Amount <span class="text-danger">*</span>
                                            </label>
                                            <input type="number" step="0.01" min="0"
                                                id="freight_amount" name="freight_amount"
                                                class="form-control"
                                                placeholder="0.00" required>
                                        </div>
                                    </div>

                                    <!-- Advance -->
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="advance_paid">Advance Paid</label>
                                            <input type="number" step="0.01" min="0"
                                                id="advance_paid" name="advance_paid"
                                                class="form-control" placeholder="0.00">
                                        </div>
                                    </div>

                                    <!-- Party notes -->
                                    <div class="col-md-12">
                                        <div class="form-group mb-0">
                                            <label for="party_notes">Party Notes</label>
                                            <input type="text" id="party_notes" name="party_notes"
                                                class="form-control" maxlength="255"
                                                placeholder="Optional short note for this party's consignment">
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>

                        <!-- ==================== ITEMS ==================== -->
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex align-items-center justify-content-between flex-wrap">
                                <h6 class="m-0 font-weight-bold text-primary mb-2 mb-sm-0">
                                    <i class="fas fa-boxes mr-1"></i> Items on this Consignment
                                </h6>
                                <div>
                                    <button type="button" id="addItemBtn" class="btn btn-sm btn-primary">
                                        <i class="fas fa-plus mr-1"></i> Add Item Row
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0" id="itemsTable">
                                        <thead>
                                            <tr>
                                                <th style="min-width:200px;">Item Name</th>
                                                <th style="min-width:90px;">Unit</th>
                                                <th style="min-width:90px;" class="text-right">Qty</th>
                                                <th style="min-width:110px;" class="text-right">Rate</th>
                                                <th style="min-width:120px;" class="text-right">Amount</th>
                                                <th style="min-width:50px;" class="text-center">×</th>
                                            </tr>
                                        </thead>
                                        <tbody id="itemsWrap"></tbody>
                                    </table>
                                </div>
                                <div class="items-grand-total d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-calculator mr-1"></i> Grand Total</span>
                                    <span>₹ <span id="itemsGrandTotal">0</span></span>
                                </div>
                            </div>
                        </div>

                        <!-- ==================== ACTION BAR ==================== -->
                        <div class="card shadow mb-4">
                            <div class="card-body py-3 d-flex align-items-center justify-content-between flex-wrap">
                                <div class="text-muted small mb-2 mb-sm-0">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Fields marked <span class="text-danger">*</span> are required.
                                </div>
                                <div>
                                    <a href="trip.php" class="btn btn-secondary">
                                        <i class="fas fa-redo mr-1"></i> Clear
                                    </a>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-check mr-1"></i> Save Trip
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

    <!-- Master modal (iframe) -->
    <div class="modal fade" id="masterModal" tabindex="-1" role="dialog"
        aria-labelledby="masterModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="masterModalTitle">Add New</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <iframe id="masterModalIframe" src="about:blank" title="Master form"></iframe>
                </div>
            </div>
        </div>
    </div>

    <script src="js/trip.js"></script>

</body>

</html>