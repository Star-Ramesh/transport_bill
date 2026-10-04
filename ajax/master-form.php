<?php
/* =========================================================
   ajax/master-form.php
   ---------------------------------------------------------
   Returns just the form HTML for the master modal on trip.php.
   The party form mirrors party.php (with GST fetch).

   Usage:
     ajax/master-form.php?entity=party
     ajax/master-form.php?entity=lorry
     ajax/master-form.php?entity=driver
   ========================================================= */

include __DIR__ . '/../constant.php';
include __DIR__ . '/../session.php';

$entity = $_GET['entity'] ?? '';

$allowed = ['party', 'lorry', 'driver'];
if (!in_array($entity, $allowed, true)) {
    echo '<div class="p-3 text-danger">Unknown form.</div>';
    exit;
}

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

        <!-- =========================================================
         GST VERIFICATION (mirrors party.php)
         ========================================================= -->
        <div class="card shadow-sm mb-3">
            <div class="card-header py-2 d-flex align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-search mr-1"></i>
                    GST Verification
                </h6>
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="modal_no_gst">
                    <label class="custom-control-label" for="modal_no_gst">
                        Party has no GSTIN
                    </label>
                </div>
            </div>
            <div class="card-body py-3">

                <div class="form-group mb-0">
                    <label for="modal_gstin">GST Number</label>
                    <div class="input-group">
                        <input
                            type="text"
                            id="modal_gstin"
                            name="gstin"
                            form="masterForm"
                            class="form-control text-uppercase"
                            maxlength="15"
                            autocomplete="off"
                            placeholder="Enter GSTIN">

                        <div class="input-group-append">
                            <button type="button" id="modal_fetchGst" class="btn btn-primary">
                                <span id="modal_fetchText">
                                    <i class="fas fa-search mr-1"></i>
                                    Fetch Details
                                </span>
                                <span id="modal_fetchLoading" class="d-none">
                                    <span class="spinner-border spinner-border-sm mr-1"></span>
                                    Fetching...
                                </span>
                            </button>

                            <button type="button" id="modal_changeGst"
                                class="btn btn-outline-secondary d-none">
                                <i class="fas fa-times mr-1"></i>
                                Change GSTIN
                            </button>
                        </div>
                    </div>
                    <small class="form-text text-muted" id="modal_gstHelpText">
                        Enter GSTIN to automatically fill the registered party details.
                    </small>
                </div>

                <div id="modal_gstResult" class="d-none mt-3">
                    <div class="alert alert-light border mb-0 py-2">
                        <div class="row small">
                            <div class="col-md-4 mb-1 mb-md-0">
                                <strong>GSTIN:</strong>
                                <span id="modal_resultGstin">-</span>
                            </div>
                            <div class="col-md-4 mb-1 mb-md-0">
                                <strong>Status:</strong>
                                <span id="modal_resultStatus" class="badge badge-success">-</span>
                            </div>
                            <div class="col-md-4">
                                <strong>Taxpayer Type:</strong>
                                <span id="modal_resultType">-</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- =========================================================
         PARTY DETAILS
         ========================================================= -->
        <div class="card shadow-sm mb-3">
            <div class="card-header py-2">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-user mr-1"></i>
                    Party Details
                </h6>
            </div>
            <div class="card-body">

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
                        <label>Legal Name <span class="text-danger">*</span></label>
                        <input type="text" name="legal_name" id="modal_legal_name"
                            class="form-control" maxlength="255"
                            placeholder="Enter legal name" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Trade Name</label>
                        <input type="text" name="trade_name" id="modal_trade_name"
                            class="form-control" maxlength="255"
                            placeholder="Enter trade name">
                    </div>
                    <div class="form-group col-md-6">
                        <label>Phone</label>
                        <input type="text" name="phone" id="modal_phone"
                            class="form-control" maxlength="15"
                            placeholder="Enter phone number">
                    </div>
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="text" name="email" id="modal_email"
                        class="form-control" maxlength="150"
                        placeholder="Enter email">
                </div>

                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" id="modal_address"
                        class="form-control" rows="2"
                        maxlength="500" placeholder="Enter address"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-4 mb-md-0">
                        <label>City</label>
                        <input type="text" name="city" id="modal_city"
                            class="form-control" maxlength="100"
                            placeholder="Enter city">
                    </div>
                    <div class="form-group col-md-4 mb-md-0">
                        <label>State</label>
                        <input type="text" name="state" id="modal_state"
                            class="form-control" maxlength="100"
                            placeholder="Enter state">
                    </div>
                    <div class="form-group col-md-4 mb-0">
                        <label>Pincode</label>
                        <input type="text" name="pincode" id="modal_pincode"
                            class="form-control" maxlength="6"
                            placeholder="Enter 6-digit pincode">
                    </div>
                </div>

                <!-- Hidden status set by GST fetch -->
                <input type="hidden" name="status" id="modal_gst_status" value="">

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
                <input type="text" name="capacity" class="form-control" maxlength="15"
                    placeholder="Enter capacity">
            </div>
        </div>

        <div class="form-group">
            <label>Owner Name</label>
            <input type="text" name="owner_name" class="form-control" maxlength="100"
                placeholder="Enter owner name">
        </div>

        <div class="form-group">
            <label>Address</label>
            <input type="text" name="address" class="form-control" maxlength="255"
                placeholder="Enter address">
        </div>

        <div class="form-row">
            <div class="form-group col-md-4 mb-md-0">
                <label>City</label>
                <input type="text" name="city" class="form-control" maxlength="50"
                    placeholder="Enter city">
            </div>
            <div class="form-group col-md-4 mb-md-0">
                <label>State</label>
                <input type="text" name="state" class="form-control" maxlength="50"
                    placeholder="Enter state">
            </div>
            <div class="form-group col-md-4 mb-0">
                <label>Pincode</label>
                <input type="text" name="pincode" class="form-control" maxlength="6"
                    placeholder="Enter 6-digit pincode">
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
                <input type="text" name="phone" class="form-control" maxlength="15"
                    placeholder="Enter phone number">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-md-6">
                <label>License Number</label>
                <input type="text" name="license_no" class="form-control text-uppercase"
                    maxlength="30" placeholder="Enter license number">
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
            <input type="text" name="address" class="form-control" maxlength="255"
                placeholder="Enter address">
        </div>

        <div class="form-row">
            <div class="form-group col-md-4 mb-md-0">
                <label>City</label>
                <input type="text" name="city" class="form-control" maxlength="50"
                    placeholder="Enter city">
            </div>
            <div class="form-group col-md-4 mb-md-0">
                <label>State</label>
                <input type="text" name="state" class="form-control" maxlength="50"
                    placeholder="Enter state">
            </div>
            <div class="form-group col-md-4 mb-0">
                <label>Pincode</label>
                <input type="text" name="pincode" class="form-control" maxlength="6"
                    placeholder="Enter 6-digit pincode">
            </div>
        </div>

    <?php endif; ?>

    <div class="d-flex justify-content-end pt-2 border-top mt-3">
        <button type="button" class="btn btn-secondary mr-2" data-dismiss="modal">
            <i class="fas fa-times mr-1"></i> Cancel
        </button>
        <button type="submit" class="btn btn-success">
            <i class="fas fa-check mr-1"></i> Save
        </button>
    </div>
</form>