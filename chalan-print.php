<?php
/* =========================================================
   chalan-print.php
   ---------------------------------------------------------
   Renders a printable Lorry Chalan for one trip.
   Usage:  chalan-print.php?id=N
   Format: A5 landscape
   ========================================================= */

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
                t.id, t.trip_no, t.start_date, t.end_date, t.status,
                t.distance_km, t.notes,
                l.lorry_number, l.lorry_type, l.owner_name,
                l.address AS lorry_address,
                d.driver_name, d.phone AS driver_phone, d.license_no AS driver_license,
                bf.branch_name AS from_branch_name,
                bf.branch_code AS from_branch_code,
                bf.address     AS from_branch_address,
                bf.city        AS from_branch_city,
                bf.state       AS from_branch_state,
                bf.phone       AS from_branch_phone,
                bt.branch_name AS to_branch_name,
                bt.branch_code AS to_branch_code,
                bt.address     AS to_branch_address,
                bt.city        AS to_branch_city,
                bt.state       AS to_branch_state
            FROM trip t
            LEFT JOIN lorry  l  ON l.id  = t.lorry_id
            LEFT JOIN driver d  ON d.id  = t.driver_id
            LEFT JOIN branch bf ON bf.id = t.from_branch_id
            LEFT JOIN branch bt ON bt.id = t.to_branch_id
            WHERE t.id = $tripId $branchCheck
            LIMIT 1";

$resTrip = mysqli_query($conn, $sqlTrip);
if (!$resTrip || mysqli_num_rows($resTrip) !== 1) {
    header("Location: trips-list.php");
    exit;
}

$trip = mysqli_fetch_assoc($resTrip);

/* =========================================================
   LOAD PARTIES + ITEMS (flat list for the table)
   ========================================================= */
$items = [];
$totalFreight = 0;
$totalAdvance = 0;
$partyCount = 0;

$resParties = mysqli_query(
    $conn,
    "SELECT tp.id, tp.freight_amount, tp.advance_paid,
            p.legal_name, p.trade_name, p.gstin
     FROM trip_party tp
     LEFT JOIN party p ON p.id = tp.party_id
     WHERE tp.trip_id = $tripId
     ORDER BY tp.id ASC"
);

if ($resParties) {
    while ($p = mysqli_fetch_assoc($resParties)) {
        $partyCount++;
        $tpId = (int) $p['id'];

        $partyName = $p['legal_name'] ?: '—';
        if (!empty($p['trade_name'])) {
            $partyName .= ' (' . $p['trade_name'] . ')';
        }

        $totalFreight += (float) $p['freight_amount'];
        $totalAdvance += (float) $p['advance_paid'];

        $resItems = mysqli_query(
            $conn,
            "SELECT item_name, unit, quantity, rate, amount
             FROM trip_item
             WHERE trip_party_id = $tpId
             ORDER BY id ASC"
        );
        if ($resItems) {
            while ($it = mysqli_fetch_assoc($resItems)) {
                $it['party_name'] = $partyName;
                $items[] = $it;
            }
        }
    }
}

$totalDue     = $totalFreight - $totalAdvance;
$valueOfGoods = 0;
foreach ($items as $it) {
    $valueOfGoods += (float) $it['amount'];
}

