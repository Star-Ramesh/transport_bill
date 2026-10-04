<?php

include 'session.php';
include 'constant.php';

requirePermission('trip.view');

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

/* Payment types → 'payment'; charge types → 'charge'.
   Keep in sync with js/trip-view.js and ajax/payment.php. */
function kindForType($type)
{
    switch ($type) {
        case 'Loading Charge':
        case 'Unloading Charge':
        case 'Detention':
        case 'Extra Charge':
            return 'charge';
        default:
            return 'payment';
    }
}

/* ---------- AJAX: change status ---------- */
if (isset($_GET['action']) && $_GET['action'] === 'change_status') {

    header('Content-Type: application/json');

    if (!hasPermission('trip.edit')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied.']);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'POST required.']);
        exit;
    }

    $tripId    = (int) ($_POST['id']     ?? 0);
    $newStatus = trim($_POST['status']   ?? '');

    if ($tripId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid trip ID.']);
        exit;
    }

    $allowedStatuses = ['Scheduled', 'In Progress', 'Delivered', 'Billed', 'Paid'];
    if (!in_array($newStatus, $allowedStatuses, true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status value.']);
        exit;
    }

    $canSeeAll = hasPermission('trip.view.all');
    $myBranch  = (int) ($_SESSION['branch_id'] ?? 0);

    $branchCheck = '';
    if (!$canSeeAll) {
        $branchCheck = $myBranch > 0 ? " AND branch_id = $myBranch" : " AND 1=0";
    }

    $check = mysqli_query($conn, "SELECT id, active FROM trip WHERE id = $tripId $branchCheck LIMIT 1");
    if (!$check || mysqli_num_rows($check) !== 1) {
        echo json_encode(['success' => false, 'message' => 'Trip not found.']);
        exit;
    }

    $tripRow = mysqli_fetch_assoc($check);
    if ((int) $tripRow['active'] !== 1) {
        echo json_encode(['success' => false, 'message' => 'Cannot change status of an inactive trip.']);
        exit;
    }

    $statusSafe = mysqli_real_escape_string($conn, $newStatus);
    if (!mysqli_query($conn, "UPDATE trip SET status = '$statusSafe' WHERE id = $tripId")) {
        echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
        exit;
    }

    echo json_encode(['success' => true, 'id' => $tripId, 'status' => $newStatus]);
    exit;
}

/* ---------- AJAX: delete one expense ---------- */
if (isset($_GET['action']) && $_GET['action'] === 'delete_expense') {

    header('Content-Type: application/json');

    if (!hasPermission('trip.edit')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied.']);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'POST required.']);
        exit;
    }

    $expenseId = (int) ($_POST['expense_id'] ?? 0);
    $tripId    = (int) ($_POST['trip_id']    ?? 0);

    if ($expenseId <= 0 || $tripId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid IDs.']);
        exit;
    }

    $canSeeAll = hasPermission('trip.view.all');
    $myBranch  = (int) ($_SESSION['branch_id'] ?? 0);

    $branchCheck = '';
    if (!$canSeeAll) {
        $branchCheck = $myBranch > 0 ? " AND branch_id = $myBranch" : " AND 1=0";
    }

    $check = mysqli_query(
        $conn,
        "SELECT id, amount FROM expense
         WHERE id = $expenseId AND trip_id = $tripId AND active = 1 $branchCheck
         LIMIT 1"
    );

    if (!$check || mysqli_num_rows($check) !== 1) {
        echo json_encode(['success' => false, 'message' => 'Expense not found.']);
        exit;
    }

    $expRow = mysqli_fetch_assoc($check);
    $deletedAmount = (float) $expRow['amount'];

    if (!mysqli_query($conn, "UPDATE expense SET active = 0 WHERE id = $expenseId")) {
        echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
        exit;
    }

    echo json_encode(['success' => true, 'expense_id' => $expenseId, 'deleted_amount' => $deletedAmount]);
    exit;
}

/* ---------- Load trip ---------- */
$tripId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($tripId <= 0) {
    header("Location: trips-list.php");
    exit;
}

$canSeeAll = hasPermission('trip.view.all');
$myBranch  = (int) ($_SESSION['branch_id'] ?? 0);

$branchCheck = '';
if (!$canSeeAll) {
    $branchCheck = $myBranch > 0 ? " AND branch_id = $myBranch" : " AND 1=0";
}

$resTrip = mysqli_query($conn, "SELECT * FROM trip WHERE id = $tripId $branchCheck LIMIT 1");
if (!$resTrip || mysqli_num_rows($resTrip) !== 1) {
    header("Location: trips-list.php");
    exit;
}
$trip = mysqli_fetch_assoc($resTrip);

/* Lorry */
$lorryNumber = $lorryType = $ownerName = '';
if ((int) $trip['lorry_id'] > 0) {
    $r = mysqli_query($conn, "SELECT lorry_number, lorry_type, owner_name FROM lorry WHERE id = " . (int) $trip['lorry_id'] . " LIMIT 1");
    if ($r && mysqli_num_rows($r) === 1) {
        $lr = mysqli_fetch_assoc($r);
        $lorryNumber = $lr['lorry_number'] ?? '';
        $lorryType   = $lr['lorry_type']   ?? '';
        $ownerName   = $lr['owner_name']   ?? '';
    }
}

/* Driver */
$driverName = $driverPhone = '';
if ((int) $trip['driver_id'] > 0) {
    $r = mysqli_query($conn, "SELECT driver_name, phone FROM driver WHERE id = " . (int) $trip['driver_id'] . " LIMIT 1");
    if ($r && mysqli_num_rows($r) === 1) {
        $dr = mysqli_fetch_assoc($r);
        $driverName  = $dr['driver_name'] ?? '';
        $driverPhone = $dr['phone']       ?? '';
    }
}

/* Branch */
$branchName = '';
if ((int) $trip['branch_id'] > 0) {
    $r = mysqli_query($conn, "SELECT branch_name FROM branch WHERE id = " . (int) $trip['branch_id'] . " LIMIT 1");
    if ($r && mysqli_num_rows($r) === 1) {
        $br = mysqli_fetch_assoc($r);
        $branchName = $br['branch_name'] ?? '';
    }
}

/* Created by */
$createdByName = '';
if ((int) $trip['created_by'] > 0) {
    $r = mysqli_query($conn, "SELECT full_name, username FROM `user` WHERE id = " . (int) $trip['created_by'] . " LIMIT 1");
    if ($r && mysqli_num_rows($r) === 1) {
        $ur = mysqli_fetch_assoc($r);
        $createdByName = $ur['full_name'] ?: $ur['username'];
    }
}

/* ---------- Parties + items + payments/charges ---------- */
$parties = [];
$totalFreight  = 0;
$totalReceived = 0;
$totalExtra    = 0;

$resParties = mysqli_query($conn, "SELECT * FROM trip_party WHERE trip_id = $tripId ORDER BY id ASC");

if ($resParties) {
    while ($row = mysqli_fetch_assoc($resParties)) {
        $tpId = (int) $row['id'];

        /* Items */
        $row['items'] = [];
        $resItems = mysqli_query($conn, "SELECT * FROM trip_inventory WHERE trip_party_id = $tpId ORDER BY id ASC");
        if ($resItems) {
            while ($it = mysqli_fetch_assoc($resItems)) {
                $row['items'][] = $it;
            }
        }
        $row['items_total'] = 0;
        foreach ($row['items'] as $it) {
            $row['items_total'] += (float) $it['amount'];
        }

        /* Payments + charges — same table, split by entry_kind */
        $row['payments'] = [];
        $row['charges']  = [];

        $resPay = mysqli_query($conn, "SELECT * FROM trip_payment WHERE trip_party_id = $tpId AND active = 1 ORDER BY payment_date ASC, id ASC");
        if ($resPay) {
            while ($p = mysqli_fetch_assoc($resPay)) {
                $kind = $p['entry_kind'] !== '' ? $p['entry_kind'] : kindForType($p['payment_type']);
                if ($kind === 'charge') {
                    $row['charges'][] = $p;
                } else {
                    $row['payments'][] = $p;
                }
            }
        }

        $row['payments_total'] = 0;
        foreach ($row['payments'] as $p) {
            $row['payments_total'] += (float) $p['amount'];
        }

        $row['extra_total'] = 0;
        foreach ($row['charges'] as $p) {
            $row['extra_total'] += (float) $p['amount'];
        }

        /* Consignor */
        $row['consignor_name']  = '';
        $row['consignor_trade'] = '';
        $row['consignor_gstin'] = '';
        $row['consignor_phone'] = '';
        if ((int) $row['party_id'] > 0) {
            $pid = (int) $row['party_id'];
            $pr = mysqli_query($conn, "SELECT legal_name, trade_name, gstin, phone FROM party WHERE id = $pid LIMIT 1");
            if ($pr && mysqli_num_rows($pr) === 1) {
                $pp = mysqli_fetch_assoc($pr);
                $row['consignor_name']  = $pp['legal_name'] ?? '';
                $row['consignor_trade'] = $pp['trade_name'] ?? '';
                $row['consignor_gstin'] = $pp['gstin']      ?? '';
                $row['consignor_phone'] = $pp['phone']      ?? '';
            }
        }

        /* Consignee */
        $row['consignee_name']  = '';
        $row['consignee_trade'] = '';
        $row['consignee_gstin'] = '';
        $row['consignee_phone'] = '';
        if ((int) $row['consignee_party_id'] > 0) {
            $cpId = (int) $row['consignee_party_id'];
            $cr = mysqli_query($conn, "SELECT legal_name, trade_name, gstin, phone FROM party WHERE id = $cpId LIMIT 1");
            if ($cr && mysqli_num_rows($cr) === 1) {
                $cc = mysqli_fetch_assoc($cr);
                $row['consignee_name']  = $cc['legal_name'] ?? '';
                $row['consignee_trade'] = $cc['trade_name'] ?? '';
                $row['consignee_gstin'] = $cc['gstin']      ?? '';
                $row['consignee_phone'] = $cc['phone']      ?? '';
            }
        }

        $totalFreight  += (float) $row['freight_amount'];
        $totalReceived += (float) $row['payments_total'];
        $totalExtra    += (float) $row['extra_total'];

        $parties[] = $row;
    }
}

$totalBalance = ($totalFreight + $totalExtra) - $totalReceived;

/* ---------- Expenses ---------- */
$expenses = [];
$resExp = mysqli_query($conn, "SELECT id, expense_date, category, amount, notes, active, supplier_id FROM expense WHERE trip_id = $tripId AND active = 1 ORDER BY expense_date ASC, id ASC");
if ($resExp) {
    while ($x = mysqli_fetch_assoc($resExp)) {
        $x['supplier_name'] = '';
        if ((int) $x['supplier_id'] > 0) {
            $sid = (int) $x['supplier_id'];
            $sr = mysqli_query($conn, "SELECT supplier_name FROM supplier WHERE id = $sid LIMIT 1");
            if ($sr && mysqli_num_rows($sr) === 1) {
                $srr = mysqli_fetch_assoc($sr);
                $x['supplier_name'] = $srr['supplier_name'] ?? '';
            }
        }
        $expenses[] = $x;
    }
}

$totalExpense = 0;
foreach ($expenses as $e) {
    $totalExpense += (float) $e['amount'];
}

$netProfit = $totalFreight - $totalExpense;

/* ---------- Reference lists ---------- */
$expenseCategories = [
    'Diesel', 'Toll', 'Driver Food', 'Mechanic', 'Spare Parts',
    'Loading / Unloading', 'Parking', 'Police / RTO',
    'Truck Hire Charge', 'Other',
];

$suppliers = [];
$resSup = mysqli_query($conn, "SELECT id, supplier_name, supplier_type FROM supplier WHERE active = 1 ORDER BY supplier_name");
if ($resSup) {
    while ($s = mysqli_fetch_assoc($resSup)) {
        $suppliers[] = $s;
    }
}

function statusPillClass($status)
{
    switch ($status) {
        case 'Scheduled':   return 'status-scheduled';
        case 'In Progress': return 'status-in-progress';
        case 'Delivered':   return 'status-delivered';
        case 'Billed':      return 'status-billed';
        case 'Paid':        return 'status-paid';
        default:            return 'status-scheduled';
    }
}

function statusDotClass($status)
{
    switch ($status) {
        case 'Scheduled':   return 'scheduled';
        case 'In Progress': return 'in-progress';
        case 'Delivered':   return 'delivered';
        case 'Billed':      return 'billed';
        case 'Paid':        return 'paid';
        default:            return 'scheduled';
    }
}

function statusIcon($status)
{
    switch ($status) {
        case 'Scheduled':   return 'fa-clock';
        case 'In Progress': return 'fa-truck-moving';
        case 'Delivered':   return 'fa-box-open';
        case 'Billed':      return 'fa-file-invoice-dollar';
        case 'Paid':        return 'fa-check-circle';
        default:            return 'fa-clock';
    }
}

$pageTitle = 'Trip ' . $trip['trip_no'] . ' | Billing Portal';

$currentStatus   = $trip['status'] ?: 'Scheduled';
$allowedStatuses = ['Scheduled', 'In Progress', 'Delivered', 'Billed', 'Paid'];
$tripIsActive    = ((int) $trip['active'] === 1);
$canEdit         = hasPermission('trip.edit');
$canChangeStatus = $canEdit && $tripIsActive;

$flash = '';
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'added':    $flash = 'Trip added successfully.';    break;
        case 'updated':  $flash = 'Trip updated successfully.';  break;
        case 'locked':   $flash = 'That trip is Billed or Paid and cannot be edited.'; break;
        case 'inactive': $flash = 'That trip is inactive.';      break;
    }
}
$flashIsWarning = (isset($_GET['msg']) && in_array($_GET['msg'], ['locked', 'inactive'], true));

