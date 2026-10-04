<?php
/* =========================================================
   expenses-report.php
   Business-style expenses report.
   Filters: date range, category, supplier, branch.
   CSV export. No JOINs — single-table SELECT + PHP enrichment.
   ========================================================= */

include 'session.php';
include 'constant.php';

requirePermission('report.view');

$isAdminUser = isAdmin();
$myBranch    = (int) ($_SESSION['branch_id'] ?? 0);
$canSeeAll   = $isAdminUser || hasPermission('trip.view.all');

/* Filters */
$fromDate  = isset($_GET['from'])     ? trim($_GET['from'])     : '';
$toDate    = isset($_GET['to'])       ? trim($_GET['to'])       : '';
$categoryF = isset($_GET['category']) ? trim($_GET['category']) : '';
$supplierF = isset($_GET['supplier']) ? (int) $_GET['supplier'] : 0;
$branchF   = isset($_GET['branch'])   ? (int) $_GET['branch']   : 0;

if ($fromDate !== '') {
    $d = DateTime::createFromFormat('Y-m-d', $fromDate);
    if (!$d || $d->format('Y-m-d') !== $fromDate) $fromDate = '';
}
if ($toDate !== '') {
    $d = DateTime::createFromFormat('Y-m-d', $toDate);
    if (!$d || $d->format('Y-m-d') !== $toDate) $toDate = '';
}

/* Dropdown data */
$branches = [];
$resB = mysqli_query($conn, "SELECT id, branch_name FROM branch WHERE active = 1 ORDER BY branch_name");
if ($resB) while ($b = mysqli_fetch_assoc($resB)) $branches[] = $b;

$suppliers = [];
$resS = mysqli_query($conn, "SELECT id, supplier_name FROM supplier WHERE active = 1 ORDER BY supplier_name");
if ($resS) while ($s = mysqli_fetch_assoc($resS)) $suppliers[] = $s;

$categories = [
    'Diesel', 'Toll', 'Driver Food', 'Mechanic', 'Spare Parts',
    'Loading / Unloading', 'Parking', 'Police / RTO',
    'Truck Hire Charge', 'Other',
];

/* WHERE */
$where = ["active = 1"];

if (!$canSeeAll) {
    $where[] = $myBranch > 0 ? "branch_id = $myBranch" : "1=0";
} elseif ($branchF > 0) {
    $where[] = "branch_id = $branchF";
}

if ($fromDate !== '') {
    $fd = mysqli_real_escape_string($conn, $fromDate);
    $where[] = "expense_date >= '$fd'";
}
if ($toDate !== '') {
    $td = mysqli_real_escape_string($conn, $toDate);
    $where[] = "expense_date <= '$td'";
}
if ($categoryF !== '') {
    $cf = mysqli_real_escape_string($conn, $categoryF);
    $where[] = "category = '$cf'";
}
if ($supplierF > 0) {
    $where[] = "supplier_id = $supplierF";
}

$whereSql = implode(' AND ', $where);

/* Fetch expenses */
$sql = "SELECT id, expense_date, category, amount, notes,
               supplier_id, trip_id, branch_id, created_by, created_at
        FROM expense
        WHERE $whereSql
        ORDER BY expense_date DESC, id DESC";

$result = mysqli_query($conn, $sql);
if (!$result) die("Query failed: " . mysqli_error($conn));

$expenses = [];
while ($row = mysqli_fetch_assoc($result)) {
    $expenses[] = $row;
}

/* Enrich */
$supplierNames = [];
$resSup = mysqli_query($conn, "SELECT id, supplier_name FROM supplier");
if ($resSup) while ($s = mysqli_fetch_assoc($resSup)) $supplierNames[(int)$s['id']] = $s['supplier_name'];

$branchNames = [];
$resBr = mysqli_query($conn, "SELECT id, branch_name FROM branch");
if ($resBr) while ($b = mysqli_fetch_assoc($resBr)) $branchNames[(int)$b['id']] = $b['branch_name'];

$userNames = [];
$resU = mysqli_query($conn, "SELECT id, full_name, username FROM `user`");
if ($resU) while ($u = mysqli_fetch_assoc($resU)) {
    $userNames[(int)$u['id']] = $u['full_name'] ?: $u['username'];
}

