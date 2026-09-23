<?php
/* =========================================================
   save-master.php
   ---------------------------------------------------------
   AJAX endpoint used by trip.php to quick-add master records
   (party, lorry, driver) without leaving the trip form.

   !! IMPORTANT !!
   The validation rules in this file duplicate the ones in
   party.php, lorry.php, and driver.php. If you change a rule
   in those files, change it here too, and vice versa.
   ========================================================= */

include '../constant.php';
include '../session.php';

header('Content-Type: application/json');

/* ---------------------------------------------------------
   Auth check
   --------------------------------------------------------- */
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

/* ---------------------------------------------------------
   Only accept POST
   --------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required.']);
    exit;
}

/* ---------------------------------------------------------
   Read and validate the entity
   --------------------------------------------------------- */
$entity = $_POST['entity'] ?? '';

$allowed = ['party', 'lorry', 'driver'];
if (!in_array($entity, $allowed, true)) {
    echo json_encode(['success' => false, 'message' => 'Unknown entity.']);
    exit;
}

/* ---------------------------------------------------------
   Permission gate — must have create permission on that entity
   --------------------------------------------------------- */
$permKey = $entity . '.create';
if (!hasPermission($permKey)) {
    echo json_encode(['success' => false, 'message' => 'Permission denied.']);
    exit;
}

/* ---------------------------------------------------------
   Helper: escape
   --------------------------------------------------------- */
$esc = function ($v) use ($conn) {
    return mysqli_real_escape_string($conn, $v);
};

/* ---------------------------------------------------------
   Helper: read POST safely
   --------------------------------------------------------- */
$post = function ($key, $default = '') {
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
};
$postInt = function ($key) {
    return isset($_POST[$key]) ? (int) $_POST[$key] : 0;
};

$errorList = [];

/* =========================================================
   PARTY
   ========================================================= */
if ($entity === 'party') {

    $branch_id  = $postInt('branch_id');
    $gstin      = strtoupper($post('gstin'));
    $legal_name = $post('legal_name');
    $trade_name = $post('trade_name');
    $phone      = $post('phone');
    $email      = $post('email');
    $address    = $post('address');
    $city       = $post('city');
    $state      = $post('state');
    $pincode    = $post('pincode');
    $status     = $post('status');

    if ($branch_id <= 0) {
        $errorList[] = 'Please select a branch.';
    }
    if ($legal_name === '') {
        $errorList[] = 'Legal name is required.';
    }
    if (
        $gstin !== '' &&
        !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $gstin)
    ) {
        $errorList[] = 'Invalid GSTIN format.';
    }
    if ($phone !== '' && !preg_match('/^[0-9+\-\s]{6,15}$/', $phone)) {
        $errorList[] = 'Invalid phone number.';
    }
    if ($email !== '') {
        if (
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            !preg_match('/^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i', $email)
        ) {
            $errorList[] = 'Invalid email address.';
        }
    }
    if ($pincode !== '' && !preg_match('/^[0-9]{6}$/', $pincode)) {
        $errorList[] = 'Pincode must be exactly 6 digits.';
    }

    /* Duplicate GSTIN */
    if (empty($errorList) && $gstin !== '') {
        $gstin_safe = $esc($gstin);
        $dup = mysqli_query(
            $conn,
            "SELECT id FROM party WHERE UPPER(gstin) = UPPER('$gstin_safe') LIMIT 1"
        );
        if ($dup && mysqli_num_rows($dup) > 0) {
            $errorList[] = 'A party with this GSTIN already exists.';
        }
    }

    if (!empty($errorList)) {
        echo json_encode(['success' => false, 'message' => implode(' ', $errorList)]);
        exit;
    }

    /* Insert */
    $gstin_v      = $gstin      !== '' ? "'" . $esc($gstin)      . "'" : "NULL";
    $trade_name_v = $trade_name !== '' ? "'" . $esc($trade_name) . "'" : "NULL";
    $phone_v      = $phone      !== '' ? "'" . $esc($phone)      . "'" : "NULL";
    $email_v      = $email      !== '' ? "'" . $esc($email)      . "'" : "NULL";
    $address_v    = $address    !== '' ? "'" . $esc($address)    . "'" : "NULL";
    $city_v       = $city       !== '' ? "'" . $esc($city)       . "'" : "NULL";
    $state_v      = $state      !== '' ? "'" . $esc($state)      . "'" : "NULL";
    $pincode_v    = $pincode    !== '' ? "'" . $esc($pincode)    . "'" : "NULL";
    $status_v     = $status     !== '' ? "'" . $esc($status)     . "'" : "NULL";
    $legal_name_v = "'" . $esc($legal_name) . "'";

    $sql = "INSERT INTO party
                (branch_id, gstin, legal_name, trade_name, address, city, state,
                 pincode, phone, email, status, active)
            VALUES
                ($branch_id, $gstin_v, $legal_name_v, $trade_name_v, $address_v,
                 $city_v, $state_v, $pincode_v, $phone_v, $email_v,
                 $status_v, 1)";

    if (!mysqli_query($conn, $sql)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save party: ' . mysqli_error($conn)]);
        exit;
    }

    $newId = mysqli_insert_id($conn);

    /* Build a display label */
    $label = $legal_name;
    if ($trade_name !== '') {
        $label .= ' (' . $trade_name . ')';
    }

    echo json_encode([
        'success' => true,
        'entity'  => 'party',
        'id'      => $newId,
        'label'   => $label
    ]);
    exit;
}

