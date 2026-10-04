/* =========================================================
   js/trip-view.js
   ---------------------------------------------------------
   Trip view interactions:
     - status change (AJAX)
     - add / delete payment (payments bucket)
     - add / delete charge  (charges bucket)
     - add / delete expense
     - live summary recalculation

   Payments and charges share the trip_payment table but live
   in separate UI tables. Server decides entry_kind from
   payment_type and echoes it back; the client just renders.
   ========================================================= */

$(function () {

  var DATA = window.TRIP_VIEW_DATA || {};

  var TRIP_ID  = parseInt(DATA.tripId, 10) || 0;
  var CAN_EDIT = !!DATA.canEdit;

  /* =========================================================
     TYPE → KIND CLASSIFICATION
     ---------------------------------------------------------
     Must match ajax/payment.php::kindForType().
     payment → Received bucket, reduces balance
     charge  → Extra    bucket, increases balance
     ========================================================= */
  var PAYMENT_TYPE_MAP = {
    'Advance':          'payment',
    'Freight Payment':  'payment',
    'Loading Charge':   'charge',
    'Unloading Charge': 'charge',
    'Detention':        'charge',
    'Extra Charge':     'charge'
  };

  function kindOf(type) {
    return PAYMENT_TYPE_MAP[type] || 'payment';
  }

  var PAYMENT_TYPES = ['Advance', 'Freight Payment'];
  var CHARGE_TYPES  = ['Loading Charge', 'Unloading Charge', 'Detention', 'Extra Charge'];

  /* =========================================================
     HELPERS
     ========================================================= */
  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function formatMoney(n) {
    n = parseFloat(n) || 0;
    return n.toLocaleString('en-IN', {
      maximumFractionDigits: 2,
      minimumFractionDigits: 2
    });
  }

  function parseMoney(s) {
    var cleaned = String(s).replace(/[^0-9.\-]/g, '');
    return parseFloat(cleaned) || 0;
  }

  function formatDateShort(iso) {
    if (!iso) return '';
    var parts = iso.split('-');
    if (parts.length !== 3) return iso;
    var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                  'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    var m = parseInt(parts[1], 10) - 1;
    return String(parts[2]).padStart(2, '0') + ' ' +
           months[m] + ' ' +
           parts[0];
  }

  function newToken() {
    return Math.random().toString(36).substring(2) + Date.now().toString(36);
  }

  function showFlash(type, msg) {
    var $wrap = $('#tripViewFlash');
    if (!$wrap.length) return;

    var icon = (type === 'success')
      ? '<i class="fas fa-check-circle mr-1"></i>'
      : (type === 'warning')
        ? '<i class="fas fa-exclamation-triangle mr-1"></i>'
        : '<i class="fas fa-exclamation-circle mr-1"></i>';

    var html =
      '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
        icon + escapeHtml(msg) +
        '<button type="button" class="close" data-dismiss="alert">' +
          '<span>&times;</span>' +
        '</button>' +
      '</div>';

    $wrap.html(html);

    setTimeout(function () {
      $wrap.find('.alert').fadeOut(400, function () {
        $(this).remove();
      });
    }, 5000);
  }

  function statusPillClass(status) {
    switch (status) {
      case 'Scheduled':   return 'status-scheduled';
      case 'In Progress': return 'status-in-progress';
      case 'Delivered':   return 'status-delivered';
      case 'Billed':      return 'status-billed';
      case 'Paid':        return 'status-paid';
      default:            return 'status-scheduled';
    }
  }

  /* =========================================================
     SUMMARY — recompute the trip-level cards
     ---------------------------------------------------------
     Received = sum of payment-kind rows (Advance + Freight Payment)
     Extra    = sum of charge-kind  rows (Loading + Unloading +
                Detention + Extra Charge)
     Balance  = (Freight + Extra) − Received
     ========================================================= */
  function recalcSummary() {
    var freight  = parseMoney($('#summaryFreight').text());
    var received = parseMoney($('#summaryReceived').text());
    var extra    = parseMoney($('#summaryExtra').text());
    var expense  = parseMoney($('#summaryExpense').text());

    var balance = (freight + extra) - received;
    var net     = freight - expense;

    $('#summaryBalance').text('₹ ' + formatMoney(balance));
    $('#summaryNet').text('₹ ' + formatMoney(net));

    var $balanceCard = $('#summaryBalanceCard .tv-sum-card');
    if ($balanceCard.length) {
      $balanceCard.removeClass('green red');
      $balanceCard.addClass(balance > 0.01 ? 'red' : 'green');
    }

    var $netCard = $('#summaryNetCard .tv-sum-card');
    if ($netCard.length) {
      $netCard.removeClass('green red');
      $netCard.addClass(net >= 0 ? 'green' : 'red');
    }
  }

  /* =========================================================
     STATUS CHANGE
     ========================================================= */
  $(document).on('click', '.js-status-change', function (e) {
    e.preventDefault();

    var $item     = $(this);
    var newStatus = $item.data('status');
    var id        = $item.data('id');
    var $badge    = $('#tripStatusBadge');

    if (!newStatus || !id) return;
    if (newStatus === $badge.data('current')) return;

    var $text        = $badge.find('.js-status-text');
    var previousText = $text.text().trim();

    $badge.prop('disabled', true);

    $.ajax({
      url: 'trip-view.php?action=change_status',
      type: 'POST',
      dataType: 'json',
      data: { id: id, status: newStatus }
    })
    .done(function (res) {
      if (!res || !res.success) {
        showFlash('danger', res && res.message ? res.message : 'Failed to update status.');
        return;
      }

      $badge.data('current', res.status);
      $text.text(res.status);

      $badge
        .removeClass('status-scheduled status-in-progress status-delivered status-billed status-paid')
        .addClass(statusPillClass(res.status));

      $('.js-status-change').removeClass('active');
      $('.js-status-change[data-status="' + res.status + '"]').addClass('active');

      showFlash('success', 'Status updated to "' + res.status + '".');
    })
    .fail(function () {
      $text.text(previousText);
      showFlash('danger', 'Server error. Please try again.');
    })
    .always(function () {
      $badge.prop('disabled', false);
    });
  });

  /* =========================================================
     ADD — modal setup
     ---------------------------------------------------------
     modalKind is set by the button that opens the modal and
     decides the option list + title. Not a form field.
     ========================================================= */
  var $entryModal      = $('#paymentModal');
  var $entryForm       = $('#paymentForm');
  var $entryErrBox     = $('#paymentFormError');
  var $entrySubmitBtn  = $('#paymentSubmitBtn');
  var $entryModalTitle = $('#paymentModalTitle');
  var $entryTypeSelect = $entryForm.find('select[name="payment_type"]');
  var $entryModeField  = $entryForm.find('.js-mode-field');

  var entrySubmitting = false;
  var modalKind       = 'payment';

  function buildTypeOptions(kind) {
    var list = (kind === 'charge') ? CHARGE_TYPES : PAYMENT_TYPES;
    var html = '<option value="">— Select —</option>';
    for (var i = 0; i < list.length; i++) {
      html += '<option value="' + escapeHtml(list[i]) + '">' + escapeHtml(list[i]) + '</option>';
    }
    return html;
  }

  function openEntryModal(kind, tpId, partyName) {
    if (tpId <= 0) return;

    modalKind = (kind === 'charge') ? 'charge' : 'payment';

    $entryForm[0].reset();
    $entryForm.find('input[name="trip_party_id"]').val(tpId);

    /* today */
    var today = new Date();
    var yyyy  = today.getFullYear();
    var mm    = String(today.getMonth() + 1).padStart(2, '0');
    var dd    = String(today.getDate()).padStart(2, '0');
    $entryForm.find('input[name="payment_date"]').val(yyyy + '-' + mm + '-' + dd);

    /* populate types + title */
    $entryTypeSelect.html(buildTypeOptions(modalKind));

    if (modalKind === 'charge') {
      $entryModalTitle.html('<i class="fas fa-plus-circle mr-1"></i> Add Charge — ' + escapeHtml(partyName));
      $entryModeField.addClass('d-none');
    } else {
      $entryModalTitle.html('<i class="fas fa-money-bill-wave mr-1"></i> Add Payment — ' + escapeHtml(partyName));
      $entryModeField.removeClass('d-none');
    }

    $entryErrBox.addClass('d-none').html('');
    entrySubmitting = false;

    $entryModal.modal({ backdrop: 'static', keyboard: false, show: true });

    setTimeout(function () {
      $entryForm.find('select[name="payment_type"]').focus();
    }, 300);
  }

  $(document).on('click', '.js-open-payment', function () {
    var tpId      = parseInt($(this).data('trip-party-id'), 10) || 0;
    var partyName = $(this).data('party-name') || '';
    openEntryModal('payment', tpId, partyName);
  });

  $(document).on('click', '.js-open-charge', function () {
    var tpId      = parseInt($(this).data('trip-party-id'), 10) || 0;
    var partyName = $(this).data('party-name') || '';
    openEntryModal('charge', tpId, partyName);
  });

  /* =========================================================
     ADD — submit
     ========================================================= */
  $entrySubmitBtn.on('click', function (e) {
    e.preventDefault();
    e.stopImmediatePropagation();

    if (entrySubmitting) return;
    entrySubmitting = true;

    $entryErrBox.addClass('d-none').html('');
    $entrySubmitBtn.prop('disabled', true).html(
      '<span class="spinner-border spinner-border-sm mr-1"></span> Saving...'
    );

    var token   = newToken();
    var payload = $entryForm.serializeArray();
    payload.push({ name: '_token', value: token });

    $.ajax({
      url: 'ajax/payment.php?action=save',
      type: 'POST',
      dataType: 'json',
      data: $.param(payload)
    })
    .done(function (res) {
      if (!res || !res.success) {
        $entryErrBox
          .removeClass('d-none')
          .html(
            '<div class="alert alert-danger mb-3">' +
              '<i class="fas fa-exclamation-circle mr-1"></i>' +
              escapeHtml(res && res.message ? res.message : 'Save failed.') +
            '</div>'
          );
        return;
      }

      addRow(res);
      applyEntryDelta(res.trip_party_id, res.entry_kind, parseFloat(res.amount) || 0, +1);

      $entryModal.modal('hide');
      showFlash('success', (res.entry_kind === 'charge' ? 'Charge' : 'Payment') + ' recorded.');
    })
    .fail(function (xhr) {
      var msg = 'Server error.';
      if (xhr.responseJSON && xhr.responseJSON.message) {
        msg = xhr.responseJSON.message;
      }
      $entryErrBox
        .removeClass('d-none')
        .html(
          '<div class="alert alert-danger mb-3">' +
            '<i class="fas fa-exclamation-circle mr-1"></i>' +
            escapeHtml(msg) +
          '</div>'
        );
    })
    .always(function () {
      entrySubmitting = false;
      $entrySubmitBtn.prop('disabled', false).html(
        '<i class="fas fa-check mr-1"></i> Save'
      );
    });
  });

  $entryForm.on('submit', function (e) {
    e.preventDefault();
    e.stopImmediatePropagation();
    return false;
  });

  function addRow(res) {
    var kind = res.entry_kind === 'charge' ? 'charge' : 'payment';
    var tpId = res.trip_party_id;

    var $tbody = $('#' + (kind === 'charge' ? 'chargesBody-' : 'paymentsBody-') + tpId);
    var $empty = $('#' + (kind === 'charge' ? 'chargesEmpty-' : 'paymentsEmpty-') + tpId);
    var $wrap  = $('#' + (kind === 'charge' ? 'chargesWrap-' : 'paymentsWrap-') + tpId);

    if (!$tbody.length) return;

    $empty.hide();
    $wrap.show();

    var dateStr   = formatDateShort(res.date);
    var amountStr = '₹ ' + formatMoney(res.amount);
    var typeStr   = res.payment_type || '—';
    var modeStr   = res.mode || '—';
    var refStr    = res.reference || '—';
    var notesStr  = res.notes || '—';

    var deleteCell = '';
    if (CAN_EDIT) {
      deleteCell =
        '<td class="text-center">' +
          '<button type="button" ' +
            'class="tv-icon-btn tv-icon-btn-danger js-delete-payment" ' +
            'data-payment-id="' + res.id + '" ' +
            'data-trip-party-id="' + tpId + '" ' +
            'data-amount="' + (parseFloat(res.amount) || 0) + '" ' +
            'data-type="' + escapeHtml(res.payment_type) + '" ' +
            'data-kind="' + kind + '" ' +
            'title="Delete">' +
            '<i class="fas fa-trash-alt"></i>' +
          '</button>' +
        '</td>';
    }

    var kindClass = kind === 'charge' ? ' extra' : '';

    var html =
      '<tr data-payment-id="' + res.id + '">' +
        '<td class="text-muted small tv-pay-index">' + ($tbody.find('tr').length + 1) + '</td>' +
        '<td>' + escapeHtml(dateStr) + '</td>' +
        '<td><span class="tv-pay-type' + kindClass + '">' + escapeHtml(typeStr) + '</span></td>' +
        '<td>' + escapeHtml(modeStr) + '</td>' +
        '<td class="small text-muted">' + escapeHtml(refStr) + '</td>' +
        '<td class="small text-muted">' + escapeHtml(notesStr) + '</td>' +
        '<td class="tv-pay-amount">' + amountStr + '</td>' +
        deleteCell +
      '</tr>';

    $tbody.append(html);

    $tbody.find('tr').each(function (i) {
      $(this).find('.tv-pay-index').text(i + 1);
    });

    updateBucketTotal(kind, tpId);
  }

  /* =========================================================
     DELETE
     ========================================================= */
  $(document).on('click', '.js-delete-payment', function () {
    var $btn   = $(this);
    var payId  = $btn.data('payment-id');
    var amount = parseFloat($btn.data('amount')) || 0;
    var type   = $btn.data('type') || 'entry';
    var kind   = $btn.data('kind') || kindOf(type);

    if (!payId) return;

    if (!confirm('Delete "' + type + '" of ₹ ' + formatMoney(amount) + '?')) {
      return;
    }

    $btn.prop('disabled', true);

    $.ajax({
      url: 'ajax/payment.php?action=delete',
      type: 'POST',
      dataType: 'json',
      data: { payment_id: payId }
    })
    .done(function (res) {
      if (!res || !res.success) {
        showFlash('danger', res && res.message ? res.message : 'Delete failed.');
        return;
      }

      var resKind = res.entry_kind === 'charge' ? 'charge' : 'payment';
      var tpId    = res.trip_party_id;

      var $row = $('tr[data-payment-id="' + payId + '"]');
      $row.fadeOut(200, function () {
        $(this).remove();

        var $tbody = $('#' + (resKind === 'charge' ? 'chargesBody-' : 'paymentsBody-') + tpId);
        $tbody.find('tr').each(function (i) {
          $(this).find('.tv-pay-index').text(i + 1);
        });

        if ($tbody.find('tr').length === 0) {
          $('#' + (resKind === 'charge' ? 'chargesWrap-' : 'paymentsWrap-') + tpId).hide();
          $('#' + (resKind === 'charge' ? 'chargesEmpty-' : 'paymentsEmpty-') + tpId).show();
        }

        updateBucketTotal(resKind, tpId);
      });

      applyEntryDelta(tpId, resKind, parseFloat(res.deleted_amount) || 0, -1);

      showFlash('success', (resKind === 'charge' ? 'Charge' : 'Payment') + ' deleted.');
    })
    .fail(function () {
      showFlash('danger', 'Server error. Please try again.');
    })
    .always(function () {
      $btn.prop('disabled', false);
    });
  });

  /* =========================================================
     Update the per-party bucket total (Received or Extra)
     by walking the visible rows.
     ========================================================= */
  function updateBucketTotal(kind, tpId) {
    var $body = $('#' + (kind === 'charge' ? 'chargesBody-' : 'paymentsBody-') + tpId);
    var total = 0;
    $body.find('tr').each(function () {
      var amt = parseMoney($(this).find('.tv-pay-amount').text());
      total += amt;
    });

    var $t = $('#' + (kind === 'charge' ? 'chargesTotal-' : 'paymentsTotal-') + tpId);
    if ($t.length) $t.text('₹ ' + formatMoney(total));
  }

  /* =========================================================
     Apply a signed delta to the party card and trip summary.
     direction = +1 for add, -1 for delete
     ========================================================= */
  function applyEntryDelta(tpId, kind, amount, direction) {
    var k = (kind === 'charge') ? 'charge' : 'payment';
    var delta = amount * direction;

    var $partyReceived = $('#party-received-' + tpId);
    var $partyExtra    = $('#party-extra-' + tpId);
    var $partyBalance  = $('#party-balance-' + tpId);
    var $partyCard     = $('#party-card-' + tpId);

    if ($partyCard.length) {
      var freight  = parseFloat($partyCard.data('freight')) || 0;
      var received = parseMoney($partyReceived.text());
      var extra    = parseMoney($partyExtra.text());

      if (k === 'payment') {
        received += delta;
      } else {
        extra += delta;
      }

      if (received < 0) received = 0;
      if (extra    < 0) extra    = 0;

      $partyReceived.text('₹ ' + formatMoney(received));
      $partyExtra.text('₹ ' + formatMoney(extra));

      var bal = (freight + extra) - received;
      $partyBalance.text('₹ ' + formatMoney(bal));
      $partyBalance.removeClass('green red');
      $partyBalance.addClass(bal > 0.01 ? 'red' : 'green');
    }

    /* Trip summary */
    if (k === 'payment') {
      var $rec = $('#summaryReceived');
      if ($rec.length) {
        var r = parseMoney($rec.text()) + delta;
        if (r < 0) r = 0;
        $rec.text('₹ ' + formatMoney(r));
      }
    } else {
      var $ext = $('#summaryExtra');
      if ($ext.length) {
        var e = parseMoney($ext.text()) + delta;
        if (e < 0) e = 0;
        $ext.text('₹ ' + formatMoney(e));
      }
    }

    recalcSummary();
  }

  /* =========================================================
     EXPENSES — open modal
     ========================================================= */
  var $expenseModal     = $('#expenseModal');
  var $expenseForm      = $('#expenseForm');
  var $expenseErrBox    = $('#expenseFormError');
  var $expenseSubmitBtn = $('#expenseSubmitBtn');

  var expenseSubmitting = false;

  function syncExpenseSupplierField() {
    var cat = $expenseForm.find('select[name="category"]').val();
    var $sup = $expenseForm.find('.js-supplier-field');

    if (cat === 'Truck Hire Charge') {
      $sup.removeClass('d-none');
    } else {
      $sup.addClass('d-none');
      $sup.find('select').val('');
    }
  }

  $expenseForm.on('change', 'select[name="category"]', syncExpenseSupplierField);

  $(document).on('click', '.js-open-expense', function () {
    $expenseForm[0].reset();
    syncExpenseSupplierField();
    $expenseErrBox.addClass('d-none').html('');
    expenseSubmitting = false;

    $expenseModal.modal({ backdrop: 'static', keyboard: false, show: true });

    setTimeout(function () {
      $expenseForm.find('input[name="expense_date"]').focus();
    }, 300);
  });

  $expenseSubmitBtn.on('click', function (e) {
    e.preventDefault();
    e.stopImmediatePropagation();

    if (expenseSubmitting) return;
    expenseSubmitting = true;

    $expenseErrBox.addClass('d-none').html('');

    $expenseSubmitBtn.prop('disabled', true).html(
      '<span class="spinner-border spinner-border-sm mr-1"></span> Saving...'
    );

    $.ajax({
      url: 'ajax/save-expense.php',
      type: 'POST',
      dataType: 'json',
      data: $expenseForm.serialize()
    })
    .done(function (res) {
      if (!res || !res.success) {
        $expenseErrBox
          .removeClass('d-none')
          .html(
            '<div class="alert alert-danger mb-3">' +
              '<i class="fas fa-exclamation-circle mr-1"></i>' +
              escapeHtml(res && res.message ? res.message : 'Save failed.') +
            '</div>'
          );
        return;
      }

      addExpenseRow(res);
      updateSummaryAfterExpenseDelta(parseFloat(res.amount) || 0);

      $expenseModal.modal('hide');
      showFlash('success', 'Expense added.');
    })
    .fail(function (xhr) {
      var msg = 'Server error.';
      if (xhr.responseJSON && xhr.responseJSON.message) {
        msg = xhr.responseJSON.message;
      }
      $expenseErrBox
        .removeClass('d-none')
        .html(
          '<div class="alert alert-danger mb-3">' +
            '<i class="fas fa-exclamation-circle mr-1"></i>' +
            escapeHtml(msg) +
          '</div>'
        );
    })
    .always(function () {
      expenseSubmitting = false;
      $expenseSubmitBtn.prop('disabled', false).html(
        '<i class="fas fa-check mr-1"></i> Save Expense'
      );
    });
  });

  $expenseForm.on('submit', function (e) {
    e.preventDefault();
    e.stopImmediatePropagation();
    return false;
  });

  function addExpenseRow(res) {
    $('#expensesEmpty').hide();
    $('#expensesTableWrap').show();

    var rowCount = $('#expensesBody tr').length + 1;
    var dateStr  = formatDateShort(res.date);
    var supplier = res.supplier_name ? res.supplier_name : '—';
    var notes    = res.notes ? res.notes : '—';

    var deleteCell = '';
    if (CAN_EDIT) {
      deleteCell =
        '<td class="text-center">' +
          '<button type="button" ' +
            'class="tv-icon-btn tv-icon-btn-danger js-delete-expense" ' +
            'data-expense-id="' + res.id + '" ' +
            'data-amount="' + (parseFloat(res.amount) || 0) + '" ' +
            'data-category="' + escapeHtml(res.category) + '" ' +
            'title="Delete expense">' +
            '<i class="fas fa-trash-alt"></i>' +
          '</button>' +
        '</td>';
    }

    var html =
      '<tr data-expense-id="' + res.id + '">' +
        '<td class="text-muted small tv-exp-index">' + rowCount + '</td>' +
        '<td>' + escapeHtml(dateStr) + '</td>' +
        '<td><span class="tv-pill">' + escapeHtml(res.category) + '</span></td>' +
        '<td>' + escapeHtml(supplier) + '</td>' +
        '<td class="text-muted small">' + escapeHtml(notes) + '</td>' +
        '<td class="tv-pay-amount" style="color:#c0392b;">₹ ' + formatMoney(res.amount) + '</td>' +
        deleteCell +
      '</tr>';

    $('#expensesBody').append(html);

    var $count = $('#expenseCount');
    if ($count.length) $count.text(parseInt($count.text(), 10) + 1);

    var $total = $('#expensesTotalCell');
    if ($total.length) {
      var currentTotal = parseMoney($total.text());
      $total.text('₹ ' + formatMoney(currentTotal + (parseFloat(res.amount) || 0)));
    }
  }

  /* =========================================================
     EXPENSES — delete
     ========================================================= */
  $(document).on('click', '.js-delete-expense', function () {
    var $btn      = $(this);
    var expenseId = $btn.data('expense-id');
    var amount    = parseFloat($btn.data('amount')) || 0;
    var category  = $btn.data('category') || 'this expense';

    if (!expenseId) return;

    if (!confirm('Delete "' + category + '" of ₹ ' + formatMoney(amount) + '?')) {
      return;
    }

    $btn.prop('disabled', true);

    $.ajax({
      url: 'trip-view.php?action=delete_expense',
      type: 'POST',
      dataType: 'json',
      data: { trip_id: TRIP_ID, expense_id: expenseId }
    })
    .done(function (res) {
      if (!res || !res.success) {
        showFlash('danger', res && res.message ? res.message : 'Failed to delete.');
        return;
      }

      var $row = $('#expensesBody tr[data-expense-id="' + expenseId + '"]');
      $row.fadeOut(200, function () {
        $(this).remove();

        $('#expensesBody tr').each(function (i) {
          $(this).find('.tv-exp-index').text(i + 1);
        });

        if ($('#expensesBody tr').length === 0) {
          $('#expensesTableWrap').hide();
          $('#expensesEmpty').show();
        }
      });

      var $count = $('#expenseCount');
      if ($count.length) {
        var n = parseInt($count.text(), 10) - 1;
        if (n < 0) n = 0;
        $count.text(n);
      }

      var $total = $('#expensesTotalCell');
      if ($total.length) {
        var newTotal = parseMoney($total.text()) - (parseFloat(res.deleted_amount) || 0);
        if (newTotal < 0) newTotal = 0;
        $total.text('₹ ' + formatMoney(newTotal));
      }

      updateSummaryAfterExpenseDelta(-(parseFloat(res.deleted_amount) || 0));

      showFlash('success', 'Expense deleted.');
    })
    .fail(function () {
      showFlash('danger', 'Server error. Please try again.');
    })
    .always(function () {
      $btn.prop('disabled', false);
    });
  });

  function updateSummaryAfterExpenseDelta(delta) {
    var $exp = $('#summaryExpense');
    if ($exp.length) {
      var currentExpense = parseMoney($exp.text()) + delta;
      if (currentExpense < 0) currentExpense = 0;
      $exp.text('₹ ' + formatMoney(currentExpense));
    }
    recalcSummary();
  }

});