$cameFromForm = (isset($_GET['src']) && $_GET['src'] === 'form');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <?php include 'layout/header.php'; ?>
    <style>
        .tv-hero {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: #fff;
            border-radius: .5rem;
            padding: 1.25rem 1.5rem;
            box-shadow: 0 .15rem 1.75rem 0 rgba(58, 59, 69, .15);
            position: relative;
            overflow: hidden;
        }

        .tv-hero::after {
            content: "\f5d1";
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            position: absolute;
            right: -.5rem;
            bottom: -1rem;
            font-size: 8rem;
            line-height: 1;
            color: rgba(255, 255, 255, .08);
            pointer-events: none;
        }

        .tv-hero h1 {
            font-family: "SFMono-Regular", Menlo, Consolas, monospace;
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: .02em;
            margin: 0 0 .25rem;
        }

        .tv-hero-meta { font-size: .85rem; opacity: .9; }

        .tv-hero-route {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            background: rgba(255, 255, 255, .18);
            border: 1px solid rgba(255, 255, 255, .3);
            border-radius: 999px;
            padding: .2rem .7rem;
            font-size: .8rem;
            font-weight: 700;
            margin-top: .5rem;
        }

        .tv-status-btn {
            border: none;
            border-radius: 999px;
            padding: .5rem 1rem;
            font-size: .78rem;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: #fff;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .15);
            display: inline-flex;
            align-items: center;
            gap: .45rem;
        }

        .tv-status-btn:hover,
        .tv-status-btn:focus {
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, .22);
            outline: none;
        }

        .tv-status-btn .tv-status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #fff;
            opacity: .9;
        }

        .tv-status-btn.status-scheduled    { background: #6b6d7d; }
        .tv-status-btn.status-in-progress  { background: #36b9cc; }
        .tv-status-btn.status-delivered    { background: #4e73df; }
        .tv-status-btn.status-billed       { background: #f6a90e; }
        .tv-status-btn.status-paid         { background: #1cc88a; }

        .tv-status-menu .dropdown-item {
            display: flex;
            align-items: center;
            gap: .6rem;
            padding: .5rem .75rem;
            border-radius: .25rem;
            font-size: .88rem;
            font-weight: 600;
        }

        .tv-status-menu .dropdown-item.active::after {
            content: "\f00c";
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            margin-left: auto;
            color: #4e73df;
            font-size: .8rem;
        }

        .tv-status-menu .tv-menu-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: .7rem;
        }

        .tv-status-menu .tv-menu-icon.scheduled   { background: #6b6d7d; }
        .tv-status-menu .tv-menu-icon.in-progress { background: #36b9cc; }
        .tv-status-menu .tv-menu-icon.delivered   { background: #4e73df; }
        .tv-status-menu .tv-menu-icon.billed      { background: #f6a90e; }
        .tv-status-menu .tv-menu-icon.paid        { background: #1cc88a; }

        .tv-static-status {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            border-radius: 999px;
            padding: .5rem 1rem;
            font-size: .78rem;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: #fff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .15);
        }

        .tv-static-status .tv-status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #fff;
            opacity: .9;
        }

        .tv-sum-card {
            border-left: 4px solid #e3e6f0;
            border-radius: .5rem;
            height: 100%;
        }

        .tv-sum-card .tv-sum-label {
            font-size: .7rem;
            font-weight: 800;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: #858796;
            margin-bottom: .25rem;
        }

        .tv-sum-card .tv-sum-value {
            font-family: "SFMono-Regular", Menlo, Consolas, monospace;
            font-size: 1.25rem;
            font-weight: 800;
            color: #2c2e3e;
        }

        .tv-sum-card.blue   { border-left-color: #4e73df; }
        .tv-sum-card.cyan   { border-left-color: #36b9cc; }
        .tv-sum-card.amber  { border-left-color: #f6c23e; }
        .tv-sum-card.green  { border-left-color: #1cc88a; }
        .tv-sum-card.red    { border-left-color: #e74a3b; }

        .tv-party {
            border: 1px solid #e3e6f0;
            border-radius: .5rem;
            background: #fff;
            margin-bottom: 1rem;
        }

        .tv-party:last-child { margin-bottom: 0; }

        .tv-party-head {
            background: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            padding: .9rem 1.1rem;
            border-radius: .5rem .5rem 0 0;
        }

        .tv-party-name {
            font-size: 1rem;
            font-weight: 800;
            color: #2c2e3e;
            display: flex;
            align-items: center;
            gap: .4rem;
            margin-bottom: .15rem;
        }

        .tv-party-name i { color: #4e73df; font-size: .85rem; }

        .tv-party-meta {
            font-size: .78rem;
            color: #6e707e;
            display: flex;
            flex-wrap: wrap;
            gap: .65rem;
        }

        .tv-fig-label {
            font-size: .65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #858796;
        }

        .tv-fig-value {
            font-family: "SFMono-Regular", Menlo, Consolas, monospace;
            font-size: .95rem;
            font-weight: 800;
            color: #2c2e3e;
        }

        .tv-fig-value.red   { color: #c0392b; }
        .tv-fig-value.green { color: #0c7d5b; }
        .tv-fig-value.amber { color: #b8860b; }

        .tv-table {
            width: 100%;
            margin: 0;
            font-size: .88rem;
        }

        .tv-table thead th {
            font-size: .68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #858796;
            padding: .5rem .65rem;
            border-bottom: 1px solid #e3e6f0;
            white-space: nowrap;
        }

        .tv-table tbody td {
            padding: .5rem .65rem;
            border-bottom: 1px solid #f0f2f7;
            vertical-align: middle;
        }

        .tv-table tr:last-child td { border-bottom: 0; }

        .tv-table td.num {
            text-align: right;
            font-family: "SFMono-Regular", Menlo, Consolas, monospace;
            font-weight: 600;
        }

        .tv-table tfoot td {
            font-weight: 800;
            background: #f8f9fc;
            border-top: 2px solid #e3e6f0;
            padding: .55rem .65rem;
        }

        .tv-section-title {
            font-size: .7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #6e707e;
            margin: 0 0 .5rem;
            display: flex;
            align-items: center;
            gap: .4rem;
        }

        .tv-section-title i { color: #4e73df; }

        .tv-pill {
            display: inline-block;
            padding: .18rem .6rem;
            border-radius: 4px;
            font-size: .72rem;
            font-weight: 700;
            background: #f5f7fb;
            color: #5a5c69;
            border: 1px solid #e6e9f0;
        }

        .tv-pill-blue   { background: #eef2ff; color: #3f51b5; border-color: #dbe2ff; }
        .tv-pill-green  { background: #e8fbf4; color: #0c7d5b; border-color: #c5f0e1; }
        .tv-pill-amber  { background: #fff8e1; color: #856404; border-color: #ffe082; }
        .tv-pill-red    { background: #fdecea; color: #842029; border-color: #f5c2c7; }

        .tv-icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 6px;
            border: 1px solid transparent;
            background: transparent;
            color: #b7b9cc;
            transition: all .15s ease;
            padding: 0;
            font-size: .8rem;
        }

        .tv-icon-btn-danger:hover {
            color: #e74a3b;
            background: #fdecea;
            border-color: #f5c2c7;
        }

        .tv-pay-amount {
            text-align: right;
            font-family: "SFMono-Regular", Menlo, Consolas, monospace;
            font-weight: 700;
            color: #0c7d5b;
        }

        .tv-pay-type {
            display: inline-block;
            padding: .15rem .5rem;
            border-radius: 4px;
            font-size: .72rem;
            font-weight: 700;
            background: #e8fbf4;
            color: #0c7d5b;
            border: 1px solid #c5f0e1;
        }

        .tv-pay-type.extra {
            background: #fff8e1;
            color: #856404;
            border-color: #ffe082;
        }

        .tv-empty {
            text-align: center;
            padding: 1.25rem 1rem;
            color: #858796;
            font-size: .85rem;
        }

        .tv-empty i {
            font-size: 1.5rem;
            color: #d1d3e2;
            display: block;
            margin-bottom: .4rem;
        }

        .tv-money-block {
            border: 1px solid #e3e6f0;
            border-radius: .5rem;
            padding: .75rem .9rem;
            background: #fdfdff;
            height: 100%;
        }

        .tv-money-block.charge-block { background: #fffdf5; border-color: #f3e2b3; }

        .tv-money-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: .5rem;
        }

        .tv-money-head .tv-money-title {
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: #4e73df;
            display: flex;
            align-items: center;
            gap: .35rem;
        }

        .tv-money-block.charge-block .tv-money-title { color: #856404; }

        .tv-money-total {
            font-family: "SFMono-Regular", Menlo, Consolas, monospace;
            font-size: .95rem;
            font-weight: 800;
            color: #0c7d5b;
        }

        .tv-money-block.charge-block .tv-money-total { color: #b8860b; }

        @media (max-width: 767.98px) {
            .tv-hero h1 { font-size: 1.25rem; }
            .tv-sum-card .tv-sum-value { font-size: 1.05rem; }
            .tv-table { font-size: .8rem; }
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
                        <div class="alert alert-<?= $flashIsWarning ? 'warning' : 'success' ?> alert-dismissible fade show" role="alert">
                            <i class="fas <?= $flashIsWarning ? 'fa-exclamation-triangle' : 'fa-check-circle' ?> mr-1"></i>
                            <?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?>
                            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                        </div>
                    <?php endif; ?>

                    <div id="tripViewFlash"></div>

                    <!-- HERO -->
                    <div class="tv-hero mb-4">
                        <div class="d-flex flex-wrap justify-content-between align-items-start">
                            <div>
                                <div class="small text-uppercase font-weight-bold" style="opacity:.75; letter-spacing:.1em;">
                                    <i class="fas fa-route mr-1"></i> Trip
                                </div>
                                <h1><?= htmlspecialchars($trip['trip_no'], ENT_QUOTES, 'UTF-8') ?></h1>
                                <div class="tv-hero-meta">
                                    <span class="mr-2">
                                        <i class="fas fa-building mr-1"></i>
                                        <?= htmlspecialchars($branchName ?: '—', ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <span class="mr-2">
                                        <i class="fas fa-calendar mr-1"></i>
                                        <?= date('d M Y', strtotime($trip['start_date'])) ?>
                                    </span>
                                    <span class="mr-2">
                                        <i class="fas fa-truck mr-1"></i>
                                        <?= htmlspecialchars($lorryNumber ?: '—', ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </div>
                                <div class="tv-hero-route">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?= htmlspecialchars($trip['source'] ?: '—', ENT_QUOTES, 'UTF-8') ?>
                                    <i class="fas fa-arrow-right" style="font-size:.7rem;"></i>
                                    <?= htmlspecialchars($trip['destination'] ?: '—', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>

                            <div class="d-flex flex-column align-items-end mt-2 mt-sm-0">
                                <?php if ($canChangeStatus): ?>
                                    <div class="dropdown">
                                        <button type="button"
                                            id="tripStatusBadge"
                                            class="tv-status-btn <?= htmlspecialchars(statusPillClass($currentStatus), ENT_QUOTES, 'UTF-8') ?>"
                                            data-toggle="dropdown"
                                            data-current="<?= htmlspecialchars($currentStatus, ENT_QUOTES, 'UTF-8') ?>"
                                            aria-haspopup="true"
                                            aria-expanded="false">
                                            <span class="tv-status-dot"></span>
                                            <span class="js-status-text"><?= htmlspecialchars($currentStatus, ENT_QUOTES, 'UTF-8') ?></span>
                                            <i class="fas fa-chevron-down" style="font-size:.65rem;"></i>
                                        </button>

                                        <div class="dropdown-menu dropdown-menu-right tv-status-menu shadow">
                                            <?php foreach ($allowedStatuses as $st): ?>
                                                <a class="dropdown-item js-status-change <?= $st === $currentStatus ? 'active' : '' ?>"
                                                    href="#"
                                                    data-status="<?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?>"
                                                    data-id="<?= (int) $trip['id'] ?>">
                                                    <span class="tv-menu-icon <?= htmlspecialchars(statusDotClass($st), ENT_QUOTES, 'UTF-8') ?>">
                                                        <i class="fas <?= htmlspecialchars(statusIcon($st), ENT_QUOTES, 'UTF-8') ?>"></i>
                                                    </span>
                                                    <span><?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?></span>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="tv-static-status <?= htmlspecialchars(statusPillClass($currentStatus), ENT_QUOTES, 'UTF-8') ?>">
                                        <span class="tv-status-dot"></span>
                                        <?= htmlspecialchars($currentStatus, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php endif; ?>

                                <div class="mt-2 d-flex" style="gap:.4rem;">
                                    <a href="trip-consignment-print.php?id=<?= (int) $trip['id'] ?>"
                                        target="_blank" class="btn btn-sm btn-light">
                                        <i class="fas fa-file-alt mr-1"></i> Consignment
                                    </a>
                                    <a href="chalan-print.php?id=<?= (int) $trip['id'] ?>"
                                        target="_blank" class="btn btn-sm btn-light">
                                        <i class="fas fa-truck-moving mr-1"></i> Chalan
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SUMMARY -->
                    <div class="row mb-4">
                        <div class="col-6 col-lg mb-3">
                            <div class="card tv-sum-card blue shadow-sm h-100">
                                <div class="card-body py-3">
                                    <div class="tv-sum-label">Freight</div>
                                    <div class="tv-sum-value" id="summaryFreight">₹ <?= number_format($totalFreight, 2) ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-6 col-lg mb-3">
                            <div class="card tv-sum-card green shadow-sm h-100">
                                <div class="card-body py-3">
                                    <div class="tv-sum-label">Received</div>
                                    <div class="tv-sum-value" id="summaryReceived">₹ <?= number_format($totalReceived, 2) ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-6 col-lg mb-3">
                            <div class="card tv-sum-card amber shadow-sm h-100">
                                <div class="card-body py-3">
                                    <div class="tv-sum-label">Charges</div>
                                    <div class="tv-sum-value" id="summaryExtra">₹ <?= number_format($totalExtra, 2) ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-6 col-lg mb-3" id="summaryBalanceCard">
                            <div class="card tv-sum-card <?= $totalBalance > 0.01 ? 'red' : 'green' ?> shadow-sm h-100">
                                <div class="card-body py-3">
                                    <div class="tv-sum-label">Balance</div>
                                    <div class="tv-sum-value" id="summaryBalance">₹ <?= number_format($totalBalance, 2) ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-6 col-lg mb-3">
                            <div class="card tv-sum-card cyan shadow-sm h-100">
                                <div class="card-body py-3">
                                    <div class="tv-sum-label">Expenses</div>
                                    <div class="tv-sum-value" id="summaryExpense">₹ <?= number_format($totalExpense, 2) ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-6 col-lg mb-3" id="summaryNetCard">
                            <div class="card tv-sum-card <?= $netProfit >= 0 ? 'green' : 'red' ?> shadow-sm h-100">
                                <div class="card-body py-3">
                                    <div class="tv-sum-label">Net</div>
                                    <div class="tv-sum-value" id="summaryNet">₹ <?= number_format($netProfit, 2) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- DETAILS + PARTIES -->
                    <div class="row">
                        <div class="col-lg-4 mb-4">
                            <div class="card shadow-sm h-100">
                                <div class="card-header py-2">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="fas fa-info-circle mr-1"></i> Trip Details
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm table-borderless mb-0" style="font-size:.9rem;">
                                        <tr>
                                            <td class="text-muted small" width="40%">Source</td>
                                            <td class="font-weight-bold"><?= htmlspecialchars($trip['source'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted small">Destination</td>
                                            <td class="font-weight-bold"><?= htmlspecialchars($trip['destination'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted small">Truck</td>
                                            <td>
                                                <div class="font-weight-bold"><?= htmlspecialchars($lorryNumber ?: '—', ENT_QUOTES, 'UTF-8') ?></div>
                                                <?php if ($lorryType): ?>
                                                    <div class="small text-muted"><?= htmlspecialchars($lorryType, ENT_QUOTES, 'UTF-8') ?></div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted small">Driver</td>
                                            <td>
                                                <div class="font-weight-bold"><?= htmlspecialchars($driverName ?: '—', ENT_QUOTES, 'UTF-8') ?></div>
                                                <?php if ($driverPhone): ?>
                                                    <div class="small text-muted">
                                                        <i class="fas fa-phone fa-xs mr-1"></i><?= htmlspecialchars($driverPhone, ENT_QUOTES, 'UTF-8') ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted small">Branch</td>
                                            <td class="font-weight-bold"><?= htmlspecialchars($branchName ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted small">Start Date</td>
                                            <td class="font-weight-bold"><?= date('d M Y', strtotime($trip['start_date'])) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted small">Created By</td>
                                            <td>
                                                <div class="font-weight-bold"><?= htmlspecialchars($createdByName ?: '—', ENT_QUOTES, 'UTF-8') ?></div>
                                                <div class="small text-muted"><?= date('d M Y, h:i A', strtotime($trip['created_at'])) ?></div>
                                            </td>
                                        </tr>
                                    </table>

                                    <?php if (!empty($trip['notes'])): ?>
                                        <hr>
                                        <div class="small text-muted mb-1 font-weight-bold text-uppercase">Notes</div>
                                        <div style="white-space:pre-wrap; font-size:.9rem;"><?= htmlspecialchars($trip['notes'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-8 mb-4">
                            <div class="card shadow-sm">
                                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="fas fa-users mr-1"></i> Parties
                                        <span class="badge badge-primary badge-pill ml-1"><?= count($parties) ?></span>
                                    </h6>
                                </div>
                                <div class="card-body">

                                    <?php if (empty($parties)): ?>
                                        <div class="tv-empty">
                                            <i class="fas fa-users"></i>
                                            No parties on this trip yet.
                                        </div>
                                    <?php else: ?>

                                        <?php foreach ($parties as $p):
                                            $pFreight  = (float) $p['freight_amount'];
                                            $pReceived = (float) $p['payments_total'];
                                            $pExtra    = (float) $p['extra_total'];
                                            $pBalance  = ($pFreight + $pExtra) - $pReceived;
                                            $pMode     = (int) ($p['freight_mode'] ?? 0);
                                            $tpId      = (int) $p['id'];
                                        ?>
                                            <div class="tv-party" id="party-card-<?= $tpId ?>" data-freight="<?= $pFreight ?>">

                                                <div class="tv-party-head">
                                                    <div class="d-flex flex-wrap justify-content-between align-items-start" style="gap:1rem;">
                                                        <div>
                                                            <div class="tv-party-name">
                                                                <i class="fas fa-user-tag"></i>
                                                                <?= htmlspecialchars($p['consignor_name'] ?: '—', ENT_QUOTES, 'UTF-8') ?>
                                                                <?php if ($pMode === 0): ?>
                                                                    <span class="tv-pill tv-pill-blue">Fixed</span>
                                                                <?php else: ?>
                                                                    <span class="tv-pill tv-pill-green">Item-wise</span>
                                                                <?php endif; ?>
                                                            </div>

                                                            <div class="tv-party-meta">
                                                                <?php if ($p['consignor_trade']): ?>
                                                                    <span><i class="fas fa-store mr-1"></i><?= htmlspecialchars($p['consignor_trade'], ENT_QUOTES, 'UTF-8') ?></span>
                                                                <?php endif; ?>
                                                                <?php if ($p['consignor_gstin']): ?>
                                                                    <span><i class="fas fa-file-invoice mr-1"></i><?= htmlspecialchars($p['consignor_gstin'], ENT_QUOTES, 'UTF-8') ?></span>
                                                                <?php endif; ?>
                                                                <?php if ($p['consignor_phone']): ?>
                                                                    <span><i class="fas fa-phone mr-1"></i><?= htmlspecialchars($p['consignor_phone'], ENT_QUOTES, 'UTF-8') ?></span>
                                                                <?php endif; ?>
                                                            </div>

                                                            <?php if ($p['consignee_name']): ?>
                                                                <div class="mt-2 pt-2 border-top small">
                                                                    <span class="tv-pill tv-pill-blue">Consignee</span>
                                                                    <strong class="ml-1"><?= htmlspecialchars($p['consignee_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                                    <?php if ($p['consignee_phone']): ?>
                                                                        <span class="text-muted ml-2"><i class="fas fa-phone fa-xs mr-1"></i><?= htmlspecialchars($p['consignee_phone'], ENT_QUOTES, 'UTF-8') ?></span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>

                                                        <div class="d-flex flex-wrap" style="gap:1.25rem;">
                                                            <div>
                                                                <div class="tv-fig-label">Freight</div>
                                                                <div class="tv-fig-value">₹ <?= number_format($pFreight, 2) ?></div>
                                                            </div>
                                                            <div>
                                                                <div class="tv-fig-label">Received</div>
                                                                <div class="tv-fig-value green" id="party-received-<?= $tpId ?>">₹ <?= number_format($pReceived, 2) ?></div>
                                                            </div>
                                                            <div>
                                                                <div class="tv-fig-label">Charges</div>
                                                                <div class="tv-fig-value amber" id="party-extra-<?= $tpId ?>">₹ <?= number_format($pExtra, 2) ?></div>
                                                            </div>
                                                            <div>
                                                                <div class="tv-fig-label">Balance</div>
                                                                <div class="tv-fig-value <?= $pBalance > 0.01 ? 'red' : 'green' ?>" id="party-balance-<?= $tpId ?>">₹ <?= number_format($pBalance, 2) ?></div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <?php if (!empty($p['notes'])): ?>
                                                        <div class="mt-2 pt-2 border-top small text-muted">
                                                            <i class="fas fa-sticky-note mr-1"></i><?= htmlspecialchars($p['notes'], ENT_QUOTES, 'UTF-8') ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="card-body py-3">

                                                    <!-- Items -->
                                                    <div class="tv-section-title">
                                                        <i class="fas fa-box"></i> Inventory
                                                    </div>

                                                    <?php if (empty($p['items'])): ?>
                                                        <div class="text-muted small">No items recorded.</div>
                                                    <?php else: ?>
                                                        <div class="table-responsive">
                                                            <table class="tv-table">
                                                                <thead>
                                                                    <tr>
                                                                        <th>Inventory</th>
                                                                        <th>Unit</th>
                                                                        <th class="text-right">Qty</th>
                                                                        <?php if ($pMode === 1): ?>
                                                                            <th class="text-right">Rate</th>
                                                                            <th class="text-right">Amount</th>
                                                                        <?php endif; ?>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php foreach ($p['items'] as $it): ?>
                                                                        <tr>
                                                                            <td><?= htmlspecialchars($it['inventory_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                                                            <td><?= htmlspecialchars($it['unit'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                                                                            <td class="num"><?= (int) $it['quantity'] ?></td>
                                                                            <?php if ($pMode === 1): ?>
                                                                                <td class="num">₹ <?= number_format((float) $it['rate'], 2) ?></td>
                                                                                <td class="num">₹ <?= number_format((float) $it['amount'], 2) ?></td>
                                                                            <?php endif; ?>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                                <?php if ($pMode === 1): ?>
                                                                    <tfoot>
                                                                        <tr>
                                                                            <td colspan="4">Item Total</td>
                                                                            <td class="num">₹ <?= number_format($p['items_total'], 2) ?></td>
                                                                        </tr>
                                                                    </tfoot>
                                                                <?php endif; ?>
                                                            </table>
                                                        </div>
                                                    <?php endif; ?>

                                                    <!-- Payments + Charges -->
                                                    <div class="row mt-4">

                                                        <!-- Payments -->
                                                        <div class="col-12 col-md-6 mb-3">
                                                            <div class="tv-money-block">
                                                                <div class="tv-money-head">
                                                                    <div class="tv-money-title">
                                                                        <i class="fas fa-money-bill-wave"></i> Payments
                                                                    </div>
                                                                    <?php if ($canEdit): ?>
                                                                        <button type="button"
                                                                            class="btn btn-sm btn-success js-open-payment"
                                                                            data-trip-party-id="<?= $tpId ?>"
                                                                            data-party-name="<?= htmlspecialchars($p['consignor_name'] ?: '', ENT_QUOTES, 'UTF-8') ?>">
                                                                            <i class="fas fa-plus mr-1"></i> Add
                                                                        </button>
                                                                    <?php endif; ?>
                                                                </div>

                                                                <div id="paymentsEmpty-<?= $tpId ?>"
                                                                    class="tv-empty"
                                                                    style="<?= empty($p['payments']) ? '' : 'display:none;' ?>">
                                                                    <i class="fas fa-money-bill-wave"></i>
                                                                    No payments yet.
                                                                </div>

                                                                <div id="paymentsWrap-<?= $tpId ?>"
                                                                    class="table-responsive"
                                                                    style="<?= empty($p['payments']) ? 'display:none;' : '' ?>">
                                                                    <table class="tv-table">
                                                                        <thead>
                                                                            <tr>
                                                                                <th width="30">#</th>
                                                                                <th>Date</th>
                                                                                <th>Type</th>
                                                                                <th>Mode</th>
                                                                                <th>Ref</th>
                                                                                <th>Notes</th>
                                                                                <th class="text-right">Amount</th>
                                                                                <?php if ($canEdit): ?>
                                                                                    <th width="50" class="text-center"></th>
                                                                                <?php endif; ?>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody id="paymentsBody-<?= $tpId ?>">
                                                                            <?php foreach ($p['payments'] as $i => $pay): ?>
                                                                                <tr data-payment-id="<?= (int) $pay['id'] ?>">
                                                                                    <td class="text-muted small tv-pay-index"><?= $i + 1 ?></td>
                                                                                    <td><?= date('d M Y', strtotime($pay['payment_date'])) ?></td>
                                                                                    <td><span class="tv-pay-type"><?= htmlspecialchars($pay['payment_type'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                                                                    <td class="small"><?= htmlspecialchars($pay['mode'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                                                                                    <td class="small text-muted"><?= htmlspecialchars($pay['reference'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                                                                                    <td class="small text-muted"><?= htmlspecialchars($pay['notes'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                                                                                    <td class="tv-pay-amount">₹ <?= number_format((float) $pay['amount'], 2) ?></td>
                                                                                    <?php if ($canEdit): ?>
                                                                                        <td class="text-center">
                                                                                            <button type="button"
                                                                                                class="tv-icon-btn tv-icon-btn-danger js-delete-payment"
                                                                                                data-payment-id="<?= (int) $pay['id'] ?>"
                                                                                                data-trip-party-id="<?= $tpId ?>"
                                                                                                data-amount="<?= (float) $pay['amount'] ?>"
                                                                                                data-type="<?= htmlspecialchars($pay['payment_type'], ENT_QUOTES, 'UTF-8') ?>"
                                                                                                data-kind="payment"
                                                                                                title="Delete">
                                                                                                <i class="fas fa-trash-alt"></i>
                                                                                            </button>
                                                                                        </td>
                                                                                    <?php endif; ?>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        </tbody>
                                                                        <tfoot>
                                                                            <tr>
                                                                                <td colspan="<?= $canEdit ? '6' : '5' ?>" class="text-right">Total Received</td>
                                                                                <td class="tv-pay-amount" id="paymentsTotal-<?= $tpId ?>" style="font-size:.95rem;">
                                                                                    ₹ <?= number_format($pReceived, 2) ?>
                                                                                </td>
                                                                                <?php if ($canEdit): ?><td></td><?php endif; ?>
                                                                            </tr>
                                                                        </tfoot>
                                                                    </table>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Charges -->
                                                        <div class="col-12 col-md-6 mb-3">
                                                            <div class="tv-money-block charge-block">
                                                                <div class="tv-money-head">
                                                                    <div class="tv-money-title">
                                                                        <i class="fas fa-plus-square"></i> Charges
                                                                    </div>
                                                                    <?php if ($canEdit): ?>
                                                                        <button type="button"
                                                                            class="btn btn-sm btn-warning js-open-charge"
                                                                            data-trip-party-id="<?= $tpId ?>"
                                                                            data-party-name="<?= htmlspecialchars($p['consignor_name'] ?: '', ENT_QUOTES, 'UTF-8') ?>">
                                                                            <i class="fas fa-plus mr-1"></i> Add
                                                                        </button>
                                                                    <?php endif; ?>
                                                                </div>

                                                                <div id="chargesEmpty-<?= $tpId ?>"
                                                                    class="tv-empty"
                                                                    style="<?= empty($p['charges']) ? '' : 'display:none;' ?>">
                                                                    <i class="fas fa-plus-square"></i>
                                                                    No charges yet.
                                                                </div>

                                                                <div id="chargesWrap-<?= $tpId ?>"
                                                                    class="table-responsive"
                                                                    style="<?= empty($p['charges']) ? 'display:none;' : '' ?>">
                                                                    <table class="tv-table">
                                                                        <thead>
                                                                            <tr>
                                                                                <th width="30">#</th>
                                                                                <th>Date</th>
                                                                                <th>Type</th>
                                                                                <th>Notes</th>
                                                                                <th class="text-right">Amount</th>
                                                                                <?php if ($canEdit): ?>
                                                                                    <th width="50" class="text-center"></th>
                                                                                <?php endif; ?>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody id="chargesBody-<?= $tpId ?>">
                                                                            <?php foreach ($p['charges'] as $i => $ch): ?>
                                                                                <tr data-payment-id="<?= (int) $ch['id'] ?>">
                                                                                    <td class="text-muted small tv-pay-index"><?= $i + 1 ?></td>
                                                                                    <td><?= date('d M Y', strtotime($ch['payment_date'])) ?></td>
                                                                                    <td><span class="tv-pay-type extra"><?= htmlspecialchars($ch['payment_type'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                                                                    <td class="small text-muted"><?= htmlspecialchars($ch['notes'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                                                                                    <td class="tv-pay-amount" style="color:#b8860b;">₹ <?= number_format((float) $ch['amount'], 2) ?></td>
                                                                                    <?php if ($canEdit): ?>
                                                                                        <td class="text-center">
                                                                                            <button type="button"
                                                                                                class="tv-icon-btn tv-icon-btn-danger js-delete-payment"
                                                                                                data-payment-id="<?= (int) $ch['id'] ?>"
                                                                                                data-trip-party-id="<?= $tpId ?>"
                                                                                                data-amount="<?= (float) $ch['amount'] ?>"
                                                                                                data-type="<?= htmlspecialchars($ch['payment_type'], ENT_QUOTES, 'UTF-8') ?>"
                                                                                                data-kind="charge"
                                                                                                title="Delete">
                                                                                                <i class="fas fa-trash-alt"></i>
                                                                                            </button>
                                                                                        </td>
                                                                                    <?php endif; ?>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        </tbody>
                                                                        <tfoot>
                                                                            <tr>
                                                                                <td colspan="<?= $canEdit ? '4' : '3' ?>" class="text-right">Total Charges</td>
                                                                                <td class="tv-pay-amount" id="chargesTotal-<?= $tpId ?>" style="color:#b8860b; font-size:.95rem;">
                                                                                    ₹ <?= number_format($pExtra, 2) ?>
                                                                                </td>
                                                                                <?php if ($canEdit): ?><td></td><?php endif; ?>
                                                                            </tr>
                                                                        </tfoot>
                                                                    </table>
                                                                </div>
                                                            </div>
                                                        </div>

                                                    </div>

                                                </div>
                                            </div>
                                        <?php endforeach; ?>

                                    <?php endif; ?>

                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- EXPENSES -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header py-2 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-receipt mr-1"></i> Expenses (Company Paid)
                                <span class="badge badge-primary badge-pill ml-1" id="expenseCount"><?= count($expenses) ?></span>
                            </h6>
                            <?php if ($canEdit): ?>
                                <button type="button" class="btn btn-sm btn-primary js-open-expense">
                                    <i class="fas fa-plus mr-1"></i> Add Expense
                                </button>
                            <?php endif; ?>
                        </div>

                        <div id="expensesEmpty" class="tv-empty" style="<?= empty($expenses) ? '' : 'display:none;' ?>">
                            <i class="fas fa-receipt"></i>
                            No expenses recorded yet.
                        </div>

                        <div id="expensesTableWrap" class="table-responsive" style="<?= empty($expenses) ? 'display:none;' : '' ?>">
                            <table class="tv-table" id="expensesTable">
                                <thead>
                                    <tr>
                                        <th width="40">#</th>
                                        <th>Date</th>
                                        <th>Category</th>
                                        <th>Supplier</th>
                                        <th>Notes</th>
                                        <th class="text-right">Amount</th>
                                        <?php if ($canEdit): ?>
                                            <th width="60" class="text-center">Action</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody id="expensesBody">
                                    <?php foreach ($expenses as $i => $x): ?>
                                        <tr data-expense-id="<?= (int) $x['id'] ?>">
                                            <td class="text-muted small tv-exp-index"><?= $i + 1 ?></td>
                                            <td><?= date('d M Y', strtotime($x['expense_date'])) ?></td>
                                            <td><span class="tv-pill"><?= htmlspecialchars($x['category'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                            <td><?= htmlspecialchars($x['supplier_name'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="small text-muted"><?= htmlspecialchars($x['notes'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="tv-pay-amount" style="color:#c0392b;">₹ <?= number_format((float) $x['amount'], 2) ?></td>
                                            <?php if ($canEdit): ?>
                                                <td class="text-center">
                                                    <button type="button"
                                                        class="tv-icon-btn tv-icon-btn-danger js-delete-expense"
                                                        data-expense-id="<?= (int) $x['id'] ?>"
                                                        data-amount="<?= (float) $x['amount'] ?>"
                                                        data-category="<?= htmlspecialchars($x['category'], ENT_QUOTES, 'UTF-8') ?>"
                                                        title="Delete expense">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="<?= $canEdit ? '5' : '4' ?>" class="text-right">Total Expenses</td>
                                        <td class="tv-pay-amount" id="expensesTotalCell" style="color:#c0392b; font-size:.95rem;">
                                            ₹ <?= number_format($totalExpense, 2) ?>
                                        </td>
                                        <?php if ($canEdit): ?><td></td><?php endif; ?>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
            <?php include 'layout/footer.php'; ?>
        </div>
    </div>

    <!-- PAYMENT / CHARGE MODAL (shared) -->
    <?php if ($canEdit): ?>
    <div class="modal fade" id="paymentModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentModalTitle">
                        <i class="fas fa-money-bill-wave mr-1"></i> Add
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    <div id="paymentFormError" class="d-none"></div>

                    <form id="paymentForm" onsubmit="return false;">
                        <input type="hidden" name="trip_party_id" value="">

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Date <span class="text-danger">*</span></label>
                                <input type="date" name="payment_date" class="form-control" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label>Amount <span class="text-danger">*</span></label>
                                <input type="number" name="amount" class="form-control"
                                    step="0.01" min="0" placeholder="0.00" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Type <span class="text-danger">*</span></label>
                                <select name="payment_type" class="form-control" required>
                                    <option value="">— Select —</option>
                                </select>
                            </div>
                            <div class="form-group col-md-6 js-mode-field">
                                <label>Mode</label>
                                <select name="mode" class="form-control">
                                    <option value="">— Select —</option>
                                    <option value="Cash">Cash</option>
                                    <option value="UPI">UPI</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Cheque">Cheque</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group js-mode-field">
                            <label>Reference</label>
                            <input type="text" name="reference" class="form-control"
                                maxlength="100" placeholder="Cheque no / UPI ref / etc.">
                        </div>

                        <div class="form-group mb-0">
                            <label>Notes</label>
                            <input type="text" name="notes" class="form-control"
                                maxlength="255" placeholder="Optional">
                        </div>
                    </form>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-success" id="paymentSubmitBtn">
                        <i class="fas fa-check mr-1"></i> Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- EXPENSE MODAL -->
    <div class="modal fade" id="expenseModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-receipt mr-1"></i> Add Expense
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    <div id="expenseFormError" class="d-none"></div>

                    <form id="expenseForm" onsubmit="return false;">
                        <input type="hidden" name="trip_id" value="<?= (int) $trip['id'] ?>">

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Expense Date <span class="text-danger">*</span></label>
                                <input type="date" name="expense_date" class="form-control"
                                    value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label>Category <span class="text-danger">*</span></label>
                                <select name="category" class="form-control js-expense-category" required>
                                    <option value="">— Select Category —</option>
                                    <?php foreach ($expenseCategories as $c): ?>
                                        <option value="<?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Amount <span class="text-danger">*</span></label>
                            <input type="number" name="amount" class="form-control"
                                step="0.01" min="0" placeholder="0.00" required>
                        </div>

                        <div class="form-group js-supplier-field d-none">
                            <label>Supplier</label>
                            <select name="supplier_id" class="form-control">
                                <option value="">— None —</option>
                                <?php foreach ($suppliers as $s): ?>
                                    <option value="<?= (int) $s['id'] ?>">
                                        <?= htmlspecialchars($s['supplier_name'], ENT_QUOTES, 'UTF-8') ?>
                                        <?= $s['supplier_type'] ? ' (' . htmlspecialchars($s['supplier_type'], ENT_QUOTES, 'UTF-8') . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group mb-0">
                            <label>Notes</label>
                            <input type="text" name="notes" class="form-control"
                                maxlength="255" placeholder="Optional">
                        </div>
                    </form>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-success" id="expenseSubmitBtn">
                        <i class="fas fa-check mr-1"></i> Save Expense
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        window.TRIP_VIEW_DATA = {
            tripId:   <?= (int) $trip['id'] ?>,
            canEdit:  <?= $canEdit ? 'true' : 'false' ?>,
            freight:  <?= (float) $totalFreight ?>,
            received: <?= (float) $totalReceived ?>,
            extra:    <?= (float) $totalExtra ?>,
            expense:  <?= (float) $totalExpense ?>
        };
    </script>

    <script src="js/trip-view.js"></script>

    <?php if ($cameFromForm): ?>
    <script>
        (function () {
            try {
                history.replaceState(null, '', 'trips-list.php');
                history.pushState(null, '', 'trip-view.php?id=<?= (int) $trip['id'] ?>');
            } catch (e) {}
        })();
    </script>
    <?php endif; ?>

</body>

</html>