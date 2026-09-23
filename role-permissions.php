<?php

include 'constant.php';
include 'session.php';

requirePermission('role.edit');
// requirePermission('role.create');


// Load role
$roleId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($roleId <= 0) {
    header("Location: roles-list.php");
    exit;
}

$resRole = mysqli_query($conn, "SELECT * FROM role WHERE id = $roleId LIMIT 1");
if (!$resRole || mysqli_num_rows($resRole) !== 1) {
    header("Location: roles-list.php");
    exit;
}
$role = mysqli_fetch_assoc($resRole);

// Admin role is not editable — redirect away
if ((int) $role['id'] === 1) {
    header("Location: roles-list.php");
    exit;
}

$pageTitle = 'Permissions — ' . $role['role_name'] . ' | Billing Portal';

$success = '';
$error   = '';

/* =========================================================
   HANDLE SUBMIT
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Clean slate for this role
    mysqli_query($conn, "DELETE FROM role_permission WHERE role_id = $roleId");

    $selected = $_POST['perms'] ?? [];

    if (!is_array($selected)) {
        $selected = [];
    }

    // Convert to integers and dedupe
    $selected = array_unique(array_map('intval', $selected));

    // Drop anything ≤ 0
    $selected = array_filter($selected, function ($v) {
        return $v > 0;
    });

    if (!empty($selected)) {

        // Fetch valid permission ids from DB
        $resValid = mysqli_query($conn, "SELECT id FROM permission");
        $validIds = [];
        if ($resValid) {
            while ($v = mysqli_fetch_assoc($resValid)) {
                $validIds[] = (int) $v['id'];
            }
        }

        $values = [];
        foreach ($selected as $pid) {
            if (in_array($pid, $validIds, true)) {
                $values[] = "($roleId, $pid)";
            }
        }

        if (!empty($values)) {
            $sqlIns = "INSERT INTO role_permission (role_id, permission_id) VALUES " . implode(',', $values);
            if (!mysqli_query($conn, $sqlIns)) {
                $error = 'Failed to save permissions: ' . mysqli_error($conn);
            } else {
                $success = 'Permissions updated successfully.';
            }
        } else {
            $success = 'Permissions updated successfully.';
        }
    } else {
        $success = 'Permissions updated successfully.';
    }
}

/* =========================================================
   LOAD ALL PERMISSIONS (grouped)
   ========================================================= */
$permsByGroup = [];
$resP = mysqli_query($conn, "SELECT * FROM permission ORDER BY perm_group, sort_order, id");
if ($resP) {
    while ($p = mysqli_fetch_assoc($resP)) {
        $permsByGroup[$p['perm_group']][] = $p;
    }
}

/* =========================================================
   LOAD CURRENTLY GRANTED PERMISSION IDS
   (renamed to $granted to avoid clash with sidebar's $current)
   ========================================================= */
$granted = [];
$resC = mysqli_query($conn, "SELECT permission_id FROM role_permission WHERE role_id = $roleId");
if ($resC) {
    while ($c = mysqli_fetch_assoc($resC)) {
        $granted[] = (int) $c['permission_id'];
    }
}

