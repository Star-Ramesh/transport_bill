<?php

include 'constant.php';
include 'session.php';

requirePermission('role.view');

/* =========================================================
   FLASH
   ========================================================= */
$flash = '';
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'added':
            $flash = 'Role added successfully.';
            break;
        case 'updated':
            $flash = 'Role updated successfully.';
            break;
    }
}

/* =========================================================
   PAGE DATA
   ========================================================= */
$pageTitle = 'Roles | Billing Portal';

$sql = "SELECT r.id, r.role_name, r.role_desc, r.active, r.created_at,
               (SELECT COUNT(*) FROM role_permission rp WHERE rp.role_id = r.id) AS perm_count
        FROM role r
        WHERE r.id <> 1
        ORDER BY r.id ASC";

$result = mysqli_query($conn, $sql);
if (!$result) die("Query failed: " . mysqli_error($conn));

$roles = [];
while ($row = mysqli_fetch_assoc($result)) {
    $roles[] = $row;
}

$totalAll = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM permission"))['c'];
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
        #rolesTable {
            font-size: 0.95rem;
        }

        #rolesTable thead th {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #4a4c59;
            padding: 0.85rem 0.9rem;
            border-bottom: 2px solid #e3e6f0;
        }

        #rolesTable tbody td {
            padding: 0.9rem 0.9rem;
            vertical-align: middle;
            color: #3a3b45;
        }

        #rolesTable tbody tr:hover>td {
            background-color: #f7f9fc;
        }

        .role-name {
            font-size: 1.02rem;
            font-weight: 700;
            color: #2c2e3e;
        }

        .role-desc {
            color: #6e707e;
            font-size: 0.9rem;
        }

        .perm-badge {
            display: inline-block;
            padding: 0.35rem 0.85rem;
            border-radius: 2rem;
            font-size: 0.85rem;
            font-weight: 700;
            background-color: #eef2ff;
            color: #3f51b5;
            border: 1px solid #dbe2ff;
            min-width: 60px;
            text-align: center;
        }

        .btn-manage {
            font-weight: 600;
            padding: 0.4rem 1rem;
            border-radius: 2rem;
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
                            <i class="fas fa-user-shield mr-2"></i>Roles &amp; Permissions
                        </h1>

                        <?php if (hasPermission('role.create')): ?>
                            <a href="role.php"
                                class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                                <i class="fas fa-plus fa-sm text-white-50 mr-1"></i>
                                Add Role
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
                                All Roles
                            </h6>
                        </div>

                        <div class="card-body">

                            <?php if (empty($roles)): ?>

                                <div class="text-center py-5">
                                    <i class="fas fa-user-shield fa-3x text-gray-300 mb-3"></i>
                                    <h5 class="text-gray-700">No roles yet</h5>
                                    <p class="text-muted">
                                        Click "Add Role" to create your first one.
                                    </p>
                                    <?php if (hasPermission('role.create')): ?>
                                        <a href="role.php" class="btn btn-primary btn-sm">
                                            <i class="fas fa-plus mr-1"></i>Add Role
                                        </a>
                                    <?php endif; ?>
                                </div>

                            <?php else: ?>

                                <div class="table-responsive">

                                    <table class="table table-bordered table-hover mb-0"
                                        id="rolesTable"
                                        width="100%"
                                        cellspacing="0">

                                        <thead class="thead-light">
                                            <tr>
                                                <th width="60">#</th>
                                                <th>Role Name</th>
                                                <th class="d-none d-md-table-cell">Description</th>
                                                <th width="140" class="text-center">Permissions</th>
                                                <th width="180" class="text-center">Action</th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            <?php foreach ($roles as $i => $row): ?>
                                                <tr>

                                                    <td class="text-muted small align-middle font-weight-bold">
                                                        <?= $i + 1 ?>
                                                    </td>

                                                    <td class="align-middle">
                                                        <span class="role-name">
                                                            <?= htmlspecialchars($row['role_name'], ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    </td>

                                                    <td class="align-middle d-none d-md-table-cell">
                                                        <span class="role-desc">
                                                            <?= !empty($row['role_desc'])
                                                                ? htmlspecialchars($row['role_desc'], ENT_QUOTES, 'UTF-8')
                                                                : '—' ?>
                                                        </span>
                                                    </td>

                                                    <td class="text-center align-middle">
                                                        <span class="perm-badge">
                                                            <?= (int) $row['perm_count'] ?>
                                                            <span class="text-muted font-weight-normal"
                                                                style="font-size:0.78rem;">
                                                                / <?= $totalAll ?>
                                                            </span>
                                                        </span>
                                                    </td>

                                                    <td class="text-center align-middle">
                                                        <div class="table-actions">

                                                            <?php if (hasPermission('role.edit')): ?>
                                                                <a href="role-permissions.php?id=<?= (int) $row['id'] ?>"
                                                                    class="btn btn-sm btn-primary btn-manage"
                                                                    title="Select permissions">
                                                                    <i class="fas fa-key mr-1"></i>
                                                                    <span class="d-none d-sm-inline">Select</span> Permissions
                                                                </a>
                                                            <?php else: ?>
                                                                <span class="text-muted small">—</span>
                                                            <?php endif; ?>

                                                        </div>
                                                    </td>

                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>

                                    </table>

                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

            </div>

            <?php include 'layout/footer.php'; ?>

        </div>

    </div>

</body>

</html>