/* =========================================================
   LORRY
   ========================================================= */
if ($entity === 'lorry') {

    $branch_id    = $postInt('branch_id');
    $lorry_number = strtoupper($post('lorry_number'));
    $lorry_type   = $post('lorry_type');
    $capacity     = $post('capacity');
    $owner_name   = $post('owner_name');
    $address      = $post('address');
    $city         = $post('city');
    $state        = $post('state');
    $pincode      = $post('pincode');

    if ($branch_id <= 0) {
        $errorList[] = 'Please select a branch.';
    }
    if ($lorry_number === '') {
        $errorList[] = 'Lorry number is required.';
    } elseif (!preg_match('/^[A-Z0-9\-\s]{4,15}$/', $lorry_number)) {
        $errorList[] = 'Invalid lorry number.';
    }
    if ($pincode !== '' && !preg_match('/^[0-9]{6}$/', $pincode)) {
        $errorList[] = 'Pincode must be exactly 6 digits.';
    }

    if (empty($errorList) && $lorry_number !== '') {
        $ln_safe = $esc($lorry_number);
        $dup = mysqli_query(
            $conn,
            "SELECT id FROM lorry WHERE UPPER(lorry_number) = UPPER('$ln_safe') LIMIT 1"
        );
        if ($dup && mysqli_num_rows($dup) > 0) {
            $errorList[] = 'A lorry with this number already exists.';
        }
    }

    if (!empty($errorList)) {
        echo json_encode(['success' => false, 'message' => implode(' ', $errorList)]);
        exit;
    }

    $lorry_number_v = "'" . $esc($lorry_number) . "'";
    $lorry_type_v   = $lorry_type !== '' ? "'" . $esc($lorry_type) . "'" : "NULL";
    $capacity_v     = $capacity   !== '' ? "'" . $esc($capacity)   . "'" : "NULL";
    $owner_name_v   = $owner_name !== '' ? "'" . $esc($owner_name) . "'" : "NULL";
    $address_v      = $address    !== '' ? "'" . $esc($address)    . "'" : "NULL";
    $city_v         = $city       !== '' ? "'" . $esc($city)       . "'" : "NULL";
    $state_v        = $state      !== '' ? "'" . $esc($state)      . "'" : "NULL";
    $pincode_v      = $pincode    !== '' ? "'" . $esc($pincode)    . "'" : "NULL";

    $sql = "INSERT INTO lorry
                (branch_id, lorry_number, lorry_type, capacity, owner_name,
                 address, city, state, pincode, active)
            VALUES
                ($branch_id, $lorry_number_v, $lorry_type_v, $capacity_v, $owner_name_v,
                 $address_v, $city_v, $state_v, $pincode_v, 1)";

    if (!mysqli_query($conn, $sql)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save lorry: ' . mysqli_error($conn)]);
        exit;
    }

    $newId = mysqli_insert_id($conn);

    $label = $lorry_number;
    if ($lorry_type !== '') {
        $label .= ' (' . $lorry_type . ')';
    }

    echo json_encode([
        'success' => true,
        'entity'  => 'lorry',
        'id'      => $newId,
        'label'   => $label
    ]);
    exit;
}

