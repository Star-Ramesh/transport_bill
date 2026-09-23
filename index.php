<?php

include 'session.php';
include 'constant.php';

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
$canSeeTrips    = hasPermission('trip.view');
$canCreateTrip  = hasPermission('trip.create');

/* =========================================================
   Load counts (only if permitted)
   ========================================================= */
$totalParties  = 0;
$totalLorries  = 0;
$totalBranches = 0;
$totalDrivers  = 0;
$totalVendors  = 0;
$totalItems    = 0;
$totalTrips    = 0;

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

/* Trips count — same branch-visibility rule as trips-list.php */
if ($canSeeTrips) {

    $tripWhere = '';

    if (!hasPermission('trip.view.all')) {
        $myBranchId = (int) ($_SESSION['branch_id'] ?? 0);
        if ($myBranchId > 0) {
            $tripWhere = " AND (branch_id = $myBranchId
                             OR from_branch_id = $myBranchId
                             OR to_branch_id   = $myBranchId)";
        } else {
            $tripWhere = " AND 1=0";
        }
    }

    $res = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS c FROM trip WHERE active = 1" . $tripWhere
    );
    $totalTrips = $res ? (int) mysqli_fetch_assoc($res)['c'] : 0;
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
        /* =========================================================
           STAT CARD — compact
           ========================================================= */
        .stat-card {
            border-radius: 0.5rem;
            transition: transform .12s ease, box-shadow .12s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1.5rem 0 rgba(58, 59, 69, .18) !important;
        }

        .stat-card .card-body {
            padding: 1rem 1.15rem;
        }

        .stat-card .stat-label {
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 0.25rem;
        }

        .stat-card .stat-value {
            font-size: 1.65rem;
            font-weight: 800;
            line-height: 1;
            color: #2c2e3e;
            font-family: "SFMono-Regular", Menlo, Consolas, monospace;
        }

        .stat-card .stat-icon {
            font-size: 1.75rem;
            color: #e3e6f0;
            line-height: 1;
        }

        /* =========================================================
           TRIP CARD — the hero
           ========================================================= */
        .trip-hero {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: #fff;
            border-radius: 0.6rem;
            overflow: hidden;
            position: relative;
            box-shadow: 0 0.6rem 1.8rem 0 rgba(78, 115, 223, .28);
            min-height: 100%;
            display: flex;
            flex-direction: column;
        }

        .trip-hero::after {
            /* Decorative watermark icon */
            content: "\f5d1";
            /* fa-route */
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            position: absolute;
            right: -0.5rem;
            bottom: -1rem;
            font-size: 10rem;
            line-height: 1;
            color: rgba(255, 255, 255, 0.10);
            pointer-events: none;
        }

        .trip-hero .trip-hero-body {
            padding: 1.5rem 1.75rem 1.75rem;
            position: relative;
            z-index: 1;
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
        }

        .trip-hero .trip-eyebrow {
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            opacity: 0.85;
            margin-bottom: 0.35rem;
        }

        .trip-hero .trip-value {
            font-size: 3.25rem;
            font-weight: 800;
            line-height: 1;
            font-family: "SFMono-Regular", Menlo, Consolas, monospace;
            margin-bottom: 0.25rem;
        }

        .trip-hero .trip-sub {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        .trip-hero .trip-hero-actions {
            margin-top: auto;
            padding-top: 1.25rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .trip-hero .trip-hero-actions .btn {
            font-weight: 700;
            padding: 0.55rem 1.25rem;
            border-radius: 0.4rem;
        }

        .trip-hero .btn-trip-add {
            background-color: #ffffff;
            color: #224abe;
            border: 1px solid #ffffff;
        }

        .trip-hero .btn-trip-add:hover {
            background-color: #f0f3fa;
            color: #224abe;
            border-color: #f0f3fa;
        }

        .trip-hero .btn-trip-view {
            background-color: transparent;
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.6);
        }

        .trip-hero .btn-trip-view:hover {
            background-color: rgba(255, 255, 255, 0.12);
            color: #ffffff;
            border-color: #ffffff;
        }

        /* =========================================================
           SECTION HEADING
           ========================================================= */
        .section-heading {
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #858796;
            margin-bottom: 0.75rem;
        }

        /* =========================================================
           MOBILE TWEAKS
           ========================================================= */
        @media (max-width: 991.98px) {
            .trip-hero .trip-value {
                font-size: 2.75rem;
            }

            .trip-hero .trip-hero-body {
                padding: 1.25rem 1.5rem 1.5rem;
            }
        }

        @media (max-width: 575.98px) {
            .trip-hero .trip-value {
                font-size: 2.25rem;
            }

            .trip-hero .trip-hero-body {
                padding: 1.1rem 1.25rem 1.25rem;
            }

            .trip-hero::after {
                font-size: 6rem;
                right: -1rem;
                bottom: -0.75rem;
            }

            .trip-hero .trip-hero-actions .btn {
                width: 100%;
            }

            .stat-card .stat-value {
                font-size: 1.4rem;
            }
        }
    </style>

</head>

<body id="page-top">

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

                    <!-- =========================================================
                         MAIN ROW — Trip hero (left) + Masters (right)
                         ========================================================= -->
                    <div class="row">

                        <!-- ==================================================
                             TRIP HERO — larger, colorful
                             ================================================== -->
                        <?php if ($canSeeTrips): ?>
                            <div class="col-lg-5 mb-4">
                                <div class="trip-hero h-100">
                                    <div class="trip-hero-body">

                                        <div class="trip-eyebrow">
                                            <i class="fas fa-route mr-1"></i> Trips
                                        </div>

                                        <div class="trip-value">
                                            <?= $totalTrips ?>
                                        </div>

                                        <div class="trip-sub">
                                            <?php if (hasPermission('trip.view.all')): ?>
                                                Across all branches
                                            <?php else: ?>
                                                Trips touching your branch
                                            <?php endif; ?>
                                        </div>

                                        <div class="trip-hero-actions">
                                            <?php if ($canCreateTrip): ?>
                                                <a href="trip.php" class="btn btn-trip-add">
                                                    <i class="fas fa-plus mr-1"></i> Add Trip
                                                </a>
                                            <?php endif; ?>
                                            <a href="trips-list.php" class="btn btn-trip-view">
                                                <i class="fas fa-list mr-1"></i> View Trips
                                            </a>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- ==================================================
                             MASTERS — 2×2 grid on the right
                             ================================================== -->
                        <?php if ($canSeeParties || $canSeeLorries || $canSeeDrivers || $canSeeBranches): ?>

                            <div class="col-lg-7 mb-4">
                                <div class="section-heading">
                                    <i class="fas fa-database mr-1"></i> Masters
                                </div>

                                <div class="row">

                                    <!-- Parties -->
                                    <?php if ($canSeeParties): ?>
                                        <div class="col-sm-6 mb-3">
                                            <a href="parties-list.php" class="text-decoration-none text-reset">
                                                <div class="card stat-card border-left-primary shadow h-100">
                                                    <div class="card-body">
                                                        <div class="row no-gutters align-items-center">
                                                            <div class="col mr-2">
                                                                <div class="stat-label text-primary">
                                                                    Parties
                                                                </div>
                                                                <div class="stat-value">
                                                                    <?= $totalParties ?>
                                                                </div>
                                                            </div>
                                                            <div class="col-auto">
                                                                <i class="fas fa-users stat-icon"></i>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </a>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Lorries -->
                                    <?php if ($canSeeLorries): ?>
                                        <div class="col-sm-6 mb-3">
                                            <a href="lorries-list.php" class="text-decoration-none text-reset">
                                                <div class="card stat-card border-left-success shadow h-100">
                                                    <div class="card-body">
                                                        <div class="row no-gutters align-items-center">
                                                            <div class="col mr-2">
                                                                <div class="stat-label text-success">
                                                                    Lorries
                                                                </div>
                                                                <div class="stat-value">
                                                                    <?= $totalLorries ?>
                                                                </div>
                                                            </div>
                                                            <div class="col-auto">
                                                                <i class="fas fa-truck stat-icon"></i>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </a>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Drivers -->
                                    <?php if ($canSeeDrivers): ?>
                                        <div class="col-sm-6 mb-3">
                                            <a href="drivers-list.php" class="text-decoration-none text-reset">
                                                <div class="card stat-card border-left-info shadow h-100">
                                                    <div class="card-body">
                                                        <div class="row no-gutters align-items-center">
                                                            <div class="col mr-2">
                                                                <div class="stat-label text-info">
                                                                    Drivers
                                                                </div>
                                                                <div class="stat-value">
                                                                    <?= $totalDrivers ?>
                                                                </div>
                                                            </div>
                                                            <div class="col-auto">
                                                                <i class="fas fa-id-card stat-icon"></i>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </a>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Branches -->
                                    <?php if ($canSeeBranches): ?>
                                        <div class="col-sm-6 mb-3">
                                            <a href="branches-list.php" class="text-decoration-none text-reset">
                                                <div class="card stat-card border-left-warning shadow h-100">
                                                    <div class="card-body">
                                                        <div class="row no-gutters align-items-center">
                                                            <div class="col mr-2">
                                                                <div class="stat-label text-warning">
                                                                    Branches
                                                                </div>
                                                                <div class="stat-value">
                                                                    <?= $totalBranches ?>
                                                                </div>
                                                            </div>
                                                            <div class="col-auto">
                                                                <i class="fas fa-building stat-icon"></i>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </a>
                                        </div>
                                    <?php endif; ?>

                                </div>
                            </div>

                        <?php endif; ?>

                    </div>

                    <!-- =========================================================
                         Empty state when nothing is visible
                         ========================================================= -->
                    <?php if (!$canSeeParties && !$canSeeLorries && !$canSeeDrivers && !$canSeeBranches && !$canSeeTrips): ?>
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