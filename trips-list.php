<?php

include 'constant.php';
include 'session.php';

requirePermission('trip.view');

/* =========================================================
   AJAX — toggle active
   ========================================================= */
if (isset($_GET['action']) && $_GET['action'] === 'toggle_active') {

    header('Content-Type: application/json');

    if (!hasPermission('trip.delete')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied.']);
        exit;
    }

    $id = (int) ($_POST['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid trip ID.']);
        exit;
    }

    /* Branch restriction — only for users without trip.view.all */
    $branchFilter = '';
    if (!hasPermission('trip.view.all')) {
        $myBranch = (int) ($_SESSION['branch_id'] ?? 0);
        if ($myBranch > 0) {
            $branchFilter = " AND (branch_id = $myBranch
                                OR from_branch_id = $myBranch
                                OR to_branch_id   = $myBranch)";
        } else {
            $branchFilter = " AND 1=0";
        }
    }

    $check = mysqli_query(
        $conn,
        "SELECT id, trip_no, active FROM trip
         WHERE id = $id $branchFilter LIMIT 1"
    );

    if (!$check || mysqli_num_rows($check) !== 1) {
        echo json_encode(['success' => false, 'message' => 'Trip not found.']);
        exit;
    }

    $row = mysqli_fetch_assoc($check);
    $new = ((int) $row['active'] === 1) ? 0 : 1;

    $upd = mysqli_query($conn, "UPDATE trip SET active = $new WHERE id = $id");

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
            $flash = 'Trip added successfully.';
            break;
        case 'updated':
            $flash = 'Trip updated successfully.';
            break;
    }
}

/* =========================================================
   PAGE DATA
   ========================================================= */
$pageTitle = 'Trips | Billing Portal';

/* Branch restriction:
   - Admin OR anyone with trip.view.all  →  sees everything
   - Otherwise                            →  only trips touching their branch */
$restrictedToBranch = !hasPermission('trip.view.all');

$where = '';
if ($restrictedToBranch) {
    $myBranch = (int) ($_SESSION['branch_id'] ?? 0);
    if ($myBranch > 0) {
        $where = "WHERE (
                    t.branch_id      = $myBranch
                 OR t.from_branch_id = $myBranch
                 OR t.to_branch_id   = $myBranch
                 )";
    } else {
        $where = "WHERE 1=0";
    }
}

$sql = "SELECT
            t.id, t.trip_no, t.start_date, t.status, t.active,
            l.lorry_number,
            d.driver_name,
            bf.branch_name AS from_branch_name,
            bf.branch_code AS from_branch_code,
            bt.branch_name AS to_branch_name,
            bt.branch_code AS to_branch_code
        FROM trip t
        LEFT JOIN lorry  l  ON l.id  = t.lorry_id
        LEFT JOIN driver d  ON d.id  = t.driver_id
        LEFT JOIN branch bf ON bf.id = t.from_branch_id
        LEFT JOIN branch bt ON bt.id = t.to_branch_id
        $where
        ORDER BY t.id DESC";

$result = mysqli_query($conn, $sql);
if (!$result) die("Query failed: " . mysqli_error($conn));

