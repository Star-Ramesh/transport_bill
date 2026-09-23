<?php
/* =========================================================
   item_search.php — AJAX autocomplete endpoint for items
   Used by js/trip.js to suggest item names as the user types.
   Returns JSON only. Never returns HTML.
   ========================================================= */

include '../constant.php';
include '../session.php';

header('Content-Type: application/json');

/* ---------------------------------------------------------
   1. Auth check — session.php already redirects if not logged in.
      But for AJAX we want a JSON response, not a redirect.
   --------------------------------------------------------- */
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Not authenticated.',
        'items'   => []
    ]);
    exit;
}

/* ---------------------------------------------------------
   2. Permission check — must have item.view to search items
   --------------------------------------------------------- */
if (!hasPermission('item.view')) {
    echo json_encode([
        'success' => false,
        'message' => 'Permission denied.',
        'items'   => []
    ]);
    exit;
}

/* ---------------------------------------------------------
   3. Read and clean the search term
   --------------------------------------------------------- */
$q = trim($_GET['q'] ?? '');

// Minimum 1 character to search (prevents pulling the whole table)
if ($q === '') {
    echo json_encode([
        'success' => true,
        'items'   => []
    ]);
    exit;
}

// Cap length to prevent abuse
if (strlen($q) > 50) {
    $q = substr($q, 0, 50);
}

$q_safe = mysqli_real_escape_string($conn, $q);

/* ---------------------------------------------------------
   4. Query — match item_name containing the search term
      Only active items. Sorted so that names starting with
      the search term come first, then the rest.
   --------------------------------------------------------- */
$sql = "SELECT id, item_name, unit
        FROM item
        WHERE active = 1
          AND item_name LIKE '%$q_safe%'
        ORDER BY
            CASE WHEN item_name LIKE '$q_safe%' THEN 0 ELSE 1 END,
            item_name ASC
        LIMIT 10";

$result = mysqli_query($conn, $sql);

if (!$result) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error.',
        'items'   => []
    ]);
    exit;
}

/* ---------------------------------------------------------
   5. Build response
   --------------------------------------------------------- */
$items = [];
while ($row = mysqli_fetch_assoc($result)) {
    $items[] = [
        'id'    => (int) $row['id'],
        'name'  => $row['item_name'],
        'unit'  => $row['unit'] ?? ''
    ];
}

echo json_encode([
    'success' => true,
    'items'   => $items
]);
