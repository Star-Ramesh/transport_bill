<?php
/* =========================================================
   consignment-print.php
   ---------------------------------------------------------
   Prints the Consignment Note for one trip.
   Usage: consignment-print.php?id=N
   Format: A5 landscape
   ========================================================= */

include 'constant.php';
include 'session.php';

requirePermission('trip.view');

/* =========================================================
   PAYMENT TYPE CLASSIFICATION (must match trip-view.php)
   ---------------------------------------------------------
   receipt → money received from party → reduces balance
   extra   → money owed by party       → increases balance
   ========================================================= */
function paymentKind($type)
{
    switch ($type) {
        case 'Loading Charge':
        case 'Unloading Charge':
        case 'Detention':
        case 'Extra Charge':
            return 'extra';
        case 'Advance':
        case 'Freight Payment':
        case 'Refund':
        default:
            return 'receipt';
    }
}

/* =========================================================
   LOAD THE TRIP (single-table, no JOINs)
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
        $branchCheck = " AND branch_id = $myBranch";
    } else {
        $branchCheck = " AND 1=0";
    }
}

$resTrip = mysqli_query($conn, "SELECT * FROM trip WHERE id = $tripId $branchCheck LIMIT 1");
if (!$resTrip || mysqli_num_rows($resTrip) !== 1) {
    header("Location: trips-list.php");
    exit;
}

$trip = mysqli_fetch_assoc($resTrip);

/* Lookup lorry */
$lorryNumber = '';
$lorryType   = '';
$ownerName   = '';
if ((int) $trip['lorry_id'] > 0) {
    $r = mysqli_query($conn, "SELECT lorry_number, lorry_type, owner_name FROM lorry WHERE id = " . (int) $trip['lorry_id'] . " LIMIT 1");
    if ($r && mysqli_num_rows($r) === 1) {
        $lr = mysqli_fetch_assoc($r);
        $lorryNumber = $lr['lorry_number'] ?? '';
        $lorryType   = $lr['lorry_type']   ?? '';
        $ownerName   = $lr['owner_name']   ?? '';
    }
}

/* Lookup driver */
$driverName    = '';
$driverPhone   = '';
$driverLicense = '';
if ((int) $trip['driver_id'] > 0) {
    $r = mysqli_query($conn, "SELECT driver_name, phone, license_no FROM driver WHERE id = " . (int) $trip['driver_id'] . " LIMIT 1");
    if ($r && mysqli_num_rows($r) === 1) {
        $dr = mysqli_fetch_assoc($r);
        $driverName    = $dr['driver_name'] ?? '';
        $driverPhone   = $dr['phone']       ?? '';
        $driverLicense = $dr['license_no']  ?? '';
    }
}

/* =========================================================
   LOAD PARTIES + ITEMS (no JOINs)
   ========================================================= */
$items = [];
$totalFreight  = 0;
$totalReceived = 0;
$totalExtra    = 0;
$partyCount    = 0;

$consignorPartyName  = '';
$consignorTradeName  = '';
$consignorGstin      = '';
$consignorAddress    = '';
$consignorCity       = '';
$consignorState      = '';
$consignorPincode    = '';
$consignorPhone      = '';

$consigneePartyName  = '';
$consigneeTradeName  = '';
$consigneeGstin      = '';
$consigneeAddress    = '';
$consigneeCity       = '';
$consigneeState      = '';
$consigneePincode    = '';
$consigneePhone      = '';

$resParties = mysqli_query(
    $conn,
    "SELECT * FROM trip_party WHERE trip_id = $tripId ORDER BY id ASC"
);

