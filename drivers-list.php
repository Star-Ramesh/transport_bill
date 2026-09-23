<?php

include 'constant.php';
include 'session.php';

requirePermission('driver.view');

/* =========================================================
   AJAX — toggle active
   ========================================================= */
if (isset($_GET['action']) && $_GET['action'] === 'toggle_active') {

    header('Content-Type: application/json');

    if (!hasPermission('driver.delete')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied.']);
        exit;
    }

    $id = (int) ($_POST['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid driver ID.']);
        exit;
    }

    $check = mysqli_query(
        $conn,
        "SELECT id, driver_name, active FROM driver WHERE id = $id LIMIT 1"
    );

    if (!$check || mysqli_num_rows($check) !== 1) {
        echo json_encode(['success' => false, 'message' => 'Driver not found.']);
        exit;
    }

    $row = mysqli_fetch_assoc($check);
    $new = ((int) $row['active'] === 1) ? 0 : 1;

    $upd = mysqli_query($conn, "UPDATE driver SET active = $new WHERE id = $id");

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
            $flash = 'Driver added successfully.';
            break;
        case 'updated':
            $flash = 'Driver updated successfully.';
            break;
    }
}

/* =========================================================
   PAGE DATA
   ========================================================= */
$pageTitle = 'Drivers | Billing Portal';

$sql = "SELECT d.id, d.driver_name, d.phone, d.license_no, d.license_expiry,
               d.city, d.state, d.active, d.created_at,
               b.branch_name
        FROM driver d
        LEFT JOIN branch b ON b.id = d.branch_id
        ORDER BY d.id DESC";

$result = mysqli_query($conn, $sql);
if (!$result) die("Query failed: " . mysqli_error($conn));

$drivers = [];
while ($row = mysqli_fetch_assoc($result)) {
    $drivers[] = $row;
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
        /* Inactive row tint */
        table.dataTable tbody tr.row-inactive>td {
            background-color: #fdecea !important;
            color: #842029;
        }

        table.dataTable tbody tr.row-inactive:hover>td {
            background-color: #fbd9d4 !important;
        }

        /* Readability */
        #driversTable {
            font-size: 0.95rem;
        }

        #driversTable thead th {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #4a4c59;
            padding: 0.85rem 0.9rem;
            border-bottom: 2px solid #e3e6f0;
            white-space: nowrap;
        }

        #driversTable tbody td {
            padding: 0.9rem 0.9rem;
            vertical-align: middle;
            color: #3a3b45;
            white-space: nowrap;
        }

        #driversTable tbody tr:hover>td {
            background-color: #f7f9fc;
        }

        .driver-name {
            font-size: 1.02rem;
            font-weight: 700;
            color: #2c2e3e;
        }

        .driver-phone a {
            color: inherit;
            text-decoration: none;
        }

        .driver-phone a:hover {
            color: #4e73df;
            text-decoration: underline;
        }

        .license-code {
            font-family: "SFMono-Regular", Menlo, Consolas, monospace;
            font-size: 0.85rem;
            color: #3a3b45;
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

        /* License expiry warning */
        .license-warning {
            display: inline-block;
            padding: 0.15rem 0.5rem;
            border-radius: 0.3rem;
            font-size: 0.75rem;
            font-weight: 700;
            margin-top: 3px;
        }

        .license-warning.expired {
            background-color: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
        }

        .license-warning.expiring {
            background-color: #fff3cd;
            color: #664d03;
            border: 1px solid #ffecb5;
        }

        /* Toggle switch */
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

        /* Filter pills */
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

        /* DataTables tweaks */
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
                            <i class="fas fa-id-card mr-2"></i>Drivers
                        </h1>

                        <?php if (hasPermission('driver.create')): ?>
                            <a href="driver.php"
                                class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                                <i class="fas fa-user-plus fa-sm text-white-50 mr-1"></i>
                                Add Driver
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
                                All Drivers
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
                                    id="driversTable"
                                    width="100%"
                                    cellspacing="0">

                                    <thead class="thead-light">
                                        <tr>
                                            <th width="50">#</th>
                                            <th>Driver Name</th>
                                            <th>Phone</th>
                                            <th>License</th>
                                            <th>Branch</th>
                                            <th width="140" class="text-center">Action</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <?php foreach ($drivers as $i => $row):
                                            $isActive = ((int) $row['active'] === 1);

                                            /* License expiry check */
                                            $licenseWarning = '';
                                            $licenseClass   = '';
                                            if (!empty($row['license_expiry'])) {
                                                $expiryTs = strtotime($row['license_expiry']);
                                                $todayTs  = strtotime(date('Y-m-d'));
                                                $days     = (int) floor(($expiryTs - $todayTs) / 86400);

                                                if ($days < 0) {
                                                    $licenseWarning = 'Expired';
                                                    $licenseClass   = 'expired';
                                                } elseif ($days <= 30) {
                                                    $licenseWarning = 'Expiring in ' . $days . ' day' . ($days === 1 ? '' : 's');
                                                    $licenseClass   = 'expiring';
                                                }
                                            }
                                        ?>
                                            <tr
                                                data-id="<?= (int) $row['id'] ?>"
                                                data-active="<?= $isActive ? '1' : '0' ?>"
                                                class="<?= $isActive ? '' : 'row-inactive' ?>">

                                                <td class="text-muted small align-middle">
                                                    <?= $i + 1 ?>
                                                </td>

                                                <td class="align-middle">
                                                    <span class="driver-name">
                                                        <?= htmlspecialchars($row['driver_name'], ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                </td>

                                                <td class="align-middle small driver-phone">
                                                    <?php if (!empty($row['phone'])): ?>
                                                        <i class="fas fa-phone fa-xs text-gray-500 mr-1"></i>
                                                        <a href="tel:<?= htmlspecialchars($row['phone'], ENT_QUOTES, 'UTF-8') ?>">
                                                            <?= htmlspecialchars($row['phone'], ENT_QUOTES, 'UTF-8') ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>

                                                <td class="align-middle small">
                                                    <?php if (!empty($row['license_no'])): ?>
                                                        <span class="license-code">
                                                            <?= htmlspecialchars($row['license_no'], ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                        <?php if ($licenseWarning): ?>
                                                            <br>
                                                            <span class="license-warning <?= $licenseClass ?>">
                                                                <?= htmlspecialchars($licenseWarning, ENT_QUOTES, 'UTF-8') ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
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

                                                        <?php if (hasPermission('driver.edit')): ?>
                                                            <a href="driver.php?id=<?= (int) $row['id'] ?>"
                                                                class="btn btn-sm btn-primary"
                                                                title="Edit driver">
                                                                <i class="fas fa-pen"></i>
                                                            </a>
                                                        <?php endif; ?>

                                                        <?php if (hasPermission('driver.delete')): ?>
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

            var table = $('#driversTable').DataTable({
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
                    targets: [5]
                }],
                language: {
                    search: '',
                    searchPlaceholder: 'Search drivers...',
                    lengthMenu: 'Show _MENU_',
                    info: 'Showing _START_ to _END_ of _TOTAL_',
                    infoEmpty: 'No drivers',
                    infoFiltered: '(filtered from _MAX_)',
                    zeroRecords: 'No matching drivers found',
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
                        url: 'drivers-list.php?action=toggle_active',
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