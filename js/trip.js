/* =========================================================
   js/trip.js — Trip form logic
   ---------------------------------------------------------
   Handles:
     1) Datalist sync (party / lorry / driver / branch)
     2) Dynamic item rows (add / remove)
     3) Amount calculation
     4) Item name autocomplete (via ajax/item_search.php)
     5) Master modal — AJAX-loaded form (party / lorry / driver)
     6) Client-side validation of required datalist inputs
   ========================================================= */

$(function () {

    /* =========================================================
       CONSTANTS
       ========================================================= */
    var ITEM_ROWS_WRAPPER = '#itemsWrap';
    var ITEM_ROW_CLASS    = '.item-row';
    var ADD_ITEM_BTN      = '#addItemBtn';
    var AUTOSUGGEST_BOX   = '.item-suggest';
    var GRAND_TOTAL_ID    = '#itemsGrandTotal';

    var MODAL_ID          = '#masterModal';
    var MODAL_BODY_ID     = '#masterModalBody';
    var MODAL_TITLE_ID    = '#masterModalTitle';

    /* =========================================================
       HELPERS
       ========================================================= */
    function formatMoney(n) {
        n = parseFloat(n) || 0;
        return n.toLocaleString('en-IN', {
            maximumFractionDigits: 2,
            minimumFractionDigits: 0
        });
    }
    function num(v) {
        var n = parseFloat(v);
        return isNaN(n) ? 0 : n;
    }
    function escHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    /* =========================================================
       1) DATALIST SYNC
       ---------------------------------------------------------
       For each <input list="..."> with a data-target="hiddenId",
       look up the matching <option data-id="N"> when the input
       value changes, and put N into the hidden input.
       If the typed text doesn't match any option, clear the
       hidden input (treats as no selection).
       ========================================================= */

    function syncDatalistInput($input) {
        var targetId = $input.data('target');
        if (!targetId) return;

        var listId = $input.attr('list');
        if (!listId) return;

        var typed   = ($input.val() || '').trim();
        var $hidden = $('#' + targetId);

        if (typed === '') {
            $hidden.val('');
            return;
        }

        var matchedValue = null;

        $('#' + listId).find('option').each(function () {
            var $opt   = $(this);
            var optVal = ($opt.attr('value') || $opt.val() || '').trim();
            if (optVal === typed) {
                matchedValue = $opt.data('id');
                return false; // break
            }
        });

        if (matchedValue !== null && matchedValue !== undefined) {
            $hidden.val(matchedValue);
        } else {
            $hidden.val('');
        }
    }

    $(document).on('input change', '.js-datalist-input', function () {
        syncDatalistInput($(this));
    });

    // Safety net: sync all datalist inputs once just before submit
    $(document).on('submit', '#tripForm', function () {
        $('.js-datalist-input').each(function () {
            syncDatalistInput($(this));
        });
    });

    /* External helper used by the master modal to auto-select
       a newly created record: setDatalistValue('party', 42, 'Label') */
    function setDatalistValue(entity, id, label) {
        var map = {
            'party':  { input: '#party_input',  hidden: '#party_id',  list: '#partyList'  },
            'lorry':  { input: '#lorry_input',  hidden: '#lorry_id',  list: '#lorryList'  },
            'driver': { input: '#driver_input', hidden: '#driver_id', list: '#driverList' }
        };
        var cfg = map[entity];
        if (!cfg) return;

        // Add the option to the datalist if it's missing
        var $list = $(cfg.list);
        var exists = false;
        $list.find('option').each(function () {
            if ($(this).attr('value') === label) { exists = true; return false; }
        });
        if (!exists) {
            $list.append(
                '<option data-id="' + id + '" value="' + escHtml(label) + '"></option>'
            );
        }

        // Set the visible input text + the hidden id
        $(cfg.input).val(label);
        $(cfg.hidden).val(id);
    }

    /* =========================================================
       2) ITEM ROWS
       ========================================================= */
    function buildItemRowHTML(index) {
        return '' +
            '<tr class="item-row">' +
                '<td class="align-middle">' +
                    '<div class="position-relative">' +
                        '<input type="text" ' +
                            'class="form-control form-control-sm item-name-input" ' +
                            'name="items[' + index + '][item_name]" ' +
                            'placeholder="Enter item name" ' +
                            'autocomplete="off" ' +
                            'list="" ' +
                            'maxlength="100" required>' +
                        '<input type="hidden" class="item-id-input" ' +
                            'name="items[' + index + '][item_id]" value="">' +
                        '<div class="item-suggest list-group shadow-sm d-none"></div>' +
                    '</div>' +
                '</td>' +
                '<td class="align-middle">' +
                    '<input type="text" ' +
                        'class="form-control form-control-sm item-unit-input" ' +
                        'name="items[' + index + '][unit]" ' +
                        'placeholder="Unit" maxlength="20">' +
                '</td>' +
                '<td class="align-middle">' +
                    '<input type="number" ' +
                        'class="form-control form-control-sm item-qty-input text-right" ' +
                        'name="items[' + index + '][quantity]" ' +
                        'placeholder="0" step="0.001" min="0">' +
                '</td>' +
                '<td class="align-middle">' +
                    '<input type="number" ' +
                        'class="form-control form-control-sm item-rate-input text-right" ' +
                        'name="items[' + index + '][rate]" ' +
                        'placeholder="0.00" step="0.01" min="0">' +
                '</td>' +
                '<td class="align-middle text-right">' +
                    '<span class="item-amount-display font-weight-bold">0</span>' +
                    '<input type="hidden" class="item-amount-input" ' +
                        'name="items[' + index + '][amount]" value="0">' +
                '</td>' +
                '<td class="align-middle text-center">' +
                    '<button type="button" ' +
                        'class="btn btn-sm btn-outline-danger js-remove-item">' +
                        '<i class="fas fa-times"></i>' +
                    '</button>' +
                '</td>' +
            '</tr>';
    }

    function reindexItemRows() {
        $(ITEM_ROWS_WRAPPER).find(ITEM_ROW_CLASS).each(function (i) {
            $(this).find('input').each(function () {
                var name = $(this).attr('name');
                if (!name) return;
                $(this).attr('name', name.replace(/items\[\d+\]/, 'items[' + i + ']'));
            });
        });
    }

    function addItemRow() {
        var index = $(ITEM_ROWS_WRAPPER).find(ITEM_ROW_CLASS).length;
        $(ITEM_ROWS_WRAPPER).append(buildItemRowHTML(index));
        $(ITEM_ROWS_WRAPPER).find(ITEM_ROW_CLASS).last()
            .find('.item-name-input').focus();
    }

    $(document).on('click', ADD_ITEM_BTN, function (e) {
        e.preventDefault();
        addItemRow();
    });

    $(document).on('click', '.js-remove-item', function (e) {
        e.preventDefault();
        var $rows = $(ITEM_ROWS_WRAPPER).find(ITEM_ROW_CLASS);
        if ($rows.length === 1) {
            // Keep at least one row — just clear it
            var $row = $rows.first();
            $row.find('input').val('');
            $row.find('.item-amount-display').text('0');
            recalcTotals();
            return;
        }
        $(this).closest(ITEM_ROW_CLASS).remove();
        reindexItemRows();
        recalcTotals();
    });

    /* =========================================================
       3) AMOUNT CALCULATION
       ========================================================= */
    function recalcRow($row) {
        var qty  = num($row.find('.item-qty-input').val());
        var rate = num($row.find('.item-rate-input').val());
        var amt  = qty * rate;

        $row.find('.item-amount-display').text(formatMoney(amt));
        $row.find('.item-amount-input').val(amt.toFixed(2));
    }

    function recalcTotals() {
        var grand = 0;
        $(ITEM_ROWS_WRAPPER).find(ITEM_ROW_CLASS).each(function () {
            recalcRow($(this));
            grand += num($(this).find('.item-amount-input').val());
        });
        $(GRAND_TOTAL_ID).text(formatMoney(grand));
    }

    $(document).on('input', '.item-qty-input, .item-rate-input', function () {
        recalcRow($(this).closest(ITEM_ROW_CLASS));
        recalcTotals();
    });

    /* =========================================================
       4) ITEM AUTOCOMPLETE (via ajax/item_search.php)
       ========================================================= */
    var suggestTimer = null;

    function hideSuggest($row) {
        $row.find(AUTOSUGGEST_BOX).addClass('d-none').empty();
    }
    function hideAllSuggests() {
        $(ITEM_ROWS_WRAPPER).find(ITEM_ROW_CLASS).each(function () {
            hideSuggest($(this));
        });
    }
    function showSuggest($row, items) {
        var $box = $row.find(AUTOSUGGEST_BOX);
        if (!items || items.length === 0) { hideSuggest($row); return; }

        var html = '';
        for (var i = 0; i < items.length; i++) {
            var it = items[i];
            html += '<a href="#" class="list-group-item list-group-item-action py-1 px-2 js-pick-item" ' +
                    'data-id="' + parseInt(it.id, 10) + '" ' +
                    'data-name="' + escHtml(it.name) + '" ' +
                    'data-unit="' + escHtml(it.unit || '') + '">' +
                    '<div class="d-flex justify-content-between align-items-center">' +
                    '<span>' + escHtml(it.name) + '</span>' +
                    (it.unit ? '<small class="text-muted ml-2">' + escHtml(it.unit) + '</small>' : '') +
                    '</div></a>';
        }
        $box.html(html).removeClass('d-none');
    }

    $(document).on('input', '.item-name-input', function () {
        var $input = $(this);
        var $row   = $input.closest(ITEM_ROW_CLASS);
        var term   = $input.val().trim();

        // Reset the hidden item id as soon as the user types
        $row.find('.item-id-input').val('');

        clearTimeout(suggestTimer);

        if (term.length === 0) {
            hideSuggest($row);
            return;
        }

        suggestTimer = setTimeout(function () {
            $.ajax({
                url: 'ajax/item_search.php',
                type: 'GET',
                dataType: 'json',
                data: { q: term }
            })
            .done(function (res) {
                if (res && res.success && res.items && res.items.length) {
                    showSuggest($row, res.items);
                } else {
                    hideSuggest($row);
                }
            })
            .fail(function () { hideSuggest($row); });
        }, 180);
    });

    $(document).on('click', '.js-pick-item', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var $link = $(this);
        var $row  = $link.closest(ITEM_ROW_CLASS);

        $row.find('.item-name-input').val($link.data('name'));
        $row.find('.item-id-input').val($link.data('id'));

        var $unitInput = $row.find('.item-unit-input');
        if ($.trim($unitInput.val()) === '') {
            $unitInput.val($link.data('unit') || '');
        }

        hideSuggest($row);
    });

    // Click outside closes all suggestion boxes
    $(document).on('click', function (e) {
        if ($(e.target).closest('.item-name-input').length) return;
        if ($(e.target).closest(AUTOSUGGEST_BOX).length) return;
        hideAllSuggests();
    });

    // Esc hides the suggestion box for the focused row
    $(document).on('keydown', '.item-name-input', function (e) {
        if (e.key === 'Escape') {
            hideSuggest($(this).closest(ITEM_ROW_CLASS));
        }
    });

    /* =========================================================
       5) MASTER MODAL — AJAX-loaded form for party / lorry / driver
       ========================================================= */

    function setModalLoading() {
        $(MODAL_BODY_ID).html(
            '<div class="text-center py-5">' +
                '<div class="spinner-border text-primary mb-3"></div>' +
                '<div class="text-muted">Loading form...</div>' +
            '</div>'
        );
    }

    function openMasterModal(entity, titleText) {
        setModalLoading();
        $(MODAL_TITLE_ID).text(titleText || ('Add New ' + entity));
        $(MODAL_ID).modal({ backdrop: 'static', keyboard: false, show: true });

        $.ajax({
            url: 'ajax/master-form.php',
            type: 'GET',
            dataType: 'html',
            data: { entity: entity }
        })
        .done(function (html) {
            $(MODAL_BODY_ID).html(html);
            $(MODAL_ID).data('entity', entity);
            $(MODAL_BODY_ID).find('input:visible,select:visible').first().focus();
        })
        .fail(function () {
            $(MODAL_BODY_ID).html(
                '<div class="alert alert-danger m-3">Failed to load form.</div>'
            );
        });
    }

    function closeMasterModal() {
        $(MODAL_ID).modal('hide');
    }

    $(document).on('click', '.js-open-master', function (e) {
        e.preventDefault();
        var entity = $(this).data('entity');
        var title  = $(this).data('title') || ('Add New ' + entity);
        if (!entity) return;
        openMasterModal(entity, title);
    });

    $(document).on('submit', '#masterForm', function (e) {
        e.preventDefault();

        var $form   = $(this);
        var entity  = $(MODAL_ID).data('entity');
        var $btn    = $form.find('button[type="submit"]');
        var $errBox = $('#masterFormError');

        $errBox.addClass('d-none').html('');

        $btn.prop('disabled', true).html(
            '<span class="spinner-border spinner-border-sm mr-1"></span> Saving...'
        );

        $.ajax({
            url: 'ajax/save-master.php',
            type: 'POST',
            dataType: 'json',
            data: $form.serialize()
        })
        .done(function (res) {

            if (!res || !res.success) {
                $errBox
                    .removeClass('d-none')
                    .html(
                        '<div class="alert alert-danger mb-3">' +
                            '<i class="fas fa-exclamation-circle mr-1"></i>' +
                            escHtml(res && res.message ? res.message : 'Save failed.') +
                        '</div>'
                    );
                return;
            }

            setDatalistValue(entity, res.id, res.label);
            closeMasterModal();
            showTripFlash('success', 'New ' + entity + ' added and selected.');
        })
        .fail(function (xhr) {
            var msg = 'Server error.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            } else if (xhr.responseText) {
                msg = xhr.responseText;
            }
            $errBox
                .removeClass('d-none')
                .html(
                    '<div class="alert alert-danger mb-3">' +
                        '<i class="fas fa-exclamation-circle mr-1"></i>' +
                        escHtml(msg) +
                    '</div>'
                );
        })
        .always(function () {
            $btn.prop('disabled', false).html(
                '<i class="fas fa-check mr-1"></i> Save'
            );
        });
    });

    /* =========================================================
       6) VALIDATION — required datalist inputs
       ---------------------------------------------------------
       The `required` HTML attribute only checks that the input
       has text. It doesn't verify that the text matches a real
       option. So we add a JS check that fires on submit and
       looks at the hidden input's value.
       ========================================================= */

    $(document).on('submit', '#tripForm', function (e) {

        var invalid = false;

        $('.js-datalist-input[required]').each(function () {
            var $input   = $(this);
            var targetId = $input.data('target');
            var $hidden  = $('#' + targetId);

            if (!$hidden.val() || $hidden.val() === '0' || $hidden.val() === '') {
                $input.addClass('is-invalid');
                invalid = true;
            } else {
                $input.removeClass('is-invalid');
            }
        });

        if (invalid) {
            e.preventDefault();
            showTripFlash('warning', 'Please select valid Lorry and Driver from the list.');
            $('.js-datalist-input.is-invalid').first().focus();
        }
    });

    // Clear the is-invalid class as soon as the user picks a valid option
    $(document).on('input change', '.js-datalist-input', function () {
        var $input   = $(this);
        var targetId = $input.data('target');
        var $hidden  = $('#' + targetId);
        if ($hidden.val() && $hidden.val() !== '0') {
            $input.removeClass('is-invalid');
        }
    });

    /* =========================================================
       7) FLASH helper
       ========================================================= */
    function showTripFlash(type, msg) {
        var $wrap = $('#tripFlash');
        if (!$wrap.length) return;

        var icon = (type === 'warning')
            ? '<i class="fas fa-exclamation-triangle mr-1"></i>'
            : '<i class="fas fa-check-circle mr-1"></i>';

        var html =
            '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
                icon +
                escHtml(msg) +
                '<button type="button" class="close" data-dismiss="alert">' +
                    '<span>&times;</span>' +
                '</button>' +
            '</div>';

        $wrap.html(html);
        setTimeout(function () {
            $wrap.find('.alert').fadeOut(400, function () { $(this).remove(); });
        }, 6000);
    }

    /* =========================================================
       INIT
       ========================================================= */
    if ($(ITEM_ROWS_WRAPPER).find(ITEM_ROW_CLASS).length === 0) {
        addItemRow();
    }
    recalcTotals();

    /* Expose for debugging */
    window.tripJS = {
        addItemRow:      addItemRow,
        recalcTotals:    recalcTotals,
        openMasterModal: openMasterModal,
        setDatalistValue: setDatalistValue
    };

});