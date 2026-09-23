<?php
/* save-expense.php — AJAX insert of one expense row */

include __DIR__ . '/../session.php';
include __DIR__ . '/../constant.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required.']);
    exit;
}

if (!hasPermission('trip.edit')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied.']);
    exit;
}

$trip_id      = (int) ($_POST['trip_id']      ?? 0);
$expense_date = trim($_POST['expense_date'] ?? '');
$category     = trim($_POST['category']     ?? '');
$amount       = trim($_POST['amount']       ?? '0');
$vendor_id    = (int) ($_POST['vendor_id']  ?? 0);
$notes        = trim($_POST['notes']        ?? '');

$errorList = [];

if ($trip_id <= 0) $errorList[] = 'Invalid trip.';

if ($expense_date === '') {
    $errorList[] = 'Expense date is required.';
} else {
    $d = DateTime::createFromFormat('Y-m-d', $expense_date);
    if (!$d || $d->format('Y-m-d') !== $expense_date) {
        $errorList[] = 'Invalid expense date.';
    }
}

if ($category === '') $errorList[] = 'Category is required.';
if ($amount === '' || !is_numeric($amount) || (float) $amount < 0) {
    $errorList[] = 'Amount must be a valid non-negative number.';
}

$tripBranchId = 0;

if ($trip_id > 0) {
    $myBranch  = (int) ($_SESSION['branch_id'] ?? 0);
    $canSeeAll = hasPermission('trip.view.all');

    $branchCheck = '';
    if (!$canSeeAll) {
        if ($myBranch > 0) {
            $branchCheck = " AND (branch_id = $myBranch
                                OR from_branch_id = $myBranch
                                OR to_branch_id   = $myBranch)";
        } else {
            $branchCheck = " AND 1=0";
        }
    }

    $resT = mysqli_query($conn,
        "SELECT id, branch_id FROM trip WHERE id = $trip_id $branchCheck LIMIT 1");

    if (!$resT || mysqli_num_rows($resT) !== 1) {
        $errorList[] = 'Trip not found.';
    } else {
        $tripRow = mysqli_fetch_assoc($resT);
        $tripBranchId = (int) $tripRow['branch_id'];
    }
}

if (!empty($errorList)) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errorList)]);
    exit;
}

$esc = function ($v) use ($conn) {
    return mysqli_real_escape_string($conn, $v);
};

$date_v     = "'" . $esc($expense_date) . "'";
$category_v = "'" . $esc($category) . "'";
$amount_v   = "'" . number_format((float) $amount, 2, '.', '') . "'";
$vendor_v   = $vendor_id > 0 ? (int) $vendor_id : "NULL";
$notes_v    = $notes !== '' ? "'" . $esc($notes) . "'" : "NULL";
$branch_v   = (int) $tripBranchId;
$created_by = (int) ($_SESSION['user_id'] ?? 0);

$sql = "INSERT INTO expense
            (trip_id, expense_date, category, amount, vendor_id, notes,
             branch_id, created_by, active)
        VALUES
            ($trip_id, $date_v, $category_v, $amount_v, $vendor_v, $notes_v,
             $branch_v, $created_by, 1)";

if (!mysqli_query($conn, $sql)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save expense: ' . mysqli_error($conn)]);
    exit;
}

$newId = mysqli_insert_id($conn);

$vendorName = '';
if ($vendor_id > 0) {
    $resV = mysqli_query($conn, "SELECT vendor_name FROM vendor WHERE id = $vendor_id LIMIT 1");
    if ($resV && mysqli_num_rows($resV) === 1) {
        $vrow = mysqli_fetch_assoc($resV);
        $vendorName = $vrow['vendor_name'];
    }
}

echo json_encode([
    'success'     => true,
    'id'          => $newId,
    'trip_id'     => $trip_id,
    'amount'      => (float) $amount,
    'category'    => $category,
    'date'        => $expense_date,
    'vendor_name' => $vendorName,
    'notes'       => $notes
]);