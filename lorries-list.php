<?php

include 'constant.php';
include 'session.php';

requirePermission('lorry.view');

/* =========================================================
   AJAX — toggle active
   ========================================================= */
if (isset($_GET['action']) && $_GET['action'] === 'toggle_active') {

    header('Content-Type: application/json');

    if (!hasPermission('lorry.delete')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied.']);
        exit;
    }

    $id = (int) ($_POST['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid lorry ID.']);
        exit;
    }

    $branchFilter = '';
    if (!isAdmin()) {
        $myBranch = (int) ($_SESSION['branch_id'] ?? 0);
        $branchFilter = " AND branch_id = $myBranch";
    }

    $check = mysqli_query(
        $conn,
        "SELECT id, lorry_number, active FROM lorry
         WHERE id = $id $branchFilter LIMIT 1"
    );

    if (!$check || mysqli_num_rows($check) !== 1) {
        echo json_encode(['success' => false, 'message' => 'Lorry not found.']);
        exit;
    }

    $row = mysqli_fetch_assoc($check);
    $new = ((int) $row['active'] === 1) ? 0 : 1;

    $upd = mysqli_query($conn, "UPDATE lorry SET active = $new WHERE id = $id");

    if (!$upd) {
        echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'id'      => $id,
        'active'  => $new,
    ]);
    exit;
}

/* =========================================================
   FLASH
   ========================================================= */
$flash = '';
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'added':
            $flash = 'Lorry added successfully.';
            break;
        case 'updated':
            $flash = 'Lorry updated successfully.';
            break;
    }
}

/* =========================================================
   DATA
   ========================================================= */
$pageTitle = 'Lorries | Billing Portal';

$where = '';
if (!isAdmin()) {
    $myBranch = (int) ($_SESSION['branch_id'] ?? 0);
    $where = "WHERE l.branch_id = $myBranch";
}

$sql = "SELECT l.id, l.branch_id, l.lorry_number, l.lorry_type, l.capacity,
               l.owner_name, l.address, l.city, l.state, l.pincode,
               l.active, l.created_at,
               b.branch_name
        FROM lorry l
        LEFT JOIN branch b ON b.id = l.branch_id
        $where
        ORDER BY l.id DESC";

$result = mysqli_query($conn, $sql);
if (!$result) die("Query failed: " . mysqli_error($conn));

$lorries = [];
while ($row = mysqli_fetch_assoc($result)) {
    $lorries[] = $row;
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

        #lorriesTable {
            font-size: 0.95rem;
        }

        #lorriesTable thead th {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #4a4c59;
            padding: 0.85rem 0.9rem;
            border-bottom: 2px solid #e3e6f0;
            white-space: nowrap;
        }

        #lorriesTable tbody td {
            padding: 0.9rem 0.9rem;
            vertical-align: middle;
            color: #3a3b45;
            white-space: nowrap;
        }

        #lorriesTable tbody tr:hover>td {
            background-color: #f7f9fc;
        }

        .lorry-number {
            font-size: 1.02rem;
            font-weight: 700;
            color: #2c2e3e;
            font-family: "SFMono-Regular", Menlo, Consolas, monospace;
            letter-spacing: 0.02em;
        }

        .lorry-owner,
        .lorry-capacity {
            color: #3a3b45;
            font-weight: 500;
        }

        .lorry-type-badge {
            display: inline-block;
            padding: 0.35rem 0.7rem;
            border-radius: 0.35rem;
            font-size: 0.82rem;
            font-weight: 600;
            background-color: #eef2ff;
            color: #3f51b5;
            border: 1px solid #dbe2ff;
            letter-spacing: 0.02em;
        }

        .branch-badge {
            display: inline-block;
            padding: 0.25rem 0.65rem;
            border-radius: 0.35rem;
            font-size: 0.8rem;
            font-weight: 600;
            background-color: #e8fbf4;
            color: #0c7d5b;
            border: 1px solid #c5f0e1;
        }

        .cell-empty {
            color: #b7b9cc;
            font-size: 1rem;
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
                            <i class="fas fa-truck mr-2"></i>Lorries
                        </h1>

                        <?php if (hasPermission('lorry.create')): ?>
                            <a href="lorry.php"
                                class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                                <i class="fas fa-plus fa-sm text-white-50 mr-1"></i>
                                Add Lorry
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
                                All Lorries
                                <?php if (!isAdmin()): ?>
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
                                    id="lorriesTable"
                                    width="100%"
                                    cellspacing="0">

                                    <thead class="thead-light">
                                        <tr>
                                            <th width="50">#</th>
                                            <th>Lorry Number</th>
                                            <th>Owner Name</th>
                                            <th>Capacity</th>
                                            <th>Type</th>
                                            <th>Branch</th>
                                            <th width="140" class="text-center">Action</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <?php foreach ($lorries as $i => $row):
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
                                                    <span class="lorry-number">
                                                        <?= htmlspecialchars($row['lorry_number'], ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                </td>

                                                <td class="align-middle">
                                                    <?php if (!empty($row['owner_name'])): ?>
                                                        <span class="lorry-owner">
                                                            <?= htmlspecialchars($row['owner_name'], ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="cell-empty">—</span>
                                                    <?php endif; ?>
                                                </td>

                                                <td class="align-middle">
                                                    <?php if (!empty($row['capacity'])): ?>
                                                        <span class="lorry-capacity">
                                                            <i class="fas fa-weight-hanging text-gray-500 mr-1"></i>
                                                            <?= htmlspecialchars($row['capacity'], ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="cell-empty">—</span>
                                                    <?php endif; ?>
                                                </td>

                                                <td class="align-middle">
                                                    <?php if (!empty($row['lorry_type'])): ?>
                                                        <span class="lorry-type-badge">
                                                            <?= htmlspecialchars($row['lorry_type'], ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="cell-empty">—</span>
                                                    <?php endif; ?>
                                                </td>

                                                <td class="align-middle">
                                                    <?php if (!empty($row['branch_name'])): ?>
                                                        <span class="branch-badge">
                                                            <?= htmlspecialchars($row['branch_name'], ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted small">—</span>
                                                    <?php endif; ?>
                                                </td>

                                                <td class="align-middle text-center text-nowrap">
                                                    <div class="table-actions">

                                                        <?php if (hasPermission('lorry.edit')): ?>
                                                            <a href="lorry.php?id=<?= (int) $row['id'] ?>"
                                                                class="btn btn-sm btn-primary"
                                                                title="Edit lorry">
                                                                <i class="fas fa-pen"></i>
                                                            </a>
                                                        <?php endif; ?>

                                                        <?php if (hasPermission('lorry.delete')): ?>
                                                            <label class="switch mb-0"
                                                                title="<?= $isActive ? 'Mark as Inactive' : 'Mark as Active' ?>">
                                                                <input
                                                                    type="checkbox"
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

            var table = $('#lorriesTable').DataTable({
                order: [
                    [0, 'asc']
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
                    searchPlaceholder: 'Search lorries...',
                    lengthMenu: 'Show _MENU_',
                    info: 'Showing _START_ to _END_ of _TOTAL_',
                    infoEmpty: 'No lorries',
                    infoFiltered: '(filtered from _MAX_)',
                    zeroRecords: 'No matching lorries found',
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
                        url: 'lorries-list.php?action=toggle_active',
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