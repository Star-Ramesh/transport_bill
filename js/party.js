$(function () {

    /* =========================================================
       Helper: show a Bootstrap alert in #gstAlert
       ========================================================= */
    function showAlert(type, message) {

        $('#gstAlert').html(
            '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
                message +
                '<button type="button" class="close" data-dismiss="alert">' +
                    '<span>&times;</span>' +
                '</button>' +
            '</div>'
        );

        $('html, body').animate({
            scrollTop: $('#gstAlert').offset().top - 100
        }, 300);
    }

    /* =========================================================
       Helper: toggle fetch button loading state
       ========================================================= */
    function setLoading(loading) {

        $('#fetchGst').prop('disabled', loading);

        if (loading) {
            $('#fetchText').addClass('d-none');
            $('#fetchLoading').removeClass('d-none');
        } else {
            $('#fetchText').removeClass('d-none');
            $('#fetchLoading').addClass('d-none');
        }
    }

    /* =========================================================
       Lock / Unlock GSTIN — belt and braces
       ========================================================= */
    function lockGstin() {

        var $g = $('#gstin');

        $g.attr('readonly', 'readonly');
        $g.prop('readonly', true);
        $g.addClass('bg-light');
        $g.css('pointer-events', 'none');

        $('#fetchGst').addClass('d-none');
        $('#changeGst').removeClass('d-none');
        $('#gstHelpText').text('GSTIN is locked. Click "Change GSTIN" to edit.');

        console.log('[lockGstin] readonly =', $g.prop('readonly'));
    }

    function unlockGstin() {

        var $g = $('#gstin');

        $g.removeAttr('readonly');
        $g.prop('readonly', false);
        $g.removeClass('bg-light');
        $g.css('pointer-events', '');

        $('#fetchGst').removeClass('d-none');
        $('#changeGst').addClass('d-none');
        $('#gstHelpText').text('Enter GSTIN to automatically fill the registered party details.');

        console.log('[unlockGstin] readonly =', $g.prop('readonly'));
    }

    /* =========================================================
       GSTIN input — force uppercase, strip spaces
       ========================================================= */
    $(document).on('input', '#gstin', function () {
        this.value = this.value.toUpperCase().replace(/\s/g, '');
    });

    /* =========================================================
       Fetch GST details
       ========================================================= */
    $(document).on('click', '#fetchGst', function () {

        var gstin = $('#gstin').val().trim();

        if (!gstin) {
            showAlert('warning', 'Please enter GSTIN.');
            $('#gstin').focus();
            return;
        }

        if (!/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/.test(gstin)) {
            showAlert('warning', 'Please enter a valid 15-character GSTIN.');
            $('#gstin').focus();
            return;
        }

        setLoading(true);
        $('#gstResult').addClass('d-none');

        $.ajax({
            url: 'ajax/gst_lookup.php',
            type: 'GET',
            dataType: 'json',
            data: { gstin: gstin }
        })
        .done(function (response) {

            console.log('[fetchGst] response:', response);

            if (!response.success) {
                showAlert('danger', response.message || 'GST lookup failed.');
                return;
            }

            var data = response.data;

            $('#legal_name').val(data.legal_name || '');
            $('#trade_name').val(data.trade_name || '');
            $('#address').val(data.address || '');
            $('#city').val(data.city || '');
            $('#state').val(data.state || '');
            $('#pincode').val(data.pincode || '');
            $('#gst_status').val(data.status || '');

            $('#resultGstin').text(data.gstin || gstin);
            $('#resultType').text(data.taxpayer_type || '-');

            $('#resultStatus')
                .text(data.status || '-')
                .removeClass('badge-success badge-danger badge-warning')
                .addClass(data.status === 'Active' ? 'badge-success' : 'badge-warning');

            $('#gstResult').removeClass('d-none');

            // Lock the GSTIN after successful fetch
            lockGstin();

            var creditText = '';
            if (response.credits_remaining !== null && response.credits_remaining !== undefined) {
                creditText = ' | Credits remaining: ' + response.credits_remaining;
            }

            showAlert('success', 'GST details fetched successfully.' + creditText);

        })
        .fail(function () {
            showAlert('danger', 'Unable to communicate with the GST lookup service.');
        })
        .always(function () {
            setLoading(false);
        });
    });

    /* =========================================================
       Change GSTIN — unlock the field
       ========================================================= */
    $(document).on('click', '#changeGst', function () {

        unlockGstin();

        $('#gstin').val('').focus();

        $('#gstResult').addClass('d-none');
        $('#gst_status').val('');
        $('#gstAlert').empty();
    });

    /* =========================================================
       "Party has no GSTIN" checkbox
       ========================================================= */
    $(document).on('change', '#no_gst', function () {

        var noGst = $(this).is(':checked');

        $('#gstin').prop('disabled', noGst);
        $('#fetchGst').prop('disabled', noGst);

        if (noGst) {

            unlockGstin();

            $('#gstin').val('');
            $('#gstResult').addClass('d-none');
            $('#gst_status').val('');
            showAlert('info', 'GSTIN is disabled. Enter the party details manually.');

        } else {
            $('#gstAlert').empty();
        }
    });

    /* =========================================================
       Clear form
       ========================================================= */
    $(document).on('click', '#clearParty', function () {

        $('#partyForm')[0].reset();

        unlockGstin();

        $('#gstin').prop('disabled', false);
        $('#no_gst').prop('checked', false);

        $('#gstResult').addClass('d-none');
        $('#gst_status').val('');
        $('#gstAlert').empty();

        $('.form-control').removeClass('is-invalid is-valid');
    });

    /* =========================================================
       Client-side validation (server still validates)
       ========================================================= */
    $(document).on('submit', '#partyForm', function (e) {

        $('.form-control').removeClass('is-invalid');
        var valid = true;

        if (!$('#legal_name').val().trim()) {
            $('#legal_name').addClass('is-invalid');
            valid = false;
        }

        var gstin = $('#gstin').val().trim();
        if (gstin !== '' && !/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/.test(gstin)) {
            $('#gstin').addClass('is-invalid');
            valid = false;
        }

        var email = $('#email').val().trim();
        if (email !== '' && !/^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i.test(email)) {
            $('#email').addClass('is-invalid');
            valid = false;
        }

        var pincode = $('#pincode').val().trim();
        if (pincode !== '' && !/^[0-9]{6}$/.test(pincode)) {
            $('#pincode').addClass('is-invalid');
            valid = false;
        }

        if (!valid) {
            e.preventDefault();
            showAlert('warning', 'Please fix the highlighted fields.');
            $('.is-invalid').first().focus();
        }
    });

});