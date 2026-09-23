<?php

include 'constant.php';
include 'session.php';

requirePermission('trip.view');

/* =========================================================
   LOAD THE TRIP
   ========================================================= */
$tripId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($tripId <= 0) {
    header("Location: trips-list.php");
    exit;
}

$canSeeAll = hasPermission('trip.view.all');
$myBranch  = (int) ($_SESSION['branch_id'] ?? 0);

$branchCheck = '';
if (!$canSeeAll) {
    if ($myBranch > 0) {
        $branchCheck = " AND (t.branch_id = $myBranch
                           OR t.from_branch_id = $myBranch
                           OR t.to_branch_id = $myBranch)";
    } else {
        $branchCheck = " AND 1=0";
    }
}

$sqlTrip = "SELECT
                t.*,
                l.lorry_number, l.lorry_type,
                d.driver_name, d.phone AS driver_phone,
                bf.branch_name AS from_branch_name,
                bf.branch_code AS from_branch_code,
                bt.branch_name AS to_branch_name,
                bt.branch_code AS to_branch_code,
                bo.branch_name AS owner_branch_name,
                u.full_name    AS created_by_name
            FROM trip t
            LEFT JOIN lorry  l  ON l.id  = t.lorry_id
            LEFT JOIN driver d  ON d.id  = t.driver_id
            LEFT JOIN branch bf ON bf.id = t.from_branch_id
            LEFT JOIN branch bt ON bt.id = t.to_branch_id
            LEFT JOIN branch bo ON bo.id = t.branch_id
            LEFT JOIN `user` u  ON u.id  = t.created_by
            WHERE t.id = $tripId $branchCheck
            LIMIT 1";

$resTrip = mysqli_query($conn, $sqlTrip);
if (!$resTrip || mysqli_num_rows($resTrip) !== 1) {
    header("Location: trips-list.php");
    exit;
}

$trip = mysqli_fetch_assoc($resTrip);

/* =========================================================
   LOAD PARTIES + ITEMS
   ========================================================= */
$parties = [];
$resParties = mysqli_query(
    $conn,
    "SELECT tp.id, tp.party_id, tp.freight_amount, tp.advance_paid,
            tp.notes, tp.active,
            p.legal_name, p.trade_name, p.gstin, p.phone
     FROM trip_party tp
     LEFT JOIN party p ON p.id = tp.party_id
     WHERE tp.trip_id = $tripId
     ORDER BY tp.id ASC"
);

if ($resParties) {
    while ($row = mysqli_fetch_assoc($resParties)) {
        $tpId = (int) $row['id'];
        $row['items'] = [];

        $resItems = mysqli_query(
            $conn,
            "SELECT id, item_id, item_name, unit, quantity, rate, amount
             FROM trip_item
             WHERE trip_party_id = $tpId
             ORDER BY id ASC"
        );
        if ($resItems) {
            while ($it = mysqli_fetch_assoc($resItems)) {
                $row['items'][] = $it;
            }
        }

        $row['items_total'] = 0;
        foreach ($row['items'] as $it) {
            $row['items_total'] += (float) $it['amount'];
        }
        $parties[] = $row;
    }
}

/* =========================================================
   LOAD EXPENSES
   ========================================================= */
$expenses = [];
$resExp = mysqli_query(
    $conn,
    "SELECT e.id, e.expense_date, e.category, e.amount, e.notes, e.active,
            v.vendor_name
     FROM expense e
     LEFT JOIN vendor v ON v.id = e.vendor_id
     WHERE e.trip_id = $tripId
     ORDER BY e.expense_date ASC, e.id ASC"
);
if ($resExp) {
    while ($x = mysqli_fetch_assoc($resExp)) {
        $expenses[] = $x;
    }
}

/* =========================================================
   DATA FOR THE EXPENSE MODAL
   (Vendor dropdown is kept in the markup but hidden.)
   ========================================================= */
