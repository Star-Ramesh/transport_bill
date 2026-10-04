<?php

include 'session.php';
include 'constant.php';

/* =========================================================
   AJAX: Fetch Item Rate (Party-wise > Default)
   ========================================================= */
if (isset($_GET['action']) && $_GET['action'] === 'get_item_rate') {
    header('Content-Type: application/json');
    $party_id = (int)($_POST['party_id'] ?? 0);
    $inv_id   = (int)($_POST['inventory_id'] ?? 0);
    $rate     = '';

    // 1. Check Party Special Rate
    if ($party_id > 0 && $inv_id > 0) {
        $prq = mysqli_query($conn, "SELECT rate FROM party_rate WHERE party_id = $party_id AND inventory_id = $inv_id AND active = 1 LIMIT 1");
        if ($prq && mysqli_num_rows($prq) > 0) {
            $row = mysqli_fetch_assoc($prq);
            $rate = (float)$row['rate'];
        }
    }

    // 2. Check Default Inventory Rate
    if ($rate === '' && $inv_id > 0) {
        $iq = mysqli_query($conn, "SELECT rate FROM inventory WHERE id = $inv_id LIMIT 1");
        if ($iq && mysqli_num_rows($iq) > 0) {
            $row = mysqli_fetch_assoc($iq);
            $dbRate = (float)$row['rate'];
            if ($dbRate > 0) {
                $rate = $dbRate;
            }
        }
    }

    echo json_encode(['success' => true, 'rate' => $rate === '' ? '' : $rate]);
    exit;
}

/* =========================================================
   AJAX: Fetch Lorry Details
   ========================================================= */
if (isset($_GET['action']) && $_GET['action'] === 'get_lorry_details') {
    header('Content-Type: application/json');
    $lorry_id = (int)($_POST['lorry_id'] ?? 0);
    
    if ($lorry_id > 0) {
        $res = mysqli_query($conn, "SELECT ownership_type, preferred_driver_id FROM lorry WHERE id = $lorry_id LIMIT 1");
        if ($res && mysqli_num_rows($res) === 1) {
            $row = mysqli_fetch_assoc($res);
            echo json_encode([
                'success' => true, 
                'ownership_type' => (int)$row['ownership_type'], 
                'preferred_driver_id' => (int)$row['preferred_driver_id']
            ]);
            exit;
        }
    }
    
    echo json_encode(['success' => false]);
    exit;
}

$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$isEdit = ($editId > 0);

if ($isEdit) {
    requirePermission('trip.edit');
    $pageTitle = 'Edit Trip | Billing Portal';
} else {
    requirePermission('trip.create');
    $pageTitle = 'Add Trip | Billing Portal';
}

$isAdminUser = isAdmin();
$myBranch    = (int) ($_SESSION['branch_id'] ?? 0);

/* USER'S BRANCH */
$userBranchCode = 'BR';
$userBranchName = '';
if ($myBranch > 0) {
    $br = mysqli_query($conn, "SELECT branch_code, branch_name FROM branch WHERE id = $myBranch LIMIT 1");
    if ($br && mysqli_num_rows($br) === 1) {
        $brRow = mysqli_fetch_assoc($br);
        $userBranchCode = $brRow['branch_code'] !== '' ? strtoupper($brRow['branch_code']) : 'BR';
        $userBranchName = $brRow['branch_name'];
    }
}

/* MASTER LISTS */
$lorries = [];
$resL = mysqli_query($conn, "SELECT id, lorry_number, lorry_type, ownership_type, supplier_id, preferred_driver_id FROM lorry WHERE active = 1 ORDER BY lorry_number");
if ($resL) while ($r = mysqli_fetch_assoc($resL)) $lorries[] = $r;

$drivers = [];
$resD = mysqli_query($conn, "SELECT id, driver_name, phone, preferred_lorry_id FROM driver WHERE active = 1 ORDER BY driver_name");
if ($resD) while ($r = mysqli_fetch_assoc($resD)) $drivers[] = $r;

$parties = [];
$resP = mysqli_query($conn, "SELECT id, legal_name, trade_name FROM party WHERE active = 1 ORDER BY legal_name");
if ($resP) while ($r = mysqli_fetch_assoc($resP)) $parties[] = $r;

$consignees = [];
$resC = mysqli_query($conn, "SELECT id, legal_name, trade_name FROM party WHERE active = 1 ORDER BY legal_name");
if ($resC) {
    while ($r = mysqli_fetch_assoc($resC)) {
        $r['branch_name'] = '';
        $consignees[] = $r;
    }
}

$inventoryList = [];
$sqlInv = "SELECT id, item_name, unit FROM inventory WHERE active = 1";
if (!$isAdminUser && $myBranch > 0) $sqlInv .= " AND branch_id = $myBranch";
$sqlInv .= " ORDER BY item_name";
$resI = mysqli_query($conn, $sqlInv);
if ($resI) while ($r = mysqli_fetch_assoc($resI)) $inventoryList[] = $r;

$cities = [];
$resCity = mysqli_query($conn, "SELECT city_name FROM city WHERE active = 1 ORDER BY use_count DESC, city_name ASC");
if ($resCity) while ($r = mysqli_fetch_assoc($resCity)) $cities[] = $r['city_name'];

/* AUTO-PREVIEW TRIP NUMBER */
function previewTripNo($conn, $branchId, $branchCode, $startDate)
{
    $yymm = date('ym', strtotime($startDate));
    $prefix = $branchCode . '-' . $yymm . '-';
    $prefix_safe = mysqli_real_escape_string($conn, $prefix);

    $cnt_res = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS c FROM trip WHERE branch_id = $branchId AND trip_no LIKE '$prefix_safe%'"
    );
    $next_seq = 1;
    if ($cnt_res) {
        $crow = mysqli_fetch_assoc($cnt_res);
        $next_seq = ((int) $crow['c']) + 1;
    }
    return $prefix . str_pad($next_seq, 4, '0', STR_PAD_LEFT);
}

$autoTripNo = previewTripNo($conn, $myBranch, $userBranchCode, date('Y-m-d'));

/* FORM STATE */
$tripData = [
    'trip_no'            => $autoTripNo,
    'source'             => '',
    'destination'        => '',
    'lorry_id'           => 0,
    'driver_id'          => 0,
    'start_date'         => date('Y-m-d'),
    'notes'              => '',
    'status'             => 'Scheduled',
    'party_id'           => 0,
    'consignee_party_id' => 0,
    'freight_mode'       => 0,
    'freight_amount'     => '',
    'party_notes'        => '',
    'truck_hire_charge'  => '',
    'truck_hire_exp_id'  => 0,
];

