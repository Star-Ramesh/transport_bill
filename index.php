<?php

include 'constant.php';
include 'session.php';

$pageTitle = 'Dashboard | Billing Portal';

/* =========================================================
   Permission checks
   ========================================================= */
$canSeeParties  = hasPermission('party.view');
$canSeeLorries  = hasPermission('lorry.view');
$canSeeBranches = hasPermission('branch.view');
$canSeeDrivers  = hasPermission('driver.view');
$canSeeVendors  = hasPermission('vendor.view');
$canSeeItems    = hasPermission('item.view');

/* =========================================================
   Load counts (only if permitted)
   ========================================================= */
$totalParties  = 0;
$totalLorries  = 0;
$totalBranches = 0;
$totalDrivers  = 0;
$totalVendors  = 0;
$totalItems    = 0;

$branchFilter = '';
if (!isAdmin()) {
    $myBranch = (int) ($_SESSION['branch_id'] ?? 0);
    $branchFilter = " AND branch_id = $myBranch";
}

if ($canSeeParties) {
    $res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM party WHERE active = 1$branchFilter");
    $totalParties = $res ? (int) mysqli_fetch_assoc($res)['c'] : 0;
}

if ($canSeeLorries) {
    $res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM lorry WHERE active = 1$branchFilter");
    $totalLorries = $res ? (int) mysqli_fetch_assoc($res)['c'] : 0;
}

if ($canSeeBranches) {
    $res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM branch WHERE active = 1");
    $totalBranches = $res ? (int) mysqli_fetch_assoc($res)['c'] : 0;
}

if ($canSeeDrivers) {
    $res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM driver WHERE active = 1$branchFilter");
    $totalDrivers = $res ? (int) mysqli_fetch_assoc($res)['c'] : 0;
}

if ($canSeeVendors) {
    $res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM vendor WHERE active = 1$branchFilter");
    $totalVendors = $res ? (int) mysqli_fetch_assoc($res)['c'] : 0;
}