$trips = [];
while ($row = mysqli_fetch_assoc($result)) {
    $trips[] = $row;
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

        #tripsTable {
            font-size: 0.95rem;
        }

        #tripsTable thead th {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #4a4c59;
            padding: 0.85rem 0.9rem;
            border-bottom: 2px solid #e3e6f0;
            white-space: nowrap;
        }

        #tripsTable tbody td {
            padding: 0.9rem 0.9rem;
            vertical-align: middle;
            color: #3a3b45;
            white-space: nowrap;
        }

        #tripsTable tbody tr:hover>td {
            background-color: #f7f9fc;
        }

        .trip-no {
            font-size: 0.95rem;
            font-weight: 700;
            color: #2c2e3e;
            font-family: "SFMono-Regular", Menlo, Consolas, monospace;
            letter-spacing: 0.02em;
        }

        .route-cell {
            font-size: 0.88rem;
            color: #3a3b45;
        }

        .route-arrow {
            color: #b7b9cc;
            margin: 0 .25rem;
        }

        .branch-badge {
            display: inline-block;
            padding: 0.25rem 0.6rem;
            border-radius: 0.3rem;
            font-size: 0.8rem;
            font-weight: 600;
            background-color: #e8fbf4;
            color: #0c7d5b;
            border: 1px solid #c5f0e1;
        }

        .branch-code-mini {
            font-size: 0.72rem;
            font-weight: 500;
            color: #6e707e;
            margin-left: 2px;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.65rem;
            border-radius: 0.35rem;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 22px;
            margin: 0;
            vertical-align: middle;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .switch .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #b7b9cc;
            transition: .2s;
            border-radius: 22px;
        }

        .switch .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .2s;
            border-radius: 50%;
        }

        .switch input:checked+.slider {
            background-color: #1cc88a;
        }

        .switch input:checked+.slider:before {
            transform: translateX(22px);
        }

        .switch input:disabled+.slider {
            opacity: .6;
            cursor: not-allowed;
        }

        #statusFilter .nav-link {
            padding: .35rem 1rem;
            border-radius: 2rem;
            font-size: .85rem;
            font-weight: 500;
            color: #6e707e;
            white-space: nowrap;
        }

        #statusFilter .nav-link.active {
            background-color: #4e73df;
            color: #fff;
            font-weight: 600;
        }

        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #d1d3e2;
            border-radius: .35rem;
            padding: .35rem .6rem;
            font-size: .9rem;
            margin-left: .5rem;
        }

        .dataTables_wrapper .dataTables_length select {
            border: 1px solid #d1d3e2;
            border-radius: .35rem;
            padding: .35rem 1.5rem .35rem .6rem;
            font-size: .9rem;
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
                            <i class="fas fa-route mr-2"></i>Trips
                        </h1>

                        <?php if (hasPermission('trip.create')): ?>
                            <a href="trip.php"
                                class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                                <i class="fas fa-plus fa-sm text-white-50 mr-1"></i>
                                Add Trip
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
                                All Trips
                                <?php if ($restrictedToBranch): ?>
                                    <span class="text-muted small ml-1">
                                        (trips touching your branch)
                                    </span>
                                <?php endif; ?>
                            </h6>
                        </div>

                        <div class="card-body">

                            <ul class="nav nav-pills mb-3" id="statusFilter">
                                <li class="nav-item">
                                    <a class="nav-link active" href="#" data-filter="all">All</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="#" data-filter="Scheduled">Scheduled</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="#" data-filter="In Progress">In Progress</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="#" data-filter="Delivered">Delivered</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="#" data-filter="Billed">Billed</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="#" data-filter="Paid">Paid</a>
                                </li>
                            </ul>

                            <div class="table-responsive">

                                <table class="table table-bordered table-hover"
                                    id="tripsTable"
                                    width="100%"
                                    cellspacing="0">

                                    <thead class="thead-light">
                                        <tr>
                                            <th>Trip No</th>
                                            <th>Date</th>
                                            <th>Route</th>
                                            <th>Lorry</th>
                                            <th>Driver</th>
                                            <th>Status</th>
                                            <th width="130" class="text-center">Action</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <?php foreach ($trips as $row):
                                            $isActive   = ((int) $row['active'] === 1);
                                            $status     = $row['status'] ?: 'Scheduled';
                                            $badgeClass = statusBadgeClass($status);
                                        ?>
                                            <tr
                                                data-id="<?= (int) $row['id'] ?>"
                                                data-active="<?= $isActive ? '1' : '0' ?>"
                                                data-status="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"
                                                class="<?= $isActive ? '' : 'row-inactive' ?>">

                                                <td class="align-middle">
                                                    <a href="trip-view.php?id=<?= (int) $row['id'] ?>"
                                                        class="trip-no text-decoration-none">
                                                        <?= htmlspecialchars($row['trip_no'], ENT_QUOTES, 'UTF-8') ?>
                                                    </a>
                                                </td>

                                                <td class="align-middle small">
                                                    <?= date('d M Y', strtotime($row['start_date'])) ?>
                                                </td>

                                                <td class="align-middle route-cell">
                                                    <span class="branch-badge">
                                                        <?= htmlspecialchars($row['from_branch_name'] ?: '—', ENT_QUOTES, 'UTF-8') ?>
                                                        <?php if (!empty($row['from_branch_code'])): ?>
                                                            <span class="branch-code-mini">(<?= htmlspecialchars($row['from_branch_code'], ENT_QUOTES, 'UTF-8') ?>)</span>
                                                        <?php endif; ?>
                                                    </span>
                                                    <i class="fas fa-arrow-right route-arrow"></i>
                                                    <span class="branch-badge">
                                                        <?= htmlspecialchars($row['to_branch_name'] ?: '—', ENT_QUOTES, 'UTF-8') ?>
                                                        <?php if (!empty($row['to_branch_code'])): ?>
                                                            <span class="branch-code-mini">(<?= htmlspecialchars($row['to_branch_code'], ENT_QUOTES, 'UTF-8') ?>)</span>
                                                        <?php endif; ?>
                                                    </span>
                                                </td>

                                                <td class="align-middle small">
                                                    <?php if (!empty($row['lorry_number'])): ?>
                                                        <span class="font-weight-bold">
                                                            <?= htmlspecialchars($row['lorry_number'], ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>

                                                <td class="align-middle small">
                                                    <?php if (!empty($row['driver_name'])): ?>
                                                        <?= htmlspecialchars($row['driver_name'], ENT_QUOTES, 'UTF-8') ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>

                                                <td class="align-middle">
                                                    <span class="status-badge <?= $badgeClass ?>">
                                                        <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                </td>

                                                <td class="align-middle text-center text-nowrap">
                                                    <div class="table-actions">

                                                        <?php if (hasPermission('trip.edit')): ?>
                                                            <a href="trip-view.php?id=<?= (int) $row['id'] ?>"
                                                                class="btn btn-sm btn-primary"
                                                                title="Open trip">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                        <?php endif; ?>

                                                        <?php if (hasPermission('trip.delete')): ?>
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

            var table = $('#tripsTable').DataTable({
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
                    targets: [6]
                }],
                language: {
                    search: '',
                    searchPlaceholder: 'Search trips...',
                    lengthMenu: 'Show _MENU_',
                    info: 'Showing _START_ to _END_ of _TOTAL_',
                    infoEmpty: 'No trips',
                    infoFiltered: '(filtered from _MAX_)',
                    zeroRecords: 'No matching trips found',
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
                var status = String($(rowNode).data('status'));

                return status === currentFilter;
            });

            $('#statusFilter .nav-link').on('click', function(e) {
                e.preventDefault();
                $('#statusFilter .nav-link').removeClass('active');
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
                        url: 'trips-list.php?action=toggle_active',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            id: id
                        }
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