$initialItems = [];

if ($isEdit) {
    $tQuery = mysqli_query($conn, "SELECT * FROM trip WHERE id = $editId LIMIT 1");
    if (!$tQuery || mysqli_num_rows($tQuery) === 0) {
        header("Location: trips-list.php");
        exit;
    }
    $tripRow = mysqli_fetch_assoc($tQuery);

    if (in_array($tripRow['status'], ['Billed', 'Paid'], true) || (int)$tripRow['active'] !== 1) {
        header("Location: trip-view.php?id=$editId&msg=locked");
        exit;
    }

    $tripData['trip_no']     = $tripRow['trip_no'];
    $tripData['source']      = $tripRow['source']      ?? '';
    $tripData['destination'] = $tripRow['destination'] ?? '';
    $tripData['lorry_id']    = (int) $tripRow['lorry_id'];
    $tripData['driver_id']   = (int) $tripRow['driver_id'];
    $tripData['start_date']  = $tripRow['start_date'];
    $tripData['notes']       = $tripRow['notes'];
    $tripData['status']      = $tripRow['status'];

    $tpQuery = mysqli_query($conn, "SELECT * FROM trip_party WHERE trip_id = $editId LIMIT 1");
    if ($tpQuery && mysqli_num_rows($tpQuery) > 0) {
        $tpRow = mysqli_fetch_assoc($tpQuery);
        $tripPartyId = (int) $tpRow['id'];
        $tripData['party_id']           = (int) $tpRow['party_id'];
        $tripData['consignee_party_id'] = (int) $tpRow['consignee_party_id'];
        $tripData['freight_mode']       = (int) ($tpRow['freight_mode'] ?? 0);
        $tripData['freight_amount']     = $tpRow['freight_amount'];
        $tripData['party_notes']        = $tpRow['notes'];

        $tiQuery = mysqli_query($conn, "SELECT * FROM trip_inventory WHERE trip_party_id = $tripPartyId AND active = 1");
        if ($tiQuery) {
            while ($tiRow = mysqli_fetch_assoc($tiQuery)) {
                $initialItems[] = [
                    'item_id'   => (int) $tiRow['inventory_id'],
                    'item_name' => $tiRow['inventory_name'],
                    'unit'      => $tiRow['unit'],
                    'quantity'  => $tiRow['quantity'],
                    'rate'      => $tiRow['rate'],
                ];
            }
        }
    }

    $thcQuery = mysqli_query($conn, "SELECT id, amount FROM expense WHERE trip_id = $editId AND category = 'Truck Hire Charge' AND active = 1 ORDER BY id DESC LIMIT 1");
    if ($thcQuery && mysqli_num_rows($thcQuery) === 1) {
        $thcRow = mysqli_fetch_assoc($thcQuery);
        $tripData['truck_hire_exp_id'] = (int) $thcRow['id'];
        $tripData['truck_hire_charge'] = (string) $thcRow['amount'];
    }
}

/* upsertCity helper */
function upsertCity($conn, $rawName)
{
    $name = trim($rawName);
    if ($name === '') return '';

    $safe = mysqli_real_escape_string($conn, $name);

    $res = mysqli_query($conn, "SELECT id, city_name FROM city WHERE UPPER(city_name) = UPPER('$safe') LIMIT 1");

    if ($res && mysqli_num_rows($res) === 1) {
        $row = mysqli_fetch_assoc($res);
        $id  = (int) $row['id'];
        mysqli_query($conn, "UPDATE city SET use_count = use_count + 1 WHERE id = $id");
        return $row['city_name'];
    }

    mysqli_query($conn, "INSERT INTO city (city_name, use_count, active) VALUES ('$safe', 1, 1)");
    return $name;
}

$error     = '';
$errorList = [];

