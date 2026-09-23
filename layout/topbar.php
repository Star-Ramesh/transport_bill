<?php
/* =========================================================
   layout/topbar.php
   ---------------------------------------------------------
   Topbar with user dropdown.
   For non-admin users, also shows the branch name next to
   the user's full name.
   ========================================================= */

/* Fetch the branch name once for non-admin users */
$topbarBranchName = '';
$topbarIsAdmin    = isAdmin();

if (!$topbarIsAdmin) {
    $topbarBranchId = (int) ($_SESSION['branch_id'] ?? 0);
    if ($topbarBranchId > 0) {
        $resTopbarBranch = mysqli_query(
            $conn,
            "SELECT branch_name FROM branch WHERE id = $topbarBranchId LIMIT 1"
        );
        if ($resTopbarBranch && mysqli_num_rows($resTopbarBranch) === 1) {
            $rowTopbarBranch = mysqli_fetch_assoc($resTopbarBranch);
            $topbarBranchName = $rowTopbarBranch['branch_name'];
        }
    }
}

$topbarUserName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
?>
<!-- Topbar -->
<nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">

    <!-- Sidebar Toggle -->
    <button
        id="sidebarToggleTop"
        class="btn btn-link d-md-none rounded-circle mr-3">

        <i class="fa fa-bars"></i>

    </button>


    <!-- Topbar Navbar -->
    <ul class="navbar-nav ml-auto">


        <!-- User -->
        <li class="nav-item dropdown no-arrow">

            <a
                class="nav-link dropdown-toggle"
                href="#"
                id="userDropdown"
                role="button"
                data-toggle="dropdown"
                aria-haspopup="true"
                aria-expanded="false">

                <!-- User + (branch) — branch shown only for non-admins -->
                <span class="mr-2 d-none d-lg-inline text-gray-600 small text-right">
                    <span class="d-block font-weight-bold text-gray-800">
                        <?= htmlspecialchars($topbarUserName, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <?php if ($topbarBranchName !== ''): ?>
                        <span class="d-block text-muted" style="font-size: 0.75rem; line-height: 1;">
                            <i class="fas fa-building fa-xs mr-1"></i>
                            <?= htmlspecialchars($topbarBranchName, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    <?php endif; ?>
                </span>

                <img
                    class="img-profile rounded-circle"
                    src="img/undraw_profile.svg"
                    alt="Profile">

            </a>


            <!-- User Dropdown -->
            <div
                class="dropdown-menu dropdown-menu-right shadow animated--grow-in"
                aria-labelledby="userDropdown">

                <!-- Settings (password change) -->
                <a class="dropdown-item" href="settings.php">
                    <i class="fas fa-cogs fa-sm fa-fw mr-2 text-gray-400"></i>
                    Settings
                </a>

                <?php if (hasPermission('branch.view') || hasPermission('user.view') || hasPermission('role.view')): ?>

                    <div class="dropdown-divider"></div>

                    <?php if (hasPermission('branch.view')): ?>
                        <a class="dropdown-item" href="branches-list.php">
                            <i class="fas fa-building fa-sm fa-fw mr-2 text-gray-400"></i>
                            Branches
                        </a>
                    <?php endif; ?>

                    <?php if (hasPermission('user.view')): ?>
                        <a class="dropdown-item" href="users-list.php">
                            <i class="fas fa-user-friends fa-sm fa-fw mr-2 text-gray-400"></i>
                            Users
                        </a>
                    <?php endif; ?>

                    <?php if (hasPermission('role.view')): ?>
                        <a class="dropdown-item" href="roles-list.php">
                            <i class="fas fa-user-shield fa-sm fa-fw mr-2 text-gray-400"></i>
                            Roles &amp; Permissions
                        </a>
                    <?php endif; ?>

                <?php endif; ?>

                <div class="dropdown-divider"></div>

                <a
                    class="dropdown-item"
                    href="#"
                    data-toggle="modal"
                    data-target="#logoutModal">

                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>

                    Logout

                </a>

            </div>

        </li>

    </ul>

</nav>
<!-- End of Topbar -->