if ($resParties) {
    while ($p = mysqli_fetch_assoc($resParties)) {
        $partyCount++;
        $tpId = (int) $p['id'];

        /* First row becomes the printed Consignor */
        if ($consignorPartyName === '' && (int) $p['party_id'] > 0) {
            $pid = (int) $p['party_id'];
            $pr = mysqli_query($conn, "SELECT legal_name, trade_name, gstin, address, city, state, pincode, phone FROM party WHERE id = $pid LIMIT 1");
            if ($pr && mysqli_num_rows($pr) === 1) {
                $pp = mysqli_fetch_assoc($pr);
                $consignorPartyName = $pp['legal_name'] ?? '';
                $consignorTradeName = $pp['trade_name'] ?? '';
                $consignorGstin     = $pp['gstin']      ?? '';
                $consignorAddress   = $pp['address']    ?? '';
                $consignorCity      = $pp['city']       ?? '';
                $consignorState     = $pp['state']      ?? '';
                $consignorPincode   = $pp['pincode']    ?? '';
                $consignorPhone     = $pp['phone']      ?? '';
            }
        }

        /* First trip_party row that has a consignee becomes the printed Consignee */
        if ($consigneePartyName === '' && (int) $p['consignee_party_id'] > 0) {
            $cpId = (int) $p['consignee_party_id'];
            $cr = mysqli_query($conn, "SELECT legal_name, trade_name, gstin, address, city, state, pincode, phone FROM party WHERE id = $cpId LIMIT 1");
            if ($cr && mysqli_num_rows($cr) === 1) {
                $cc = mysqli_fetch_assoc($cr);
                $consigneePartyName = $cc['legal_name'] ?? '';
                $consigneeTradeName = $cc['trade_name'] ?? '';
                $consigneeGstin     = $cc['gstin']      ?? '';
                $consigneeAddress   = $cc['address']    ?? '';
                $consigneeCity      = $cc['city']       ?? '';
                $consigneeState     = $cc['state']      ?? '';
                $consigneePincode   = $cc['pincode']    ?? '';
                $consigneePhone     = $cc['phone']      ?? '';
            }
        }

        $totalFreight += (float) $p['freight_amount'];

        /* Payments for this party — split by type */
        $resPay = mysqli_query($conn, "SELECT payment_type, amount FROM trip_payment WHERE trip_party_id = $tpId AND active = 1");
        if ($resPay) {
            while ($pay = mysqli_fetch_assoc($resPay)) {
                $kind = paymentKind($pay['payment_type']);
                if ($kind === 'extra') {
                    $totalExtra += (float) $pay['amount'];
                } else {
                    $totalReceived += (float) $pay['amount'];
                }
            }
        }

        $resItems = mysqli_query(
            $conn,
            "SELECT inventory_name, unit, quantity, rate, amount
             FROM trip_inventory
             WHERE trip_party_id = $tpId
             ORDER BY id ASC"
        );
        if ($resItems) {
            while ($it = mysqli_fetch_assoc($resItems)) {
                $it['freight_mode'] = (int) $p['freight_mode'];
                $items[] = $it;
            }
        }
    }
}

$totalDue = ($totalFreight + $totalExtra) - $totalReceived;

$valueOfGoods = 0;
foreach ($items as $it) {
    if ((int) $it['freight_mode'] === 1) {
        $valueOfGoods += (float) $it['amount'];
    }
}
if ($valueOfGoods === 0.0) {
    $valueOfGoods = $totalFreight;
}

/* Any item in Item-wise mode? */
$hasItemWise = false;
foreach ($items as $it) {
    if ((int) $it['freight_mode'] === 1) {
        $hasItemWise = true;
        break;
    }
}

/* =========================================================
   COMPANY HEADER
   ========================================================= */
