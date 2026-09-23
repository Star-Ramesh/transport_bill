<?php
include_once 'session.php';
$current = basename($_SERVER['PHP_SELF']);

/* =========================================================
   Feature flags
   ========================================================= */
$SHOW_DRIVERS  = true;
$SHOW_VENDORS  = false;   /* hidden per request — flag kept */
$SHOW_ITEMS    = true;
$SHOW_TRIPS    = true;    /* enabled */
$SHOW_INVOICES = false;
$SHOW_PAYMENTS = false;
$SHOW_REPORTS  = false;

/* =========================================================
   Permission flags
   ========================================================= */
$canParty   = hasPermission('party.view');
$canLorry   = hasPermission('lorry.view');
$canDriver  = hasPermission('driver.view');
$canVendor  = hasPermission('vendor.view');
$canItem    = hasPermission('item.view');
$canTrip    = hasPermission('trip.view');
$canInvoice = hasPermission('invoice.view');
$canPayment = hasPermission('payment.view');
$canReport  = hasPermission('report.view');

$isPartySection   = in_array($current, ['party.php', 'parties-list.php'], true);
$isLorrySection   = in_array($current, ['lorry.php', 'lorries-list.php'], true);
$isDriverSection  = in_array($current, ['driver.php', 'drivers-list.php'], true);
$isVendorSection  = in_array($current, ['vendor.php', 'vendors-list.php'], true);
$isItemSection    = in_array($current, ['item.php', 'items-list.php'], true);
$isTripSection    = in_array($current, ['trip.php', 'trips-list.php', 'trip-view.php', 'trip-expense.php'], true);
$isInvoiceSection = in_array($current, ['invoice.php', 'invoices-list.php', 'invoice-print.php'], true);
$isPaymentSection = in_array($current, ['payment.php', 'payments-list.php'], true);
$isReportSection  = in_array($current, ['reports-dashboard.php', 'report-trips.php', 'report-collections.php', 'report-monthly.php', 'report-lorry.php', 'report-driver.php', 'report-expenses.php'], true);
?>