$tripInfo = [];
$tripIds  = [];
foreach ($expenses as $e) {
    $tid = (int) $e['trip_id'];
    if ($tid > 0) $tripIds[$tid] = true;
}
if (!empty($tripIds)) {
    $inList = implode(',', array_keys($tripIds));
    $resT = mysqli_query(
        $conn,
        "SELECT id, trip_no, source, destination FROM trip WHERE id IN ($inList)"
    );
    if ($resT) {
        while ($t = mysqli_fetch_assoc($resT)) {
            $tripInfo[(int)$t['id']] = $t;
        }
    }
}

foreach ($expenses as &$e) {
    $sid = (int) $e['supplier_id'];
    $bid = (int) $e['branch_id'];
    $uid = (int) $e['created_by'];
    $tid = (int) $e['trip_id'];

    $e['supplier_name']   = $sid > 0 ? ($supplierNames[$sid] ?? '') : '';
    $e['branch_name']     = $bid > 0 ? ($branchNames[$bid]   ?? '') : '';
    $e['created_by_name'] = $uid > 0 ? ($userNames[$uid]     ?? '') : '';

    $e['trip_no']          = '';
    $e['from_branch_name'] = '';
    $e['to_branch_name']   = '';

    if ($tid > 0 && isset($tripInfo[$tid])) {
        $e['trip_no']          = $tripInfo[$tid]['trip_no']    ?? '';
        $e['from_branch_name'] = $tripInfo[$tid]['source']      ?? '';
        $e['to_branch_name']   = $tripInfo[$tid]['destination'] ?? '';
    }
}
unset($e);

/* Aggregates */
$totalAmount = 0;
$byCategory  = [];
$bySupplier  = [];

foreach ($expenses as $row) {
    $amt = (float) $row['amount'];
    $totalAmount += $amt;

    $cat = $row['category'] ?: 'Other';
    if (!isset($byCategory[$cat])) $byCategory[$cat] = 0;
    $byCategory[$cat] += $amt;

    $sup = $row['supplier_name'] ?: '—';
    if (!isset($bySupplier[$sup])) $bySupplier[$sup] = 0;
    $bySupplier[$sup] += $amt;
}

$expenseCount = count($expenses);

arsort($byCategory);
$topCategory    = $expenseCount ? key($byCategory) : '—';
$topCategoryAmt = $expenseCount ? current($byCategory) : 0;

arsort($bySupplier);
$topSupplier    = $expenseCount ? key($bySupplier) : '—';
$topSupplierAmt = $expenseCount ? current($bySupplier) : 0;

$avgAmount = $expenseCount > 0 ? ($totalAmount / $expenseCount) : 0;

/* CSV export */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="expenses-report-' . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');

    fputcsv($out, ['#', 'Date', 'Category', 'Supplier', 'Amount',
                   'Trip No', 'Route', 'Branch', 'Notes', 'Created By', 'Created At']);

    $i = 0;
    foreach ($expenses as $row) {
        $i++;

        $route = trim(
            ($row['from_branch_name'] ?: '') .
            ($row['from_branch_name'] && $row['to_branch_name'] ? ' -> ' : '') .
            ($row['to_branch_name'] ?: '')
        );

        fputcsv($out, [
            $i,
            date('d-m-Y', strtotime($row['expense_date'])),
            $row['category'],
            $row['supplier_name'] ?: '',
            number_format((float) $row['amount'], 2, '.', ''),
            $row['trip_no'] ?: '',
            $route,
            $row['branch_name'] ?: '',
            $row['notes'] ?: '',
            $row['created_by_name'] ?: '',
            $row['created_at'] ? date('d-m-Y H:i', strtotime($row['created_at'])) : '',
        ]);
    }

    fclose($out);
    exit;
}

