<?php

include 'session.php';
include 'constant.php';

// Requires the new permission to view the page at all
requirePermission('party_rate.view');

/* =========================================================
   AJAX HANDLERS
   ========================================================= */
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    $action = $_GET['action'];

    // 1. Get Default Rate for an Item
    if ($action === 'get_rate') {
        $inv_id = (int)($_POST['inventory_id'] ?? 0);
        $query = mysqli_query($conn, "SELECT rate FROM inventory WHERE id = $inv_id LIMIT 1");
        if ($query && mysqli_num_rows($query) > 0) {
            $row = mysqli_fetch_assoc($query);
            echo json_encode(['success' => true, 'rate' => (float)$row['rate']]);
        } else {
            echo json_encode(['success' => false, 'rate' => '']);
        }
        exit;
    }

    // 2. Get Record for Edit
    if ($action === 'get_record') {
        if (!hasPermission('party_rate.edit')) {
            echo json_encode(['success' => false, 'message' => 'Permission denied.']);
            exit;
        }
        $id = (int)($_POST['id'] ?? 0);
        $query = mysqli_query($conn, "SELECT * FROM party_rate WHERE id = $id LIMIT 1");
        if ($query && mysqli_num_rows($query) > 0) {
            $row = mysqli_fetch_assoc($query);
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Record not found.']);
        }
        exit;
    }

    // 3. Save (Insert or Update)
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        
        // Block save if missing permissions
        if ($id > 0 && !hasPermission('party_rate.edit')) {
            echo json_encode(['success' => false, 'message' => 'Permission denied to edit.']);
            exit;
        } elseif ($id === 0 && !hasPermission('party_rate.create')) {
            echo json_encode(['success' => false, 'message' => 'Permission denied to create.']);
            exit;
        }
        
        $party_id = (int)($_POST['party_id'] ?? 0);
        $inv_id = (int)($_POST['inventory_id'] ?? 0);
        $rate = $_POST['rate'] ?? '';

        if ($party_id <= 0 || $inv_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Party and Inventory Item are required.']);
            exit;
        }

        if ($rate === '' || !is_numeric($rate) || (float)$rate < 0) {
            echo json_encode(['success' => false, 'message' => 'Enter a valid non-negative rate.']);
            exit;
        }
        $rateFloat = (float)$rate;

        // Check for duplicates
        $dupSql = "SELECT id FROM party_rate WHERE party_id = $party_id AND inventory_id = $inv_id";
        if ($id > 0) {
            $dupSql .= " AND id != $id";
        }
        $dupCheck = mysqli_query($conn, $dupSql);
        if ($dupCheck && mysqli_num_rows($dupCheck) > 0) {
            echo json_encode(['success' => false, 'message' => 'A special rate already exists for this party and item.']);
            exit;
        }

        if ($id > 0) {
            // Update
            $sql = "UPDATE party_rate SET party_id = $party_id, inventory_id = $inv_id, rate = $rateFloat WHERE id = $id";
        } else {
            // Insert
            $sql = "INSERT INTO party_rate (party_id, inventory_id, rate) VALUES ($party_id, $inv_id, $rateFloat)";
        }

        if (mysqli_query($conn, $sql)) {
            echo json_encode(['success' => true, 'message' => 'Saved successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
        }
        exit;
    }

    // 4. Toggle Active Status
    if ($action === 'toggle_active') {
        if (!hasPermission('party_rate.delete')) {
            echo json_encode(['success' => false, 'message' => 'Permission denied.']);
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        
        $check = mysqli_query($conn, "SELECT active FROM party_rate WHERE id = $id LIMIT 1");
        if ($check && mysqli_num_rows($check) === 1) {
            $row = mysqli_fetch_assoc($check);
            $new = ((int)$row['active'] === 1) ? 0 : 1;
            mysqli_query($conn, "UPDATE party_rate SET active = $new WHERE id = $id");
            echo json_encode(['success' => true, 'active' => $new]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Record not found.']);
        }
        exit;
    }
}


/* =========================================================
   PAGE DATA FETCHING
   ========================================================= */

// Fetch active parties
$parties = [];
$resP = mysqli_query($conn, "SELECT id, legal_name, trade_name FROM party WHERE active = 1 ORDER BY legal_name");
while ($row = mysqli_fetch_assoc($resP)) {
    $name = $row['legal_name'];
    if (!empty($row['trade_name'])) $name .= ' (' . $row['trade_name'] . ')';
    $parties[] = ['id' => $row['id'], 'name' => $name];
}

// Fetch active inventory (Filter by branch if not admin)
$invFilter = isAdmin() ? "" : " AND branch_id = " . (int)($_SESSION['branch_id'] ?? 0);
$inventory = [];
$resI = mysqli_query($conn, "SELECT id, item_name, unit FROM inventory WHERE active = 1 $invFilter ORDER BY item_name");
while ($row = mysqli_fetch_assoc($resI)) {
    $inventory[] = $row;
}

// Fetch existing party rates for the table
$ratesData = [];
$sqlRates = "
    SELECT pr.id, pr.party_id, pr.inventory_id, pr.rate, pr.active, 
           p.legal_name, i.item_name, i.unit 
    FROM party_rate pr
    JOIN party p ON p.id = pr.party_id
    JOIN inventory i ON i.id = pr.inventory_id
    WHERE 1=1 $invFilter
    ORDER BY pr.id DESC
";
$resR = mysqli_query($conn, $sqlRates);
if ($resR) {
    while ($row = mysqli_fetch_assoc($resR)) {
        $ratesData[] = $row;
    }
}

$pageTitle = 'Party Rates | Billing Portal';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= $pageTitle ?></title>
    <?php include 'layout/header.php'; ?>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap4.min.css">
    <style>
        .switch { position: relative; display: inline-block; width: 44px; height: 22px; margin: 0; vertical-align: middle; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .switch .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #b7b9cc; transition: .2s; border-radius: 22px; }
        .switch .slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background-color: white; transition: .2s; border-radius: 50%; }
        .switch input:checked+.slider { background-color: #1cc88a; }
        .switch input:checked+.slider:before { transform: translateX(22px); }
        tr.row-inactive > td { background-color: #fdecea !important; color: #842029; }
    </style>
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
                            <i class="fas fa-hand-holding-usd mr-2"></i>Party Special Rates
                        </h1>
                        <?php if (hasPermission('party_rate.create')): ?>
                           <button class="btn btn-sm btn-primary shadow-sm js-add-btn d-inline-block w-auto">
    <i class="fas fa-plus mr-1"></i> Add Party Rate
</button>                                                                                             
                        <?php endif; ?>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">All Party Rates</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover" id="ratesTable" width="100%" cellspacing="0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th width="50">#</th>
                                            <th>Party Name</th>
                                            <th>Item Name</th>
                                            <th>Special Rate (₹)</th>
                                            <?php if (hasPermission('party_rate.edit') || hasPermission('party_rate.delete')): ?>
                                                <th width="120" class="text-center">Action</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ratesData as $i => $r): 
                                            $isActive = (int)$r['active'] === 1;
                                        ?>
                                            <tr class="<?= $isActive ? '' : 'row-inactive' ?>" data-id="<?= $r['id'] ?>">
                                                <td><?= $i + 1 ?></td>
                                                <td><?= htmlspecialchars($r['legal_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($r['item_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                    <span class="text-muted small ml-1"><?= $r['unit'] ? "({$r['unit']})" : "" ?></span>
                                                </td>
                                                <td class="font-weight-bold text-dark">₹<?= number_format((float)$r['rate'], 2) ?></td>
                                                
                                                <?php if (hasPermission('party_rate.edit') || hasPermission('party_rate.delete')): ?>
                                                    <td class="text-center text-nowrap">
                                                        <?php if (hasPermission('party_rate.edit')): ?>
                                                            <button class="btn btn-sm btn-primary js-edit-btn" data-id="<?= $r['id'] ?>" title="Edit Rate">
                                                                <i class="fas fa-pen"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (hasPermission('party_rate.delete')): ?>
                                                            <label class="switch mb-0 ml-1" title="Toggle Active">
                                                                <input type="checkbox" class="js-toggle-active" data-id="<?= $r['id'] ?>" <?= $isActive ? 'checked' : '' ?>>
                                                                <span class="slider"></span>
                                                            </label>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include 'layout/footer.php'; ?>
        </div>
    </div>

    <!-- ADD/EDIT MODAL -->
    <div class="modal fade" id="rateModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <form id="rateForm" onsubmit="return false;">
                    <input type="hidden" id="record_id" name="id" value="0">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Add Party Rate</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    
                    <div class="modal-body">
                        <div id="modalAlert" class="alert d-none"></div>

                        <div class="form-group">
                            <label>Party <span class="text-danger">*</span></label>
                            <select id="party_id" name="party_id" class="form-control" required>
                                <option value="">— Select Party —</option>
                                <?php foreach ($parties as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Inventory Item <span class="text-danger">*</span></label>
                            <select id="inventory_id" name="inventory_id" class="form-control" required>
                                <option value="">— Select Item —</option>
                                <?php foreach ($inventory as $inv): ?>
                                    <option value="<?= $inv['id'] ?>"><?= htmlspecialchars($inv['item_name'], ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Special Rate (₹) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" id="rate" name="rate" class="form-control" step="0.01" min="0" required placeholder="0.00">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary js-fetch-rate" type="button" title="Fetch Default Rate">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                            </div>
                            <small class="form-text text-muted">Select an item above to auto-fill its default rate.</small>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success" id="btnSave">
                            <i class="fas fa-check mr-1"></i> Save Rate
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap4.min.js"></script>

    <script>
    $(function() {
        var table = $('#ratesTable').DataTable({
            order: [[0, 'asc']],
            language: { search: '', searchPlaceholder: 'Search rates...' }
        });

        var $modal = $('#rateModal');
        var $form = $('#rateForm');
        var $alert = $('#modalAlert');

        function showAlert(msg, isSuccess) {
            $alert.removeClass('d-none alert-success alert-danger')
                  .addClass(isSuccess ? 'alert-success' : 'alert-danger')
                  .html(msg);
        }

        // 1. OPEN MODAL FOR ADD
        $('.js-add-btn').on('click', function() {
            $('#record_id').val('0');
            $form[0].reset();
            $alert.addClass('d-none');
            $('#party_id').prop('disabled', false); 
            $('#inventory_id').prop('disabled', false); 
            $('#modalTitle').text('Add Party Rate');
            $modal.modal('show');
        });

        // 2. OPEN MODAL FOR EDIT
        $(document).on('click', '.js-edit-btn', function() {
            var id = $(this).data('id');
            $alert.addClass('d-none');
            $('#modalTitle').text('Edit Party Rate');
            
            $.post('party-rates.php?action=get_record', { id: id }, function(res) {
                if(res.success) {
                    $('#record_id').val(res.data.id);
                    $('#party_id').val(res.data.party_id).prop('disabled', true); 
                    $('#inventory_id').val(res.data.inventory_id).prop('disabled', true);
                    
                    var rateVal = parseFloat(res.data.rate);
                    $('#rate').val(rateVal === 0 ? '' : rateVal);
                    
                    $modal.modal('show');
                } else {
                    alert(res.message);
                }
            }, 'json');
        });

        // 3. AUTO-FETCH DEFAULT RATE ON ITEM SELECTION
        $('#inventory_id').on('change', function() {
            var invId = $(this).val();
            
            if (invId && $('#record_id').val() == '0') {
                $.post('party-rates.php?action=get_rate', { inventory_id: invId }, function(res) {
                    if (res.success) {
                        var rateVal = parseFloat(res.rate);
                        $('#rate').val(rateVal === 0 ? '' : rateVal);
                    }
                }, 'json');
            }
        });

        // Button to manually trigger fetch rate
        $('.js-fetch-rate').on('click', function() {
            var invId = $('#inventory_id').val();
            if(invId) {
                $.post('party-rates.php?action=get_rate', { inventory_id: invId }, function(res) {
                    if (res.success) {
                        var rateVal = parseFloat(res.rate);
                        $('#rate').val(rateVal === 0 ? '' : rateVal);
                    }
                }, 'json');
            } else {
                showAlert('Select an inventory item first.', false);
            }
        });

        // 4. SAVE (INSERT / UPDATE)
        $form.on('submit', function(e) {
            e.preventDefault();
            
            var $party = $('#party_id');
            var $inv = $('#inventory_id');
            var partyDisabled = $party.prop('disabled');
            var invDisabled = $inv.prop('disabled');
            
            $party.prop('disabled', false);
            $inv.prop('disabled', false);
            
            var formData = $form.serialize();
            
            $party.prop('disabled', partyDisabled);
            $inv.prop('disabled', invDisabled);

            var $btn = $('#btnSave');
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving...');

            $.post('party-rates.php?action=save', formData, function(res) {
                if (res.success) {
                    $modal.modal('hide');
                    location.reload(); 
                } else {
                    showAlert(res.message, false);
                    $btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i> Save Rate');
                }
            }, 'json').fail(function() {
                showAlert('Server error occurred.', false);
                $btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i> Save Rate');
            });
        });

        // 5. TOGGLE ACTIVE STATUS
        $(document).on('change', '.js-toggle-active', function() {
            var $cb = $(this);
            var id = $cb.data('id');
            var isNow = $cb.is(':checked');
            var $row = $cb.closest('tr');
            
            $cb.prop('disabled', true);
            $.post('party-rates.php?action=toggle_active', { id: id }, function(res) {
                if (!res.success) {
                    $cb.prop('checked', !isNow);
                    if (res.message) alert(res.message);
                } else {
                    if (res.active === 1) {
                        $row.removeClass('row-inactive');
                    } else {
                        $row.addClass('row-inactive');
                    }
                }
            }, 'json')
            .fail(function() {
                $cb.prop('checked', !isNow);
                alert("Server Error");
            })
            .always(function() {
                $cb.prop('disabled', false);
            });
        });

    });
    </script>
</body>
</html>