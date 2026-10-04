/* =========================================================
   js/lorry-form.js
   ---------------------------------------------------------
   - Radio toggle for Own vs 3rd Party
   - Supplier datalist — auto-fill owner + address
   - Keep supplier_id in sync (hidden field)
   ========================================================= */

$(function () {

    var SUPPLIERS = (window.LORRY_FORM_DATA && window.LORRY_FORM_DATA.suppliers) || [];

    /* Build a fast lookup by lowercase name */
    var SUPPLIERS_BY_NAME = {};
    (function () {
        for (var i = 0; i < SUPPLIERS.length; i++) {
            SUPPLIERS_BY_NAME[SUPPLIERS[i].name.toLowerCase()] = SUPPLIERS[i];
        }
    })();

    /* =========================================================
       OWNERSHIP RADIO TOGGLE
       ========================================================= */
    function applyOwnershipUI(val) {
        /* Update visual highlight on cards */
        $('.js-own-option').toggleClass('checked', String(val) === '0');
        $('.js-third-option').toggleClass('checked', String(val) === '1');

        /* Show/hide owner block */
        var $ownerBlock = $('.js-owner-block');
        if (String(val) === '1') {
            $ownerBlock.removeClass('d-none-by-own');
        } else {
            $ownerBlock.addClass('d-none-by-own');
        }
    }

    $(document).on('change', '.js-ownership-radio', function () {
        applyOwnershipUI($(this).val());
    });

    /* Apply on page load */
    (function () {
        var current = $('.js-ownership-radio:checked').val();
        if (current === undefined) current = '0';
        applyOwnershipUI(current);
    })();

    /* =========================================================
       SUPPLIER AUTO-FILL
       ========================================================= */
    function applySupplier(supplier) {
        if (!supplier) return;

        /* Set hidden supplier_id */
        $('#supplier_id').val(supplier.id || '');

        /* Auto-fill owner name if empty (or always — user wants auto-fill) */
        $('#owner_name').val(supplier.name || '');

        /* Auto-fill address */
        if (supplier.address) {
            $('#address').val(supplier.address);
        }
    }

    function clearSupplierLink() {
        $('#supplier_id').val('');
    }

    $(document).on('input change', '#supplier_name_input', function () {

        var typed = ($(this).val() || '').trim();

        if (typed === '') {
            clearSupplierLink();
            return;
        }

        var match = SUPPLIERS_BY_NAME[typed.toLowerCase()];

        if (match) {
            applySupplier(match);
        } else {
            /* Typed a new name → clear supplier_id so server will create a new one */
            clearSupplierLink();
        }
    });

    /* On submit — one last sync so nothing is stale */
    $(document).on('submit', '#lorryForm', function () {
        var typed = ($('#supplier_name_input').val() || '').trim();
        if (typed === '') {
            $('#supplier_id').val('');
            return;
        }
        var match = SUPPLIERS_BY_NAME[typed.toLowerCase()];
        if (match) {
            $('#supplier_id').val(match.id);
        } else {
            $('#supplier_id').val('');
        }
    });

});