$totalAll     = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM permission"))['c'];
$totalGranted = count($granted);

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
        .perm-summary {
            background: #f8f9fc;
            border: 1px solid #e3e6f0;
            border-radius: .35rem;
            padding: 1rem 1.25rem;
        }

        .perm-summary .big {
            font-size: 1.5rem;
            font-weight: 700;
            color: #4e73df;
        }

        .perm-group-card {
            border: 1px solid #e3e6f0;
            border-radius: .35rem;
            overflow: hidden;
            margin-bottom: 1rem;
            background: #fff;
        }

        .perm-group-card .group-header {
            background: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            padding: .75rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .perm-group-card .group-header h6 {
            margin: 0;
            font-weight: 700;
            color: #4e73df;
            font-size: .95rem;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .perm-group-card .group-header .group-count {
            font-size: .8rem;
            color: #858796;
            font-weight: 600;
        }

        .perm-group-card .group-body {
            padding: .5rem 1rem;
        }

        .perm-row {
            padding: .55rem 0;
            border-bottom: 1px dashed #eaecf4;
        }

        .perm-row:last-child {
            border-bottom: 0;
        }

        .perm-row label {
            display: flex;
            align-items: center;
            cursor: pointer;
            margin: 0;
            font-weight: 500;
            color: #3a3b45;
            font-size: .95rem;
        }

        .perm-row input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin-right: .65rem;
            cursor: pointer;
            flex-shrink: 0;
        }

        .perm-row .perm-key {
            font-size: .75rem;
            color: #a1a3b4;
            font-family: "SFMono-Regular", Menlo, Consolas, monospace;
            margin-left: auto;
        }

        .perm-row.checked label {
            color: #1cc88a;
            font-weight: 600;
        }

        .save-bar {
            position: sticky;
            bottom: 0;
            background: #fff;
            border-top: 2px solid #e3e6f0;
            padding: 1rem 1.25rem;
            margin-top: 1rem;
            box-shadow: 0 -4px 12px rgba(58, 59, 69, .06);
            z-index: 10;
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

                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">
                            <i class="fas fa-key mr-2"></i>
                            Permissions for: <span class="text-primary"><?= htmlspecialchars($role['role_name'], ENT_QUOTES, 'UTF-8') ?></span>
                        </h1>
                        <a href="roles-list.php"
                            class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
                            <i class="fas fa-arrow-left fa-sm text-white-50 mr-1"></i>
                            Back to Roles
                        </a>
                    </div>

                    <!-- Success / error -->
                    <?php if ($success !== ''): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle mr-1"></i>
                            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                            <button type="button" class="close" data-dismiss="alert">
                                <span>&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle mr-1"></i>
                            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                            <button type="button" class="close" data-dismiss="alert">
                                <span>&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- Summary -->
                    <div class="perm-summary mb-4 d-flex align-items-center justify-content-between flex-wrap">

                        <div>
                            <div class="text-muted small text-uppercase font-weight-bold mb-1">
                                Permissions granted
                            </div>
                            <div class="big">
                                <?= $totalGranted ?>
                                <span class="text-muted" style="font-size:1rem;">of <?= $totalAll ?></span>
                            </div>
                        </div>

                        <div>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllBtn">
                                <i class="fas fa-check-square mr-1"></i> Select all
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllBtn">
                                <i class="far fa-square mr-1"></i> Deselect all
                            </button>
                        </div>

                    </div>

                    <!-- FORM -->
                    <form id="permForm" action="" method="POST">

                        <div class="row">

                            <?php foreach ($permsByGroup as $groupName => $items): ?>

                                <div class="col-md-6">
                                    <div class="perm-group-card">

                                        <div class="group-header">
                                            <h6><?= htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8') ?></h6>
                                            <span class="group-count">
                                                <?= count($items) ?> permissions
                                            </span>
                                        </div>

                                        <div class="group-body">

                                            <?php foreach ($items as $perm):
                                                $pid = (int) $perm['id'];
                                                $isChecked = in_array($pid, $granted, true);
                                            ?>

                                                <div class="perm-row <?= $isChecked ? 'checked' : '' ?>">
                                                    <label>
                                                        <input
                                                            type="checkbox"
                                                            name="perms[]"
                                                            value="<?= $pid ?>"
                                                            <?= $isChecked ? 'checked' : '' ?>>

                                                        <span class="perm-name">
                                                            <?= htmlspecialchars($perm['perm_name'], ENT_QUOTES, 'UTF-8') ?>
                                                        </span>

                                                        <span class="perm-key">
                                                            <?= htmlspecialchars($perm['perm_key'], ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    </label>
                                                </div>

                                            <?php endforeach; ?>

                                        </div>

                                    </div>
                                </div>

                            <?php endforeach; ?>

                        </div>

                        <!-- Sticky Save bar -->
                        <div class="save-bar d-flex align-items-center justify-content-between flex-wrap">

                            <div class="text-muted small mb-2 mb-sm-0">
                                <i class="fas fa-info-circle mr-1"></i>
                                Tick the permissions you want this role to have, then click Save.
                            </div>

                            <div>
                                <a href="roles-list.php" class="btn btn-secondary">
                                    <i class="fas fa-times mr-1"></i>
                                    Cancel
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check mr-1"></i>
                                    Save Permissions
                                </button>
                            </div>

                        </div>

                    </form>

                </div>

            </div>

            <?php include 'layout/footer.php'; ?>

        </div>

    </div>

    <script>
        $(function() {

            // Toggle the .checked class on the row for visual feedback
            $(document).on('change', 'input[name="perms[]"]', function() {
                var $row = $(this).closest('.perm-row');
                if ($(this).is(':checked')) {
                    $row.addClass('checked');
                } else {
                    $row.removeClass('checked');
                }
            });

            // Select all
            $(document).on('click', '#selectAllBtn', function() {
                $('input[name="perms[]"]:not(:disabled)').prop('checked', true).trigger('change');
            });

            // Deselect all
            $(document).on('click', '#deselectAllBtn', function() {
                $('input[name="perms[]"]:not(:disabled)').prop('checked', false).trigger('change');
            });

        });
    </script>

</body>

</html>