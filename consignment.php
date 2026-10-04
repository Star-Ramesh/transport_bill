<?php

include 'session.php';
include 'constant.php';

/* =========================================================
   AJAX: Fetch Item Rate (Party-wise > Default)
   ========================================================= */
if (isset($_GET['action']) && $_GET['action'] === 'get_item_rate') {
    header('Content-Type: application/json');
    $party_id = (int)($_POST['party_id'] ?? 0);
    $inv_id   = (int)($_POST['inventory_id'] ?? 0);
    $rate     = '';

    if ($party_id > 0 && $inv_id > 0) {
        $prq = mysqli_query($conn, "SELECT rate FROM party_rate WHERE party_id = $party_id AND inventory_id = $inv_id AND active = 1 LIMIT 1");
        if ($prq && mysqli_num_rows($prq) > 0) {
            $row = mysqli_fetch_assoc($prq);
            $rate = (float)$row['rate'];
        }
    }

    if ($rate === '' && $inv_id > 0) {
        $iq = mysqli_query($conn, "SELECT rate FROM inventory WHERE id = $inv_id LIMIT 1");
        if ($iq && mysqli_num_rows($iq) > 0) {
            $row = mysqli_fetch_assoc($iq);
            $dbRate = (float)$row['rate'];
            if ($dbRate > 0) {
                $rate = $dbRate;
            }
        }
    }

    echo json_encode(['success' => true, 'rate' => $rate === '' ? '' : $rate]);
    exit;
}

$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$isEdit = ($editId > 0);

if ($isEdit) {
    requirePermission('consignment.edit');
    $pageTitle = 'Edit Consignment | Billing Portal';
} else {
    requirePermission('consignment.create');
    $pageTitle = 'Add Consignment | Billing Portal';
}

$isAdminUser = isAdmin();
$myBranch    = (int) ($_SESSION['branch_id'] ?? 0);

$userBranchCode = 'BR';
$userBranchName = '';
if ($myBranch > 0) {
    $br = mysqli_query($conn, "SELECT branch_code, branch_name FROM branch WHERE id = $myBranch LIMIT 1");
    if ($br && mysqli_num_rows($br) === 1) {
        $brRow = mysqli_fetch_assoc($br);
        $userBranchCode = $brRow['branch_code'] !== '' ? strtoupper($brRow['branch_code']) : 'BR';
        $userBranchName = $brRow['branch_name'];
    }
}

$parties = [];
$resP = mysqli_query($conn, "SELECT id, legal_name, trade_name FROM party WHERE active = 1 ORDER BY legal_name");
if ($resP) while ($r = mysqli_fetch_assoc($resP)) $parties[] = $r;

$inventoryList = [];
$sqlInv = "SELECT id, item_name, unit FROM inventory WHERE active = 1";
if (!$isAdminUser && $myBranch > 0) $sqlInv .= " AND branch_id = $myBranch";
$sqlInv .= " ORDER BY item_name";
$resI = mysqli_query($conn, $sqlInv);
if ($resI) while ($r = mysqli_fetch_assoc($resI)) $inventoryList[] = $r;

function previewConsignmentNo($conn, $branchId, $branchCode, $consignmentDate)
{
    $yymm = date('ym', strtotime($consignmentDate));
    $prefix = $branchCode . 'CON' . $yymm;
    $prefix_safe = mysqli_real_escape_string($conn, $prefix);

    $cnt_res = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS c FROM consignment WHERE branch_id = $branchId AND consignment_no LIKE '$prefix_safe%'"
    );
    $next_seq = 1;
    if ($cnt_res) {
        $crow = mysqli_fetch_assoc($cnt_res);
        $next_seq = ((int) $crow['c']) + 1;
    }
    return $prefix . str_pad($next_seq, 4, '0', STR_PAD_LEFT);
}

$autoConsignmentNo = previewConsignmentNo($conn, $myBranch, $userBranchCode, date('Y-m-d'));

$consignmentData = [
    'consignment_no'      => $autoConsignmentNo,
    'consignment_date'    => date('Y-m-d'),
    'consignor_party_id'  => 0,
    'consignee_party_id'  => 0,
    'notes'               => '',
];

$initialItems = [];

if ($isEdit) {
    $cQuery = mysqli_query($conn, "SELECT * FROM consignment WHERE id = $editId LIMIT 1");
    if (!$cQuery || mysqli_num_rows($cQuery) === 0) {
        header("Location: consignments-list.php");
        exit;
    }
    $cRow = mysqli_fetch_assoc($cQuery);

    if ((int) $cRow['active'] !== 1) {
        header("Location: consignment-print.php?id=$editId&msg=inactive");
        exit;
    }

    $consignmentData['consignment_no']     = $cRow['consignment_no'];
    $consignmentData['consignment_date']   = $cRow['consignment_date'];
    $consignmentData['consignor_party_id'] = (int) $cRow['consignor_party_id'];
    $consignmentData['consignee_party_id'] = (int) $cRow['consignee_party_id'];
    $consignmentData['notes']              = $cRow['notes'] ?? '';

    $ciQuery = mysqli_query($conn, "SELECT * FROM consignment_inventory WHERE consignment_id = $editId AND active = 1 ORDER BY id ASC");
    if ($ciQuery) {
        while ($ciRow = mysqli_fetch_assoc($ciQuery)) {
            $initialItems[] = [
                'item_id'   => (int) $ciRow['inventory_id'],
                'item_name' => $ciRow['inventory_name'],
                'unit'      => $ciRow['unit'],
                'quantity'  => $ciRow['quantity'],
                'rate'      => $ciRow['rate'],
            ];
        }
    }
}

