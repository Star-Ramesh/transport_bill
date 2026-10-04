<?php
/* =========================================================
   consignment-print.php
   Prints a party-to-party consignment (no trip).
   Usage: consignment-print.php?id=N
   Format: A5 landscape
   ========================================================= */

include 'constant.php';
include 'session.php';

requirePermission('consignment.view');

$consignmentId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($consignmentId <= 0) {
    header("Location: consignments-list.php");
    exit;
}

/* Branch scope */
$canSeeAll = hasPermission('trip.view.all');
$myBranch  = (int) ($_SESSION['branch_id'] ?? 0);

$branchCheck = '';
if (!$canSeeAll) {
    $branchCheck = $myBranch > 0 ? " AND branch_id = $myBranch" : " AND 1=0";
}

/* Load consignment */
$resC = mysqli_query($conn, "SELECT * FROM consignment WHERE id = $consignmentId $branchCheck LIMIT 1");
if (!$resC || mysqli_num_rows($resC) !== 1) {
    header("Location: consignments-list.php");
    exit;
}
$consignment = mysqli_fetch_assoc($resC);

/* Lookup consignor */
$consignorName = $consignorTrade = $consignorGstin = $consignorAddress = '';
$consignorCity = $consignorState = $consignorPincode = $consignorPhone = '';

$cid = (int) $consignment['consignor_party_id'];
if ($cid > 0) {
    $r = mysqli_query($conn, "SELECT legal_name, trade_name, gstin, address, city, state, pincode, phone FROM party WHERE id = $cid LIMIT 1");
    if ($r && mysqli_num_rows($r) === 1) {
        $p = mysqli_fetch_assoc($r);
        $consignorName    = $p['legal_name'] ?? '';
        $consignorTrade   = $p['trade_name'] ?? '';
        $consignorGstin   = $p['gstin']      ?? '';
        $consignorAddress = $p['address']    ?? '';
        $consignorCity    = $p['city']       ?? '';
        $consignorState   = $p['state']      ?? '';
        $consignorPincode = $p['pincode']    ?? '';
        $consignorPhone   = $p['phone']      ?? '';
    }
}

/* Lookup consignee */
$consigneeName = $consigneeTrade = $consigneeGstin = $consigneeAddress = '';
$consigneeCity = $consigneeState = $consigneePincode = $consigneePhone = '';

$rid = (int) $consignment['consignee_party_id'];
if ($rid > 0) {
    $r = mysqli_query($conn, "SELECT legal_name, trade_name, gstin, address, city, state, pincode, phone FROM party WHERE id = $rid LIMIT 1");
    if ($r && mysqli_num_rows($r) === 1) {
        $p = mysqli_fetch_assoc($r);
        $consigneeName    = $p['legal_name'] ?? '';
        $consigneeTrade   = $p['trade_name'] ?? '';
        $consigneeGstin   = $p['gstin']      ?? '';
        $consigneeAddress = $p['address']    ?? '';
        $consigneeCity    = $p['city']       ?? '';
        $consigneeState   = $p['state']      ?? '';
        $consigneePincode = $p['pincode']    ?? '';
        $consigneePhone   = $p['phone']      ?? '';
    }
}

/* Load items */
$items = [];
$resI = mysqli_query($conn, "SELECT inventory_name, unit, quantity, rate, amount FROM consignment_inventory WHERE consignment_id = $consignmentId AND active = 1 ORDER BY id ASC");
if ($resI) {
    while ($it = mysqli_fetch_assoc($resI)) {
        $items[] = $it;
    }
}

$totalAmount = 0;
foreach ($items as $it) {
    $totalAmount += (float) $it['amount'];
}