$expenseCategories = [
    'Diesel',
    'Toll',
    'Driver Food',
    'Mechanic',
    'Spare Parts',
    'Loading / Unloading',
    'Parking',
    'Police / RTO',
    'Other',
];

$vendors = [];
$sqlV = "SELECT id, vendor_name, vendor_type FROM vendor WHERE active = 1";
if (!$canSeeAll && $myBranch > 0) {
    $sqlV .= " AND branch_id = $myBranch";
}
$sqlV .= " ORDER BY vendor_name";
$resV = mysqli_query($conn, $sqlV);
if ($resV) {
    while ($v = mysqli_fetch_assoc($resV)) {
        $vendors[] = $v;
    }
}

/* =========================================================
   SUMMARY
   ========================================================= */
$totalFreight = 0;
$totalAdvance = 0;
$totalItems   = 0;

foreach ($parties as $p) {
    $totalFreight += (float) $p['freight_amount'];
    $totalAdvance += (float) $p['advance_paid'];
    $totalItems   += count($p['items']);
}
$totalDue = $totalFreight - $totalAdvance;

$totalExpense = 0;
foreach ($expenses as $e) {
    $totalExpense += (float) $e['amount'];
}

$netProfit = $totalFreight - $totalExpense;

$flash = '';
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'added':
            $flash = 'Trip added successfully.';
            break;
        case 'updated':
            $flash = 'Trip updated successfully.';
            break;
    }
}

function statusBadgeClass($status)
{
    switch ($status) {
        case 'Scheduled':
            return 'badge-secondary';
        case 'In Progress':
            return 'badge-info';
        case 'Delivered':
            return 'badge-primary';
        case 'Billed':
            return 'badge-warning';
        case 'Paid':
            return 'badge-success';
        default:
            return 'badge-secondary';
    }
}

