<?php

include 'constant.php';
include 'session.php';

$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$isEdit = $editId > 0;

$isPopup = isset($_GET['popup']) && (int) $_GET['popup'] === 1;

requirePermission($isEdit ? 'inventory.edit' : 'inventory.create');

$pageTitle = $isEdit ? 'Edit Inventory | Billing Portal' : 'Add Inventory | Billing Portal';

$error     = '';
$errorList = [];

$popup_saved_id    = 0;
$popup_saved_label = '';

$old = [
    'branch_id' => isAdmin() ? 0 : (int) ($_SESSION['branch_id'] ?? 0),
    'item_name' => '',
    'unit'      => '',
    'rate'      => '', // Set to blank by default
    'active'    => 1,
];

/* Branches dropdown */
$branches = [];
$resB = mysqli_query(
    $conn,
    "SELECT id, branch_name FROM branch WHERE active = 1 ORDER BY branch_name"
);
if ($resB) {
    while ($b = mysqli_fetch_assoc($resB)) {
        $branches[] = $b;
    }
}

/* LOAD (Edit mode) */
if ($isEdit) {
    $res = mysqli_query($conn, "SELECT * FROM inventory WHERE id = $editId LIMIT 1");

    if (!$res || mysqli_num_rows($res) !== 1) {
        if ($isPopup) {
            echo "<p class='text-danger p-3'>Inventory not found.</p>";
            exit;
        }
        header("Location: inventory-list.php");
        exit;
    }

    $row = mysqli_fetch_assoc($res);

    $old['branch_id'] = (int) ($row['branch_id'] ?? 0);
    $old['item_name'] = $row['item_name'] ?? '';
    $old['unit']      = $row['unit']      ?? '';
    
    // If the database rate is 0, keep it blank. Otherwise, show the rate.
    $dbRate = (float) ($row['rate'] ?? 0);
    $old['rate']      = ($dbRate == 0) ? '' : $dbRate;
    
    $old['active']    = (int) ($row['active'] ?? 1);
}

/* SUBMIT */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['popup_mode']) && (int) $_POST['popup_mode'] === 1) {
        $isPopup = true;
    }

    $old['branch_id'] = (int) ($_POST['branch_id'] ?? 0);
    $old['item_name'] = trim($_POST['item_name'] ?? '');
    $old['unit']      = trim($_POST['unit']      ?? '');
    $old['rate']      = trim($_POST['rate'] ?? '');
    $old['active']    = isset($_POST['active']) ? (int) $_POST['active'] : 1;

    if ($old['branch_id'] <= 0) {
        $errorList[] = 'Please select a branch.';
    }

    if ($old['item_name'] === '') {
        $errorList[] = 'Inventory name is required.';
    } elseif (strlen($old['item_name']) > 80) {
        $errorList[] = 'Inventory name is too long (max 80).';
    }

    if (strlen($old['unit']) > 20) {
        $errorList[] = 'Unit is too long (max 20).';
    }

    /* Rate validation - Accept blank as 0 */
    if ($old['rate'] === '') {
        $old['rate'] = '0'; // Auto-convert blank to 0 for database
    } elseif (!is_numeric($old['rate'])) {
        $errorList[] = 'Rate must be a valid number.';
    } else {
        $rateFloat = (float) $old['rate'];
        if ($rateFloat < 0) {
            $errorList[] = 'Rate cannot be negative.';
        }
    }

    /* Duplicate check — scoped to branch */
    if (empty($errorList)) {
        $name_safe = mysqli_real_escape_string($conn, $old['item_name']);
        $br_v      = (int) $old['branch_id'];

        $sqlDup = "SELECT id FROM inventory
                   WHERE UPPER(item_name) = UPPER('$name_safe')
                     AND branch_id = $br_v";
        if ($isEdit) {
            $sqlDup .= " AND id <> $editId";
        }
        $sqlDup .= " LIMIT 1";

        $dup = mysqli_query($conn, $sqlDup);
        if ($dup && mysqli_num_rows($dup) > 0) {
            $errorList[] = 'This inventory name already exists in the selected branch.';
        }
    }

    /* Insert or Update */
    if (empty($errorList)) {

        $esc = function ($v) use ($conn) {
            return mysqli_real_escape_string($conn, $v);
        };

        $br_v      = (int) $old['branch_id'];
        $name_v    = "'" . $esc($old['item_name']) . "'";
        $unit_v    = $old['unit'] !== '' ? "'" . $esc($old['unit']) . "'" : "NULL";
        $rate_v    = (float) $old['rate'];
        $active_v  = (int) $old['active'];

        if ($isEdit) {

            $sql = "UPDATE inventory SET
                        branch_id = $br_v,
                        item_name = $name_v,
                        unit      = $unit_v,
                        rate      = $rate_v,
                        active    = $active_v
                    WHERE id = $editId";

            if (mysqli_query($conn, $sql)) {
                if ($isPopup) {
                    $popup_saved_id    = $editId;
                    $popup_saved_label = $old['item_name'];
                } else {
                    header("Location: inventory-list.php?msg=updated");
                    exit;
                }
            } else {
                $errorList[] = 'Failed to update inventory: ' . mysqli_error($conn);
            }
        } else {

            $sql = "INSERT INTO inventory (branch_id, item_name, unit, rate, active)
                    VALUES ($br_v, $name_v, $unit_v, $rate_v, $active_v)";

            if (mysqli_query($conn, $sql)) {
                $newId = mysqli_insert_id($conn);

                if ($isPopup) {
                    $popup_saved_id    = $newId;
                    $popup_saved_label = $old['item_name'];
                } else {
                    header("Location: inventory-list.php?msg=added");
                    exit;
                }
            } else {
                $errorList[] = 'Failed to save inventory: ' . mysqli_error($conn);
            }
        }
    }

    if (!empty($errorList)) {
        $error = implode('<br>', array_map(function ($e) {
            return '&bull; ' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8');
        }, $errorList));
    }
}