$companyName    = 'DIPAK TRANSPORT SERVICES';
$companyTagline = 'Truck Suppliers & Commission Agent';
$companyArea    = 'ALL BENGAL, BIHAR & JHARKHAND';
$companyAddress = 'Office: Masjid Road, AlBhai Garage, Bolpur, Birbhum, Pin: 731204';
$companyEmail   = 'dipaktransportservices@gmail.com';
$companyPhones  = 'Mob. No.: 9434456545 / 9614131415';
$companyGstin   = '19AMRPT0703N1ZQ';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Consignment Note — <?= htmlspecialchars($trip['trip_no'], ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        @page { size: A5 landscape; margin: 6mm; }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        html, body { margin: 0; padding: 0; }

        body {
            font-family: "Nunito", Arial, Helvetica, sans-serif;
            background: #e8eaf0;
            color: #000;
            font-size: 9px;
            line-height: 1.22;
        }

        .print-toolbar {
            background: #fff;
            padding: 10px 16px;
            border-bottom: 1px solid #c8cddb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .print-toolbar .btn {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 4px;
            border: 1px solid #4e73df;
            background: #4e73df;
            color: #fff;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
        }

        .print-toolbar .btn.secondary { background: #fff; color: #4e73df; }
        .print-toolbar .hint { color: #6e707e; font-size: 12px; }

        .page {
            width: 210mm;
            height: 148mm;
            margin: 16px auto;
            padding: 6mm;
            background: #fff;
            box-shadow: 0 4px 14px rgba(0, 0, 0, .14);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            border-bottom: 1.5px solid #000;
            padding-bottom: 3px;
            margin-bottom: 4px;
            gap: 6px;
        }

        .header-left { flex: 1 1 auto; min-width: 0; }

        .company-name {
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 0.4px;
            margin: 0;
            text-transform: uppercase;
            line-height: 1.1;
        }

        .company-tagline { font-size: 8px; font-weight: 700; margin: 1px 0 0; }
        .company-area { font-size: 7px; font-weight: 700; letter-spacing: 0.5px; margin: 0; }

        .company-contact {
            font-size: 7.5px;
            margin: 2px 0 0;
            line-height: 1.3;
        }

        .header-right { flex: 0 0 auto; text-align: right; }

        .doc-title {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1px;
            margin: 0 0 2px;
            text-transform: uppercase;
        }

        .chalan-gstin {
            font-size: 7.5px;
            font-weight: 700;
            border: 1px solid #000;
            padding: 1px 5px;
            display: inline-block;
            white-space: nowrap;
        }

        .meta-strip {
            display: flex;
            border: 1px solid #000;
            margin-bottom: 3px;
        }

        .meta-strip>div {
            flex: 1 1 0;
            padding: 2px 5px;
            border-right: 1px solid #000;
            min-width: 0;
        }

        .meta-strip>div:last-child { border-right: 0; }

        .meta-strip .label {
            font-weight: 700;
            text-transform: uppercase;
            font-size: 6.5px;
            letter-spacing: 0.3px;
            display: block;
            color: #333;
        }

        .meta-strip .value {
            font-weight: 700;
            font-size: 9.5px;
            font-family: "SFMono-Regular", Menlo, Consolas, monospace;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .addr-row {
            display: flex;
            border: 1px solid #000;
            margin-bottom: 3px;
        }

        .addr-block {
            flex: 1 1 0;
            padding: 3px 6px;
            border-right: 1px solid #000;
            min-width: 0;
        }

        .addr-block:last-child { border-right: 0; }

        .addr-title {
            font-size: 7px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #333;
            border-bottom: 1px solid #ccc;
            padding-bottom: 1px;
            margin-bottom: 2px;
        }

        .addr-name {
            font-size: 9.5px;
            font-weight: 700;
            line-height: 1.2;
        }

        .addr-line { font-size: 7.5px; line-height: 1.3; color: #222; }
        .addr-blank { border-bottom: 1px dotted #888; height: 10px; margin: 2px 0; }
        .addr-empty { font-size: 7.5px; color: #888; font-style: italic; }

        .detail-block {
            display: flex;
            border: 1px solid #000;
            margin-bottom: 3px;
        }

        .detail-col {
            flex: 1 1 0;
            padding: 3px 6px;
            border-right: 1px solid #000;
            min-width: 0;
        }

        .detail-col:last-child { border-right: 0; }

        .detail-row {
            display: flex;
            font-size: 8.5px;
            margin-bottom: 2px;
            align-items: baseline;
            gap: 4px;
        }

        .detail-row:last-child { margin-bottom: 0; }

        .detail-row .k {
            flex: 0 0 42%;
            font-weight: 700;
            font-size: 7.5px;
            text-transform: uppercase;
            color: #222;
            letter-spacing: 0.2px;
        }

        .detail-row .v {
            flex: 1 1 auto;
            border-bottom: 1px solid #999;
            min-height: 11px;
            font-weight: 600;
            padding: 0 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .items-wrap {
            flex: 1 1 auto;
            margin-bottom: 3px;
            overflow: hidden;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5px;
            table-layout: fixed;
        }

        table.items th,
        table.items td {
            border: 1px solid #000;
            padding: 1.5px 3px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        table.items thead th {
            background: #efefef;
            font-weight: 700;
            font-size: 7px;
            text-transform: uppercase;
            letter-spacing: 0.2px;
            text-align: center;
            padding: 2px 3px;
        }

        table.items td.num {
            text-align: right;
            font-family: "SFMono-Regular", Menlo, Consolas, monospace;
            white-space: nowrap;
        }

        table.items td.center { text-align: center; }

        table.items tfoot td {
            font-weight: 700;
            background: #f7f7f7;
            font-size: 8.5px;
            padding: 2px 4px;
        }

        .declaration {
            font-size: 7.5px;
            border: 1px solid #000;
            padding: 3px 6px;
            margin-bottom: 3px;
            color: #333;
        }

        .declaration strong { font-weight: 800; color: #000; }

        .signatures {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: auto;
            padding-top: 6mm;
        }

        .sig-block {
            flex: 1 1 0;
            text-align: center;
            padding: 0 4px;
        }

        .sig-line { border-top: 1px solid #000; height: 0; margin-bottom: 3px; }

        .sig-label {
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            font-size: 7.5px;
        }

        .sig-sub { font-size: 6.5px; color: #333; margin-top: 1px; }

        .page-footer {
            margin-top: 3px;
            padding-top: 3px;
            border-top: 1px dotted #888;
            font-size: 7px;
            color: #444;
            display: flex;
            justify-content: space-between;
        }

        @media print {
            body { background: #fff; font-size: 9px; }
            .print-toolbar { display: none !important; }
            .page {
                width: auto;
                height: auto;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
                overflow: visible;
            }
        }
    </style>
</head>

<body>

    <div class="print-toolbar">
        <div>
            <a href="trip-view.php?id=<?= (int) $trip['id'] ?>" class="btn secondary">
                &larr; Back to Trip
            </a>
            <button type="button" class="btn" style="margin-left: 8px;" onclick="window.print();">
                Print Consignment
            </button>
        </div>
        <div class="hint">
            Paper: A5 landscape · Trip <?= htmlspecialchars($trip['trip_no'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    </div>

    <div class="page">

        <!-- HEADER -->
        <div class="header">
            <div class="header-left">
                <h1 class="company-name"><?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></h1>
                <div class="company-tagline"><?= htmlspecialchars($companyTagline, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="company-area"><?= htmlspecialchars($companyArea, ENT_QUOTES, 'UTF-8') ?></div>
                <p class="company-contact">
                    <?= htmlspecialchars($companyAddress, ENT_QUOTES, 'UTF-8') ?><br>
                    Email: <?= htmlspecialchars($companyEmail, ENT_QUOTES, 'UTF-8') ?> ·
                    <?= htmlspecialchars($companyPhones, ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>
            <div class="header-right">
                <div class="doc-title">Consignment Note</div>
                <div class="chalan-gstin">
                    GSTIN: <?= htmlspecialchars($companyGstin, ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
        </div>

        <!-- META STRIP -->
        <div class="meta-strip">
            <div>
                <span class="label">Consignment No</span>
                <span class="value"><?= htmlspecialchars($trip['trip_no'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div>
                <span class="label">Date</span>
                <span class="value"><?= date('d-m-Y', strtotime($trip['start_date'])) ?></span>
            </div>
            <div>
                <span class="label">Source</span>
                <span class="value"><?= htmlspecialchars($trip['source'] ?: '—', ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div>
                <span class="label">Destination</span>
                <span class="value"><?= htmlspecialchars($trip['destination'] ?: '—', ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>

        <!-- CONSIGNOR / CONSIGNEE -->
        <div class="addr-row">

            <div class="addr-block">
                <div class="addr-title">Consignor</div>
                <?php if ($consignorPartyName !== ''): ?>
                    <div class="addr-name"><?= htmlspecialchars($consignorPartyName, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php if ($consignorTradeName !== ''): ?>
                        <div class="addr-line"><?= htmlspecialchars($consignorTradeName, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                    <?php
                    $cAddr = array_filter([$consignorAddress, $consignorCity, $consignorState, $consignorPincode]);
                    ?>
                    <?php if (!empty($cAddr)): ?>
                        <div class="addr-line"><?= htmlspecialchars(implode(', ', $cAddr), ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                    <?php if ($consignorPhone !== ''): ?>
                        <div class="addr-line"><strong>Phone:</strong> <?= htmlspecialchars($consignorPhone, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                    <?php if ($consignorGstin !== ''): ?>
                        <div class="addr-line"><strong>GSTIN:</strong> <?= htmlspecialchars($consignorGstin, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="addr-blank"></div>
                    <div class="addr-blank"></div>
                    <div class="addr-blank"></div>
                <?php endif; ?>
            </div>

            <div class="addr-block">
                <div class="addr-title">Consignee</div>
                <?php if ($consigneePartyName !== ''): ?>
                    <div class="addr-name"><?= htmlspecialchars($consigneePartyName, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php if ($consigneeTradeName !== ''): ?>
                        <div class="addr-line"><?= htmlspecialchars($consigneeTradeName, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                    <?php
                    $ccAddr = array_filter([$consigneeAddress, $consigneeCity, $consigneeState, $consigneePincode]);
                    ?>
                    <?php if (!empty($ccAddr)): ?>
                        <div class="addr-line"><?= htmlspecialchars(implode(', ', $ccAddr), ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                    <?php if ($consigneePhone !== ''): ?>
                        <div class="addr-line"><strong>Phone:</strong> <?= htmlspecialchars($consigneePhone, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                    <?php if ($consigneeGstin !== ''): ?>
                        <div class="addr-line"><strong>GSTIN:</strong> <?= htmlspecialchars($consigneeGstin, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="addr-empty">(Same as Consignor — not specified)</div>
                    <div class="addr-blank"></div>
                    <div class="addr-blank"></div>
                    <div class="addr-blank"></div>
                <?php endif; ?>
            </div>

        </div>

        <!-- DETAILS -->
        <div class="detail-block">
            <div class="detail-col">
                <div class="detail-row">
                    <span class="k">Vehicle No</span>
                    <span class="v"><?= htmlspecialchars($lorryNumber ?: '', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="detail-row">
                    <span class="k">Driver's Name</span>
                    <span class="v"><?= htmlspecialchars($driverName ?: '', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="detail-row">
                    <span class="k">Driver's L. No</span>
                    <span class="v"><?= htmlspecialchars($driverLicense ?: '', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>
            <div class="detail-col">
                <div class="detail-row">
                    <span class="k">Value of Goods Rs.</span>
                    <span class="v"><?= number_format($valueOfGoods, 2) ?></span>
                </div>
                <div class="detail-row">
                    <span class="k">Invoice No</span>
                    <span class="v">&nbsp;</span>
                </div>
                <div class="detail-row">
                    <span class="k">No. of Items</span>
                    <span class="v"><?= count($items) ?></span>
                </div>
            </div>
        </div>

        <!-- ITEMS TABLE -->
        <?php if ($hasItemWise): ?>
            <div class="items-wrap">
                <table class="items">
                    <colgroup>
                        <col style="width: 22px;">
                        <col>
                        <col style="width: 55px;">
                        <col style="width: 45px;">
                        <col style="width: 65px;">
                        <col style="width: 80px;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Description of Goods</th>
                            <th>Qty</th>
                            <th>Unit</th>
                            <th>Rate</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="6" class="center" style="padding: 8px;">No items recorded.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $i => $it):
                                $qty  = (int) $it['quantity'];
                                $isIW = ((int) $it['freight_mode'] === 1);
                                $rate = $isIW ? (float) $it['rate']   : 0;
                                $amt  = $isIW ? (float) $it['amount'] : 0;
                            ?>
                                <tr>
                                    <td class="center"><?= $i + 1 ?></td>
                                    <td><?= htmlspecialchars($it['inventory_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="num"><?= $qty ?></td>
                                    <td class="center"><?= htmlspecialchars($it['unit'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="num"><?= $isIW ? number_format($rate, 2) : '—' ?></td>
                                    <td class="num"><?= $isIW ? number_format($amt, 2)  : '—' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" style="text-align: right;">Total Freight</td>
                            <td class="num"><?= number_format($totalFreight, 2) ?></td>
                        </tr>
                        <?php if ($totalExtra > 0.001): ?>
                        <tr>
                            <td colspan="5" style="text-align: right;">Extra Charges</td>
                            <td class="num"><?= number_format($totalExtra, 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td colspan="5" style="text-align: right;">Received</td>
                            <td class="num"><?= number_format($totalReceived, 2) ?></td>
                        </tr>
                        <tr>
                            <td colspan="5" style="text-align: right;">Balance Due</td>
                            <td class="num"><?= number_format($totalDue, 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <div class="items-wrap">
                <table class="items">
                    <colgroup>
                        <col style="width: 26px;">
                        <col>
                        <col style="width: 70px;">
                        <col style="width: 55px;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Description of Goods</th>
                            <th>Qty</th>
                            <th>Unit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="4" class="center" style="padding: 8px;">No items recorded.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $i => $it):
                                $qty = (int) $it['quantity'];
                            ?>
                                <tr>
                                    <td class="center"><?= $i + 1 ?></td>
                                    <td><?= htmlspecialchars($it['inventory_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="num"><?= $qty ?></td>
                                    <td class="center"><?= htmlspecialchars($it['unit'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" style="text-align: right;">Total Freight</td>
                            <td class="num"><?= number_format($totalFreight, 2) ?></td>
                        </tr>
                        <?php if ($totalExtra > 0.001): ?>
                        <tr>
                            <td colspan="3" style="text-align: right;">Extra Charges</td>
                            <td class="num"><?= number_format($totalExtra, 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td colspan="3" style="text-align: right;">Received</td>
                            <td class="num"><?= number_format($totalReceived, 2) ?></td>
                        </tr>
                        <tr>
                            <td colspan="3" style="text-align: right;">Balance Due</td>
                            <td class="num"><?= number_format($totalDue, 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>

        <!-- DECLARATION -->
        <div class="declaration">
            <strong>Declaration:</strong>
            The goods described above are booked for transport as per the terms and conditions of
            <?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?>.
            Consignor declares that the contents and value of the consignment are correct.
        </div>

        <!-- SIGNATURES -->
        <div class="signatures">
            <div class="sig-block">
                <div class="sig-line"></div>
                <div class="sig-label">Consignor</div>
                <div class="sig-sub">Signature</div>
            </div>
            <div class="sig-block">
                <div class="sig-line"></div>
                <div class="sig-label">Driver / Agent</div>
                <div class="sig-sub">Signature</div>
            </div>
            <div class="sig-block">
                <div class="sig-line"></div>
                <div class="sig-label">Consignee</div>
                <div class="sig-sub">Received in good condition</div>
            </div>
        </div>

        <!-- FOOTER -->
        <div class="page-footer">
            <span>For <?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></span>
            <span>Printed: <?= date('d-m-Y H:i') ?></span>
        </div>

    </div>

    <script>
        (function() {
            var params = new URLSearchParams(window.location.search);
            if (params.get('print') === '1') {
                window.print();
            }
        })();
    </script>

</body>

</html>