$pageTitle = 'Trip ' . $trip['trip_no'] . ' | Billing Portal';
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
        .trip-header-card {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: #fff;
            border-radius: 0.5rem;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem;
        }

        .trip-header-no {
            font-family: "SFMono-Regular", Menlo, Consolas, monospace;
            font-size: 1.8rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            margin: 0;
        }

        .trip-header-meta {
            font-size: 0.85rem;
            opacity: 0.85;
            margin-top: 0.25rem;
        }

        .trip-header-card .status-badge {
            display: inline-block;
            padding: 0.35rem 0.85rem;
            border-radius: 2rem;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .summary-card {
            border-left: 0.25rem solid #4e73df;
            background-color: #fff;
            padding: 1rem 1.25rem;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 0.6rem 0 rgba(58, 59, 69, .1);
            height: 100%;
        }

        .summary-card .label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #858796;
            font-weight: 700;
            margin-bottom: 0.35rem;
        }

        .summary-card .value {
            font-size: 1.35rem;
            font-weight: 700;
            color: #2c2e3e;
            font-family: "SFMono-Regular", Menlo, Consolas, monospace;
        }

        .summary-card.success {
            border-left-color: #1cc88a;
        }

        .summary-card.warning {
            border-left-color: #f6c23e;
        }

        .summary-card.danger {
            border-left-color: #e74a3b;
        }

        .summary-card.info {
            border-left-color: #36b9cc;
        }

        .summary-card.danger .value {
            color: #e74a3b;
        }

        .summary-card.success .value {
            color: #0c7d5b;
        }

        .party-block {
            border: 1px solid #e3e6f0;
            border-radius: 0.35rem;
            margin-bottom: 1rem;
            overflow: hidden;
        }

        .party-block .party-header {
            background-color: #f8f9fc;
            padding: 0.85rem 1.1rem;
            border-bottom: 1px solid #e3e6f0;
        }

        .party-block .party-body {
            padding: 0.75rem 1.1rem;
        }

        .party-name {
            font-size: 1.05rem;
            font-weight: 700;
            color: #2c2e3e;
        }

        .party-sub {
            font-size: 0.8rem;
            color: #6e707e;
        }

        .party-figures {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            font-size: 0.85rem;
            color: #3a3b45;
        }

        .party-figures .fig-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            color: #858796;
            font-weight: 700;
            display: block;
        }

        .party-figures .fig-value {
            font-weight: 700;
            font-family: "SFMono-Regular", Menlo, Consolas, monospace;
        }

        .items-mini-table {
            width: 100%;
            font-size: 0.88rem;
            margin: 0;
        }

        .items-mini-table th {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #6e707e;
            background-color: #fff;
            border-bottom: 1px solid #e3e6f0;
            padding: 0.45rem 0.5rem;
            font-weight: 700;
        }

        .items-mini-table td {
            padding: 0.45rem 0.5rem;
            border-bottom: 1px dashed #eaecf4;
            vertical-align: middle;
        }

        .items-mini-table tr:last-child td {
            border-bottom: 0;
        }

        .items-mini-table .num {
            text-align: right;
            font-family: "SFMono-Regular", Menlo, Consolas, monospace;
        }

        .items-mini-table .item-total-row td {
            border-top: 2px solid #e3e6f0;
            font-weight: 700;
            padding-top: 0.6rem;
        }

        #expensesTable thead th {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #6e707e;
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            padding: 0.55rem 0.75rem;
        }

        #expensesTable tbody td {
            padding: 0.6rem 0.75rem;
            vertical-align: middle;
            font-size: 0.9rem;
        }

        .expense-amount {
            font-family: "SFMono-Regular", Menlo, Consolas, monospace;
            font-weight: 700;
            text-align: right;
        }

        .trip-header-actions .btn {
            font-size: 0.8rem;
            font-weight: 600;
            padding: 0.35rem 0.85rem;
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

                    <div class="mb-3">
                        <a href="trips-list.php" class="text-decoration-none small text-muted">
                            <i class="fas fa-arrow-left mr-1"></i> Back to Trips
                        </a>
                    </div>

                    <?php if ($flash !== ''): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle mr-1"></i>
                            <?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?>
                            <button type="button" class="close" data-dismiss="alert">
                                <span>&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <div id="tripViewFlash"></div>

                    <!-- =========================================================
                         TRIP HEADER
                         ========================================================= -->
                    <div class="trip-header-card">
                        <div class="d-flex justify-content-between align-items-start flex-wrap">
                            <div class="mb-2 mb-md-0">
                                <h1 class="trip-header-no">
                                    <?= htmlspecialchars($trip['trip_no'], ENT_QUOTES, 'UTF-8') ?>
                                </h1>
                                <div class="trip-header-meta">
                                    <i class="fas fa-building mr-1"></i>
                                    <?= htmlspecialchars($trip['owner_branch_name'] ?: '—', ENT_QUOTES, 'UTF-8') ?>
                                    &nbsp;&nbsp;·&nbsp;&nbsp;
                                    <i class="fas fa-calendar mr-1"></i>
                                    <?= date('d M Y', strtotime($trip['start_date'])) ?>
                                    <?php if (!empty($trip['end_date'])): ?>
                                        &nbsp;–&nbsp;
                                        <?= date('d M Y', strtotime($trip['end_date'])) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="status-badge badge badge-<?= str_replace('badge-', '', statusBadgeClass($trip['status'])) === 'secondary' ? 'secondary' : str_replace('badge-', '', statusBadgeClass($trip['status'])) ?> mb-2">
                                    <?= htmlspecialchars($trip['status'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <div class="trip-header-actions mt-2">
                                    <a href="consignment-print.php?id=<?= (int) $trip['id'] ?>"
                                        target="_blank" class="btn btn-light btn-sm"
                                        title="Print Consignment Note">
                                        <i class="fas fa-file-alt mr-1"></i> Consignment
                                    </a>
                                    <a href="chalan-print.php?id=<?= (int) $trip['id'] ?>"
                                        target="_blank" class="btn btn-light btn-sm"
                                        title="Print Lorry Chalan">
                                        <i class="fas fa-truck-moving mr-1"></i> Chalan
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- =========================================================
                         SUMMARY BAND
                         ========================================================= -->
                    <div class="row mb-4">
                        <div class="col-md-6 col-lg mb-3">
                            <div class="summary-card">
                                <div class="label">Freight</div>
                                <div class="value" id="summaryFreight">₹ <?= number_format($totalFreight, 2) ?></div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg mb-3">
                            <div class="summary-card info">
                                <div class="label">Advance Received</div>
                                <div class="value">₹ <?= number_format($totalAdvance, 2) ?></div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg mb-3">
                            <div class="summary-card <?= $totalDue > 0.01 ? 'danger' : 'success' ?>">
                                <div class="label">Amount Due</div>
                                <div class="value">₹ <?= number_format($totalDue, 2) ?></div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg mb-3">
                            <div class="summary-card warning">
                                <div class="label">Total Expense</div>
                                <div class="value" id="summaryExpense">₹ <?= number_format($totalExpense, 2) ?></div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg mb-3" id="summaryNetCard">
                            <div class="summary-card <?= $netProfit >= 0 ? 'success' : 'danger' ?>">
                                <div class="label">Net (Freight − Expense)</div>
                                <div class="value" id="summaryNet">₹ <?= number_format($netProfit, 2) ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- =========================================================
                         TRIP DETAILS + PARTIES
                         ========================================================= -->
                    <div class="row">
                        <div class="col-lg-4 mb-4">
                            <div class="card shadow">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="fas fa-info-circle mr-1"></i> Trip Details
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm table-borderless mb-0">
                                        <tr>
                                            <td class="text-muted small" style="width:45%;">Lorry</td>
                                            <td class="font-weight-bold">
                                                <?= htmlspecialchars($trip['lorry_number'] ?: '—', ENT_QUOTES, 'UTF-8') ?>
                                                <?php if (!empty($trip['lorry_type'])): ?>
                                                    <div class="text-muted small"><?= htmlspecialchars($trip['lorry_type'], ENT_QUOTES, 'UTF-8') ?></div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted small">Driver</td>
                                            <td class="font-weight-bold">
                                                <?= htmlspecialchars($trip['driver_name'] ?: '—', ENT_QUOTES, 'UTF-8') ?>
                                                <?php if (!empty($trip['driver_phone'])): ?>
                                                    <div class="text-muted small">
                                                        <i class="fas fa-phone fa-xs"></i>
                                                        <?= htmlspecialchars($trip['driver_phone'], ENT_QUOTES, 'UTF-8') ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted small">Route</td>
                                            <td>
                                                <span class="font-weight-bold">
                                                    <?= htmlspecialchars($trip['from_branch_name'] ?: '—', ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                                <i class="fas fa-arrow-right text-muted mx-1"></i>
                                                <span class="font-weight-bold">
                                                    <?= htmlspecialchars($trip['to_branch_name'] ?: '—', ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </td>
                                        </tr>

                                        <tr>
                                            <td class="text-muted small">Start Date</td>
                                            <td class="font-weight-bold">
                                                <?= date('d M Y', strtotime($trip['start_date'])) ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted small">End Date</td>
                                            <td class="font-weight-bold">
                                                <?php if (!empty($trip['end_date'])): ?>
                                                    <?= date('d M Y', strtotime($trip['end_date'])) ?>
                                                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted small">Created By</td>
                                            <td class="font-weight-bold">
                                                <?= htmlspecialchars($trip['created_by_name'] ?: '—', ENT_QUOTES, 'UTF-8') ?>
                                                <div class="text-muted small">
                                                    <?= date('d M Y, h:i A', strtotime($trip['created_at'])) ?>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                    <?php if (!empty($trip['notes'])): ?>
                                        <hr class="my-3">
                                        <div class="text-muted small text-uppercase font-weight-bold mb-1">Notes</div>
                                        <div style="white-space: pre-wrap;"><?= htmlspecialchars($trip['notes'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-8 mb-4">
                            <div class="card shadow">
                                <div class="card-header py-3 d-flex align-items-center justify-content-between flex-wrap">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="fas fa-users mr-1"></i>
                                        Parties on this Trip
                                        <span class="badge badge-primary ml-1"><?= count($parties) ?></span>
                                    </h6>
                                    <?php if (hasPermission('trip.edit')): ?>
                                        <a href="#" class="btn btn-sm btn-primary disabled d-none"
                                            title="Coming soon">
                                            <i class="fas fa-plus mr-1"></i> Add Party
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($parties)): ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-users fa-3x text-gray-300 mb-3"></i>
                                            <h6 class="text-gray-700 mb-2">No parties yet</h6>
                                            <p class="text-muted small mb-0">Add a party to start recording consignment details.</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($parties as $p):
                                            $pFreight = (float) $p['freight_amount'];
                                            $pAdvance = (float) $p['advance_paid'];
                                            $pDue     = $pFreight - $pAdvance;
                                            $isActive = ((int) $p['active'] === 1);
                                        ?>
                                            <div class="party-block" style="<?= $isActive ? '' : 'opacity:0.55;' ?>">
                                                <div class="party-header">
                                                    <div class="d-flex justify-content-between align-items-start flex-wrap">
                                                        <div class="mb-2 mb-sm-0">
                                                            <div class="party-name">
                                                                <?= htmlspecialchars($p['legal_name'] ?: '— Unknown Party —', ENT_QUOTES, 'UTF-8') ?>
                                                            </div>
                                                            <?php if (!empty($p['trade_name'])): ?>
                                                                <div class="party-sub"><?= htmlspecialchars($p['trade_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                                            <?php endif; ?>
                                                            <?php if (!empty($p['gstin'])): ?>
                                                                <div class="party-sub">
                                                                    <i class="fas fa-file-invoice mr-1"></i>
                                                                    GSTIN: <?= htmlspecialchars($p['gstin'], ENT_QUOTES, 'UTF-8') ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if (!empty($p['phone'])): ?>
                                                                <div class="party-sub">
                                                                    <i class="fas fa-phone mr-1"></i>
                                                                    <?= htmlspecialchars($p['phone'], ENT_QUOTES, 'UTF-8') ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="party-figures">
                                                            <div>
                                                                <span class="fig-label">Freight</span>
                                                                <span class="fig-value">₹ <?= number_format($pFreight, 2) ?></span>
                                                            </div>
                                                            <div>
                                                                <span class="fig-label">Advance</span>
                                                                <span class="fig-value">₹ <?= number_format($pAdvance, 2) ?></span>
                                                            </div>
                                                            <div>
                                                                <span class="fig-label">Due</span>
                                                                <span class="fig-value" style="<?= $pDue > 0.01 ? 'color:#e74a3b;' : 'color:#0c7d5b;' ?>">
                                                                    ₹ <?= number_format($pDue, 2) ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php if (!empty($p['notes'])): ?>
                                                        <div class="party-sub mt-2">
                                                            <i class="fas fa-sticky-note mr-1"></i>
                                                            <?= htmlspecialchars($p['notes'], ENT_QUOTES, 'UTF-8') ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="party-body">
                                                    <?php if (empty($p['items'])): ?>
                                                        <p class="text-muted small mb-0">No items recorded for this party.</p>
                                                    <?php else: ?>
                                                        <div class="table-responsive">
                                                            <table class="items-mini-table">
                                                                <thead>
                                                                    <tr>
                                                                        <th>Item</th>
                                                                        <th>Unit</th>
                                                                        <th class="text-right">Qty</th>
                                                                        <th class="text-right">Rate</th>
                                                                        <th class="text-right">Amount</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php foreach ($p['items'] as $it):
                                                                        $qty = (float) $it['quantity'];
                                                                        $qtyFormatted = rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.');
                                                                    ?>
                                                                        <tr>
                                                                            <td><?= htmlspecialchars($it['item_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                                                            <td><?= htmlspecialchars($it['unit'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                                                                            <td class="num"><?= $qtyFormatted ?></td>
                                                                            <td class="num">₹ <?= number_format((float) $it['rate'], 2) ?></td>
                                                                            <td class="num">₹ <?= number_format((float) $it['amount'], 2) ?></td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                    <tr class="item-total-row">
                                                                        <td colspan="4">Item Total</td>
                                                                        <td class="num">₹ <?= number_format($p['items_total'], 2) ?></td>
                                                                    </tr>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- =========================================================
                         EXPENSES
                         ========================================================= -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex align-items-center justify-content-between flex-wrap">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-receipt mr-1"></i>
                                Expenses
                                <span class="badge badge-primary ml-1" id="expenseCount"><?= count($expenses) ?></span>
                            </h6>
                            <?php if (hasPermission('trip.edit')): ?>
                                <button type="button"
                                    class="btn btn-sm btn-primary js-open-expense"
                                    data-trip-id="<?= (int) $trip['id'] ?>">
                                    <i class="fas fa-plus mr-1"></i> Add Expense
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-0">
                            <div id="expensesEmpty" class="text-center py-4" style="<?= empty($expenses) ? '' : 'display:none;' ?>">
                                <i class="fas fa-receipt fa-3x text-gray-300 mb-3"></i>
                                <h6 class="text-gray-700 mb-2">No expenses yet</h6>
                                <p class="text-muted small mb-0">Add diesel, toll, food, and other trip expenses here.</p>
                            </div>

                            <div id="expensesTableWrap" class="table-responsive" style="<?= empty($expenses) ? 'display:none;' : '' ?>">
                                <table class="table table-sm mb-0" id="expensesTable">
                                    <thead>
                                        <tr>
                                            <th width="60">#</th>
                                            <th>Date</th>
                                            <th>Category</th>
                                            <th>Vendor</th>
                                            <th>Notes</th>
                                            <th class="text-right" width="140">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody id="expensesBody">
                                        <?php foreach ($expenses as $i => $x): ?>
                                            <tr>
                                                <td class="text-muted small"><?= $i + 1 ?></td>
                                                <td><?= date('d M Y', strtotime($x['expense_date'])) ?></td>
                                                <td>
                                                    <span class="badge badge-light border">
                                                        <?= htmlspecialchars($x['category'], ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($x['vendor_name'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-muted small"><?= htmlspecialchars($x['notes'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="expense-amount">₹ <?= number_format((float) $x['amount'], 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr style="background-color:#f8f9fc;">
                                            <td colspan="5" class="text-right font-weight-bold">Total</td>
                                            <td class="expense-amount font-weight-bold" id="expensesTotalCell" style="font-size:1rem;">
                                                ₹ <?= number_format($totalExpense, 2) ?>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <?php include 'layout/footer.php'; ?>

        </div>
    </div>

    <!-- =========================================================
         EXPENSE MODAL
         ========================================================= -->
    <?php if (hasPermission('trip.edit')): ?>
        <div class="modal fade" id="expenseModal" tabindex="-1" role="dialog"
            aria-labelledby="expenseModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-scrollable" role="document">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title" id="expenseModalTitle">Add Expense</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        <form id="expenseForm" novalidate>
                            <input type="hidden" name="trip_id" value="<?= (int) $trip['id'] ?>">

                            <div id="expenseFormError" class="d-none"></div>

                            <div class="alert alert-light border small mb-3">
                                <i class="fas fa-info-circle mr-1"></i>
                                Adding expense to trip
                                <strong><?= htmlspecialchars($trip['trip_no'], ENT_QUOTES, 'UTF-8') ?></strong>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Expense Date <span class="text-danger">*</span></label>
                                    <input type="date" name="expense_date" class="form-control"
                                        value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Category <span class="text-danger">*</span></label>
                                    <select name="category" class="form-control" required>
                                        <option value="">— Select Category —</option>
                                        <?php foreach ($expenseCategories as $c): ?>
                                            <option value="<?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-12">
                                    <label>Amount <span class="text-danger">*</span></label>
                                    <input type="number" name="amount" class="form-control"
                                        step="0.01" min="0" placeholder="0.00" required>
                                </div>
                                <div class="form-group col-md-6 d-none">
                                    <label>Vendor (optional)</label>
                                    <select name="vendor_id" class="form-control">
                                        <option value="">— None —</option>
                                        <?php foreach ($vendors as $v): ?>
                                            <option value="<?= (int) $v['id'] ?>">
                                                <?= htmlspecialchars($v['vendor_name'], ENT_QUOTES, 'UTF-8') ?>
                                                <?= $v['vendor_type'] ? ' (' . htmlspecialchars($v['vendor_type'], ENT_QUOTES, 'UTF-8') . ')' : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group mb-0">
                                <label>Notes</label>
                                <input type="text" name="notes" class="form-control"
                                    maxlength="255" placeholder="Optional short note">
                            </div>
                        </form>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fas fa-times mr-1"></i> Cancel
                        </button>
                        <button type="submit" form="expenseForm" class="btn btn-success" id="expenseSubmitBtn">
                            <i class="fas fa-check mr-1"></i> Save Expense
                        </button>
                    </div>

                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- =========================================================
         INLINE JS — expense modal handling
         ========================================================= -->
    <?php if (hasPermission('trip.edit')): ?>
        <script>
            $(function() {

                var $modal = $('#expenseModal');
                var $form = $('#expenseForm');
                var $errBox = $('#expenseFormError');
                var $submitBtn = $('#expenseSubmitBtn');

                /* Open modal and reset state */
                $(document).on('click', '.js-open-expense', function() {
                    $form[0].reset();
                    $errBox.addClass('d-none').html('');
                    $modal.modal({
                        backdrop: 'static',
                        keyboard: false,
                        show: true
                    });
                    setTimeout(function() {
                        $form.find('input[name="expense_date"]').focus();
                    }, 300);
                });

                /* Submit via AJAX */
                $form.on('submit', function(e) {
                    e.preventDefault();

                    $errBox.addClass('d-none').html('');

                    $submitBtn.prop('disabled', true).html(
                        '<span class="spinner-border spinner-border-sm mr-1"></span> Saving...'
                    );

                    $.ajax({
                            url: 'ajax/save-expense.php',
                            type: 'POST',
                            dataType: 'json',
                            data: $form.serialize()
                        })
                        .done(function(res) {

                            if (!res || !res.success) {
                                $errBox
                                    .removeClass('d-none')
                                    .html(
                                        '<div class="alert alert-danger mb-3">' +
                                        '<i class="fas fa-exclamation-circle mr-1"></i>' +
                                        escapeHtml(res && res.message ? res.message : 'Save failed.') +
                                        '</div>'
                                    );
                                return;
                            }

                            /* ---- Append the new row to the expenses table ---- */
                            addExpenseRow(res);

                            /* ---- Update summary cards ---- */
                            updateSummaryAfterExpense(res.amount);

                            /* ---- Close modal + flash ---- */
                            $modal.modal('hide');
                            showFlash('success', 'Expense added successfully.');
                        })
                        .fail(function(xhr) {
                            var msg = 'Server error.';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                msg = xhr.responseJSON.message;
                            } else if (xhr.responseText) {
                                msg = xhr.responseText;
                            }
                            $errBox
                                .removeClass('d-none')
                                .html(
                                    '<div class="alert alert-danger mb-3">' +
                                    '<i class="fas fa-exclamation-circle mr-1"></i>' +
                                    escapeHtml(msg) +
                                    '</div>'
                                );
                        })
                        .always(function() {
                            $submitBtn.prop('disabled', false).html(
                                '<i class="fas fa-check mr-1"></i> Save Expense'
                            );
                        });
                });

                /* Append a row to the expenses table, unhiding if empty */
                function addExpenseRow(res) {
                    /* Show the table, hide the empty state */
                    $('#expensesEmpty').hide();
                    $('#expensesTableWrap').show();

                    var rowCount = $('#expensesBody tr').length + 1;

                    var dateFormatted = formatDateShort(res.date);
                    var vendorName = res.vendor_name ? res.vendor_name : '—';
                    var notes = res.notes ? res.notes : '—';

                    var html =
                        '<tr>' +
                        '<td class="text-muted small">' + rowCount + '</td>' +
                        '<td>' + escapeHtml(dateFormatted) + '</td>' +
                        '<td><span class="badge badge-light border">' +
                        escapeHtml(res.category) +
                        '</span></td>' +
                        '<td>' + escapeHtml(vendorName) + '</td>' +
                        '<td class="text-muted small">' + escapeHtml(notes) + '</td>' +
                        '<td class="expense-amount">₹ ' + formatMoney(res.amount) + '</td>' +
                        '</tr>';

                    $('#expensesBody').append(html);

                    /* Update the row count badge */
                    var $count = $('#expenseCount');
                    $count.text(parseInt($count.text(), 10) + 1);

                    /* Update the total cell */
                    var currentTotal = parseMoney($('#expensesTotalCell').text());
                    var newTotal = currentTotal + parseFloat(res.amount || 0);
                    $('#expensesTotalCell').text('₹ ' + formatMoney(newTotal));
                }

                /* Update the two summary cards affected by an expense */
                function updateSummaryAfterExpense(amount) {
                    amount = parseFloat(amount) || 0;

                    var currentExpense = parseMoney($('#summaryExpense').text());
                    var currentNet = parseMoney($('#summaryNet').text());

                    var newExpense = currentExpense + amount;
                    var newNet = currentNet - amount;

                    $('#summaryExpense').text('₹ ' + formatMoney(newExpense));
                    $('#summaryNet').text('₹ ' + formatMoney(newNet));

                    /* Swap the Net card color class if it crossed zero */
                    var $netCard = $('#summaryNetCard .summary-card');
                    $netCard.removeClass('success danger');
                    $netCard.addClass(newNet >= 0 ? 'success' : 'danger');
                }

                /* ---- Small helpers ---- */
                function escapeHtml(s) {
                    return String(s == null ? '' : s)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#39;');
                }

                function formatMoney(n) {
                    n = parseFloat(n) || 0;
                    return n.toLocaleString('en-IN', {
                        maximumFractionDigits: 2,
                        minimumFractionDigits: 2
                    });
                }

                function parseMoney(s) {
                    var cleaned = String(s).replace(/[^0-9.\-]/g, '');
                    return parseFloat(cleaned) || 0;
                }

                function formatDateShort(iso) {
                    if (!iso) return '';
                    var parts = iso.split('-');
                    if (parts.length !== 3) return iso;
                    var d = new Date(parts[0], parseInt(parts[1], 10) - 1, parts[2]);
                    var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                        'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
                    ];
                    return String(parts[2]).padStart(2, '0') + ' ' +
                        months[d.getMonth()] + ' ' +
                        parts[0];
                }

                /* Flash at the top of the page */
                function showFlash(type, msg) {
                    var $wrap = $('#tripViewFlash');
                    var html =
                        '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
                        '<i class="fas fa-check-circle mr-1"></i>' +
                        escapeHtml(msg) +
                        '<button type="button" class="close" data-dismiss="alert">' +
                        '<span>&times;</span>' +
                        '</button>' +
                        '</div>';
                    $wrap.html(html);
                    setTimeout(function() {
                        $wrap.find('.alert').fadeOut(400, function() {
                            $(this).remove();
                        });
                    }, 5000);
                }

            });
        </script>
    <?php endif; ?>

</body>

</html>