<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

    <!-- Brand -->
    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="index.php">
        <div class="sidebar-brand-icon">
            <i class="fas fa-file-invoice-dollar"></i>
        </div>
        <div class="sidebar-brand-text mx-3">Billing Portal</div>
    </a>

    <hr class="sidebar-divider my-0">

    <!-- Dashboard -->
    <li class="nav-item <?= $current === 'index.php' ? 'active' : '' ?>">
        <a class="nav-link" href="index.php">
            <i class="fas fa-fw fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
    </li>

    <!-- =========================================================
         TRIPS (moved to top)
         ========================================================= -->
    <?php if ($SHOW_TRIPS && $canTrip): ?>
        <li class="nav-item <?= $isTripSection ? 'active' : '' ?>">
            <a class="nav-link <?= $isTripSection ? '' : 'collapsed' ?>"
                href="#"
                data-toggle="collapse"
                data-target="#collapseTripsTop"
                aria-expanded="<?= $isTripSection ? 'true' : 'false' ?>"
                aria-controls="collapseTripsTop">
                <i class="fas fa-fw fa-route"></i>
                <span>Trips</span>
            </a>
            <div id="collapseTripsTop" class="collapse <?= $isTripSection ? 'show' : '' ?>" data-parent="#accordionSidebar">
                <div class="bg-white py-2 collapse-inner rounded">
                    <h6 class="collapse-header">Trips:</h6>
                    <?php if (hasPermission('trip.create')): ?>
                        <a class="collapse-item <?= $current === 'trip.php' ? 'active' : '' ?>" href="trip.php">
                            <i class="fas fa-fw fa-plus mr-1"></i> Add Trip
                        </a>
                    <?php endif; ?>
                    <a class="collapse-item <?= in_array($current, ['trips-list.php', 'trip-view.php'], true) ? 'active' : '' ?>" href="trips-list.php">
                        <i class="fas fa-fw fa-list mr-1"></i> All Trips
                    </a>
                </div>
            </div>
        </li>
    <?php endif; ?>

    <?php if ($canParty || $canLorry || ($SHOW_DRIVERS && $canDriver) || ($SHOW_VENDORS && $canVendor) || ($SHOW_ITEMS && $canItem)): ?>
        <hr class="sidebar-divider">
        <div class="sidebar-heading">Master</div>
    <?php endif; ?>

    <!-- PARTIES -->
    <?php if ($canParty): ?>
        <li class="nav-item <?= $isPartySection ? 'active' : '' ?>">
            <a class="nav-link <?= $isPartySection ? '' : 'collapsed' ?>"
                href="#"
                data-toggle="collapse"
                data-target="#collapseParties"
                aria-expanded="<?= $isPartySection ? 'true' : 'false' ?>"
                aria-controls="collapseParties">
                <i class="fas fa-fw fa-users"></i>
                <span>Parties</span>
            </a>
            <div id="collapseParties" class="collapse <?= $isPartySection ? 'show' : '' ?>" data-parent="#accordionSidebar">
                <div class="bg-white py-2 collapse-inner rounded">
                    <h6 class="collapse-header">Parties:</h6>
                    <?php if (hasPermission('party.create')): ?>
                        <a class="collapse-item <?= $current === 'party.php' ? 'active' : '' ?>" href="party.php">
                            <i class="fas fa-fw fa-user-plus mr-1"></i> Add Party
                        </a>
                    <?php endif; ?>
                    <a class="collapse-item <?= $current === 'parties-list.php' ? 'active' : '' ?>" href="parties-list.php">
                        <i class="fas fa-fw fa-list mr-1"></i> All Parties
                    </a>
                </div>
            </div>
        </li>
    <?php endif; ?>

    <!-- LORRIES -->
    <?php if ($canLorry): ?>
        <li class="nav-item <?= $isLorrySection ? 'active' : '' ?>">
            <a class="nav-link <?= $isLorrySection ? '' : 'collapsed' ?>"
                href="#"
                data-toggle="collapse"
                data-target="#collapseLorries"
                aria-expanded="<?= $isLorrySection ? 'true' : 'false' ?>"
                aria-controls="collapseLorries">
                <i class="fas fa-fw fa-truck"></i>
                <span>Lorries</span>
            </a>
            <div id="collapseLorries" class="collapse <?= $isLorrySection ? 'show' : '' ?>" data-parent="#accordionSidebar">
                <div class="bg-white py-2 collapse-inner rounded">
                    <h6 class="collapse-header">Lorries:</h6>
                    <?php if (hasPermission('lorry.create')): ?>
                        <a class="collapse-item <?= $current === 'lorry.php' ? 'active' : '' ?>" href="lorry.php">
                            <i class="fas fa-fw fa-plus mr-1"></i> Add Lorry
                        </a>
                    <?php endif; ?>
                    <a class="collapse-item <?= $current === 'lorries-list.php' ? 'active' : '' ?>" href="lorries-list.php">
                        <i class="fas fa-fw fa-list mr-1"></i> All Lorries
                    </a>
                </div>
            </div>
        </li>
    <?php endif; ?>

    <!-- DRIVERS -->
    <?php if ($SHOW_DRIVERS && $canDriver): ?>
        <li class="nav-item <?= $isDriverSection ? 'active' : '' ?>">
            <a class="nav-link <?= $isDriverSection ? '' : 'collapsed' ?>"
                href="#"
                data-toggle="collapse"
                data-target="#collapseDrivers"
                aria-expanded="<?= $isDriverSection ? 'true' : 'false' ?>"
                aria-controls="collapseDrivers">
                <i class="fas fa-fw fa-id-card"></i>
                <span>Drivers</span>
            </a>
            <div id="collapseDrivers" class="collapse <?= $isDriverSection ? 'show' : '' ?>" data-parent="#accordionSidebar">
                <div class="bg-white py-2 collapse-inner rounded">
                    <h6 class="collapse-header">Drivers:</h6>
                    <?php if (hasPermission('driver.create')): ?>
                        <a class="collapse-item <?= $current === 'driver.php' ? 'active' : '' ?>" href="driver.php">
                            <i class="fas fa-fw fa-plus mr-1"></i> Add Driver
                        </a>
                    <?php endif; ?>
                    <a class="collapse-item <?= $current === 'drivers-list.php' ? 'active' : '' ?>" href="drivers-list.php">
                        <i class="fas fa-fw fa-list mr-1"></i> All Drivers
                    </a>
                </div>
            </div>
        </li>
    <?php endif; ?>

    <!-- VENDORS (hidden by flag, kept for later) -->
    <?php if ($SHOW_VENDORS && $canVendor): ?>
        <li class="nav-item <?= $isVendorSection ? 'active' : '' ?>">
            <a class="nav-link <?= $isVendorSection ? '' : 'collapsed' ?>"
                href="#"
                data-toggle="collapse"
                data-target="#collapseVendors"
                aria-expanded="<?= $isVendorSection ? 'true' : 'false' ?>"
                aria-controls="collapseVendors">
                <i class="fas fa-fw fa-store"></i>
                <span>Vendors</span>
            </a>
            <div id="collapseVendors" class="collapse <?= $isVendorSection ? 'show' : '' ?>" data-parent="#accordionSidebar">
                <div class="bg-white py-2 collapse-inner rounded">
                    <h6 class="collapse-header">Vendors:</h6>
                    <?php if (hasPermission('vendor.create')): ?>
                        <a class="collapse-item <?= $current === 'vendor.php' ? 'active' : '' ?>" href="vendor.php">
                            <i class="fas fa-fw fa-plus mr-1"></i> Add Vendor
                        </a>
                    <?php endif; ?>
                    <a class="collapse-item <?= $current === 'vendors-list.php' ? 'active' : '' ?>" href="vendors-list.php">
                        <i class="fas fa-fw fa-list mr-1"></i> All Vendors
                    </a>
                </div>
            </div>
        </li>
    <?php endif; ?>

    <!-- ITEMS -->
    <?php if ($SHOW_ITEMS && $canItem): ?>
        <li class="nav-item <?= $isItemSection ? 'active' : '' ?>">
            <a class="nav-link <?= $isItemSection ? '' : 'collapsed' ?>"
                href="#"
                data-toggle="collapse"
                data-target="#collapseItems"
                aria-expanded="<?= $isItemSection ? 'true' : 'false' ?>"
                aria-controls="collapseItems">
                <i class="fas fa-fw fa-box"></i>
                <span>Items</span>
            </a>
            <div id="collapseItems" class="collapse <?= $isItemSection ? 'show' : '' ?>" data-parent="#accordionSidebar">
                <div class="bg-white py-2 collapse-inner rounded">
                    <h6 class="collapse-header">Items:</h6>
                    <?php if (hasPermission('item.create')): ?>
                        <a class="collapse-item <?= $current === 'item.php' ? 'active' : '' ?>" href="item.php">
                            <i class="fas fa-fw fa-plus mr-1"></i> Add Item
                        </a>
                    <?php endif; ?>
                    <a class="collapse-item <?= $current === 'items-list.php' ? 'active' : '' ?>" href="items-list.php">
                        <i class="fas fa-fw fa-list mr-1"></i> All Items
                    </a>
                </div>
            </div>
        </li>
    <?php endif; ?>

    <!-- =========================================================
         TRIPS — original position (kept, disabled via false &&)
         Re-enable by removing "false &&" and hiding the top block.
         ========================================================= -->
    <?php if (false && $SHOW_TRIPS && $canTrip): ?>
        <li class="nav-item <?= $isTripSection ? 'active' : '' ?>">
            <a class="nav-link <?= $isTripSection ? '' : 'collapsed' ?>"
                href="#"
                data-toggle="collapse"
                data-target="#collapseTrips"
                aria-expanded="<?= $isTripSection ? 'true' : 'false' ?>"
                aria-controls="collapseTrips">
                <i class="fas fa-fw fa-route"></i>
                <span>Trips</span>
            </a>
            <div id="collapseTrips" class="collapse <?= $isTripSection ? 'show' : '' ?>" data-parent="#accordionSidebar">
                <div class="bg-white py-2 collapse-inner rounded">
                    <h6 class="collapse-header">Trips:</h6>
                    <?php if (hasPermission('trip.create')): ?>
                        <a class="collapse-item <?= $current === 'trip.php' ? 'active' : '' ?>" href="trip.php">
                            <i class="fas fa-fw fa-plus mr-1"></i> Add Trip
                        </a>
                    <?php endif; ?>
                    <a class="collapse-item <?= in_array($current, ['trips-list.php', 'trip-view.php'], true) ? 'active' : '' ?>" href="trips-list.php">
                        <i class="fas fa-fw fa-list mr-1"></i> All Trips
                    </a>
                </div>
            </div>
        </li>
    <?php endif; ?>

    <!-- INVOICES (hidden until built) -->
    <?php if ($SHOW_INVOICES && $canInvoice): ?>
        <li class="nav-item <?= $isInvoiceSection ? 'active' : '' ?>">
            <a class="nav-link <?= $isInvoiceSection ? '' : 'collapsed' ?>"
                href="#"
                data-toggle="collapse"
                data-target="#collapseInvoices"
                aria-expanded="<?= $isInvoiceSection ? 'true' : 'false' ?>"
                aria-controls="collapseInvoices">
                <i class="fas fa-fw fa-file-invoice"></i>
                <span>Invoices</span>
            </a>
            <div id="collapseInvoices" class="collapse <?= $isInvoiceSection ? 'show' : '' ?>" data-parent="#accordionSidebar">
                <div class="bg-white py-2 collapse-inner rounded">
                    <h6 class="collapse-header">Invoices:</h6>
                    <?php if (hasPermission('invoice.create')): ?>
                        <a class="collapse-item <?= $current === 'invoice.php' ? 'active' : '' ?>" href="invoice.php">
                            <i class="fas fa-fw fa-plus mr-1"></i> Create Invoice
                        </a>
                    <?php endif; ?>
                    <a class="collapse-item <?= $current === 'invoices-list.php' ? 'active' : '' ?>" href="invoices-list.php">
                        <i class="fas fa-fw fa-list mr-1"></i> All Invoices
                    </a>
                </div>
            </div>
        </li>
    <?php endif; ?>

    <!-- PAYMENTS (hidden until built) -->
    <?php if ($SHOW_PAYMENTS && $canPayment): ?>
        <li class="nav-item <?= $isPaymentSection ? 'active' : '' ?>">
            <a class="nav-link <?= $isPaymentSection ? '' : 'collapsed' ?>"
                href="#"
                data-toggle="collapse"
                data-target="#collapsePayments"
                aria-expanded="<?= $isPaymentSection ? 'true' : 'false' ?>"
                aria-controls="collapsePayments">
                <i class="fas fa-fw fa-money-bill-wave"></i>
                <span>Payments</span>
            </a>
            <div id="collapsePayments" class="collapse <?= $isPaymentSection ? 'show' : '' ?>" data-parent="#accordionSidebar">
                <div class="bg-white py-2 collapse-inner rounded">
                    <h6 class="collapse-header">Payments:</h6>
                    <?php if (hasPermission('payment.create')): ?>
                        <a class="collapse-item <?= $current === 'payment.php' ? 'active' : '' ?>" href="payment.php">
                            <i class="fas fa-fw fa-plus mr-1"></i> Record Payment
                        </a>
                    <?php endif; ?>
                    <a class="collapse-item <?= $current === 'payments-list.php' ? 'active' : '' ?>" href="payments-list.php">
                        <i class="fas fa-fw fa-list mr-1"></i> All Payments
                    </a>
                </div>
            </div>
        </li>
    <?php endif; ?>

    <!-- REPORTS (hidden until built) -->
    <?php if ($SHOW_REPORTS && $canReport): ?>
        <li class="nav-item <?= $isReportSection ? 'active' : '' ?>">
            <a class="nav-link <?= $isReportSection ? '' : 'collapsed' ?>"
                href="#"
                data-toggle="collapse"
                data-target="#collapseReports"
                aria-expanded="<?= $isReportSection ? 'true' : 'false' ?>"
                aria-controls="collapseReports">
                <i class="fas fa-fw fa-chart-line"></i>
                <span>Reports</span>
            </a>
            <div id="collapseReports" class="collapse <?= $isReportSection ? 'show' : '' ?>" data-parent="#accordionSidebar">
                <div class="bg-white py-2 collapse-inner rounded">
                    <h6 class="collapse-header">Reports:</h6>
                    <a class="collapse-item <?= $current === 'reports-dashboard.php' ? 'active' : '' ?>" href="reports-dashboard.php">
                        <i class="fas fa-fw fa-chart-pie mr-1"></i> Dashboard
                    </a>
                </div>
            </div>
        </li>
    <?php endif; ?>

    <hr class="sidebar-divider d-none d-md-block">

    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>

</ul>