<?php

include 'constant.php';
include 'session.php';

$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$isEdit = $editId > 0;

/* Return-to-sender support */
$return_to = isset($_GET['return'])
    ? preg_replace('/[^a-z0-9_\-\.]/i', '', $_GET['return'])
    : '';

requirePermission($isEdit ? 'item.edit' : 'item.create');

$pageTitle = $isEdit ? 'Edit Item | Billing Portal' : 'Add Item | Billing Portal';

/* =========================================================
   FORM STATE
   ========================================================= */
$error     = '';
$errorList = [];

$old = [
    'item_name' => '',
    'unit'      => '',
    'active'    => 1,
];

/* =========================================================
   LOAD (Edit mode)
   ========================================================= */
if ($isEdit) {

    $res = mysqli_query($conn, "SELECT * FROM item WHERE id = $editId LIMIT 1");

    if (!$res || mysqli_num_rows($res) !== 1) {
        header("Location: items-list.php");
        exit;
    }

    $row = mysqli_fetch_assoc($res);

    $old['item_name'] = $row['item_name'] ?? '';
    $old['unit']      = $row['unit']      ?? '';
    $old['active']    = (int) ($row['active'] ?? 1);
}

/* =========================================================
   SUBMIT
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $old['item_name'] = trim($_POST['item_name'] ?? '');
    $old['unit']      = trim($_POST['unit']      ?? '');
    $old['active']    = isset($_POST['active']) ? (int) $_POST['active'] : 1;

    /* Return-to-sender: prefer posted value over GET */
    if (isset($_POST['return_to'])) {
        $return_to = preg_replace('/[^a-z0-9_\-\.]/i', '', $_POST['return_to']);
    }

    /* Validate */
    if ($old['item_name'] === '') {
        $errorList[] = 'Item name is required.';
    } elseif (strlen($old['item_name']) > 80) {
        $errorList[] = 'Item name is too long (max 80).';
    }

    if (strlen($old['unit']) > 20) {
        $errorList[] = 'Unit is too long (max 20).';
    }

    /* Duplicate check */
    if (empty($errorList)) {

        $name_safe = mysqli_real_escape_string($conn, $old['item_name']);

        $sqlDup = "SELECT id FROM item WHERE UPPER(item_name) = UPPER('$name_safe')";
        if ($isEdit) {
            $sqlDup .= " AND id <> $editId";
        }
        $sqlDup .= " LIMIT 1";

        $dup = mysqli_query($conn, $sqlDup);
        if ($dup && mysqli_num_rows($dup) > 0) {
            $errorList[] = 'An item with this name already exists.';
        }
    }

    /* Insert or Update */
    if (empty($errorList)) {

        $esc = function ($v) use ($conn) {
            return mysqli_real_escape_string($conn, $v);
        };

        $name_v   = "'" . $esc($old['item_name']) . "'";
        $unit_v   = $old['unit'] !== '' ? "'" . $esc($old['unit']) . "'" : "NULL";
        $active_v = (int) $old['active'];

        if ($isEdit) {

            $sql = "UPDATE item SET
                        item_name = $name_v,
                        unit      = $unit_v,
                        active    = $active_v
                    WHERE id = $editId";

            if (mysqli_query($conn, $sql)) {
                header("Location: items-list.php?msg=updated");
                exit;
            } else {
                $errorList[] = 'Failed to update item: ' . mysqli_error($conn);
            }
        } else {

            $sql = "INSERT INTO item (item_name, unit, active)
                    VALUES ($name_v, $unit_v, $active_v)";

            if (mysqli_query($conn, $sql)) {

                $newId = mysqli_insert_id($conn);

                if ($return_to !== '') {
                    $sep = (strpos($return_to, '?') !== false) ? '&' : '?';
                    header("Location: $return_to{$sep}new_item_id=$newId&msg=added");
                } else {
                    header("Location: items-list.php?msg=added");
                }
                exit;
            } else {
                $errorList[] = 'Failed to save item: ' . mysqli_error($conn);
            }
        }
    }

    if (!empty($errorList)) {
        $error = implode('<br>', array_map(function ($e) {
            return '&bull; ' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8');
        }, $errorList));
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
                            <i class="fas <?= $isEdit ? 'fa-edit' : 'fa-plus-circle' ?> mr-2"></i>
                            <?= $isEdit ? 'Edit Item' : 'Add Item' ?>
                        </h1>
                        <a href="items-list.php"
                            class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
                            <i class="fas fa-arrow-left fa-sm text-white-50 mr-1"></i>
                            Back to Items
                        </a>
                    </div>

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle mr-1"></i>
                            <strong>Please fix the following:</strong>
                            <div class="mt-2"><?= $error ?></div>
                            <button type="button" class="close" data-dismiss="alert">
                                <span>&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <form id="itemForm" action="" method="POST" novalidate>

                        <input type="hidden" name="active" value="<?= (int) $old['active'] ?>">

                        <!-- Return-to-sender -->
                        <input type="hidden" name="return_to" value="<?= htmlspecialchars($return_to, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="card shadow mb-4">

                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-box mr-1"></i>
                                    Item Details
                                </h6>
                            </div>

                            <div class="card-body">

                                <div class="row">

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="item_name">
                                                Item Name <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" id="item_name" name="item_name"
                                                class="form-control" maxlength="80"
                                                placeholder="Enter item name"
                                                value="<?= htmlspecialchars($old['item_name'], ENT_QUOTES, 'UTF-8') ?>">
                                            <small class="form-text text-muted">
                                                e.g. Cement, Steel, FMCG, Coal
                                            </small>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="unit">Unit of Measure</label>
                                            <input type="text" id="unit" name="unit"
                                                class="form-control" maxlength="20"
                                                placeholder="Enter unit"
                                                value="<?= htmlspecialchars($old['unit'], ENT_QUOTES, 'UTF-8') ?>">
                                            <small class="form-text text-muted">
                                                e.g. Bags, Tons, Pieces, Cartons
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
                                        <a href="items-list.php" class="btn btn-secondary">
                                            <i class="fas fa-times mr-1"></i>Cancel
                                        </a>
                                    <?php else: ?>
                                        <a href="item.php" class="btn btn-secondary">
                                            <i class="fas fa-redo mr-1"></i>Clear
                                        </a>
                                    <?php endif; ?>

                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-check mr-1"></i>
                                        <?= $isEdit ? 'Update Item' : 'Save Item' ?>
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

</body>

</html>