$error     = '';
$errorList = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $consignmentData['consignment_no']     = strtoupper(trim($_POST['consignment_no']   ?? ''));
    $consignmentData['consignment_date']   = trim($_POST['consignment_date'] ?? '');
    $consignmentData['consignor_party_id'] = (int) ($_POST['consignor_party_id'] ?? 0);
    $consignmentData['consignee_party_id'] = (int) ($_POST['consignee_party_id'] ?? 0);
    $consignmentData['notes']              = trim($_POST['notes'] ?? '');

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
            ];
        }
    }

    if ($consignmentData['consignment_no'] === '') {
        $errorList[] = 'Consignment No is required.';
    } elseif (strlen($consignmentData['consignment_no']) > 50) {
        $errorList[] = 'Consignment No is too long (max 50).';
    }

    if ($consignmentData['consignment_date'] === '') {
        $errorList[] = 'Date is required.';
    } else {
        $d = DateTime::createFromFormat('Y-m-d', $consignmentData['consignment_date']);
        if (!$d || $d->format('Y-m-d') !== $consignmentData['consignment_date']) {
            $errorList[] = 'Invalid date.';
        }
    }

    if ($consignmentData['consignor_party_id'] <= 0) $errorList[] = 'Please select a consignor.';
    if ($consignmentData['consignee_party_id'] <= 0) $errorList[] = 'Please select a consignee.';

    if ($consignmentData['consignor_party_id'] > 0 && $consignmentData['consignor_party_id'] === $consignmentData['consignee_party_id']) {
        $errorList[] = 'Consignor and Consignee cannot be the same party.';
    }

    if (empty($errorList)) {
        $cn_safe = mysqli_real_escape_string($conn, $consignmentData['consignment_no']);
        $sqlDup = "SELECT id FROM consignment WHERE UPPER(consignment_no) = UPPER('$cn_safe')";
        if ($isEdit) $sqlDup .= " AND id <> $editId";
        $sqlDup .= " LIMIT 1";
        $dup = mysqli_query($conn, $sqlDup);
        if ($dup && mysqli_num_rows($dup) > 0) {
            $errorList[] = 'Consignment No "' . htmlspecialchars($consignmentData['consignment_no']) . '" already exists. Please choose another.';
        }
    }

    if (count($items) === 0) {
        $errorList[] = 'Add at least one inventory row.';
    } else {
        foreach ($items as $i => $it) {
            $n = $i + 1;
            if ($it['quantity'] === '' || !is_numeric($it['quantity'])) {
                $errorList[] = "Row #$n: quantity must be a valid number.";
            } else {
                $qtyF = (float) $it['quantity'];
                if (floor($qtyF) != $qtyF) {
                    $errorList[] = "Row #$n: quantity must be a whole number (no decimals).";
                } elseif ($qtyF < 0) {
                    $errorList[] = "Row #$n: quantity cannot be negative.";
                }
            }
            if ($it['rate'] === '' || !is_numeric($it['rate']) || (float) $it['rate'] < 0) {
                $errorList[] = "Row #$n: rate must be a valid non-negative number.";
            }
        }
    }

    if (empty($errorList)) {

        $esc = function ($v) use ($conn) {
            return mysqli_real_escape_string($conn, $v);
        };

        mysqli_begin_transaction($conn);

        try {
            $cno_v      = "'" . $esc($consignmentData['consignment_no']) . "'";
            $cdate_v    = "'" . $esc($consignmentData['consignment_date']) . "'";
            $consignor_v = (int) $consignmentData['consignor_party_id'];
            $consignee_v = (int) $consignmentData['consignee_party_id'];
            $notes_v    = $consignmentData['notes'] !== '' ? "'" . $esc($consignmentData['notes']) . "'" : "NULL";

            if ($isEdit) {

                $sql_upd = "UPDATE consignment SET
                                consignment_no = $cno_v,
                                consignment_date = $cdate_v,
                                consignor_party_id = $consignor_v,
                                consignee_party_id = $consignee_v,
                                notes = $notes_v
                            WHERE id = $editId";
                if (!mysqli_query($conn, $sql_upd)) {
                    throw new Exception('Failed to update consignment: ' . mysqli_error($conn));
                }

                mysqli_query($conn, "UPDATE consignment_inventory SET active = 0 WHERE consignment_id = $editId");

                $target_id = $editId;
                $msgKey = 'updated';

            } else {

                $branch_v     = (int) $myBranch;
                $created_by_v = (int) ($_SESSION['user_id'] ?? 0);

                $sql_ins = "INSERT INTO consignment
                                (consignment_no, consignment_date,
                                 consignor_party_id, consignee_party_id,
                                 notes, branch_id, created_by, active)
                            VALUES
                                ($cno_v, $cdate_v,
                                 $consignor_v, $consignee_v,
                                 $notes_v, $branch_v, $created_by_v, 1)";
                if (!mysqli_query($conn, $sql_ins)) {
                    throw new Exception('Failed to save consignment: ' . mysqli_error($conn));
                }
                $target_id = mysqli_insert_id($conn);
                $msgKey = 'added';
            }

            if ($isAdminUser || $myBranch <= 0) {
                $invBranchId = 0;
                $invBranchRes = mysqli_query($conn, "SELECT id FROM branch WHERE active = 1 ORDER BY id LIMIT 1");
                if ($invBranchRes && mysqli_num_rows($invBranchRes) === 1) {
                    $ibr = mysqli_fetch_assoc($invBranchRes);
                    $invBranchId = (int) $ibr['id'];
                }
            } else {
                $invBranchId = (int) $myBranch;
            }

            foreach ($items as $it) {
                $inventory_id = (int) $it['item_id'];
                
                if ($inventory_id <= 0) {
                    $name_check = mysqli_real_escape_string($conn, $it['item_name']);
                    $chk = mysqli_query($conn, "SELECT id FROM inventory WHERE UPPER(item_name) = UPPER('$name_check') AND branch_id = $invBranchId LIMIT 1");
                    if ($chk && mysqli_num_rows($chk) === 1) {
                        $chk_row = mysqli_fetch_assoc($chk);
                        $inventory_id = (int) $chk_row['id'];
                    } else {
                        $new_name_v = "'" . mysqli_real_escape_string($conn, $it['item_name']) . "'";
                        $new_unit_v = $it['unit'] !== '' ? "'" . mysqli_real_escape_string($conn, $it['unit']) . "'" : "NULL";
                        $ins_inv = "INSERT INTO inventory (branch_id, item_name, unit, rate, active) VALUES ($invBranchId, $new_name_v, $new_unit_v, 0.00, 1)";
                        if (!mysqli_query($conn, $ins_inv)) {
                            throw new Exception('Failed to add inventory master row: ' . mysqli_error($conn));
                        }
                        $inventory_id = mysqli_insert_id($conn);
                    }
                }

                $inv_name_v = "'" . $esc($it['item_name']) . "'";
                $inv_unit_v = $it['unit'] !== '' ? "'" . $esc($it['unit']) . "'" : "NULL";
                $inv_id_v   = $inventory_id > 0 ? $inventory_id : "NULL";
                $qty_int    = (int) $it['quantity'];
                $rate       = (float) $it['rate'];
                $amt        = $qty_int * $rate;

                $rate_v = "'" . number_format($rate, 2, '.', '') . "'";
                $amt_v  = "'" . number_format($amt, 2, '.', '') . "'";

                $sql_it = "INSERT INTO consignment_inventory
                              (consignment_id, inventory_id, inventory_name, unit,
                               quantity, rate, amount, active)
                           VALUES
                              ($target_id, $inv_id_v, $inv_name_v, $inv_unit_v,
                               $qty_int, $rate_v, $amt_v, 1)";
                if (!mysqli_query($conn, $sql_it)) {
                    throw new Exception('Failed to save item row "' . $it['item_name'] . '": ' . mysqli_error($conn));
                }
            }

            mysqli_commit($conn);

            $redirectUrl = "consignments-list.php?msg=$msgKey";
            header("Location: $redirectUrl");
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

if (empty($initialItems)) {
    $initialItems = [ [] ];
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
        .cns-card {
            border: 1px solid #e3e6f0;
            border-radius: 10px;
            background: #fff;
            margin-bottom: 1rem;
            box-shadow: 0 1px 4px rgba(30, 34, 51, .04);
        }

        .cns-card-header {
            padding: .85rem 1.1rem;
            border-bottom: 1px solid #eaeef6;
            background: #fbfcff;
            border-radius: 10px 10px 0 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
            flex-wrap: wrap;
        }

        .cns-card-header h6 {
            margin: 0;
            font-size: .92rem;
            font-weight: 800;
            color: #2c2e3e;
            display: flex;
            align-items: center;
            gap: .4rem;
        }

        .cns-card-header h6 i { color: #4e73df; }

        .cns-card-body { padding: 1.1rem; }

        @media (max-width: 575.98px) {
            .cns-card-body { padding: .85rem; }
            .cns-card-header { padding: .7rem .9rem; }
        }

        .cns-card label {
            font-size: .82rem;
            font-weight: 700;
            color: #3a3b45;
            margin-bottom: .3rem;
            display: block;
        }

        .cns-card label .req { color: #e74a3b; }

        .cns-card .form-control {
            font-size: .9rem;
            padding: .5rem .75rem;
            height: auto;
            border-radius: .4rem;
        }

        /* party pair */
        .party-pair {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            position: relative;
        }

        @media (max-width: 767.98px) {
            .party-pair { grid-template-columns: 1fr; }
            .party-pair::after { display: none; }
        }

        .party-pair::after {
            content: "\f061";
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.85);
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #fff;
            border: 1px solid #e3e6f0;
            color: #4e73df;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .72rem;
            box-shadow: 0 1px 4px rgba(30, 34, 51, .06);
            pointer-events: none;
            z-index: 1;
        }

        .party-block {
            border: 1px solid #e3e6f0;
            border-radius: .5rem;
            padding: 1rem;
            background: #fdfdff;
            position: relative;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .party-block.from { background: #f5f8ff; border-color: #dbe2ff; }
        .party-block.to   { background: #f4fdfa; border-color: #c5f0e1; }

        .party-block .party-title {
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .05em;
            text-transform: uppercase;
            margin-bottom: .75rem;
            display: flex;
            align-items: center;
            gap: .4rem;
        }

        .party-block.from .party-title { color: #3f51b5; }
        .party-block.to   .party-title { color: #0c7d5b; }

        .party-block .party-title .req { color: #e74a3b; }

        .party-block .party-hint {
            font-size: .72rem;
            color: #858796;
            margin-top: .35rem;
            display: block;
        }

        @media (max-width: 575.98px) {
            .party-block { padding: .8rem; }
        }

        /* items */
        .items-table {
            width: 100%;
            margin: 0;
            border-collapse: collapse;
        }

        .items-table thead th {
            font-size: .7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #6e707e;
            background: #f8f9fc;
            padding: .55rem .5rem;
            border-bottom: 2px solid #e3e6f0;
            text-align: left;
            white-space: nowrap;
        }

        .items-table tbody td {
            padding: .5rem .5rem;
            border-bottom: 1px solid #f0f2f7;
            vertical-align: middle;
        }

        .items-table .form-control {
            font-size: .85rem;
            padding: .35rem .5rem;
            height: auto;
        }

        .items-total {
            background: #f8f9fc;
            padding: .75rem 1rem;
            font-weight: 800;
            font-size: .95rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 2px solid #e3e6f0;
        }

        .items-bottom-bar {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            padding: .75rem 1rem;
            background: #fff;
            border-top: 1px solid #f0f2f7;
            gap: .5rem;
            flex-wrap: wrap;
        }

        @media (max-width: 575.98px) {
            .items-bottom-bar { flex-direction: column; align-items: stretch; }
            .items-bottom-bar #addItemBtn { width: 100%; }
        }

        @media (max-width: 767.98px) {
            .items-table thead { display: none; }
            .items-table,
            .items-table tbody,
            .items-table tr,
            .items-table td { display: block; width: 100%; }

            .items-table tr {
                border: 1px solid #e3e6f0;
                border-radius: .5rem;
                margin-bottom: .75rem;
                padding: .5rem;
                background: #fff;
            }

            .items-table tbody td { border: 0; padding: .35rem 0; }

            .items-table tbody td::before {
                content: attr(data-label);
                display: block;
                font-size: .68rem;
                font-weight: 800;
                text-transform: uppercase;
                color: #6e707e;
                margin-bottom: .15rem;
                letter-spacing: .04em;
            }

            .items-table tbody td[data-label=""]::before { display: none; }

            .items-table .js-remove-item { width: 100%; }
        }

        .action-bar {
            background: #fff;
            border: 1px solid #e3e6f0;
            border-radius: 10px;
            padding: .9rem 1.1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            flex-wrap: wrap;
        }

        .action-bar .ab-left {
            display: flex;
            align-items: center;
            gap: .5rem;
            flex-wrap: wrap;
        }

        .action-bar .ab-cno-label {
            font-size: .68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #858796;
        }

        .action-bar .ab-cno-value {
            font-family: "SFMono-Regular", Menlo, Consolas, monospace;
            font-size: .95rem;
            font-weight: 800;
            color: #2c2e3e;
            letter-spacing: .02em;
            word-break: break-all;
        }

        .action-bar .ab-cno-edit {
            background: #f8f9fc;
            border: 1px solid #d1d3e2;
            color: #4e73df;
            border-radius: 6px;
            width: 28px;
            height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            font-size: .75rem;
            cursor: pointer;
            transition: all .15s ease;
            flex-shrink: 0;
        }

        .action-bar .ab-cno-edit:hover {
            background: #4e73df;
            color: #fff;
            border-color: #4e73df;
        }

        .action-bar .hint { color: #858796; font-size: .8rem; }
        .action-bar .hint .req { color: #e74a3b; }

        .action-bar .btns {
            display: flex;
            gap: .5rem;
            flex-wrap: wrap;
        }

        @media (max-width: 575.98px) {
            .action-bar { flex-direction: column; align-items: stretch; }
            .action-bar .ab-left { justify-content: center; }
            .action-bar .btns { width: 100%; }
            .action-bar .btns .btn { flex: 1 1 0; }
        }

        .input-with-btn {
            display: flex;
            gap: 0;
            align-items: stretch;
        }

        .input-with-btn .form-control {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
            flex: 1 1 auto;
            min-width: 0;
        }

        .input-with-btn .btn {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
            padding: .5rem .8rem;
            flex: 0 0 auto;
            border: 1px solid #4e73df;
            border-left: 0;
            color: #4e73df;
            background: #fff;
            font-weight: 700;
        }

        .input-with-btn .btn:hover { background: #4e73df; color: #fff; }

        #consignmentNoModal .modal-dialog { max-width: 440px; }
        #consignmentNoModal .modal-body { padding: 1rem 1.25rem; }

        #cno_status {
            display: block;
            font-size: .78rem;
            margin-top: .35rem;
            min-height: 1rem;
        }

        #cno_status.checking { color: #6e707e; }
        #cno_status.ok       { color: #0c7d5b; }
        #cno_status.error    { color: #e74a3b; font-weight: 700; }

        #consignment_no_input.is-duplicate {
            border-color: #e74a3b;
            background-color: #fff5f5;
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

                    <div class="d-sm-flex align-items-center justify-content-between mb-3">
                        <h1 class="h3 mb-0 text-gray-800">
                            <i class="fas fa-file-alt mr-2"></i>
                            <?= $isEdit ? 'Edit Consignment' : 'Add Consignment' ?>
                            <?php if ($userBranchName !== ''): ?>
                                <span class="branch-badge-soft" style="display:inline-block;padding:.15rem .55rem;border-radius:4px;font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;background:#eef2ff;color:#3f51b5;border:1px solid #dbe2ff;margin-left:.4rem;">
                                    <?= htmlspecialchars($userBranchName, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            <?php endif; ?>
                        </h1>

                        <a href="consignments-list.php"
                            class="d-inline-block btn btn-sm btn-secondary shadow-sm">
                            <i class="fas fa-arrow-left fa-sm text-white-50 mr-1"></i>
                            Back to Consignments
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

                    <form id="consignmentForm" action="" method="POST" novalidate>

                        <input type="hidden" name="consignment_no" id="consignment_no"
                            value="<?= htmlspecialchars($consignmentData['consignment_no'], ENT_QUOTES, 'UTF-8') ?>"
                            data-edit-id="<?= (int) $editId ?>">

                        <!-- CONSIGNOR + CONSIGNEE -->
                        <div class="cns-card">
                            <div class="cns-card-header">
                                <h6><i class="fas fa-users"></i> Parties</h6>
                            </div>
                            <div class="cns-card-body">

                                <div class="party-pair">

                                    <div class="party-block from">
                                        <div class="party-title">
                                            <i class="fas fa-user-tag"></i>
                                            Consignor (sender)
                                            <span class="req">*</span>
                                        </div>
                                        <div class="form-group mb-0">
                                            <label for="consignor_party_input">Party</label>
                                            <div class="input-with-btn">
                                                <input type="text" id="consignor_party_input"
                                                    class="form-control js-datalist-input"
                                                    list="consignorList" data-target="consignor_party_id"
                                                    placeholder="Select or type party"
                                                    autocomplete="off"
                                                    value="<?= htmlspecialchars(array_reduce($parties, function($carry, $item) use ($consignmentData) {
                                                        return $item['id'] == $consignmentData['consignor_party_id']
                                                            ? $item['legal_name'] . (!empty($item['trade_name']) ? ' (' . $item['trade_name'] . ')' : '')
                                                            : $carry;
                                                    }, ''), ENT_QUOTES, 'UTF-8') ?>"
                                                    required>
                                                <input type="hidden" name="consignor_party_id" id="consignor_party_id" value="<?= $consignmentData['consignor_party_id'] ?: '' ?>">
                                                <a href="#" class="btn js-open-master"
                                                    data-entity="party" data-slot="consignor"
                                                    data-title="Add New Party (Consignor)"
                                                    data-no-perm-check="1"
                                                    title="Add new party">
                                                    <i class="fas fa-plus"></i>
                                                </a>
                                            </div>
                                            <small class="party-hint">
                                                Who is sending the goods.
                                            </small>
                                        </div>
                                    </div>

                                    <div class="party-block to">
                                        <div class="party-title">
                                            <i class="fas fa-user-check"></i>
                                            Consignee (receiver)
                                            <span class="req">*</span>
                                        </div>
                                        <div class="form-group mb-0">
                                            <label for="consignee_party_input">Party</label>
                                            <div class="input-with-btn">
                                                <input type="text" id="consignee_party_input"
                                                    class="form-control js-datalist-input"
                                                    list="consigneeList" data-target="consignee_party_id"
                                                    placeholder="Select or type party"
                                                    autocomplete="off"
                                                    value="<?= htmlspecialchars(array_reduce($parties, function($carry, $item) use ($consignmentData) {
                                                        return $item['id'] == $consignmentData['consignee_party_id']
                                                            ? $item['legal_name'] . (!empty($item['trade_name']) ? ' (' . $item['trade_name'] . ')' : '')
                                                            : $carry;
                                                    }, ''), ENT_QUOTES, 'UTF-8') ?>"
                                                    required>
                                                <input type="hidden" name="consignee_party_id" id="consignee_party_id" value="<?= $consignmentData['consignee_party_id'] ?: '' ?>">
                                                <a href="#" class="btn js-open-master"
                                                    data-entity="party" data-slot="consignee"
                                                    data-title="Add New Party (Consignee)"
                                                    data-no-perm-check="1"
                                                    title="Add new party">
                                                    <i class="fas fa-plus"></i>
                                                </a>
                                            </div>
                                            <small class="party-hint">
                                                Who receives the goods.
                                            </small>
                                        </div>
                                    </div>

                                </div>

                            </div>
                        </div>

                        <!-- DETAILS -->
                        <div class="cns-card">
                            <div class="cns-card-header">
                                <h6><i class="fas fa-calendar-alt"></i> Consignment Details</h6>
                            </div>
                            <div class="cns-card-body">
                                <div class="row">
                                    <div class="col-12 col-md-4">
                                        <div class="form-group mb-0">
                                            <label for="consignment_date">
                                                Date <span class="req">*</span>
                                            </label>
                                            <input type="date" id="consignment_date" name="consignment_date"
                                                class="form-control"
                                                value="<?= htmlspecialchars($consignmentData['consignment_date'], ENT_QUOTES, 'UTF-8') ?>"
                                                required>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-8">
                                        <div class="form-group mb-0">
                                            <label for="notes">Notes</label>
                                            <input type="text" id="notes" name="notes"
                                                class="form-control"
                                                maxlength="500"
                                                placeholder="Optional"
                                                value="<?= htmlspecialchars($consignmentData['notes'], ENT_QUOTES, 'UTF-8') ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- INVENTORY -->
                        <div class="cns-card">
                            <div class="cns-card-header">
                                <h6><i class="fas fa-box"></i> Items</h6>
                            </div>

                            <div class="table-responsive">
                                <table class="items-table" id="itemsTable">
                                    <thead>
                                        <tr>
                                            <th style="min-width:220px;">Item</th>
                                            <th style="min-width:80px;">Unit</th>
                                            <th style="min-width:80px;" class="text-right">Qty</th>
                                            <th style="min-width:100px;" class="text-right">Rate</th>
                                            <th style="min-width:110px;" class="text-right">Amount</th>
                                            <th style="min-width:50px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="itemsWrap"></tbody>
                                </table>
                            </div>

                            <div class="items-total">
                                <span><i class="fas fa-calculator mr-1"></i> Grand Total</span>
                                <span>₹ <span id="itemsGrandTotal">0</span></span>
                            </div>

                            <div class="items-bottom-bar">
                                <button type="button" id="addItemBtn" class="btn btn-sm btn-primary">
                                    <i class="fas fa-plus mr-1"></i> Add Row
                                </button>
                            </div>
                        </div>

                        <!-- ACTION BAR -->
                        <div class="action-bar">
                            <div class="ab-left">
                                <span class="ab-cno-label"><i class="fas fa-hashtag mr-1"></i>Consignment No</span>
                                <span class="ab-cno-value" id="consignmentNoText"><?= htmlspecialchars($consignmentData['consignment_no'], ENT_QUOTES, 'UTF-8') ?></span>
                                <button type="button" class="ab-cno-edit" id="consignmentNoEditBtn"
                                    title="Edit Consignment No">
                                    <i class="fas fa-pen"></i>
                                </button>
                            </div>

                            <div class="hint d-none d-sm-block">
                                Fields marked <span class="req">*</span> are required.
                            </div>

                            <div class="btns">
                                <a href="consignment.php<?= $isEdit ? '?id=' . $editId : '' ?>" class="btn btn-secondary">
                                    <i class="fas fa-redo mr-1"></i> Reset
                                </a>
                                <button type="submit" id="saveConsignmentBtn" class="btn btn-success">
                                    <i class="fas fa-check mr-1"></i>
                                    <?= $isEdit ? 'Update Consignment' : 'Save Consignment' ?>
                                </button>
                            </div>
                        </div>

                    </form>

                </div>
            </div>
            <?php include 'layout/footer.php'; ?>
        </div>
    </div>

    <!-- CONSIGNMENT NO EDIT MODAL -->
    <div class="modal fade" id="consignmentNoModal" tabindex="-1" role="dialog"
        aria-labelledby="consignmentNoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="consignmentNoModalLabel">
                        <i class="fas fa-hashtag mr-1"></i>
                        Edit Consignment No
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-0">
                        <label for="consignment_no_input" class="font-weight-bold">
                            Consignment No <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                                id="consignment_no_input"
                                class="form-control text-uppercase"
                                maxlength="50"
                                autocomplete="off"
                                placeholder="e.g. BOLCON26090001">
                        <small id="cno_status" class="cno-status">
                            <i class="fas fa-info-circle mr-1"></i>
                            Auto-generated. You may edit if needed.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-success" id="consignmentNoModalSave">
                        <i class="fas fa-check mr-1"></i> Save Consignment No
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MASTER MODAL for party popup -->
    <div class="modal fade" id="masterModal" tabindex="-1" role="dialog"
        aria-labelledby="masterModalTitle" aria-hidden="true">
        <div class="modal-dialog" role="document">
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

    <!-- Global Datalists (Moved to root to fix mobile browser dropdown positioning bugs) -->
    <datalist id="consignorList">
        <?php foreach ($parties as $p):
            $label = $p['legal_name'];
            if (!empty($p['trade_name'])) $label .= ' (' . $p['trade_name'] . ')';
        ?>
            <option data-id="<?= (int) $p['id'] ?>" value="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"></option>
        <?php endforeach; ?>
    </datalist>

    <datalist id="consigneeList">
        <?php foreach ($parties as $p):
            $label = $p['legal_name'];
            if (!empty($p['trade_name'])) $label .= ' (' . $p['trade_name'] . ')';
        ?>
            <option data-id="<?= (int) $p['id'] ?>" value="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"></option>
        <?php endforeach; ?>
    </datalist>

    <!-- Master datalist populated via JavaScript to avoid duplicating for every item row -->
    <datalist id="inventoryMasterList"></datalist>

    <script>
        var MASTER_INVENTORY = <?= json_encode(array_map(function ($r) {
                                    return [
                                        'id'    => (int) $r['id'],
                                        'name'  => $r['item_name'],
                                        'unit'  => $r['unit'] ?? ''
                                    ];
                                }, $inventoryList)); ?>;

        var CONSIGNMENT_INITIAL_ITEMS = <?= json_encode($initialItems); ?>;
    </script>

    <script>
        $(function () {
            
            /* Populate Master Inventory Datalist ONCE */
            function escHtml(s) {
                return String(s == null ? "" : s)
                    .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
            }

            var invHtml = "";
            for (var i = 0; i < MASTER_INVENTORY.length; i++) {
                var inv = MASTER_INVENTORY[i];
                invHtml += '<option data-id="' + inv.id + '" data-unit="' + escHtml(inv.unit) + '" value="' + escHtml(inv.name) + '"></option>';
            }
            $("#inventoryMasterList").html(invHtml);


            /* AJAX Rate Fetching */
            function fetchRateForRow($row, invId, forcePartyId) {
                if (!invId) return;
                var pId = forcePartyId !== undefined ? forcePartyId : ($("#consignor_party_id").val() || 0);

                $.ajax({
                    url: 'consignment.php?action=get_item_rate',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        party_id: pId,
                        inventory_id: invId
                    },
                    success: function(res) {
                        if (res && res.success) {
                            var rInput = $row.find(".item-rate-input");
                            rInput.val(res.rate);
                            rInput.trigger("input");
                        }
                    }
                });
            }

            function refreshAllItemRates() {
                var pId = $("#consignor_party_id").val() || 0;
                $("#itemsWrap").find(".item-row").each(function() {
                    var $row = $(this);
                    var invId = $row.find(".item-id-input").val();
                    if (invId) {
                        fetchRateForRow($row, invId, pId);
                    }
                });
            }

            /* DATALIST ↔ HIDDEN ID SYNC */
            function syncDatalistInput($input) {
                var targetId = $input.data("target");
                if (!targetId) return;
                var listId = $input.attr("list");
                if (!listId) return;

                var typed = ($input.val() || "").trim();
                var $hidden = $("#" + targetId);

                if (typed === "") { 
                    $hidden.val("");
                    if (targetId === "consignor_party_id") refreshAllItemRates();
                    return; 
                }

                var matched = null;
                $("#" + listId).find("option").each(function () {
                    var $opt = $(this);
                    var optVal = ($opt.attr("value") || "").trim();
                    if (optVal === typed) {
                        matched = $opt.data("id");
                        return false;
                    }
                });
                
                var oldId = $hidden.val();
                var finalId = matched !== null && matched !== undefined ? matched : "";
                $hidden.val(finalId);
                
                // If consignor changes, fetch new specific party rates
                if (targetId === "consignor_party_id" && oldId !== String(finalId)) {
                    refreshAllItemRates();
                }
            }

            $(document).on("input change", ".js-datalist-input", function () {
                syncDatalistInput($(this));
            });

            /* INVENTORY ROWS */
            function formatMoney(n) {
                n = parseFloat(n) || 0;
                return n.toLocaleString("en-IN", { maximumFractionDigits: 2, minimumFractionDigits: 0 });
            }
            function num(v) { var n = parseFloat(v); return isNaN(n) ? 0 : n; }
            function toInt(v) { var n = parseInt(v, 10); return isNaN(n) ? 0 : n; }

            function buildItemRowHTML(index, prefill) {
                prefill = prefill || {};
                
                var itemName = prefill.item_name || "";
                var itemId = prefill.item_id || "";
                var unitVal = prefill.unit || "";
                var qtyVal = prefill.quantity !== undefined && prefill.quantity !== null && prefill.quantity !== "" ? prefill.quantity : "";
                var rateVal = prefill.rate !== undefined && prefill.rate !== null && prefill.rate !== "" ? prefill.rate : "";

                var qtyInt = qtyVal === "" ? "" : parseInt(qtyVal, 10);
                var rateNum = rateVal === "" ? "" : parseFloat(rateVal);
                var amount = 0;
                if (qtyInt !== "" && rateNum !== "" && !isNaN(qtyInt) && !isNaN(rateNum)) amount = qtyInt * rateNum;

                var amountDisplay = amount > 0 || qtyInt !== "" ? formatMoney(amount) : "0";
                var amountHidden = amount > 0 ? amount.toFixed(2) : "0";

                return (
                    '<tr class="item-row">' +
                    '<td class="align-middle" data-label="Item">' +
                        '<input type="text" class="form-control form-control-sm item-name-input" ' +
                            'name="items[' + index + '][item_name]" ' +
                            'placeholder="Type or pick item..." ' +
                            'list="inventoryMasterList" autocomplete="off" maxlength="100" ' +
                            'value="' + escHtml(itemName) + '" required>' +
                        '<input type="hidden" class="item-id-input" name="items[' + index + '][item_id]" value="' + escHtml(itemId) + '">' +
                    '</td>' +
                    '<td class="align-middle" data-label="Unit">' +
                        '<input type="text" class="form-control form-control-sm item-unit-input" ' +
                            'name="items[' + index + '][unit]" placeholder="Unit" maxlength="20" value="' + escHtml(unitVal) + '">' +
                    '</td>' +
                    '<td class="align-middle" data-label="Qty">' +
                        '<input type="number" class="form-control form-control-sm item-qty-input text-right" ' +
                            'name="items[' + index + '][quantity]" placeholder="0" step="1" min="0" value="' + escHtml(qtyVal) + '">' +
                    '</td>' +
                    '<td class="align-middle" data-label="Rate">' +
                        '<input type="number" class="form-control form-control-sm item-rate-input text-right" ' +
                            'name="items[' + index + '][rate]" placeholder="0.00" step="0.01" min="0" value="' + escHtml(rateVal) + '">' +
                    '</td>' +
                    '<td class="align-middle text-right" data-label="Amount">' +
                        '<span class="item-amount-display font-weight-bold">' + amountDisplay + '</span>' +
                        '<input type="hidden" class="item-amount-input" name="items[' + index + '][amount]" value="' + amountHidden + '">' +
                    '</td>' +
                    '<td class="align-middle text-center" data-label="">' +
                        '<button type="button" class="btn btn-sm btn-outline-danger js-remove-item"><i class="fas fa-times"></i></button>' +
                    '</td>' +
                    '</tr>'
                );
            }

            function reindexItemRows() {
                $("#itemsWrap").find(".item-row").each(function (i) {
                    var $row = $(this);
                    $row.find("input").each(function () {
                        var name = $(this).attr("name");
                        if (!name) return;
                        $(this).attr("name", name.replace(/items\[\d+\]/, "items[" + i + "]"));
                    });
                });
            }

            function addItemRow(prefill) {
                var index = $("#itemsWrap").find(".item-row").length;
                $("#itemsWrap").append(buildItemRowHTML(index, prefill));
                if (!prefill || !prefill.item_name) {
                    $("#itemsWrap").find(".item-row").last().find(".item-name-input").focus();
                }
            }

            $(document).on("click", "#addItemBtn", function (e) {
                e.preventDefault();
                addItemRow();
            });

            $(document).on("click", ".js-remove-item", function (e) {
                e.preventDefault();
                var $rows = $("#itemsWrap").find(".item-row");
                if ($rows.length === 1) {
                    var $row = $rows.first();
                    $row.find('input[type="text"], input[type="number"]').val("");
                    $row.find(".item-amount-display").text("0");
                    $row.find(".item-id-input").val("");
                    recalcTotals();
                    return;
                }
                $(this).closest(".item-row").remove();
                reindexItemRows();
                recalcTotals();
            });

            $(document).on("input", ".item-name-input", function () {
                var $input = $(this);
                var $row = $input.closest(".item-row");
                var typed = ($input.val() || "").trim();
                var $datalist = $("#inventoryMasterList");
                var matchedId = "";
                var matchedUnit = "";

                $datalist.find("option").each(function () {
                    var $opt = $(this);
                    if ($opt.attr("value") === typed) {
                        matchedId = $opt.data("id") || "";
                        matchedUnit = $opt.data("unit") || "";
                        return false;
                    }
                });

                if (matchedId !== "") {
                    $row.find(".item-id-input").val(matchedId);
                    $row.find(".item-unit-input").val(matchedUnit || "");
                    
                    // Auto fetch the rate for this item
                    fetchRateForRow($row, matchedId);
                } else {
                    $row.find(".item-id-input").val("");
                    $row.find(".item-unit-input").val("");
                }
            });

            function recalcRow($row) {
                var qty = toInt($row.find(".item-qty-input").val());
                var rate = num($row.find(".item-rate-input").val());
                var amt = qty * rate;
                $row.find(".item-amount-display").text(formatMoney(amt));
                $row.find(".item-amount-input").val(amt.toFixed(2));
            }

            function recalcTotals() {
                var grand = 0;
                $("#itemsWrap").find(".item-row").each(function () {
                    recalcRow($(this));
                    grand += num($(this).find(".item-amount-input").val());
                });
                $("#itemsGrandTotal").text(formatMoney(grand));
            }

            $(document).on("input", ".item-qty-input, .item-rate-input", function () {
                recalcRow($(this).closest(".item-row"));
                recalcTotals();
            });

            /* MASTER MODAL (party quick-add) */
            var MODAL_ID = "#masterModal";
            var MODAL_IFRAME_ID = "#masterModalIframe";
            var MODAL_TITLE_ID = "#masterModalTitle";
            var lastSlot = "";

            function openMasterModal(entity, titleText, slot) {
                var url = entity + ".php?popup=1";
                lastSlot = slot || "";
                if (document.activeElement && document.activeElement.blur) document.activeElement.blur();
                $(MODAL_IFRAME_ID).attr("src", url);
                $(MODAL_TITLE_ID).text(titleText || "Add New " + entity);
                $(MODAL_ID).modal({ backdrop: "static", keyboard: false, show: true });
            }

            function closeMasterModal() {
                if (document.activeElement && document.activeElement.blur) document.activeElement.blur();
                $(MODAL_ID).modal("hide");
                $(MODAL_ID).one("hidden.bs.modal.masterOnce", function () {
                    $(MODAL_IFRAME_ID).attr("src", "about:blank");
                });
            }

            $(document).on("click", ".js-open-master", function (e) {
                e.preventDefault();
                e.stopPropagation();
                var entity = $(this).data("entity");
                var slot = $(this).data("slot") || "";
                var title = $(this).data("title") || "Add New " + entity;
                if (!entity) return;
                openMasterModal(entity, title, slot);
            });

            function setDatalistValue(slot, id, label) {
                var map = {
                    consignor: { input: "#consignor_party_input", hidden: "#consignor_party_id", list: "#consignorList" },
                    consignee: { input: "#consignee_party_input", hidden: "#consignee_party_id", list: "#consigneeList" },
                };
                var cfg = map[slot];
                if (!cfg) return;

                var $list = $(cfg.list);
                var exists = false;
                $list.find("option").each(function () {
                    if ($(this).attr("value") === label) { exists = true; return false; }
                });
                if (!exists) {
                    $list.append('<option data-id="' + id + '" value="' + escHtml(label) + '"></option>');
                }
                
                var oldId = $(cfg.hidden).val();
                $(cfg.input).val(label);
                $(cfg.hidden).val(id);
                
                if (slot === "consignor" && oldId !== String(id)) {
                    refreshAllItemRates();
                }
            }

            window.addEventListener("message", function (e) {
                var data = e.data;
                if (!data || typeof data !== "object") return;
                if (data.type === "master-saved" && data.entity === "party") {
                    setDatalistValue(lastSlot || "consignor", data.id, data.label || "");
                    closeMasterModal();
                    return;
                }
                if (data.type === "master-cancelled") {
                    closeMasterModal();
                    return;
                }
            });

            /* CONSIGNMENT NO EDIT MODAL */
            (function initConsignmentNoEditor() {
                var $hidden     = $("#consignment_no");
                var $badgeText  = $("#consignmentNoText");
                var $editBtn    = $("#consignmentNoEditBtn");
                var $modal      = $("#consignmentNoModal");
                var $input      = $("#consignment_no_input");
                var $status     = $("#cno_status");
                var $modalSave  = $("#consignmentNoModalSave");

                if (!$hidden.length || !$modal.length) return;

                var editId = parseInt($hidden.data("edit-id"), 10) || 0;
                var lastChecked = "";
                var isDuplicate = false;
                var pendingAjax = null;
                var typeTimer = null;

                function setStatus(state, msg) {
                    $status.removeClass("checking ok error");
                    if (state === "checking") {
                        $status.addClass("checking").html('<i class="fas fa-spinner fa-spin mr-1"></i>' + msg);
                    } else if (state === "ok") {
                        $status.addClass("ok").html('<i class="fas fa-check-circle mr-1"></i>' + msg);
                    } else if (state === "error") {
                        $status.addClass("error").html('<i class="fas fa-exclamation-circle mr-1"></i>' + msg);
                    } else {
                        $status.html('<i class="fas fa-info-circle mr-1"></i>Auto-generated. You may edit if needed.');
                    }
                }

                function applyDuplicate(isDup) {
                    isDuplicate = !!isDup;
                    if (isDuplicate) {
                        $input.addClass("is-duplicate");
                        $modalSave.prop("disabled", true);
                    } else {
                        $input.removeClass("is-duplicate");
                        $modalSave.prop("disabled", false);
                    }
                }

                function checkConsignmentNo() {
                    var val = $.trim($input.val()).toUpperCase();
                    if ($input.val() !== val) $input.val(val);

                    if (val === "") {
                        setStatus("error", "Consignment No is required.");
                        applyDuplicate(true);
                        lastChecked = "";
                        return;
                    }

                    if (val === lastChecked) return;
                    lastChecked = val;

                    setStatus("checking", "Checking...");
                    if (pendingAjax) pendingAjax.abort();

                    pendingAjax = $.ajax({
                        url: "ajax/check-consignment-no.php",
                        type: "GET",
                        dataType: "json",
                        data: { consignment_no: val, exclude_id: editId }
                    })
                    .done(function (res) {
                        pendingAjax = null;
                        if (!res || !res.success) {
                            setStatus("", "");
                            applyDuplicate(false);
                            return;
                        }
                        if (res.exists) {
                            setStatus("error", 'Consignment No "' + val + '" already exists. Please choose another.');
                            applyDuplicate(true);
                        } else {
                            setStatus("ok", "Consignment No is available.");
                            applyDuplicate(false);
                        }
                    })
                    .fail(function (xhr, status) {
                        if (status === "abort") return;
                        pendingAjax = null;
                        setStatus("", "Could not verify. Please try again.");
                        applyDuplicate(false);
                    });
                }

                $editBtn.on("click", function (e) {
                    e.preventDefault();
                    var current = $.trim($hidden.val());
                    $input.val(current);
                    lastChecked = "";
                    isDuplicate = false;
                    $input.removeClass("is-duplicate");
                    $modalSave.prop("disabled", false);

                    if (current === "") {
                        setStatus("error", "Consignment No is required.");
                    } else {
                        setStatus("", "Auto-generated. You may edit if needed.");
                    }

                    $modal.modal({ backdrop: "static", keyboard: false, show: true });

                    setTimeout(function () {
                        $input.trigger("focus").trigger("select");
                        if (current !== "") checkConsignmentNo();
                    }, 300);
                });

                $input.on("input", function () {
                    var pos = this.selectionStart;
                    this.value = this.value.toUpperCase();
                    this.setSelectionRange(pos, pos);
                });

                $input.on("input", function () {
                    clearTimeout(typeTimer);
                    typeTimer = setTimeout(checkConsignmentNo, 400);
                });

                $input.on("blur", checkConsignmentNo);

                $modalSave.on("click", function () {
                    if (isDuplicate) {
                        setStatus("error", "Fix the duplicate Consignment No before saving.");
                        return;
                    }
                    var val = $.trim($input.val()).toUpperCase();
                    if (val === "") {
                        setStatus("error", "Consignment No is required.");
                        return;
                    }
                    $hidden.val(val);
                    $badgeText.text(val);
                    $modal.modal("hide");
                });

                $input.on("keydown", function (e) {
                    if (e.which === 13) {
                        e.preventDefault();
                        $modalSave.trigger("click");
                    }
                });
            })();

            /* BOOTSTRAP */
            if (CONSIGNMENT_INITIAL_ITEMS.length === 0) {
                addItemRow();
            } else {
                for (var r = 0; r < CONSIGNMENT_INITIAL_ITEMS.length; r++) {
                    addItemRow(CONSIGNMENT_INITIAL_ITEMS[r]);
                }
            }
            recalcTotals();
        });
    </script>

</body>

</html>