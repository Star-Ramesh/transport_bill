<?php
/* =========================================================
   master-form.php
   ---------------------------------------------------------
   Returns just the form HTML (no chrome) for a given entity.
   Used by the AJAX modal on trip.php.

   Usage:
     master-form.php?entity=party
     master-form.php?entity=lorry
     master-form.php?entity=driver
   ========================================================= */

include '../constant.php';
include '../session.php';

$entity = $_GET['entity'] ?? '';

$allowed = ['party', 'lorry', 'driver'];
if (!in_array($entity, $allowed, true)) {
    echo '<div class="p-3 text-danger">Unknown form.</div>';
    exit;
}

/* Create permission required */
if (!hasPermission($entity . '.create')) {
    echo '<div class="p-3 text-danger">Permission denied.</div>';
    exit;
}

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

$defaultBranch = isAdmin() ? 0 : (int) ($_SESSION['branch_id'] ?? 0);
?>
<form id="masterForm" class="p-3" novalidate>
    <input type="hidden" name="entity" value="<?= htmlspecialchars($entity, ENT_QUOTES, 'UTF-8') ?>">

    <div id="masterFormError" class="d-none"></div>

    <?php if ($entity === 'party'): ?>

        <div class="form-group">
            <label>Branch <span class="text-danger">*</span></label>
            <select name="branch_id" class="form-control" required>
                <option value="">— Select Branch —</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= (int) $b['id'] ?>"
                        <?= $defaultBranch === (int) $b['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b['branch_name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <div class="form-group col-md-6">
                <label>Legal Name <span class="text-danger">*</span></label>
                <input type="text" name="legal_name" class="form-control" maxlength="255"
                    placeholder="Enter legal name" required>
            </div>
            <div class="form-group col-md-6">
                <label>Trade Name</label>
                <input type="text" name="trade_name" class="form-control" maxlength="255"
                    placeholder="Enter trade name">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-md-6">
                <label>GSTIN</label>
                <input type="text" name="gstin" class="form-control text-uppercase"
                    maxlength="15" placeholder="Enter GSTIN">
            </div>
            <div class="form-group col-md-6">
                <label>Phone</label>
                <input type="text" name="phone" class="form-control" maxlength="15"
                    placeholder="Enter phone number">
            </div>
        </div>

        <div class="form-group">
            <label>Email</label>
            <input type="text" name="email" class="form-control" maxlength="150"
                placeholder="Enter email">
        </div>

        <div class="form-group">
            <label>Address</label>
            <textarea name="address" class="form-control" rows="2"
                maxlength="500" placeholder="Enter address"></textarea>
        </div>

        <div class="form-row">
            <div class="form-group col-md-4">
                <label>City</label>
                <input type="text" name="city" class="form-control" maxlength="100">
            </div>
            <div class="form-group col-md-4">
                <label>State</label>
                <input type="text" name="state" class="form-control" maxlength="100">
            </div>
            <div class="form-group col-md-4">
                <label>Pincode</label>
                <input type="text" name="pincode" class="form-control" maxlength="6">
            </div>
        </div>

    <?php elseif ($entity === 'lorry'): ?>

        <div class="form-row">
            <div class="form-group col-md-6">
                <label>Branch <span class="text-danger">*</span></label>
                <select name="branch_id" class="form-control" required>
                    <option value="">— Select Branch —</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= (int) $b['id'] ?>"
                            <?= $defaultBranch === (int) $b['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['branch_name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-6">
                <label>Lorry Number <span class="text-danger">*</span></label>
                <input type="text" name="lorry_number" class="form-control text-uppercase"
                    maxlength="15" placeholder="Enter lorry number" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-md-6">
                <label>Lorry Type</label>
                <select name="lorry_type" class="form-control">
                    <option value="">— Select —</option>
                    <?php
                    $types = ['Open', 'Container', 'Trailer', 'Tanker', 'Flatbed', 'Refrigerated', 'Other'];
                    foreach ($types as $t):
                    ?>
                        <option value="<?= $t ?>"><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-6">
                <label>Capacity</label>
                <input type="text" name="capacity" class="form-control" maxlength="15">
            </div>
        </div>

        <div class="form-group">
            <label>Owner Name</label>
            <input type="text" name="owner_name" class="form-control" maxlength="100">
        </div>

        <div class="form-group">
            <label>Address</label>
            <input type="text" name="address" class="form-control" maxlength="255">
        </div>

        <div class="form-row">
            <div class="form-group col-md-4">
                <label>City</label>
                <input type="text" name="city" class="form-control" maxlength="50">
            </div>
            <div class="form-group col-md-4">
                <label>State</label>
                <input type="text" name="state" class="form-control" maxlength="50">
            </div>
            <div class="form-group col-md-4">
                <label>Pincode</label>
                <input type="text" name="pincode" class="form-control" maxlength="6">
            </div>
        </div>

    <?php elseif ($entity === 'driver'): ?>

        <div class="form-row">
            <div class="form-group col-md-6">
                <label>Driver Name <span class="text-danger">*</span></label>
                <input type="text" name="driver_name" class="form-control"
                    maxlength="100" placeholder="Enter driver name" required>
            </div>
            <div class="form-group col-md-6">
                <label>Phone</label>
                <input type="text" name="phone" class="form-control" maxlength="15">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-md-6">
                <label>License Number</label>
                <input type="text" name="license_no" class="form-control text-uppercase"
                    maxlength="30">
            </div>
            <div class="form-group col-md-6">
                <label>License Expiry</label>
                <input type="date" name="license_expiry" class="form-control">
            </div>
        </div>

        <div class="form-group">
            <label>Branch <span class="text-danger">*</span></label>
            <select name="branch_id" class="form-control" required>
                <option value="">— Select Branch —</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= (int) $b['id'] ?>"
                        <?= $defaultBranch === (int) $b['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b['branch_name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Address</label>
            <input type="text" name="address" class="form-control" maxlength="255">
        </div>

        <div class="form-row">
            <div class="form-group col-md-4">
                <label>City</label>
                <input type="text" name="city" class="form-control" maxlength="50">
            </div>
            <div class="form-group col-md-4">
                <label>State</label>
                <input type="text" name="state" class="form-control" maxlength="50">
            </div>
            <div class="form-group col-md-4">
                <label>Pincode</label>
                <input type="text" name="pincode" class="form-control" maxlength="6">
            </div>
        </div>

    <?php endif; ?>

    <div class="d-flex justify-content-end pt-2 border-top mt-2">
        <button type="button" class="btn btn-secondary mr-2" data-dismiss="modal">
            <i class="fas fa-times mr-1"></i> Cancel
        </button>
        <button type="submit" class="btn btn-success">
            <i class="fas fa-check mr-1"></i> Save
        </button>
    </div>
</form>