<?php

include 'session.php';
include 'constant.php';

requirePermission('consignment.view');

/* =========================================================
   AJAX — toggle active
   ========================================================= */
if (isset($_GET['action']) && $_GET['action'] === 'toggle_active') {

    header('Content-Type: application/json');

    if (!hasPermission('consignment.delete')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied.']);
        exit;
    }

    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
        exit;
    }

    $branchFilter = '';
    if (!hasPermission('trip.view.all')) {
        $myBranch = (int) ($_SESSION['branch_id'] ?? 0);
        $branchFilter = $myBranch > 0 ? " AND branch_id = $myBranch" : " AND 1=0";
    }

    $check = mysqli_query($conn, "SELECT id, consignment_no, active FROM consignment WHERE id = $id $branchFilter LIMIT 1");
    if (!$check || mysqli_num_rows($check) !== 1) {
        echo json_encode(['success' => false, 'message' => 'Consignment not found.']);
        exit;
    }

    $row = mysqli_fetch_assoc($check);
    $new = ((int) $row['active'] === 1) ? 0 : 1;

    $upd = mysqli_query($conn, "UPDATE consignment SET active = $new WHERE id = $id");
    if (!$upd) {
        echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
        exit;
    }

    echo json_encode(['success' => true, 'id' => $id, 'active' => $new]);
    exit;
}

/* =========================================================
   FLASH
   ========================================================= */
$flash = '';
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'added':
            $flash = 'Consignment added successfully.';
            break;
        case 'updated':
            $flash = 'Consignment updated successfully.';
            break;
    }
}

/* =========================================================
   PAGE DATA
   ========================================================= */
$pageTitle = 'Consignments | Billing Portal';

$canSeeAll = hasPermission('trip.view.all');
$myBranch  = (int) ($_SESSION['branch_id'] ?? 0);

$where = '';
if (!$canSeeAll) {
    $where = $myBranch > 0 ? "WHERE branch_id = $myBranch" : "WHERE 1=0";
}

/* Single-table SELECT */
$sql = "SELECT id, consignment_no, consignment_date,
               consignor_party_id, consignee_party_id,
               notes, branch_id, created_by, active, created_at
        FROM consignment
        $where
        ORDER BY id DESC";

$result = mysqli_query($conn, $sql);
if (!$result) die("Query failed: " . mysqli_error($conn));

$consignments = [];
while ($row = mysqli_fetch_assoc($result)) {
    $consignments[] = $row;
}

/* Enrich — party names */
$partyNames = [];
$resP = mysqli_query($conn, "SELECT id, legal_name FROM party");
if ($resP) while ($p = mysqli_fetch_assoc($resP)) $partyNames[(int)$p['id']] = $p['legal_name'];

/* Enrich — user names */
$userNames = [];
$resU = mysqli_query($conn, "SELECT id, full_name, username FROM `user`");
if ($resU) {
    while ($u = mysqli_fetch_assoc($resU)) {
        $userNames[(int)$u['id']] = $u['full_name'] ?: $u['username'];
    }
}

/* Enrich — item counts + totals per consignment */
$itemCounts = [];
$totals     = [];

$resI = mysqli_query($conn, "SELECT consignment_id, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total FROM consignment_inventory WHERE active = 1 GROUP BY consignment_id");
if ($resI) {
    while ($i = mysqli_fetch_assoc($resI)) {
        $itemCounts[(int)$i['consignment_id']] = (int)$i['cnt'];
        $totals[(int)$i['consignment_id']]     = (float)$i['total'];
    }
}