if ($canSeeItems) {
    $res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM item WHERE active = 1");
    $totalItems = $res ? (int) mysqli_fetch_assoc($res)['c'] : 0;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <meta name="description" content="Billing Portal Dashboard">
    <meta name="author" content="">

    <title><?= $pageTitle ?></title>

    <?php include 'layout/header.php'; ?>

    <style>
        /* Compact stat cards */
        .stat-card .card-body {
            padding: 0.85rem 1rem;
        }

        .stat-card .stat-label {
            font-size: 0.68rem;
            letter-spacing: 0.05em;
        }

        .stat-card .stat-value {
            font-size: 1.35rem;
            line-height: 1.1;
        }

        .stat-card .stat-icon {
            font-size: 1.6rem;
        }

        /* Section heading */
        .section-heading {
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #858796;
            margin-bottom: 0.75rem;
        }
    </style>

</head>

<body id="page-top">

    <!-- Page Wrapper -->
    <div id="wrapper">

        <?php include 'layout/sidebar.php'; ?>

        <div id="content-wrapper" class="d-flex flex-column min-vh-100">

            <div id="content">

                <?php include 'layout/topbar.php'; ?>

                <div class="container-fluid">

                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">
                            <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                        </h1>
                    </div>

                    <?php if ($canSeeParties || $canSeeLorries || $canSeeBranches || $canSeeDrivers || $canSeeVendors || $canSeeItems): ?>

                        <!-- Masters Group -->
                        <div class="mb-4">

                            <div class="section-heading">
                                <i class="fas fa-database mr-1"></i>Masters
                            </div>

                            <div class="row">

                                <!-- Parties -->
                                <?php if ($canSeeParties): ?>
                                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                                        <a href="parties-list.php" class="text-decoration-none text-reset">
                                            <div class="card stat-card border-left-primary shadow h-100">
                                                <div class="card-body">
                                                    <div class="row no-gutters align-items-center">
                                                        <div class="col mr-2">
                                                            <div class="stat-label font-weight-bold text-primary text-uppercase mb-1">
                                                                Parties
                                                            </div>
                                                            <div class="stat-value font-weight-bold text-gray-800">
                                                                <?= $totalParties ?>
                                                            </div>
                                                        </div>
                                                        <div class="col-auto">
                                                            <i class="fas fa-users stat-icon text-gray-300"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <!-- Lorries -->
                                <?php if ($canSeeLorries): ?>
                                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                                        <a href="lorries-list.php" class="text-decoration-none text-reset">
                                            <div class="card stat-card border-left-success shadow h-100">
                                                <div class="card-body">
                                                    <div class="row no-gutters align-items-center">
                                                        <div class="col mr-2">
                                                            <div class="stat-label font-weight-bold text-success text-uppercase mb-1">
                                                                Lorries
                                                            </div>
                                                            <div class="stat-value font-weight-bold text-gray-800">
                                                                <?= $totalLorries ?>
                                                            </div>
                                                        </div>
                                                        <div class="col-auto">
                                                            <i class="fas fa-truck stat-icon text-gray-300"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <!-- Drivers -->
                                <?php if ($canSeeDrivers): ?>
                                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                                        <a href="drivers-list.php" class="text-decoration-none text-reset">
                                            <div class="card stat-card border-left-info shadow h-100">
                                                <div class="card-body">
                                                    <div class="row no-gutters align-items-center">
                                                        <div class="col mr-2">
                                                            <div class="stat-label font-weight-bold text-info text-uppercase mb-1">
                                                                Drivers
                                                            </div>
                                                            <div class="stat-value font-weight-bold text-gray-800">
                                                                <?= $totalDrivers ?>
                                                            </div>
                                                        </div>
                                                        <div class="col-auto">
                                                            <i class="fas fa-id-card stat-icon text-gray-300"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <!-- Vendors -->
                                <?php if ($canSeeVendors): ?>
                                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                                        <a href="vendors-list.php" class="text-decoration-none text-reset">
                                            <div class="card stat-card border-left-danger shadow h-100">
                                                <div class="card-body">
                                                    <div class="row no-gutters align-items-center">
                                                        <div class="col mr-2">
                                                            <div class="stat-label font-weight-bold text-danger text-uppercase mb-1">
                                                                Vendors
                                                            </div>
                                                            <div class="stat-value font-weight-bold text-gray-800">
                                                                <?= $totalVendors ?>
                                                            </div>
                                                        </div>
                                                        <div class="col-auto">
                                                            <i class="fas fa-store stat-icon text-gray-300"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <!-- Items -->
                                <?php if ($canSeeItems): ?>
                                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                                        <a href="items-list.php" class="text-decoration-none text-reset">
                                            <div class="card stat-card border-left-secondary shadow h-100">
                                                <div class="card-body">
                                                    <div class="row no-gutters align-items-center">
                                                        <div class="col mr-2">
                                                            <div class="stat-label font-weight-bold text-secondary text-uppercase mb-1">
                                                                Items
                                                            </div>
                                                            <div class="stat-value font-weight-bold text-gray-800">
                                                                <?= $totalItems ?>
                                                            </div>
                                                        </div>
                                                        <div class="col-auto">
                                                            <i class="fas fa-box stat-icon text-gray-300"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <!-- Branches -->
                                <?php if ($canSeeBranches): ?>
                                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                                        <a href="branches-list.php" class="text-decoration-none text-reset">
                                            <div class="card stat-card border-left-warning shadow h-100">
                                                <div class="card-body">
                                                    <div class="row no-gutters align-items-center">
                                                        <div class="col mr-2">
                                                            <div class="stat-label font-weight-bold text-warning text-uppercase mb-1">
                                                                Branches
                                                            </div>
                                                            <div class="stat-value font-weight-bold text-gray-800">
                                                                <?= $totalBranches ?>
                                                            </div>
                                                        </div>
                                                        <div class="col-auto">
                                                            <i class="fas fa-building stat-icon text-gray-300"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                <?php endif; ?>

                            </div>

                        </div>
                        <!-- End Masters Group -->

                    <?php else: ?>

                        <div class="card shadow mb-4">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-user-circle fa-4x text-gray-300 mb-3"></i>
                                <h5 class="text-gray-800 mb-2">
                                    No dashboard cards available
                                </h5>
                                <p class="text-muted mb-0">
                                    Contact your administrator to grant you access.
                                </p>
                            </div>
                        </div>

                    <?php endif; ?>

                </div>

            </div>

            <?php include 'layout/footer.php'; ?>

        </div>

    </div>

</body>

</html>