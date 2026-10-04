<?php
/* =========================================================
   ajax/check-permissions.php
   ---------------------------------------------------------
   Compares the session's stored permissions with the DB's
   current state. If they differ, refreshes the session in
   place, and returns "changed: true" so the client can
   reload the page.

   Called every 30 seconds from layout/footer.php.
   ========================================================= */

include __DIR__ . '/../constant.php';
include __DIR__ . '/../session.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

$userId = (int) $_SESSION['user_id'];

/* 1. Fresh user row */
$res = mysqli_query($conn,
    "SELECT id, username, full_name, role_id, branch_id, active
     FROM `user` WHERE id = $userId LIMIT 1");

if (!$res || mysqli_num_rows($res) !== 1) {
    echo json_encode(['success' => false, 'force_logout' => true]);
    exit;
}

$userRow = mysqli_fetch_assoc($res);

if ((int) $userRow['active'] !== 1) {
    echo json_encode(['success' => false, 'force_logout' => true]);
    exit;
}

$newRoleId   = (int) $userRow['role_id'];
$newBranchId = (int) $userRow['branch_id'];

/* 2. Load current permissions for the role */
$newPermissions = [];
if ($newRoleId > 0) {
    $permRes = mysqli_query($conn,
        "SELECT p.perm_key
         FROM role_permission rp
         JOIN permission p ON p.id = rp.permission_id
         WHERE rp.role_id = $newRoleId
         ORDER BY p.perm_key");
    if ($permRes) {
        while ($p = mysqli_fetch_assoc($permRes)) {
            $newPermissions[] = $p['perm_key'];
        }
    }
}

/* 3. Build new signature (same format as header.php) */
$newPayload = json_encode([
    'role_id'   => $newRoleId,
    'branch_id' => $newBranchId,
    'perms'     => $newPermissions,
]);
$newSig = md5($newPayload);

/* 4. Build old signature from session */
$oldPermissions = $_SESSION['permissions'] ?? [];
sort($oldPermissions);
$oldPayload = json_encode([
    'role_id'   => (int) ($_SESSION['role_id']   ?? 0),
    'branch_id' => (int) ($_SESSION['branch_id'] ?? 0),
    'perms'     => $oldPermissions,
]);
$oldSig = md5($oldPayload);

/* 5. If changed, refresh the session in place */
$changed = ($newSig !== $oldSig);

if ($changed) {
    $_SESSION['role_id']     = $newRoleId;
    $_SESSION['branch_id']   = $newBranchId;
    $_SESSION['permissions'] = $newPermissions;
    $_SESSION['username']    = $userRow['username'];
    $_SESSION['full_name']   = $userRow['full_name'] ?: $userRow['username'];
}

echo json_encode([
    'success' => true,
    'changed' => $changed,
]);