/* =========================================================
   COMPANY HEADER — edit these lines when details change
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
    <title>Lorry Chalan — <?= htmlspecialchars($trip['trip_no'], ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        /* =========================================================
           PAGE SETUP — A5 landscape
           ========================================================= */
        @page {
            size: A5 landscape;
            margin: 6mm;
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        html,
        body {
            margin: 0;
            padding: 0;
        }

        body {
            font-family: "Nunito", Arial, Helvetica, sans-serif;
            background: #e8eaf0;
            color: #000;
            font-size: 9.5px;
            line-height: 1.25;
        }

        /* =========================================================
           PRINT TOOLBAR (screen only)
           ========================================================= */
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

        .print-toolbar .btn.secondary {
            background: #fff;
            color: #4e73df;
        }

        .print-toolbar .hint {
            color: #6e707e;
            font-size: 12px;
        }

        /* =========================================================
           PAGE CANVAS
           A5 landscape = 210mm x 148mm
           ========================================================= */
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

        /* =========================================================
           HEADER
           ========================================================= */
        .header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            border-bottom: 1.5px solid #000;
            padding-bottom: 3px;
            margin-bottom: 4px;
            gap: 6px;
        }

        .header-left {
            flex: 1 1 auto;
            min-width: 0;
        }

        .company-name {
            font-size: 15px;
            font-weight: 800;
            letter-spacing: 0.4px;
            margin: 0;
            text-transform: uppercase;
            line-height: 1.1;
        }

        .company-tagline {
            font-size: 8.5px;
            font-weight: 700;
            margin: 1px 0 0;
        }

        .company-area {
            font-size: 7.5px;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin: 0;
        }

        .company-contact {
            font-size: 8px;
            margin: 2px 0 0;
            line-height: 1.3;
        }

        .header-right {
            flex: 0 0 auto;
            text-align: right;
        }

        .chalan-title {
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 1px;
            margin: 0 0 2px;
            text-transform: uppercase;
        }

        .chalan-gstin {
            font-size: 8px;
            font-weight: 700;
            border: 1px solid #000;
            padding: 1px 5px;
            display: inline-block;
            white-space: nowrap;
        }

        /* =========================================================
           META STRIP
           ========================================================= */
        .meta-strip {
            display: flex;
            border: 1px solid #000;
            margin-bottom: 4px;
        }

        .meta-strip>div {
            flex: 1 1 0;
            padding: 2px 5px;
            border-right: 1px solid #000;
            min-width: 0;
        }

        .meta-strip>div:last-child {
            border-right: 0;
        }

        .meta-strip .label {
            font-weight: 700;
            text-transform: uppercase;
            font-size: 7px;
            letter-spacing: 0.3px;
            display: block;
            color: #333;
        }

        .meta-strip .value {
            font-weight: 700;
            font-size: 10px;
            font-family: "SFMono-Regular", Menlo, Consolas, monospace;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* =========================================================
           DETAIL BLOCK
           ========================================================= */
        .detail-block {
            display: flex;
            border: 1px solid #000;
            margin-bottom: 4px;
        }

        .detail-col {
            flex: 1 1 0;
            padding: 3px 6px;
            border-right: 1px solid #000;
            min-width: 0;
        }

        .detail-col:last-child {
            border-right: 0;
        }

        .detail-row {
            display: flex;
            font-size: 9px;
            margin-bottom: 1px;
            align-items: baseline;
            gap: 4px;
        }

        .detail-row:last-child {
            margin-bottom: 0;
        }

        .detail-row .k {
            flex: 0 0 40%;
            font-weight: 700;
            font-size: 8px;
            text-transform: uppercase;
            color: #222;
            letter-spacing: 0.2px;
        }

        .detail-row .v {
            flex: 1 1 auto;
            border-bottom: 1px solid #999;
            min-height: 12px;
            font-weight: 600;
            padding: 0 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* =========================================================
           ITEMS TABLE
           ========================================================= */
        .items-wrap {
            flex: 1 1 auto;
            margin-bottom: 4px;
            overflow: hidden;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            table-layout: fixed;
        }

        table.items th,
        table.items td {
            border: 1px solid #000;
            padding: 2px 4px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        table.items thead th {
            background: #efefef;
            font-weight: 700;
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: 0.2px;
            text-align: center;
            padding: 3px 4px;
        }

        table.items td.num {
            text-align: right;
            font-family: "SFMono-Regular", Menlo, Consolas, monospace;
            white-space: nowrap;
        }

        table.items td.center {
            text-align: center;
        }

        table.items td.party {
            font-weight: 600;
            font-size: 8.5px;
        }

        table.items tfoot td {
            font-weight: 700;
            background: #f7f7f7;
            font-size: 9px;
            padding: 2px 5px;
        }

        /* =========================================================
           TAX ROW
           ========================================================= */
        table.tax {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            margin-bottom: 4px;
        }

        table.tax th,
        table.tax td {
            border: 1px solid #000;
            padding: 2px 4px;
        }

        table.tax th {
            font-size: 7.5px;
            text-transform: uppercase;
            background: #efefef;
            text-align: left;
            font-weight: 700;
            letter-spacing: 0.2px;
        }

        table.tax td {
            height: 14px;
        }

        /* =========================================================
           PAYMENT BAND
           ========================================================= */
        .payment-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 8.5px;
            padding: 3px 4px;
            border: 1px solid #000;
            margin-bottom: 4px;
        }

        .payment-line .paid-box {
            display: flex;
            gap: 14px;
            font-weight: 700;
            letter-spacing: 0.4px;
        }

        .payment-line .paid-box .box {
            display: inline-block;
            width: 10px;
            height: 10px;
            border: 1px solid #000;
            vertical-align: middle;
            margin-right: 3px;
        }

        .payment-line .tax-note {
            font-size: 8px;
        }

        /* =========================================================
           SIGNATURES
           ========================================================= */
        .signatures {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: auto;
            padding-top: 8mm;
        }

        .sig-block {
            flex: 1 1 0;
            text-align: center;
            padding: 0 4px;
        }

        .sig-line {
            border-top: 1px solid #000;
            height: 0;
            margin-bottom: 3px;
        }

        .sig-label {
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            font-size: 8px;
        }

        .sig-sub {
            font-size: 7px;
            color: #333;
            margin-top: 1px;
        }

        /* =========================================================
           FOOTER
           ========================================================= */
        .page-footer {
            margin-top: 3px;
            padding-top: 3px;
            border-top: 1px dotted #888;
            font-size: 7.5px;
            color: #444;
            display: flex;
            justify-content: space-between;
        }

        /* =========================================================
           PRINT OVERRIDES
           ========================================================= */
        @media print {
            body {
                background: #fff;
                font-size: 9.5px;
            }

            .print-toolbar {
                display: none !important;
            }

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

    <!-- =========================================================
         PRINT TOOLBAR (screen only)
         ========================================================= -->
    <div class="print-toolbar">
        <div>
            <a href="trip-view.php?id=<?= (int) $trip['id'] ?>" class="btn secondary">
                &larr; Back to Trip
            </a>
            <button type="button" class="btn" style="margin-left: 8px;" onclick="window.print();">
                Print Chalan
            </button>
        </div>
        <div class="hint">
            Paper: A5 landscape · Trip <?= htmlspecialchars($trip['trip_no'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    </div>

    <!-- =========================================================
         THE CHALAN PAGE
         ========================================================= -->
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
                <div class="chalan-title">Lorry Chalan</div>
                <div class="chalan-gstin">
                    GSTIN: <?= htmlspecialchars($companyGstin, ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
        </div>

        <!-- META STRIP -->
        <div class="meta-strip">
            <div>
                <span class="label">L.R. No</span>
                <span class="value"><?= htmlspecialchars($trip['trip_no'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div>
                <span class="label">Date</span>
                <span class="value"><?= date('d-m-Y', strtotime($trip['start_date'])) ?></span>
            </div>
            <div>
                <span class="label">From</span>
                <span class="value"><?= htmlspecialchars($trip['from_branch_name'] ?: '—', ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div>
                <span class="label">To</span>
                <span class="value"><?= htmlspecialchars($trip['to_branch_name'] ?: '—', ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>

        <!-- DETAILS -->
        <div class="detail-block">

            <!-- Left: lorry / driver -->
            <div class="detail-col">
                <div class="detail-row">
                    <span class="k">Vehicle No</span>
                    <span class="v"><?= htmlspecialchars($trip['lorry_number'] ?: '', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="detail-row">
                    <span class="k">Chassis No</span>
                    <span class="v">&nbsp;</span>
                </div>
                <div class="detail-row">
                    <span class="k">Driver's Name</span>
                    <span class="v"><?= htmlspecialchars($trip['driver_name'] ?: '', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="detail-row">
                    <span class="k">Driver's L. No</span>
                    <span class="v"><?= htmlspecialchars($trip['driver_license'] ?: '', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="detail-row">
                    <span class="k">Owner's Name</span>
                    <span class="v"><?= htmlspecialchars($trip['owner_name'] ?: '', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="detail-row">
                    <span class="k">Mob</span>
                    <span class="v"><?= htmlspecialchars($trip['driver_phone'] ?: '', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>

            <!-- Right: goods -->
            <div class="detail-col">
                <div class="detail-row">
                    <span class="k">Value of Goods Rs.</span>
                    <span class="v"><?= number_format($valueOfGoods, 2) ?></span>
                </div>
                <div class="detail-row">
                    <span class="k">E-Way Bill No</span>
                    <span class="v">&nbsp;</span>
                </div>
                <div class="detail-row">
                    <span class="k">Invoice No</span>
                    <span class="v">&nbsp;</span>
                </div>
                <div class="detail-row">
                    <span class="k">No. of Parties</span>
                    <span class="v"><?= (int) $partyCount ?></span>
                </div>
                <div class="detail-row">
                    <span class="k">No. of Items</span>
                    <span class="v"><?= count($items) ?></span>
                </div>
                <div class="detail-row">
                    <span class="k">Distance (km)</span>
                    <span class="v">
                        <?php if (!empty($trip['distance_km']) && (float) $trip['distance_km'] > 0): ?>
                            <?= number_format((float) $trip['distance_km'], 2) ?>
                            <?php else: ?>&nbsp;<?php endif; ?>
                    </span>
                </div>
            </div>

        </div>

        <!-- ITEMS TABLE -->
        <div class="items-wrap">
            <table class="items">
                <colgroup>
                    <col style="width: 22px;">
                    <col style="width: 105px;">
                    <col>
                    <col style="width: 55px;">
                    <col style="width: 45px;">
                    <col style="width: 62px;">
                    <col style="width: 78px;">
                </colgroup>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Party</th>
                        <th>Particulars</th>
                        <th>Qty</th>
                        <th>Unit</th>
                        <th>Rate</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="7" class="center" style="padding: 10px;">No items recorded.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $i => $it):
                            $qty = (float) $it['quantity'];
                            $qtyFormatted = rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.');
                        ?>
                            <tr>
                                <td class="center"><?= $i + 1 ?></td>
                                <td class="party"><?= htmlspecialchars($it['party_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($it['item_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="num"><?= $qtyFormatted ?></td>
                                <td class="center"><?= htmlspecialchars($it['unit'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="num"><?= number_format((float) $it['rate'], 2) ?></td>
                                <td class="num"><?= number_format((float) $it['amount'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6" style="text-align: right;">Total Freight (All Parties)</td>
                        <td class="num"><?= number_format($totalFreight, 2) ?></td>
                    </tr>
                    <tr>
                        <td colspan="6" style="text-align: right;">Advance Paid</td>
                        <td class="num"><?= number_format($totalAdvance, 2) ?></td>
                    </tr>
                    <tr>
                        <td colspan="6" style="text-align: right;">Balance Due</td>
                        <td class="num"><?= number_format($totalDue, 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- TAX ROW (blank, handwriting) -->
        <table class="tax">
            <tr>
                <th style="width: 25%;">CGST 2.5%</th>
                <th style="width: 25%;">SGST 2.5%</th>
                <th style="width: 25%;">IGST 5%</th>
                <th style="width: 25%;">Total Rs.</th>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
        </table>

        <!-- PAYMENT BAND -->
        <div class="payment-line">
            <div class="paid-box">
                <span><span class="box"></span>PAID</span>
                <span><span class="box"></span>TO PAY</span>
            </div>
            <div class="tax-note">
                Tax Payable by Consignor / Consignee / Carrier
            </div>
        </div>

        <!-- SIGNATURES -->
        <div class="signatures">
            <div class="sig-block">
                <div class="sig-line"></div>
                <div class="sig-label">Driver / Agent</div>
                <div class="sig-sub">Signature</div>
            </div>
            <div class="sig-block">
                <div class="sig-line"></div>
                <div class="sig-label">Lorry Owner</div>
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

    <!-- Auto-print when ?print=1 -->
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