/* =========================================================
   DRIVER
   ========================================================= */
if ($entity === 'driver') {

    $branch_id      = $postInt('branch_id');
    $driver_name    = $post('driver_name');
    $phone          = $post('phone');
    $license_no     = strtoupper($post('license_no'));
    $license_expiry = $post('license_expiry');
    $address        = $post('address');
    $city           = $post('city');
    $state          = $post('state');
    $pincode        = $post('pincode');

    if ($driver_name === '') {
        $errorList[] = 'Driver name is required.';
    } elseif (strlen($driver_name) > 100) {
        $errorList[] = 'Driver name is too long.';
    }
    if ($phone !== '' && !preg_match('/^[0-9+\-\s]{6,15}$/', $phone)) {
        $errorList[] = 'Invalid phone number.';
    }
    if ($license_no !== '' && !preg_match('/^[A-Z0-9\-\/\s]{5,30}$/', $license_no)) {
        $errorList[] = 'Invalid license number.';
    }
    if ($license_expiry !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $license_expiry);
        if (!$d || $d->format('Y-m-d') !== $license_expiry) {
            $errorList[] = 'Invalid license expiry date.';
        }
    }
    if ($pincode !== '' && !preg_match('/^[0-9]{6}$/', $pincode)) {
        $errorList[] = 'Pincode must be exactly 6 digits.';
    }
    if ($branch_id <= 0) {
        $errorList[] = 'Please select a branch.';
    }

    if (empty($errorList) && $license_no !== '') {
        $lic_safe = $esc($license_no);
        $dup = mysqli_query(
            $conn,
            "SELECT id FROM driver WHERE UPPER(license_no) = UPPER('$lic_safe') LIMIT 1"
        );
        if ($dup && mysqli_num_rows($dup) > 0) {
            $errorList[] = 'Another driver already has this license number.';
        }
    }

    if (!empty($errorList)) {
        echo json_encode(['success' => false, 'message' => implode(' ', $errorList)]);
        exit;
    }

    $name_v    = "'" . $esc($driver_name) . "'";
    $phone_v   = $phone          !== '' ? "'" . $esc($phone)          . "'" : "NULL";
    $license_v = $license_no     !== '' ? "'" . $esc($license_no)     . "'" : "NULL";
    $lic_exp_v = $license_expiry !== '' ? "'" . $esc($license_expiry) . "'" : "NULL";
    $address_v = $address        !== '' ? "'" . $esc($address)        . "'" : "NULL";
    $city_v    = $city           !== '' ? "'" . $esc($city)           . "'" : "NULL";
    $state_v   = $state          !== '' ? "'" . $esc($state)          . "'" : "NULL";
    $pincode_v = $pincode        !== '' ? "'" . $esc($pincode)        . "'" : "NULL";

    $sql = "INSERT INTO driver
                (driver_name, phone, license_no, license_expiry,
                 address, city, state, pincode, branch_id, active)
            VALUES
                ($name_v, $phone_v, $license_v, $lic_exp_v,
                 $address_v, $city_v, $state_v, $pincode_v, $branch_id, 1)";

    if (!mysqli_query($conn, $sql)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save driver: ' . mysqli_error($conn)]);
        exit;
    }

    $newId = mysqli_insert_id($conn);

    $label = $driver_name;
    if ($phone !== '') {
        $label .= ' — ' . $phone;
    }

    echo json_encode([
        'success' => true,
        'entity'  => 'driver',
        'id'      => $newId,
        'label'   => $label
    ]);
    exit;
}