/* Company header */
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
    <title>Consignment — <?= htmlspecialchars($consignment['consignment_no'], ENT_QUOTES, 'UTF-8') ?></title>
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

        .doc-gstin {
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

        .meta-strip > div {
            flex: 1 1 0;
            padding: 2px 5px;
            border-right: 1px solid #000;
            min-width: 0;
        }

        .meta-strip > div:last-child { border-right: 0; }

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
            <a href="consignments-list.php" class="btn secondary">
                &larr; Back to List
            </a>
            <button type="button" class="btn" style="margin-left: 8px;" onclick="window.print();">
                Print Consignment
            </button>
        </div>
        <div class="hint">
            Paper: A5 landscape · <?= htmlspecialchars($consignment['consignment_no'], ENT_QUOTES, 'UTF-8') ?>
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
                <div class="doc-gstin">
                    GSTIN: <?= htmlspecialchars($companyGstin, ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
        </div>

        <!-- META STRIP -->
        <div class="meta-strip">
            <div>
                <span class="label">Consignment No</span>
                <span class="value"><?= htmlspecialchars($consignment['consignment_no'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div>
                <span class="label">Date</span>
                <span class="value"><?= date('d-m-Y', strtotime($consignment['consignment_date'])) ?></span>
            </div>
            <div>
                <span class="label">Items</span>
                <span class="value"><?= count($items) ?></span>
            </div>
            <div>
                <span class="label">Total Amount</span>
                <span class="value">Rs. <?= number_format($totalAmount, 2) ?></span>
            </div>
        </div>

        <!-- CONSIGNOR / CONSIGNEE -->
        <div class="addr-row">
            <div class="addr-block">
                <div class="addr-title">Consignor</div>
                <?php if ($consignorName !== ''): ?>
                    <div class="addr-name"><?= htmlspecialchars($consignorName, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php if ($consignorTrade !== ''): ?>
                        <div class="addr-line"><?= htmlspecialchars($consignorTrade, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                    <?php $cAddr = array_filter([$consignorAddress, $consignorCity, $consignorState, $consignorPincode]); ?>
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
                    <div class="addr-line">—</div>
                <?php endif; ?>
            </div>

            <div class="addr-block">
                <div class="addr-title">Consignee</div>
                <?php if ($consigneeName !== ''): ?>
                    <div class="addr-name"><?= htmlspecialchars($consigneeName, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php if ($consigneeTrade !== ''): ?>
                        <div class="addr-line"><?= htmlspecialchars($consigneeTrade, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                    <?php $rcAddr = array_filter([$consigneeAddress, $consigneeCity, $consigneeState, $consigneePincode]); ?>
                    <?php if (!empty($rcAddr)): ?>
                        <div class="addr-line"><?= htmlspecialchars(implode(', ', $rcAddr), ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                    <?php if ($consigneePhone !== ''): ?>
                        <div class="addr-line"><strong>Phone:</strong> <?= htmlspecialchars($consigneePhone, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                    <?php if ($consigneeGstin !== ''): ?>
                        <div class="addr-line"><strong>GSTIN:</strong> <?= htmlspecialchars($consigneeGstin, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="addr-line">—</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ITEMS TABLE -->
        <div class="items-wrap">
            <table class="items">
                <colgroup>
                    <col style="width: 22px;">
                    <col>
                    <col style="width: 60px;">
                    <col style="width: 50px;">
                    <col style="width: 70px;">
                    <col style="width: 85px;">
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
                        <?php foreach ($items as $i => $it): ?>
                            <tr>
                                <td class="center"><?= $i + 1 ?></td>
                                <td><?= htmlspecialchars($it['inventory_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="num"><?= (int) $it['quantity'] ?></td>
                                <td class="center"><?= htmlspecialchars($it['unit'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="num"><?= number_format((float) $it['rate'], 2) ?></td>
                                <td class="num"><?= number_format((float) $it['amount'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5" style="text-align: right;">Total Amount</td>
                        <td class="num"><?= number_format($totalAmount, 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <?php if (!empty($consignment['notes'])): ?>
            <div class="declaration">
                <strong>Notes:</strong>
                <?= htmlspecialchars($consignment['notes'], ENT_QUOTES, 'UTF-8') ?>
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