$popupPostScript = '';
if ($isPopup && $popup_saved_id > 0) {
    $safeId    = (int) $popup_saved_id;
    $safeLabel = json_encode($popup_saved_label, JSON_UNESCAPED_UNICODE);
    $popupPostScript = <<<JS
        <script>
            (function () {
                try {
                    window.parent.postMessage({
                        type: 'master-saved',
                        entity: 'inventory',
                        id: $safeId,
                        label: $safeLabel
                    }, '*');
                } catch (e) {}
            })();
        </script>
JS;
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
    <?php if ($isPopup): ?>
        <style>
            body {
                background: #fff;
            }

            #wrapper {
                display: block;
            }

            #content-wrapper {
                margin-left: 0 !important;
            }

            .container-fluid {
                padding: 1rem 1.25rem;
            }
        </style>
    <?php endif; ?>
</head>

<body id="page-top">

    <?php if ($isPopup): ?>
        <div class="container-fluid">
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle mr-1"></i>
                    <strong>Please fix the following:</strong>
                    <div class="mt-2"><?= $error ?></div>
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            <?php endif; ?>

            <form id="inventoryForm" action="" method="POST" novalidate>
                <input type="hidden" name="active" value="<?= (int) $old['active'] ?>">
                <input type="hidden" name="popup_mode" value="1">

                <div class="card shadow-sm mb-3">
                    <div class="card-header py-2">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-box mr-1"></i> Inventory Details
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Branch <span class="text-danger">*</span></label>
                            <select name="branch_id" class="form-control">
                                <option value="">— Select Branch —</option>
                                <?php foreach ($branches as $b): ?>
                                    <option value="<?= (int) $b['id'] ?>"
                                        <?= $old['branch_id'] === (int) $b['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($b['branch_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="item_name">Inventory Name <span class="text-danger">*</span></label>
                            <input type="text" id="item_name" name="item_name"
                                class="form-control" maxlength="80"
                                placeholder="Enter inventory name"
                                value="<?= htmlspecialchars($old['item_name'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="unit">Unit</label>
                                <input type="text" id="unit" name="unit"
                                    class="form-control" maxlength="20"
                                    placeholder="Bags / Tons / Pieces"
                                    value="<?= htmlspecialchars($old['unit'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="rate">Rate (₹)</label>
                                <input type="number" id="rate" name="rate"
                                    class="form-control" step="0.01" min="0"
                                    placeholder=""
                                    value="<?= htmlspecialchars($old['rate'] == '0' ? '' : $old['rate'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end pt-2 border-top">
                    <button type="button" class="btn btn-secondary mr-2" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check mr-1"></i> Save
                    </button>
                </div>
            </form>
        </div>

        <script src="vendor/jquery/jquery.min.js"></script>
        <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
        <script>
            $(function() {
                $(document).on('click', '[data-dismiss="modal"]', function() {
                    try {
                        window.parent.postMessage({
                            type: 'master-cancelled',
                            entity: 'inventory'
                        }, '*');
                    } catch (e) {}
                });
            });
        </script>
        <?= $popupPostScript ?>

    <?php else: ?>
        <div id="wrapper">
            <?php include 'layout/sidebar.php'; ?>
            <div id="content-wrapper" class="d-flex flex-column">
                <div id="content">
                    <?php include 'layout/topbar.php'; ?>
                    <div class="container-fluid">

                        <div class="d-sm-flex align-items-center justify-content-between mb-4">
                            <h1 class="h3 mb-0 text-gray-800">
                                <i class="fas <?= $isEdit ? 'fa-edit' : 'fa-plus-circle' ?> mr-2"></i>
                                <?= $isEdit ? 'Edit Inventory' : 'Add Inventory' ?>
                            </h1>
                            <a href="inventory-list.php"
                                class="d-inline-block btn btn-sm btn-secondary shadow-sm">
                                <i class="fas fa-arrow-left fa-sm text-white-50 mr-1"></i>
                                Back to Inventory
                            </a>
                        </div>

                        <?php if ($error !== ''): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle mr-1"></i>
                                <strong>Please fix the following:</strong>
                                <div class="mt-2"><?= $error ?></div>
                                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                            </div>
                        <?php endif; ?>

                        <form id="inventoryForm" action="" method="POST" novalidate>
                            <input type="hidden" name="active" value="<?= (int) $old['active'] ?>">

                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="fas fa-box mr-1"></i> Inventory Details
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="branch_id">
                                                    Branch <span class="text-danger">*</span>
                                                </label>
                                                <select id="branch_id" name="branch_id" class="form-control">
                                                    <option value="">— Select Branch —</option>
                                                    <?php foreach ($branches as $b): ?>
                                                        <option value="<?= (int) $b['id'] ?>"
                                                            <?= $old['branch_id'] === (int) $b['id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($b['branch_name'], ENT_QUOTES, 'UTF-8') ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="item_name">
                                                    Inventory Name <span class="text-danger">*</span>
                                                </label>
                                                <input type="text" id="item_name" name="item_name"
                                                    class="form-control" maxlength="80"
                                                    placeholder="Enter inventory name"
                                                    value="<?= htmlspecialchars($old['item_name'], ENT_QUOTES, 'UTF-8') ?>">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="unit">Unit</label>
                                                <input type="text" id="unit" name="unit"
                                                    class="form-control" maxlength="20"
                                                    placeholder="Bags / Tons / Pieces"
                                                    value="<?= htmlspecialchars($old['unit'], ENT_QUOTES, 'UTF-8') ?>">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="rate">Rate (₹)</label>
                                                <input type="number" id="rate" name="rate"
                                                    class="form-control" step="0.01" min="0"
                                                    placeholder="0.00"
                                                    value="<?= htmlspecialchars($old['rate'] == '0' ? '' : $old['rate'], ENT_QUOTES, 'UTF-8') ?>">
                                                <small class="form-text text-muted">
                                                    Standard rate/price per unit. Optional.
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card shadow mb-4">
                                <div class="card-body py-3 d-flex align-items-center justify-content-between flex-wrap">
                                    <div class="text-muted small mb-2 mb-sm-0">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Fields marked <span class="text-danger">*</span> are required.
                                    </div>
                                    <div>
                                        <?php if ($isEdit): ?>
                                            <a href="inventory-list.php" class="btn btn-secondary">
                                                <i class="fas fa-times mr-1"></i>Cancel
                                            </a>
                                        <?php else: ?>
                                            <a href="inventory.php" class="btn btn-secondary">
                                                <i class="fas fa-redo mr-1"></i>Clear
                                            </a>
                                        <?php endif; ?>

                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-check mr-1"></i>
                                            <?= $isEdit ? 'Update Inventory' : 'Save Inventory' ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>

                    </div>
                </div>
                <?php include 'layout/footer.php'; ?>
            </div>
        </div>
    <?php endif; ?>

</body>

</html>