$pageTitle = 'Expenses Report | Billing Portal';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= $pageTitle ?></title>
    <?php include 'layout/header.php'; ?>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap4.min.css">
    <style>
        :root {
            --er-border: #e6e9f0;
            --er-muted: #858796;
            --er-text: #1f2333;
            --er-shadow: 0 1px 3px rgba(30, 34, 51, .04);
            --er-shadow-hover: 0 4px 14px rgba(30, 34, 51, .08);
        }

        .er-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        .er-head-left {
            display: flex;
            align-items: center;
            gap: .85rem;
        }

        .er-head-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            box-shadow: 0 6px 14px rgba(78, 115, 223, .22);
        }

        .er-head h1 {
            margin: 0;
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--er-text);
            line-height: 1.2;
        }

        .er-head .er-sub {
            color: var(--er-muted);
            font-size: .8rem;
            margin-top: .15rem;
        }

        .er-head .er-sub strong { color: #3a3b45; }

        .er-export {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            background: #fff;
            border: 1px solid #d1d3e2;
            color: #3a3b45;
            font-weight: 600;
            font-size: .83rem;
            padding: .5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            transition: all .15s ease;
        }

        .er-export:hover {
            background: #f5f7fb;
            border-color: #b7b9cc;
            text-decoration: none;
            color: #2e3039;
        }

        /* KPI */
        .er-kpis {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        @media (max-width: 991.98px) { .er-kpis { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 575.98px) { .er-kpis { grid-template-columns: 1fr; } }

        .er-kpi {
            background: #fff;
            border: 1px solid var(--er-border);
            border-radius: 12px;
            padding: 1rem 1.1rem;
            display: flex;
            align-items: center;
            gap: .9rem;
            transition: box-shadow .15s ease, transform .15s ease;
            position: relative;
            overflow: hidden;
        }

        .er-kpi:hover {
            box-shadow: var(--er-shadow-hover);
            transform: translateY(-1px);
        }

        .er-kpi .kpi-icon-box {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            flex-shrink: 0;
        }

        .er-kpi.primary .kpi-icon-box { background: #eef2ff; color: #3f51b5; }
        .er-kpi.success .kpi-icon-box { background: #d2f4e8; color: #0c7d5b; }
        .er-kpi.warning .kpi-icon-box { background: #fff3cd; color: #9a6d0b; }
        .er-kpi.info    .kpi-icon-box { background: #d7f1f5; color: #1c606a; }

        .er-kpi .kpi-content {
            display: flex;
            flex-direction: column;
            gap: .15rem;
            min-width: 0;
            flex: 1 1 auto;
        }

        .er-kpi .kpi-label {
            font-size: .68rem;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: var(--er-muted);
        }

        .er-kpi .kpi-value {
            font-size: 1.35rem;
            font-weight: 800;
            line-height: 1.15;
            color: var(--er-text);
            font-family: "SFMono-Regular", Menlo, Consolas, monospace;
            letter-spacing: -.02em;
            overflow-wrap: break-word;
            word-break: break-word;
        }

        .er-kpi .kpi-value.small { font-size: 1rem; }
        .er-kpi .kpi-sub { font-size: .75rem; color: var(--er-muted); }

        /* Filters */
        .er-filters {
            background: #fff;
            border: 1px solid var(--er-border);
            border-radius: 12px;
            padding: .85rem 1rem;
            margin-bottom: 1.25rem;
        }

        .er-filters .filter-row {
            display: grid;
            grid-template-columns: 1.2fr 1.2fr 1.2fr 1.2fr 1.2fr auto;
            gap: .65rem;
            align-items: end;
        }

        @media (max-width: 991.98px) {
            .er-filters .filter-row { grid-template-columns: repeat(2, 1fr); }
            .er-filters .filter-actions {
                grid-column: 1 / -1;
                justify-content: flex-end;
                display: flex;
            }
        }

        @media (max-width: 575.98px) {
            .er-filters .filter-row { grid-template-columns: 1fr; }
        }

        .er-filter-field label {
            display: block;
            font-size: .68rem;
            font-weight: 800;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: var(--er-muted);
            margin-bottom: .3rem;
        }

        .er-filter-field .form-control {
            height: auto;
            padding: .42rem .6rem;
            font-size: .85rem;
            border-radius: 7px;
            border: 1px solid #d1d3e2;
        }

        .er-filters .filter-actions {
            display: flex;
            gap: .4rem;
        }

        .er-filters .filter-actions .btn {
            font-size: .82rem;
            font-weight: 600;
            padding: .45rem .9rem;
            border-radius: 7px;
            white-space: nowrap;
        }

        /* Card */
        .er-card {
            background: #fff;
            border: 1px solid var(--er-border);
            border-radius: 12px;
            box-shadow: var(--er-shadow);
            overflow: hidden;
        }

        .er-card-head {
            padding: .9rem 1.15rem;
            border-bottom: 1px solid var(--er-border);
            background: #fbfcff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: .5rem;
        }

        .er-card-head h2 {
            margin: 0;
            font-size: .95rem;
            font-weight: 800;
            color: var(--er-text);
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .er-card-head h2 i { color: #4e73df; }

        .er-count-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 26px;
            height: 22px;
            padding: 0 .55rem;
            border-radius: 999px;
            background: #eef2ff;
            color: #3f51b5;
            font-size: .7rem;
            font-weight: 800;
            font-family: "SFMono-Regular", Menlo, Consolas, monospace;
        }

        /* Table */
        #expensesTable {
            font-size: .9rem;
            margin: 0 !important;
        }

        #expensesTable thead th {
            font-size: .68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #6e707e;
            background: #f8f9fc;
            padding: .75rem 1rem;
            border-top: 0;
            border-bottom: 1px solid var(--er-border);
            white-space: nowrap;
        }

        #expensesTable tbody td {
            padding: .8rem 1rem;
            vertical-align: middle;
            color: #3a3b45;
            border-top: 1px solid #f1f2f6;
            white-space: nowrap;
            font-size: .88rem;
        }

        #expensesTable tbody tr:hover > td { background-color: #f8f9fc; }

        .cell-date {
            font-family: "SFMono-Regular", Menlo, Consolas, monospace;
            font-size: .84rem;
            color: #3a3b45;
        }

        .cat-pill {
            display: inline-block;
            padding: .2rem .6rem;
            border-radius: 6px;
            font-size: .72rem;
            font-weight: 700;
            background: #f5f7fb;
            color: #5a5c69;
            border: 1px solid #e6e9f0;
            letter-spacing: .01em;
        }

        .supplier-cell { color: #2c2e3e; font-weight: 600; }

        .trip-link {
            color: #4e73df;
            font-weight: 700;
            font-family: "SFMono-Regular", Menlo, Consolas, monospace;
            font-size: .84rem;
            text-decoration: none;
        }

        .trip-link:hover { text-decoration: underline; color: #224abe; }

        .route-text { color: #5a5c69; font-size: .82rem; }
        .route-arrow { color: #b7b9cc; margin: 0 .2rem; }

        .amount-cell {
            text-align: right;
            font-family: "SFMono-Regular", Menlo, Consolas, monospace;
            font-weight: 700;
            color: var(--er-text);
            font-size: .92rem;
        }

        .branch-badge {
            display: inline-block;
            padding: .2rem .55rem;
            border-radius: 5px;
            font-size: .72rem;
            font-weight: 600;
            background-color: #e8fbf4;
            color: #0c7d5b;
            border: 1px solid #c5f0e1;
        }

        .cell-empty { color: #b7b9cc; font-style: normal; }

        .notes-cell {
            max-width: 220px;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #6e707e;
            font-size: .82rem;
        }

        .created-cell { font-size: .82rem; color: #6e707e; }

        .dataTables_wrapper { padding: 0 1.15rem 1rem; }

        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #d1d3e2;
            border-radius: 7px;
            padding: .38rem .65rem;
            font-size: .84rem;
            margin-left: .5rem;
            outline: none;
        }

        .dataTables_wrapper .dataTables_length select {
            border: 1px solid #d1d3e2;
            border-radius: 7px;
            padding: .38rem 1.6rem .38rem .55rem;
            font-size: .84rem;
        }

        .dataTables_wrapper .dataTables_info {
            padding-top: 1rem;
            color: var(--er-muted);
            font-size: .8rem;
        }

        .dataTables_wrapper .dataTables_paginate { padding-top: .65rem; }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #4e73df !important;
            border-color: #4e73df !important;
            color: #fff !important;
            border-radius: 6px !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #eaecf4 !important;
            border-color: #eaecf4 !important;
            color: #4e73df !important;
            border-radius: 6px !important;
        }

        .er-empty {
            text-align: center;
            padding: 3rem 1rem;
        }

        .er-empty i {
            font-size: 2.5rem;
            color: #d1d3e2;
            margin-bottom: .75rem;
        }

        .er-empty h6 {
            color: #3a3b45;
            font-weight: 700;
            margin-bottom: .35rem;
        }

        .er-empty p {
            color: var(--er-muted);
            font-size: .85rem;
            margin: 0;
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

                    <!-- HEADER -->
                    <div class="er-head">
                        <div class="er-head-left">
                            <div class="er-head-icon">
                                <i class="fas fa-file-invoice-dollar"></i>
                            </div>
                            <div>
                                <h1>Expenses Report</h1>
                                <div class="er-sub">
                                    Active expenses only.
                                    <?php if (!$canSeeAll): ?>
                                        Scoped to <strong>your branch</strong>.
                                    <?php else: ?>
                                        <strong>All branches</strong>.
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <a href="expenses-report.php?<?= htmlspecialchars(http_build_query(array_filter([
                            'from'     => $fromDate,
                            'to'       => $toDate,
                            'category' => $categoryF,
                            'supplier' => $supplierF ?: '',
                            'branch'   => $branchF ?: '',
                            'export'   => 'csv',
                        ])), ENT_QUOTES, 'UTF-8') ?>" class="er-export">
                            <i class="fas fa-file-csv text-success"></i>
                            Export CSV
                        </a>
                    </div>

                    <!-- KPI STRIP -->
                    <div class="er-kpis">

                        <div class="er-kpi primary">
                            <div class="kpi-icon-box">
                                <i class="fas fa-rupee-sign"></i>
                            </div>
                            <div class="kpi-content">
                                <div class="kpi-label">Total Expenses</div>
                                <div class="kpi-value">₹ <?= number_format($totalAmount, 2) ?></div>
                                <div class="kpi-sub"><?= number_format($expenseCount) ?> entries</div>
                            </div>
                        </div>

                        <div class="er-kpi info">
                            <div class="kpi-icon-box">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="kpi-content">
                                <div class="kpi-label">Average</div>
                                <div class="kpi-value">₹ <?= number_format($avgAmount, 2) ?></div>
                                <div class="kpi-sub">per entry</div>
                            </div>
                        </div>

                        <div class="er-kpi warning">
                            <div class="kpi-icon-box">
                                <i class="fas fa-tag"></i>
                            </div>
                            <div class="kpi-content">
                                <div class="kpi-label">Top Category</div>
                                <div class="kpi-value small">
                                    <?= htmlspecialchars($topCategory, ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div class="kpi-sub">₹ <?= number_format($topCategoryAmt, 2) ?></div>
                            </div>
                        </div>

                        <div class="er-kpi success">
                            <div class="kpi-icon-box">
                                <i class="fas fa-truck-loading"></i>
                            </div>
                            <div class="kpi-content">
                                <div class="kpi-label">Top Supplier</div>
                                <div class="kpi-value small">
                                    <?= htmlspecialchars($topSupplier, ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div class="kpi-sub">₹ <?= number_format($topSupplierAmt, 2) ?></div>
                            </div>
                        </div>

                    </div>

                    <!-- FILTERS -->
                    <form method="GET" action="expenses-report.php" class="er-filters">
                        <div class="filter-row">

                            <div class="er-filter-field">
                                <label for="from">From Date</label>
                                <input type="date" id="from" name="from" class="form-control"
                                    value="<?= htmlspecialchars($fromDate, ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div class="er-filter-field">
                                <label for="to">To Date</label>
                                <input type="date" id="to" name="to" class="form-control"
                                    value="<?= htmlspecialchars($toDate, ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div class="er-filter-field">
                                <label for="category">Category</label>
                                <select id="category" name="category" class="form-control">
                                    <option value="">All categories</option>
                                    <?php foreach ($categories as $c): ?>
                                        <option value="<?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>"
                                            <?= $categoryF === $c ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="er-filter-field">
                                <label for="supplier">Supplier</label>
                                <select id="supplier" name="supplier" class="form-control">
                                    <option value="">All suppliers</option>
                                    <?php foreach ($suppliers as $s): ?>
                                        <option value="<?= (int) $s['id'] ?>"
                                            <?= $supplierF === (int) $s['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($s['supplier_name'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <?php if ($canSeeAll): ?>
                                <div class="er-filter-field">
                                    <label for="branch">Branch</label>
                                    <select id="branch" name="branch" class="form-control">
                                        <option value="">All branches</option>
                                        <?php foreach ($branches as $b): ?>
                                            <option value="<?= (int) $b['id'] ?>"
                                                <?= $branchF === (int) $b['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($b['branch_name'], ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>

                            <div class="filter-actions">
                                <a href="expenses-report.php" class="btn btn-light border" title="Reset filters">
                                    <i class="fas fa-redo"></i>
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter mr-1"></i> Apply
                                </button>
                            </div>

                        </div>
                    </form>

                    <!-- TABLE CARD -->
                    <div class="er-card">

                        <div class="er-card-head">
                            <h2>
                                <i class="fas fa-list-ul"></i>
                                All Expenses
                                <span class="er-count-badge"><?= number_format($expenseCount) ?></span>
                            </h2>
                        </div>

                        <?php if (empty($expenses)): ?>

                            <div class="er-empty">
                                <i class="fas fa-receipt"></i>
                                <h6>No expenses match your filters</h6>
                                <p>Try changing the date range or clearing filters.</p>
                            </div>

                        <?php else: ?>

                            <div class="table-responsive">
                                <table class="table mb-0" id="expensesTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th width="46">#</th>
                                            <th>Date</th>
                                            <th>Category</th>
                                            <th>Supplier</th>
                                            <th>Trip</th>
                                            <th>Route</th>
                                            <th>Branch</th>
                                            <th>Notes</th>
                                            <th>Created By</th>
                                            <th class="text-right" width="120">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($expenses as $i => $row): ?>
                                            <tr>
                                                <td class="text-muted small"><?= $i + 1 ?></td>
                                                <td class="cell-date"><?= date('d M Y', strtotime($row['expense_date'])) ?></td>
                                                <td>
                                                    <span class="cat-pill">
                                                        <?= htmlspecialchars($row['category'], ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($row['supplier_name'])): ?>
                                                        <span class="supplier-cell">
                                                            <?= htmlspecialchars($row['supplier_name'], ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="cell-empty">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($row['trip_no'])): ?>
                                                        <a href="trip-view.php?id=<?= (int) $row['trip_id'] ?>"
                                                            class="trip-link">
                                                            <?= htmlspecialchars($row['trip_no'], ENT_QUOTES, 'UTF-8') ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="cell-empty">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="route-text">
                                                    <?php if (!empty($row['from_branch_name']) || !empty($row['to_branch_name'])): ?>
                                                        <?= htmlspecialchars($row['from_branch_name'] ?: '—', ENT_QUOTES, 'UTF-8') ?>
                                                        <i class="fas fa-arrow-right route-arrow"></i>
                                                        <?= htmlspecialchars($row['to_branch_name'] ?: '—', ENT_QUOTES, 'UTF-8') ?>
                                                    <?php else: ?>
                                                        <span class="cell-empty">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($row['branch_name'])): ?>
                                                        <span class="branch-badge">
                                                            <?= htmlspecialchars($row['branch_name'], ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="cell-empty">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="notes-cell"
                                                    title="<?= htmlspecialchars($row['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                    <?= htmlspecialchars($row['notes'] ?: '—', ENT_QUOTES, 'UTF-8') ?>
                                                </td>
                                                <td class="created-cell">
                                                    <?= htmlspecialchars($row['created_by_name'] ?: '—', ENT_QUOTES, 'UTF-8') ?>
                                                </td>
                                                <td class="amount-cell">₹ <?= number_format((float) $row['amount'], 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                        <?php endif; ?>

                    </div>

                </div>
            </div>
            <?php include 'layout/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap4.min.js"></script>

    <script>
        $(function() {
            if ($('#expensesTable').length) {
                $('#expensesTable').DataTable({
                    order: [],
                    pageLength: 25,
                    lengthMenu: [
                        [10, 25, 50, 100, -1],
                        [10, 25, 50, 100, 'All']
                    ],
                    columnDefs: [{
                        orderable: false,
                        targets: [1, 4, 5, 7, 8]
                    }],
                    language: {
                        search: '',
                        searchPlaceholder: 'Search expenses...',
                        lengthMenu: 'Show _MENU_',
                        info: 'Showing _START_ to _END_ of _TOTAL_',
                        infoEmpty: 'No expenses',
                        infoFiltered: '(filtered from _MAX_)',
                        zeroRecords: 'No matching expenses found',
                        paginate: {
                            previous: '<i class="fas fa-chevron-left"></i>',
                            next: '<i class="fas fa-chevron-right"></i>'
                        }
                    }
                });
            }
        });
    </script>

</body>

</html>