/* SUBMIT */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $trip_no     = strtoupper(trim($_POST['trip_no'] ?? ''));
    $source      = trim($_POST['source']      ?? '');
    $destination = trim($_POST['destination'] ?? '');

    $lorry_id   = (int) ($_POST['lorry_id']  ?? 0);
    $driver_id  = (int) ($_POST['driver_id'] ?? 0);
    $start_date = trim($_POST['start_date']  ?? '');
    $trip_notes = trim($_POST['trip_notes']  ?? '');

    $party_id           = (int) ($_POST['party_id']           ?? 0);
    $consignee_party_id = (int) ($_POST['consignee_party_id'] ?? 0);
    $freight_mode       = (int) ($_POST['freight_mode'] ?? 0);
    $freight_amount     = trim($_POST['freight_amount'] ?? '0');
    $party_notes        = trim($_POST['party_notes']    ?? '');
    $truck_hire_charge  = trim($_POST['truck_hire_charge'] ?? '');

    if ($freight_mode !== 1) $freight_mode = 0;

    $tripData['trip_no']     = $trip_no;
    $tripData['source']      = $source;
    $tripData['destination'] = $destination;
    $tripData['lorry_id']    = $lorry_id;
    $tripData['driver_id']   = $driver_id;
    $tripData['start_date']  = $start_date;
    $tripData['notes']       = $trip_notes;
    $tripData['party_id']    = $party_id;
    $tripData['consignee_party_id'] = $consignee_party_id;
    $tripData['freight_mode']       = $freight_mode;
    $tripData['freight_amount']     = $freight_amount;
    $tripData['party_notes']        = $party_notes;
    $tripData['truck_hire_charge']  = $truck_hire_charge;

    $items = [];
    if (isset($_POST['items']) && is_array($_POST['items'])) {
        foreach ($_POST['items'] as $row) {
            $itm_name = trim($row['item_name'] ?? '');
            if ($itm_name === '') continue;
            $items[] = [
                'item_id'   => (int) ($row['item_id']  ?? 0),
                'item_name' => $itm_name,
                'unit'      => trim($row['unit']     ?? ''),
                'quantity'  => trim($row['quantity'] ?? '0'),
                'rate'      => trim($row['rate']     ?? '0'),
            ];
        }
    }

    if ($trip_no === '') {
        $errorList[] = 'Trip No is required.';
    } elseif (strlen($trip_no) > 30) {
        $errorList[] = 'Trip No is too long (max 30).';
    }

    if ($source === '')      $errorList[] = 'Source city is required.';
    if ($destination === '') $errorList[] = 'Destination city is required.';
    if ($lorry_id  <= 0)     $errorList[] = 'Please select a truck.';
    if ($driver_id <= 0)     $errorList[] = 'Please select a driver.';

    if ($start_date === '') {
        $errorList[] = 'Start date is required.';
    } else {
        $d = DateTime::createFromFormat('Y-m-d', $start_date);
        if (!$d || $d->format('Y-m-d') !== $start_date) $errorList[] = 'Invalid start date.';
    }

    if ($party_id <= 0) $errorList[] = 'Please select a consignor.';

    if ($freight_mode === 0) {
        if ($freight_amount === '' || !is_numeric($freight_amount) || (float) $freight_amount < 0) {
            $errorList[] = 'Freight amount must be a valid non-negative number.';
        }
    }

    if (empty($errorList) && $trip_no !== '') {
        $tn_safe = mysqli_real_escape_string($conn, $trip_no);
        $sqlDup = "SELECT id FROM trip WHERE UPPER(trip_no) = UPPER('$tn_safe')";
        if ($isEdit) $sqlDup .= " AND id <> $editId";
        $sqlDup .= " LIMIT 1";
        $dup = mysqli_query($conn, $sqlDup);
        if ($dup && mysqli_num_rows($dup) > 0) {
            $errorList[] = 'Trip No "' . htmlspecialchars($trip_no) . '" already exists. Please choose another.';
        }
    }

    if (count($items) === 0) {
        $errorList[] = 'Add at least one inventory row to the trip.';
    } else {
        foreach ($items as $i => $it) {
            $n = $i + 1;
            if ($it['quantity'] === '' || !is_numeric($it['quantity'])) {
                $errorList[] = "Row #$n: quantity must be a valid number.";
            } else {
                $qtyF = (float) $it['quantity'];
                if (floor($qtyF) != $qtyF) {
                    $errorList[] = "Row #$n: quantity must be a whole number (no decimals).";
                } elseif ($qtyF < 0) {
                    $errorList[] = "Row #$n: quantity cannot be negative.";
                }
            }
            if ($freight_mode === 1) {
                if ($it['rate'] === '' || !is_numeric($it['rate']) || (float) $it['rate'] < 0) {
                    $errorList[] = "Row #$n: rate must be a valid non-negative number.";
                }
            }
        }
    }

    $truckOwnership  = 0;
    $truckSupplierId = 0;
    if ($lorry_id > 0) {
        $truckQ = mysqli_query($conn, "SELECT ownership_type, supplier_id FROM lorry WHERE id = $lorry_id LIMIT 1");
        if ($truckQ && mysqli_num_rows($truckQ) === 1) {
            $tr = mysqli_fetch_assoc($truckQ);
            $truckOwnership  = (int) $tr['ownership_type'];
            $truckSupplierId = (int) ($tr['supplier_id'] ?? 0);
        }
    }

    if ($truckOwnership === 1) {
        if ($truck_hire_charge === '' || !is_numeric($truck_hire_charge) || (float) $truck_hire_charge < 0) {
            $errorList[] = 'Truck hire charge is required for a 3rd-party truck (enter 0 if not charged).';
        }
    } else {
        $truck_hire_charge = '';
    }

    if ($freight_mode === 1 && empty($errorList)) {
        $sum = 0;
        foreach ($items as $it) {
            $sum += (int) $it['quantity'] * (float) $it['rate'];
        }
        $freight_amount = (string) $sum;
    }

    if (empty($errorList)) {

        $esc = function ($v) use ($conn) {
            return mysqli_real_escape_string($conn, $v);
        };

        mysqli_begin_transaction($conn);

        try {
            $sourceCanonical      = upsertCity($conn, $source);
            $destinationCanonical = upsertCity($conn, $destination);

            if ($isEdit) {
                mysqli_query($conn, "DELETE FROM trip_inventory WHERE trip_party_id IN (SELECT id FROM trip_party WHERE trip_id = $editId)");
                mysqli_query($conn, "DELETE FROM trip_party WHERE trip_id = $editId");

                $lorry_v      = $lorry_id  > 0 ? $lorry_id  : "NULL";
                $driver_v     = $driver_id > 0 ? $driver_id : "NULL";
                $trip_notes_v = $trip_notes !== '' ? "'" . $esc($trip_notes) . "'" : "NULL";
                $start_date_v = "'" . $esc($start_date) . "'";
                $source_v     = "'" . $esc($sourceCanonical) . "'";
                $dest_v       = "'" . $esc($destinationCanonical) . "'";
                $trip_no_v    = "'" . $esc($trip_no) . "'";

                $sql_trip_upd = "UPDATE trip SET
                                    trip_no = $trip_no_v,
                                    source = $source_v,
                                    destination = $dest_v,
                                    lorry_id = $lorry_v,
                                    driver_id = $driver_v,
                                    start_date = $start_date_v,
                                    notes = $trip_notes_v
                                 WHERE id = $editId";
                if (!mysqli_query($conn, $sql_trip_upd)) {
                    throw new Exception('Failed to update trip: ' . mysqli_error($conn));
                }
                $target_trip_id = $editId;
                $msgKey = 'updated';

            } else {

                $lorry_v      = $lorry_id  > 0 ? $lorry_id  : "NULL";
                $driver_v     = $driver_id > 0 ? $driver_id : "NULL";
                $trip_notes_v = $trip_notes !== '' ? "'" . $esc($trip_notes) . "'" : "NULL";
                $start_date_v = "'" . $esc($start_date) . "'";
                $trip_no_v    = "'" . $esc($trip_no) . "'";
                $source_v     = "'" . $esc($sourceCanonical) . "'";
                $dest_v       = "'" . $esc($destinationCanonical) . "'";
                $created_by_v = (int) ($_SESSION['user_id'] ?? 0);
                $branch_v     = (int) $myBranch;

                $sql_trip = "INSERT INTO trip
                                (trip_no, source, destination,
                                 lorry_id, driver_id,
                                 start_date, end_date, status, notes,
                                 branch_id, created_by, active)
                             VALUES
                                ($trip_no_v, $source_v, $dest_v,
                                 $lorry_v, $driver_v,
                                 $start_date_v, NULL, 'Scheduled', $trip_notes_v,
                                 $branch_v, $created_by_v, 1)";

                if (!mysqli_query($conn, $sql_trip)) {
                    throw new Exception('Failed to save trip: ' . mysqli_error($conn));
                }
                $target_trip_id = mysqli_insert_id($conn);
                $msgKey = 'added';
            }

            $freight_v = "'" . number_format((float) $freight_amount, 2, '.', '') . "'";
            $pnotes_v  = $party_notes !== '' ? "'" . $esc($party_notes) . "'" : "NULL";
            $consignee_v = $consignee_party_id > 0 ? (int) $consignee_party_id : "NULL";
            $fmode_v     = (int) $freight_mode;

            $sql_tp = "INSERT INTO trip_party
                          (trip_id, party_id, consignee_party_id,
                           freight_amount, freight_mode, notes, active)
                       VALUES
                          ($target_trip_id, $party_id, $consignee_v,
                           $freight_v, $fmode_v, $pnotes_v, 1)";
            if (!mysqli_query($conn, $sql_tp)) {
                throw new Exception('Failed to save party consignment: ' . mysqli_error($conn));
            }
            $new_trip_party_id = mysqli_insert_id($conn);

            if ($isAdminUser || $myBranch <= 0) {
                $invBranchId = 0;
                $invBranchRes = mysqli_query($conn, "SELECT id FROM branch WHERE active = 1 ORDER BY id LIMIT 1");
                if ($invBranchRes && mysqli_num_rows($invBranchRes) === 1) {
                    $ibr = mysqli_fetch_assoc($invBranchRes);
                    $invBranchId = (int) $ibr['id'];
                }
            } else {
                $invBranchId = (int) $myBranch;
            }

            foreach ($items as $it) {
                $inventory_id = (int) $it['item_id'];
                
                // Creates an item in Master inventory if typed directly
                if ($inventory_id <= 0) {
                    $name_check = mysqli_real_escape_string($conn, $it['item_name']);
                    $chk = mysqli_query($conn, "SELECT id FROM inventory WHERE UPPER(item_name) = UPPER('$name_check') AND branch_id = $invBranchId LIMIT 1");
                    if ($chk && mysqli_num_rows($chk) === 1) {
                        $chk_row = mysqli_fetch_assoc($chk);
                        $inventory_id = (int) $chk_row['id'];
                    } else {
                        $new_name_v = "'" . mysqli_real_escape_string($conn, $it['item_name']) . "'";
                        $new_unit_v = $it['unit'] !== '' ? "'" . mysqli_real_escape_string($conn, $it['unit']) . "'" : "NULL";
                        // Note: Default rate added as 0.00 since it is newly created
                        $ins_inv = "INSERT INTO inventory (branch_id, item_name, unit, rate, active) VALUES ($invBranchId, $new_name_v, $new_unit_v, 0.00, 1)";
                        if (!mysqli_query($conn, $ins_inv)) {
                            throw new Exception('Failed to add inventory master row: ' . mysqli_error($conn));
                        }
                        $inventory_id = mysqli_insert_id($conn);
                    }
                }

                $inv_name_v = "'" . $esc($it['item_name']) . "'";
                $inv_unit_v = $it['unit'] !== '' ? "'" . $esc($it['unit']) . "'" : "NULL";
                $inv_id_v   = $inventory_id > 0 ? $inventory_id : "NULL";
                $qty_int    = (int) $it['quantity'];

                if ($freight_mode === 1) {
                    $rate = (float) $it['rate'];
                    $amt  = $qty_int * $rate;
                } else {
                    $rate = 0;
                    $amt  = 0;
                }

                $rate_v = "'" . number_format($rate, 2, '.', '') . "'";
                $amt_v  = "'" . number_format($amt,  2, '.', '') . "'";

                $sql_it = "INSERT INTO trip_inventory
                              (trip_party_id, inventory_id, inventory_name, unit,
                               quantity, rate, amount, active)
                           VALUES
                              ($new_trip_party_id, $inv_id_v, $inv_name_v, $inv_unit_v,
                               $qty_int, $rate_v, $amt_v, 1)";
                if (!mysqli_query($conn, $sql_it)) {
                    throw new Exception('Failed to save inventory row "' . $it['item_name'] . '": ' . mysqli_error($conn));
                }
            }

            $existingChargeId = 0;
            if ($isEdit) {
                $chk = mysqli_query($conn, "SELECT id FROM expense WHERE trip_id = $target_trip_id AND category = 'Truck Hire Charge' AND active = 1 ORDER BY id DESC LIMIT 1");
                if ($chk && mysqli_num_rows($chk) === 1) {
                    $cr = mysqli_fetch_assoc($chk);
                    $existingChargeId = (int) $cr['id'];
                }
            }

            if ($truckOwnership === 1) {
                $chargeAmount = (float) $truck_hire_charge;
                $chargeAmtV   = "'" . number_format($chargeAmount, 2, '.', '') . "'";
                $supplierV    = $truckSupplierId > 0 ? $truckSupplierId : "NULL";
                $chargeCat    = "'Truck Hire Charge'";
                $chargeNotes  = "'Auto-generated truck hire charge'";
                $chargeDateV  = "'" . $esc($start_date) . "'";

                if ($existingChargeId > 0) {
                    $upd = "UPDATE expense SET expense_date = $chargeDateV, amount = $chargeAmtV, supplier_id = $supplierV WHERE id = $existingChargeId";
                    if (!mysqli_query($conn, $upd)) {
                        throw new Exception('Failed to update truck hire charge: ' . mysqli_error($conn));
                    }
                } else {
                    $branchV   = (int) $myBranch;
                    $createdBy = (int) ($_SESSION['user_id'] ?? 0);

                    $ins = "INSERT INTO expense
                                (trip_id, expense_date, category, amount,
                                 supplier_id, notes, branch_id, created_by, active)
                            VALUES
                                ($target_trip_id, $chargeDateV, $chargeCat, $chargeAmtV,
                                 $supplierV, $chargeNotes, $branchV, $createdBy, 1)";
                    if (!mysqli_query($conn, $ins)) {
                        throw new Exception('Failed to save truck hire charge: ' . mysqli_error($conn));
                    }
                }
            } else {
                if ($existingChargeId > 0) {
                    $del = "UPDATE expense SET active = 0 WHERE id = $existingChargeId";
                    if (!mysqli_query($conn, $del)) {
                        throw new Exception('Failed to clear old truck hire charge: ' . mysqli_error($conn));
                    }
                }
            }

            mysqli_commit($conn);

            $redirectUrl = "trip-view.php?id=$target_trip_id&msg=$msgKey&src=form";
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Redirecting...</title></head><body>';
            echo '<script>window.location.replace(' . json_encode($redirectUrl) . ');</script>';
            echo '<p>Redirecting...</p>';
            echo '</body></html>';
            exit;

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errorList[] = $e->getMessage();
        }
    }

    if (!empty($errorList)) {
        $error = implode('<br>', array_map(function ($e) {
            return '&bull; ' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8');
        }, $errorList));
    }
}