foreach ($consignments as &$c) {
    $sid = (int) $c['consignor_party_id'];
    $rid = (int) $c['consignee_party_id'];
    $uid = (int) $c['created_by'];
    $cid = (int) $c['id'];

    $c['consignor_name'] = $sid > 0 ? ($partyNames[$sid] ?? '') : '';
    $c['consignee_name'] = $rid > 0 ? ($partyNames[$rid] ?? '') : '';
    $c['created_by_name'] = $uid > 0 ? ($userNames[$uid] ?? '') : '';
    $c['item_count']     = $itemCounts[$cid] ?? 0;
    $c['total_amount']   = $totals[$cid] ?? 0;
}
unset($c);
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title><?= $pageTitle ?></title>

    <?php include 'layout/header.php'; ?>

    <link rel="stylesheet"
        href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap4.min.css">

    <style>
        table.dataTable tbody tr.row-inactive>td {
            background-color: #fdecea !important;
            color: #842029;
        }

        table.dataTable tbody tr.row-inactive:hover>td {
            background-color: #fbd9d4 !important;
        }

        #consignmentsTable { font-size: 0.95rem; }

        #consignmentsTable thead th {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #4a4c59;
            padding: 0.85rem 0.9rem;
            border-bottom: 2px solid #e3e6f0;
            white-space: nowrap;
        }

        #consignmentsTable tbody td {
            padding: 0.9rem 0.9rem;
            vertical-align: middle;
            color: #3a3b45;
            white-space: nowrap;
        }

        #consignmentsTable tbody tr:hover>td {
            background-color: #f7f9fc;
        }

        .consignment-no {
            font-size: 0.95rem;
            font-weight: 700;
            color: #2c2e3e;
            font-family: "SFMono-Regular", Menlo, Consolas, monospace;
            letter-spacing: 0.02em;
            text-decoration: none;
        }

        .consignment-no:hover {
            color: #4e73df;
            text-decoration: underline;
        }

        .route-cell { font-size: .88rem; color: #3a3b45; }

        .route-arrow { color: #b7b9cc; margin: 0 .35rem; }

        .party-chip {
            display: inline-block;
            padding: .18rem .55rem;
            border-radius: 4px;
            font-size: .78rem;
            font-weight: 600;
            background: #eef2ff;
            color: #3f51b5;
            border: 1px solid #dbe2ff;
        }

        .party-chip.to {
            background: #e8fbf4;
            color: #0c7d5b;
            border-color: #c5f0e1;
        }

        .amount-cell {
            text-align: right;
            font-family: "SFMono-Regular", Menlo, Consolas, monospace;
            font-weight: 700;
            color: #2c2e3e;
            font-size: .92rem;
        }

        .created-by-badge {
            display: inline-block;
            padding: 0.2rem 0.55rem;
            border-radius: 5px;
            font-size: 0.76rem;
            font-weight: 600;
            background-color: #f5f7fb;
            color: #5a5c69;
            border: 1px solid #e6e9f0;
        }

        .item-count-badge {
            display: inline-block;
            padding: .15rem .5rem;
            border-radius: 999px;
            font-size: .72rem;
            font-weight: 700;
            background: #f5f7fb;
            color: #5a5c69;
            border: 1px solid #e6e9f0;
            min-width: 30px;
            text-align: center;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 22px;
            margin: 0;
            vertical-align: middle;
        }

        .switch input { opacity: 0; width: 0; height: 0; }

        .switch .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #b7b9cc;
            transition: .2s;
            border-radius: 22px;
        }

        .switch .slider:before {
            position: absolute;
            content: "";
            height: 16px; width: 16px;
            left: 3px; bottom: 3px;
            background-color: white;
            transition: .2s;
            border-radius: 50%;
        }

        .switch input:checked+.slider { background-color: #1cc88a; }
        .switch input:checked+.slider:before { transform: translateX(22px); }
        .switch input:disabled+.slider { opacity: .6; cursor: not-allowed; }

        #activeFilter .nav-link {
            padding: .35rem 1rem;
            border-radius: 2rem;
            font-size: .9rem;
            font-weight: 500;
            color: #6e707e;
        }

        #activeFilter .nav-link.active {
            background-color: #4e73df;
            color: #fff;
            font-weight: 600;
        }

        .dataTables_wrapper .dataTables_filter input,
        .dataTables_wrapper .dataTables_length select {
            border: 1px solid #d1d3e2;
            border-radius: .35rem;
            font-size: .9rem;
        }

        .dataTables_wrapper .dataTables_filter input {
            padding: .35rem .6rem;
            margin-left: .5rem;
        }

        .dataTables_wrapper .dataTables_length select {
            padding: .35rem 1.5rem .35rem .6rem;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #4e73df !important;
            border-color: #4e73df !important;
            color: #fff !important;
            border-radius: .35rem;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #eaecf4 !important;
            border-color: #eaecf4 !important;
            color: #4e73df !important;
            border-radius: .35rem;
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
                            <i class="fas fa-file-alt mr-2"></i>Consignments
                        </h1>

                        <?php if (hasPermission('consignment.create')): ?>
                            <a href="consignment.php"
                                class="d-inline-block btn btn-sm btn-primary shadow-sm">
                                <i class="fas fa-plus fa-sm text-white-50 mr-1"></i>
                                Add Consignment
                            </a>
                        <?php endif; ?>
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

                    <div class="card shadow mb-4">

                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-list mr-1"></i>
                                All Consignments
                                <?php if (!$canSeeAll): ?>
                                    <span class="text-muted small ml-1">(your branch)</span>
                                <?php endif; ?>
                            </h6>
                        </div>

                        <div class="card-body">

                            <ul class="nav nav-pills mb-3" id="activeFilter">
                                <li class="nav-item">
                                    <a class="nav-link active" href="#" data-filter="all">All</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="#" data-filter="active">Active</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="#" data-filter="inactive">Inactive</a>
                                </li>
                            </ul>

                            <div class="table-responsive">

                                <table class="table table-bordered table-hover"
                                    id="consignmentsTable"
                                    width="100%"
                                    cellspacing="0">

                                    <thead class="thead-light">
                                        <tr>
                                            <th width="50">#</th>
                                            <th>Consignment No</th>
                                            <th>Date</th>
                                            <th>Route</th>
                                            <th class="text-center">Items</th>
                                            <th class="text-right">Total</th>
                                            <th>Created By</th>
                                            <th width="140" class="text-center">Action</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <?php foreach ($consignments as $i => $row):
                                            $isActive = ((int) $row['active'] === 1);
                                        ?>
                                            <tr
                                                data-id="<?= (int) $row['id'] ?>"
                                                data-active="<?= $isActive ? '1' : '0' ?>"
                                                class="<?= $isActive ? '' : 'row-inactive' ?>">

                                                <td class="text-muted small align-middle">
                                                    <?= $i + 1 ?>
                                                </td>

                                                <td class="align-middle">
                                                    <a href="consignment-print.php?id=<?= (int) $row['id'] ?>"
                                                        target="_blank"
                                                        class="consignment-no">
                                                        <?= htmlspecialchars($row['consignment_no'], ENT_QUOTES, 'UTF-8') ?>
                                                    </a>
                                                </td>

                                                <td class="align-middle small text-nowrap">
                                                    <?= date('d M Y', strtotime($row['consignment_date'])) ?>
                                                </td>

                                                <td class="align-middle route-cell">
                                                    <span class="party-chip">
                                                        <?= htmlspecialchars($row['consignor_name'] ?: '—', ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                    <i class="fas fa-arrow-right route-arrow"></i>
                                                    <span class="party-chip to">
                                                        <?= htmlspecialchars($row['consignee_name'] ?: '—', ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                </td>

                                                <td class="align-middle text-center">
                                                    <span class="item-count-badge">
                                                        <?= (int) $row['item_count'] ?>
                                                    </span>
                                                </td>

                                                <td class="align-middle amount-cell">
                                                    ₹ <?= number_format((float) $row['total_amount'], 2) ?>
                                                </td>

                                                <td class="align-middle">
                                                    <?php if (!empty($row['created_by_name'])): ?>
                                                        <span class="created-by-badge">
                                                            <?= htmlspecialchars($row['created_by_name'], ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted small">—</span>
                                                    <?php endif; ?>
                                                </td>

                                                <td class="align-middle text-center text-nowrap">
                                                    <div class="table-actions d-inline-flex align-items-center" style="gap: 6px;">

                                                        <a href="consignment-print.php?id=<?= (int) $row['id'] ?>"
                                                            target="_blank"
                                                            class="btn btn-sm btn-secondary"
                                                            title="View / Print">
                                                            <i class="fas fa-print"></i>
                                                        </a>

                                                        <?php if (hasPermission('consignment.edit')): ?>
                                                            <a href="consignment.php?id=<?= (int) $row['id'] ?>"
                                                                class="btn btn-sm btn-primary"
                                                                title="Edit">
                                                                <i class="fas fa-pen"></i>
                                                            </a>
                                                        <?php endif; ?>

                                                        <?php if (hasPermission('consignment.delete')): ?>
                                                            <label class="switch mb-0"
                                                                title="<?= $isActive ? 'Mark as Inactive' : 'Mark as Active' ?>">
                                                                <input type="checkbox"
                                                                    class="js-toggle-active"
                                                                    data-id="<?= (int) $row['id'] ?>"
                                                                    <?= $isActive ? 'checked' : '' ?>>
                                                                <span class="slider"></span>
                                                            </label>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>

                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>

                                </table>

                            </div>

                        </div>

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

            var table = $('#consignmentsTable').DataTable({
                order: [
                    [0, 'desc']
                ],
                pageLength: 10,
                lengthMenu: [
                    [10, 25, 50, -1],
                    [10, 25, 50, 'All']
                ],
                columnDefs: [{
                    orderable: false,
                    targets: [4, 7]
                }],
                language: {
                    search: '',
                    searchPlaceholder: 'Search consignments...',
                    lengthMenu: 'Show _MENU_',
                    info: 'Showing _START_ to _END_ of _TOTAL_',
                    infoEmpty: 'No consignments',
                    infoFiltered: '(filtered from _MAX_)',
                    zeroRecords: 'No matching consignments found',
                    paginate: {
                        previous: '<i class="fas fa-chevron-left"></i>',
                        next: '<i class="fas fa-chevron-right"></i>'
                    }
                }
            });

            var currentFilter = 'all';

            $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                if (currentFilter === 'all') return true;
                var rowNode = table.row(dataIndex).node();
                var isActive = String($(rowNode).data('active')) === '1';
                if (currentFilter === 'active') return isActive;
                if (currentFilter === 'inactive') return !isActive;
                return true;
            });

            $('#activeFilter .nav-link').on('click', function(e) {
                e.preventDefault();
                $('#activeFilter .nav-link').removeClass('active');
                $(this).addClass('active');
                currentFilter = $(this).data('filter');
                table.draw();
            });

            $(document).on('change', '.js-toggle-active', function() {

                var $cb = $(this);
                var id = $cb.data('id');
                var isNow = $cb.is(':checked');
                var $row = $cb.closest('tr');

                $cb.prop('disabled', true);

                $.ajax({
                        url: 'consignments-list.php?action=toggle_active',
                        type: 'POST',
                        dataType: 'json',
                        data: { id: id }
                    })
                    .done(function(res) {
                        if (!res.success) {
                            $cb.prop('checked', !isNow);
                            if (res.message) alert(res.message);
                            return;
                        }
                        $row.attr('data-active', res.active);
                        if (res.active === 1) {
                            $row.removeClass('row-inactive');
                        } else {
                            $row.addClass('row-inactive');
                        }
                        table.draw(false);
                    })
                    .fail(function() {
                        $cb.prop('checked', !isNow);
                    })
                    .always(function() {
                        $cb.prop('disabled', false);
                    });
            });
        });
    </script>

</body>

</html>