/* Seed one blank inventory row */
if (empty($initialItems)) {
    $initialItems = [ [] ];
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
    <style>
        .trip-card {
            border: 1px solid #e3e6f0;
            border-radius: 10px;
            background: #fff;
            margin-bottom: 1rem;
            box-shadow: 0 1px 4px rgba(30, 34, 51, .04);
        }

        .trip-card-header {
            padding: .85rem 1.1rem;
            border-bottom: 1px solid #eaeef6;
            background: #fbfcff;
            border-radius: 10px 10px 0 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
            flex-wrap: wrap;
        }

        .trip-card-header h6 {
            margin: 0;
            font-size: .92rem;
            font-weight: 800;
            color: #2c2e3e;
            display: flex;
            align-items: center;
            gap: .4rem;
        }

        .trip-card-header h6 i { color: #4e73df; }

        .trip-card-body { padding: 1.1rem; }

        @media (max-width: 575.98px) {
            .trip-card-body { padding: .85rem; }
            .trip-card-header { padding: .7rem .9rem; }
            .trip-card-header h6 { font-size: .85rem; }
        }

        .trip-card .form-group { margin-bottom: .9rem; }

        .trip-card label {
            font-size: .82rem;
            font-weight: 700;
            color: #3a3b45;
            margin-bottom: .3rem;
            display: block;
        }

        .trip-card label .req { color: #e74a3b; }

        .trip-card .form-control {
            font-size: .9rem;
            padding: .5rem .75rem;
            height: auto;
            border-radius: .4rem;
        }

        .input-with-btn {
            display: flex;
            gap: 0;
            align-items: stretch;
        }

        .input-with-btn .form-control {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
            flex: 1 1 auto;
            min-width: 0;
        }

        .input-with-btn .btn {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
            padding: .5rem .8rem;
            flex: 0 0 auto;
            border: 1px solid #4e73df;
            border-left: 0;
            color: #4e73df;
            background: #fff;
            font-weight: 700;
        }

        .input-with-btn .btn:hover {
            background: #4e73df;
            color: #fff;
        }

        .party-block {
            border: 1px solid #e3e6f0;
            border-radius: .5rem;
            padding: 1rem;
            background: #fdfdff;
        }

        .party-block + .party-block { margin-top: .85rem; }

        .party-block .party-title {
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: #4e73df;
            margin-bottom: .75rem;
            display: flex;
            align-items: center;
            gap: .4rem;
        }

        .party-block .party-title .opt {
            font-weight: 500;
            color: #b7b9cc;
            text-transform: none;
            letter-spacing: 0;
            font-size: .7rem;
        }

        @media (max-width: 575.98px) {
            .party-block { padding: .8rem; }
        }

        .fm-picker {
            display: flex;
            gap: .5rem;
            flex-wrap: wrap;
            margin-bottom: .9rem;
        }

        .fm-pick {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .45rem .8rem;
            border: 2px solid #e3e6f0;
            border-radius: .4rem;
            background: #fff;
            cursor: pointer;
            font-size: .82rem;
            font-weight: 600;
            color: #3a3b45;
            margin: 0;
            user-select: none;
            transition: all .15s ease;
        }

        .fm-pick input { accent-color: #4e73df; margin: 0; }

        .fm-pick.checked {
            border-color: #4e73df;
            background: #f2f6ff;
            color: #224abe;
        }

        @media (max-width: 400px) {
            .fm-picker { flex-direction: column; }
            .fm-pick { width: 100%; }
        }

        .thc-row {
            display: none;
            background: #fff8e1;
            border: 1px solid #ffe082;
            border-radius: .5rem;
            padding: .75rem .9rem;
            margin-top: .5rem;
        }

        .thc-row.show { display: block; }

        .thc-row label {
            color: #856404;
            font-weight: 700;
            font-size: .82rem;
            margin-bottom: .3rem;
        }

        .thc-row .form-control { border-color: #ffe082; }

        .thc-row .hint {
            font-size: .72rem;
            color: #856404;
            margin-top: .3rem;
            display: block;
        }

        .items-table {
            width: 100%;
            margin: 0;
            border-collapse: collapse;
        }

        .items-table thead th {
            font-size: .7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #6e707e;
            background: #f8f9fc;
            padding: .55rem .5rem;
            border-bottom: 2px solid #e3e6f0;
            text-align: left;
            white-space: nowrap;
        }

        .items-table tbody td {
            padding: .5rem .5rem;
            border-bottom: 1px solid #f0f2f7;
            vertical-align: middle;
        }

        .items-table .form-control {
            font-size: .85rem;
            padding: .35rem .5rem;
            height: auto;
        }

        .items-total {
            background: #f8f9fc;
            padding: .75rem 1rem;
            font-weight: 800;
            font-size: .95rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 2px solid #e3e6f0;
        }

        .rate-col.hidden,
        .amount-col.hidden { display: none !important; }

        #freight_amount[readonly] {
            background-color: #f5f7fb;
            cursor: not-allowed;
            font-weight: 700;
            color: #2c2e3e;
        }

        @media (max-width: 767.98px) {
            .items-table thead { display: none; }

            .items-table,
            .items-table tbody,
            .items-table tr,
            .items-table td {
                display: block;
                width: 100%;
            }

            .items-table tr {
                border: 1px solid #e3e6f0;
                border-radius: .5rem;
                margin-bottom: .75rem;
                padding: .5rem;
                background: #fff;
            }

            .items-table tbody td {
                border: 0;
                padding: .35rem 0;
            }

            .items-table tbody td::before {
                content: attr(data-label);
                display: block;
                font-size: .68rem;
                font-weight: 800;
                text-transform: uppercase;
                color: #6e707e;
                margin-bottom: .15rem;
                letter-spacing: .04em;
            }

            .items-table tbody td[data-label=""]::before { display: none; }

            .items-table .js-remove-item { width: 100%; }
        }

        .action-bar {
            background: #fff;
            border: 1px solid #e3e6f0;
            border-radius: 10px;
            padding: .9rem 1.1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            flex-wrap: wrap;
        }

        .action-bar .ab-left {
            display: flex;
            align-items: center;
            gap: .5rem;
            flex-wrap: wrap;
        }

        .action-bar .ab-tripno-label {
            font-size: .68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #858796;
        }

        .action-bar .ab-tripno-value {
            font-family: "SFMono-Regular", Menlo, Consolas, monospace;
            font-size: .95rem;
            font-weight: 800;
            color: #2c2e3e;
            letter-spacing: .02em;
            word-break: break-all;
        }

        .action-bar .ab-tripno-edit {
            background: #f8f9fc;
            border: 1px solid #d1d3e2;
            color: #4e73df;
            border-radius: 6px;
            width: 28px;
            height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            font-size: .75rem;
            cursor: pointer;
            transition: all .15s ease;
            flex-shrink: 0;
        }

        .action-bar .ab-tripno-edit:hover {
            background: #4e73df;
            color: #fff;
            border-color: #4e73df;
        }

        .action-bar .hint {
            color: #858796;
            font-size: .8rem;
        }
        .action-bar .hint .req { color: #e74a3b; }

        .action-bar .btns {
            display: flex;
            gap: .5rem;
            flex-wrap: wrap;
        }

        @media (max-width: 575.98px) {
            .action-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .action-bar .ab-left { justify-content: center; }
            .action-bar .btns { width: 100%; }
            .action-bar .btns .btn { flex: 1 1 0; }
        }

        .branch-badge-soft {
            display: inline-block;
            padding: .15rem .55rem;
            border-radius: 4px;
            font-size: .68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .05em;
            background: #eef2ff;
            color: #3f51b5;
            border: 1px solid #dbe2ff;
            margin-left: .4rem;
        }

        .items-bottom-bar {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            padding: .75rem 1rem;
            background: #fff;
            border-top: 1px solid #f0f2f7;
            gap: .5rem;
            flex-wrap: wrap;
        }

        @media (max-width: 575.98px) {
            .items-bottom-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .items-bottom-bar #addItemBtn {
                width: 100%;
            }
        }

        #tripNoModal .modal-dialog { max-width: 440px; }
        #tripNoModal .modal-body { padding: 1rem 1.25rem; }

        #tripno_status {
            display: block;
            font-size: .78rem;
            margin-top: .35rem;
            min-height: 1rem;
        }

        #tripno_status.checking { color: #6e707e; }
        #tripno_status.ok       { color: #0c7d5b; }
        #tripno_status.error    { color: #e74a3b; font-weight: 700; }

        #trip_no_input.is-duplicate {
            border-color: #e74a3b;
            background-color: #fff5f5;
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

                    <div class="d-sm-flex align-items-center justify-content-between mb-3">
                        <h1 class="h3 mb-0 text-gray-800">
                            <i class="fas fa-route mr-2"></i>
                            <?= $isEdit ? 'Edit Trip' : 'Add Trip' ?>
                            <?php if ($userBranchName !== ''): ?>
                                <span class="branch-badge-soft"><?= htmlspecialchars($userBranchName, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </h1>

                        <a href="trips-list.php"
                            class="d-inline-block btn btn-sm btn-secondary shadow-sm">
                            <i class="fas fa-arrow-left fa-sm text-white-50 mr-1"></i>
                            Back to Trips
                        </a>
                    </div>

                    <div id="tripFlash"></div>

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

                    <form id="tripForm" action="" method="POST" novalidate>

                        <input type="hidden" name="trip_no" id="trip_no"
                            value="<?= htmlspecialchars($tripData['trip_no'], ENT_QUOTES, 'UTF-8') ?>"
                            data-edit-id="<?= (int) $editId ?>">

                        <!-- TRIP DETAILS -->
                        <div class="trip-card">
                            <div class="trip-card-header">
                                <h6>
                                    <i class="fas fa-truck-moving"></i>
                                    Trip Details
                                </h6>
                            </div>
                            <div class="trip-card-body">
                                <div class="row">

                                    <div class="col-12 col-md-6">
                                        <div class="form-group">
                                            <label for="source">
                                                Source <span class="req">*</span>
                                            </label>
                                            <input type="text" id="source" name="source"
                                                class="form-control"
                                                list="cityList"
                                                placeholder="Select or type source city"
                                                autocomplete="off"
                                                maxlength="100"
                                                value="<?= htmlspecialchars($tripData['source'], ENT_QUOTES, 'UTF-8') ?>"
                                                required>
                                        </div>
                                    </div>

                                    <div class="col-12 col-md-6">
                                        <div class="form-group">
                                            <label for="destination">
                                                Destination <span class="req">*</span>
                                            </label>
                                            <input type="text" id="destination" name="destination"
                                                class="form-control"
                                                list="cityList"
                                                placeholder="Select or type destination city"
                                                autocomplete="off"
                                                maxlength="100"
                                                value="<?= htmlspecialchars($tripData['destination'], ENT_QUOTES, 'UTF-8') ?>"
                                                required>
                                        </div>
                                    </div>

                                    <div class="col-12 col-md-6">
                                        <div class="form-group">
                                            <label for="lorry_input">
                                                Truck <span class="req">*</span>
                                            </label>
                                            <div class="input-with-btn">
                                                <input type="text" id="lorry_input"
                                                    class="form-control js-datalist-input"
                                                    list="lorryList" data-target="lorry_id"
                                                    placeholder="Select or type truck"
                                                    autocomplete="off"
                                                    value="<?= htmlspecialchars(array_reduce($lorries, function($carry, $item) use ($tripData) {
                                                        return $item['id'] == $tripData['lorry_id']
                                                            ? $item['lorry_number'] . (!empty($item['lorry_type']) ? ' — ' . $item['lorry_type'] : '')
                                                            : $carry;
                                                    }, ''), ENT_QUOTES, 'UTF-8') ?>"
                                                    required>
                                                <input type="hidden" name="lorry_id" id="lorry_id" value="<?= $tripData['lorry_id'] ?: '' ?>">
                                                <a href="#" class="btn js-open-master"
                                                    data-entity="lorry" data-slot="lorry"
                                                    data-title="Add New Truck" data-no-perm-check="1"
                                                    title="Add new truck">
                                                    <i class="fas fa-plus"></i>
                                                </a>
                                            </div>
                                            <datalist id="lorryList">
                                                <?php foreach ($lorries as $l):
                                                    $label = $l['lorry_number'];
                                                    if (!empty($l['lorry_type'])) $label .= ' — ' . $l['lorry_type'];
                                                ?>
                                                    <option data-id="<?= (int) $l['id'] ?>" value="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"></option>
                                                <?php endforeach; ?>
                                            </datalist>
                                        </div>

                                        <div class="thc-row js-thc-row <?= !empty($tripData['truck_hire_charge']) ? 'show' : '' ?>">
                                            <label for="truck_hire_charge">
                                                <i class="fas fa-handshake mr-1"></i>
                                                Truck Hire Charge <span class="req">*</span>
                                            </label>
                                            <input type="number" id="truck_hire_charge" name="truck_hire_charge"
                                                class="form-control" step="0.01" min="0" placeholder="0.00"
                                                value="<?= htmlspecialchars($tripData['truck_hire_charge'], ENT_QUOTES, 'UTF-8') ?>">
                                            <small class="hint">Auto-saved as an expense. Enter 0 if not charged.</small>
                                        </div>
                                    </div>

                                    <div class="col-12 col-md-6">
                                        <div class="form-group">
                                            <label for="driver_input">
                                                Driver <span class="req">*</span>
                                            </label>
                                            <div class="input-with-btn">
                                                <input type="text" id="driver_input"
                                                    class="form-control js-datalist-input"
                                                    list="driverList" data-target="driver_id"
                                                    placeholder="Select or type driver"
                                                    autocomplete="off"
                                                    value="<?= htmlspecialchars(array_reduce($drivers, function($carry, $item) use ($tripData) {
                                                        return $item['id'] == $tripData['driver_id']
                                                            ? $item['driver_name'] . (!empty($item['phone']) ? ' — ' . $item['phone'] : '')
                                                            : $carry;
                                                    }, ''), ENT_QUOTES, 'UTF-8') ?>"
                                                    required>
                                                <input type="hidden" name="driver_id" id="driver_id" value="<?= $tripData['driver_id'] ?: '' ?>">
                                                <a href="#" class="btn js-open-master"
                                                    data-entity="driver" data-slot="driver"
                                                    data-title="Add New Driver" data-no-perm-check="1"
                                                    title="Add new driver">
                                                    <i class="fas fa-plus"></i>
                                                </a>
                                            </div>
                                            <datalist id="driverList">
                                                <?php foreach ($drivers as $d):
                                                    $label = $d['driver_name'];
                                                    if (!empty($d['phone'])) $label .= ' — ' . $d['phone'];
                                                ?>
                                                    <option data-id="<?= (int) $d['id'] ?>" value="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"></option>
                                                <?php endforeach; ?>
                                            </datalist>
                                        </div>
                                    </div>

                                    <div class="col-12 col-md-6">
                                        <div class="form-group">
                                            <label for="start_date">
                                                Start Date <span class="req">*</span>
                                            </label>
                                            <input type="date" id="start_date" name="start_date"
                                                class="form-control"
                                                value="<?= htmlspecialchars($tripData['start_date'], ENT_QUOTES, 'UTF-8') ?>"
                                                required>
                                        </div>
                                    </div>

                                    <div class="col-12 col-md-6">
                                        <div class="form-group mb-0">
                                            <label for="trip_notes">Trip Notes</label>
                                            <input type="text" id="trip_notes" name="trip_notes"
                                                class="form-control" maxlength="255"
                                                value="<?= htmlspecialchars($tripData['notes'], ENT_QUOTES, 'UTF-8') ?>"
                                                placeholder="Optional">
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>

                        <!-- PARTY CONSIGNMENT -->
                        <div class="trip-card">
                            <div class="trip-card-header">
                                <h6>
                                    <i class="fas fa-users"></i>
                                    Party Consignment
                                </h6>
                            </div>
                            <div class="trip-card-body">

                                <div class="party-block">
                                    <div class="party-title">
                                        <i class="fas fa-user-tag"></i>
                                        Consignor (who pays)
                                        <span class="req" style="color:#e74a3b">*</span>
                                    </div>

                                    <div class="fm-picker js-freight-mode-picker">
                                        <label class="fm-pick js-fm-fixed <?= $tripData['freight_mode'] === 0 ? 'checked' : '' ?>">
                                            <input type="radio" name="freight_mode" value="0"
                                                class="js-freight-mode"
                                                <?= $tripData['freight_mode'] === 0 ? 'checked' : '' ?>>
                                            Fixed Freight
                                        </label>
                                        <label class="fm-pick js-fm-itemwise <?= $tripData['freight_mode'] === 1 ? 'checked' : '' ?>">
                                            <input type="radio" name="freight_mode" value="1"
                                                class="js-freight-mode"
                                                <?= $tripData['freight_mode'] === 1 ? 'checked' : '' ?>>
                                            Item-wise Freight
                                        </label>
                                    </div>

                                    <div class="row">
                                        <div class="col-12 col-md-6">
                                            <div class="form-group">
                                                <label for="party_input">Party</label>
                                                <div class="input-with-btn">
                                                    <input type="text" id="party_input"
                                                        class="form-control js-datalist-input"
                                                        list="partyList" data-target="party_id"
                                                        placeholder="Select or type party"
                                                        autocomplete="off"
                                                        value="<?= htmlspecialchars(array_reduce($parties, function($carry, $item) use ($tripData) {
                                                            return $item['id'] == $tripData['party_id']
                                                                ? $item['legal_name'] . (!empty($item['trade_name']) ? ' (' . $item['trade_name'] . ')' : '')
                                                                : $carry;
                                                        }, ''), ENT_QUOTES, 'UTF-8') ?>"
                                                        required>
                                                    <input type="hidden" name="party_id" id="party_id" value="<?= $tripData['party_id'] ?: '' ?>">
                                                    <a href="#" class="btn js-open-master"
                                                        data-entity="party" data-slot="consignor"
                                                        data-title="Add New Party (Consignor)"
                                                        data-no-perm-check="1"
                                                        title="Add new party">
                                                        <i class="fas fa-plus"></i>
                                                    </a>
                                                </div>
                                                <datalist id="partyList">
                                                    <?php foreach ($parties as $p):
                                                        $label = $p['legal_name'];
                                                        if (!empty($p['trade_name'])) $label .= ' (' . $p['trade_name'] . ')';
                                                    ?>
                                                        <option data-id="<?= (int) $p['id'] ?>" value="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"></option>
                                                    <?php endforeach; ?>
                                                </datalist>
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <div class="form-group">
                                                <label for="freight_amount">
                                                    Freight Amount <span class="req">*</span>
                                                </label>
                                                <input type="number" step="0.01" min="0"
                                                    id="freight_amount" name="freight_amount"
                                                    class="form-control"
                                                    value="<?= htmlspecialchars($tripData['freight_amount'], ENT_QUOTES, 'UTF-8') ?>"
                                                    placeholder="0.00" required>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="form-group mb-0">
                                                <label for="party_notes">Notes</label>
                                                <input type="text" id="party_notes" name="party_notes"
                                                    class="form-control" maxlength="255"
                                                    value="<?= htmlspecialchars($tripData['party_notes'], ENT_QUOTES, 'UTF-8') ?>"
                                                    placeholder="Optional short note">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="party-block">
                                    <div class="party-title">
                                        <i class="fas fa-user-check"></i>
                                        Consignee (who receives)
                                        <span class="opt">— optional</span>
                                    </div>

                                    <div class="form-group mb-0">
                                        <label for="consignee_party_input">Party</label>
                                        <div class="input-with-btn">
                                            <input type="text" id="consignee_party_input"
                                                class="form-control js-datalist-input"
                                                list="consigneeList" data-target="consignee_party_id"
                                                placeholder="Leave blank if same as consignor"
                                                autocomplete="off"
                                                value="<?= htmlspecialchars(array_reduce($consignees, function($carry, $item) use ($tripData) {
                                                    return $item['id'] == $tripData['consignee_party_id']
                                                        ? $item['legal_name']
                                                            . (!empty($item['trade_name']) ? ' (' . $item['trade_name'] . ')' : '')
                                                        : $carry;
                                                }, ''), ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="consignee_party_id" id="consignee_party_id" value="<?= $tripData['consignee_party_id'] ?: '' ?>">
                                            <a href="#" class="btn js-open-master"
                                                data-entity="party" data-slot="consignee"
                                                data-title="Add New Party (Consignee)"
                                                data-no-perm-check="1"
                                                title="Add new party">
                                                <i class="fas fa-plus"></i>
                                            </a>
                                        </div>
                                        <datalist id="consigneeList">
                                            <?php foreach ($consignees as $p):
                                                $label = $p['legal_name'];
                                                if (!empty($p['trade_name'])) $label .= ' (' . $p['trade_name'] . ')';
                                            ?>
                                                <option data-id="<?= (int) $p['id'] ?>" value="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"></option>
                                            <?php endforeach; ?>
                                        </datalist>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <!-- INVENTORY -->
                        <div class="trip-card">
                            <div class="trip-card-header">
                                <h6>
                                    <i class="fas fa-box"></i>
                                    Inventory on this Consignment
                                </h6>
                            </div>

                            <div class="table-responsive">
                                <table class="items-table" id="itemsTable">
                                    <thead>
                                        <tr>
                                            <th style="min-width:220px;">Item</th>
                                            <th style="min-width:80px;">Unit</th>
                                            <th style="min-width:80px;" class="text-right">Qty</th>
                                            <th style="min-width:100px;" class="text-right rate-col">Rate</th>
                                            <th style="min-width:110px;" class="text-right amount-col">Amount</th>
                                            <th style="min-width:50px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="itemsWrap"></tbody>
                                </table>
                            </div>

                            <div class="items-total">
                                <span><i class="fas fa-calculator mr-1"></i> Grand Total</span>
                                <span>₹ <span id="itemsGrandTotal">0</span></span>
                            </div>

                            <div class="items-bottom-bar">
                                <button type="button" id="addItemBtn" class="btn btn-sm btn-primary">
                                    <i class="fas fa-plus mr-1"></i> Add Row
                                </button>
                            </div>
                        </div>

                        <!-- ACTION BAR -->
                        <div class="action-bar">
                            <div class="ab-left">
                                <span class="ab-tripno-label"><i class="fas fa-hashtag mr-1"></i>Trip No</span>
                                <span class="ab-tripno-value" id="tripNoText"><?= htmlspecialchars($tripData['trip_no'], ENT_QUOTES, 'UTF-8') ?></span>
                                <button type="button" class="ab-tripno-edit" id="tripNoEditBtn"
                                    title="Edit Trip No">
                                    <i class="fas fa-pen"></i>
                                </button>
                            </div>

                            <div class="hint d-none d-sm-block">
                                Fields marked <span class="req">*</span> are required.
                            </div>

                            <div class="btns">
                                <a href="trip.php<?= $isEdit ? '?id=' . $editId : '' ?>" class="btn btn-secondary">
                                    <i class="fas fa-redo mr-1"></i> Reset
                                </a>
                                <button type="submit" id="saveTripBtn" class="btn btn-success">
                                    <i class="fas fa-check mr-1"></i>
                                    <?= $isEdit ? 'Update Trip' : 'Save Trip' ?>
                                </button>
                            </div>
                        </div>

                    </form>

                    <datalist id="cityList">
                        <?php foreach ($cities as $city): ?>
                            <option value="<?= htmlspecialchars($city, ENT_QUOTES, 'UTF-8') ?>"></option>
                        <?php endforeach; ?>
                    </datalist>

                </div>
            </div>
            <?php include 'layout/footer.php'; ?>
        </div>
    </div>

    <!-- TRIP NO EDIT MODAL -->
    <div class="modal fade" id="tripNoModal" tabindex="-1" role="dialog"
        aria-labelledby="tripNoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tripNoModalLabel">
                        <i class="fas fa-hashtag mr-1"></i>
                        Edit Trip No
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-0">
                        <label for="trip_no_input" class="font-weight-bold">
                            Trip No <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                            id="trip_no_input"
                            class="form-control text-uppercase"
                            maxlength="30"
                            autocomplete="off"
                            placeholder="e.g. BOL-2609-0001">
                        <small id="tripno_status" class="tripno-status">
                            <i class="fas fa-info-circle mr-1"></i>
                            Auto-generated. You may edit if needed.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-success" id="tripNoModalSave">
                        <i class="fas fa-check mr-1"></i> Save Trip No
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MASTER MODAL -->
    <div class="modal fade" id="masterModal" tabindex="-1" role="dialog"
        aria-labelledby="masterModalTitle" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="masterModalTitle">Add New</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <iframe id="masterModalIframe" src="about:blank" title="Master form"></iframe>
                </div>
            </div>
        </div>
    </div>

    <script>
        // MASTER arrays no longer hold stock data
        var MASTER_INVENTORY = <?= json_encode(array_map(function ($r) {
                                    return [
                                        'id'         => (int) $r['id'],
                                        'name'       => $r['item_name'],
                                        'unit'       => $r['unit'] ?? ''
                                    ];
                                }, $inventoryList)); ?>;

        var MASTER_TRUCKS = <?= json_encode(array_map(function ($r) {
                                    return [
                                        'id'                  => (int) $r['id'],
                                        'number'              => $r['lorry_number'],
                                        'type'                => $r['lorry_type'] ?? '',
                                        'ownership'           => (int) $r['ownership_type'],
                                        'supplier_id'         => (int) ($r['supplier_id'] ?? 0),
                                        'preferred_driver_id' => (int) ($r['preferred_driver_id'] ?? 0),
                                    ];
                                }, $lorries)); ?>;

        var MASTER_DRIVERS = <?= json_encode(array_map(function ($r) {
                                    return [
                                        'id'                 => (int) $r['id'],
                                        'name'               => $r['driver_name'],
                                        'phone'              => $r['phone'] ?? '',
                                        'preferred_lorry_id' => (int) ($r['preferred_lorry_id'] ?? 0),
                                    ];
                                }, $drivers)); ?>;

        var TRIP_INITIAL_ITEMS = <?= json_encode($initialItems); ?>;
        var TRIP_FREIGHT_MODE  = <?= (int) $tripData['freight_mode'] ?>;
    </script>

    <script src="js/trip.js"></script>

</body>

</html>