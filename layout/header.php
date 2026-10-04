<?php
/* =========================================================
   layout/header.php
   Publishes session permission signature, loads global CSS.
   No custom sidebar CSS — SB Admin 2's main.min.css handles it.
   ========================================================= */

if (!isset($GLOBALS['__permSig'])) {
    $__perms = $_SESSION['permissions'] ?? [];
    sort($__perms);
    $__sigPayload = json_encode([
        'role_id'   => (int) ($_SESSION['role_id']   ?? 0),
        'branch_id' => (int) ($_SESSION['branch_id'] ?? 0),
        'perms'     => $__perms,
    ]);
    $GLOBALS['__permSig'] = md5($__sigPayload);
}
?>
<script>
    window.__PERM_SIG__ = <?= json_encode($GLOBALS['__permSig']) ?>;
</script>

<!-- Fonts -->
<link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
<link
    href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
    rel="stylesheet">

<!-- Theme CSS -->
<link href="css/main.min.css" rel="stylesheet">

<style>
    /* 
     * IMPORTANT MOBILE FIX: 
     * Removing transform and overflow from wrappers prevents native 
     * <datalist> popups from detaching and appearing at the bottom of the page. 
     */
    #wrapper,
    #content-wrapper,
    #content {
        transform: none !important;
        filter: none !important;
        perspective: none !important;
        overflow-x: visible !important; 
        overflow-y: visible !important; 
    }

    /* Kill horizontal scroll securely at the root */
    html, body {
        overflow-x: hidden;
        position: relative;
    }

    /* =========================================================
       DATALIST "SELECT" VISUALS
       ---------------------------------------------------------
       Forces datalist inputs to look like standard select boxes
       with a dropdown arrow.
       ========================================================= */
    input[list] {
        appearance: none;
        -webkit-appearance: none;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' width='4' height='5' viewBox='0 0 4 5'%3e%3cpath fill='%235a5c69' d='M2 0L0 2h4zm0 5L0 3h4z'/%3e%3c/svg%3e") !important;
        background-repeat: no-repeat !important;
        background-position: right .75rem center !important;
        background-size: 8px 10px !important;
        padding-right: 2rem !important;
        cursor: pointer;
    }

    /* Expand the invisible native trigger to cover the arrow area for touch */
    input[list]::-webkit-calendar-picker-indicator {
        opacity: 0 !important;
        display: block !important;
        cursor: pointer !important;
        width: 1.5rem !important;
        height: 100% !important;
    }

    /* =========================================================
       FIXED TOPBAR
       ========================================================= */

    /* Desktop */
    @media (min-width: 768px) {
        nav.topbar {
            position: fixed !important;
            top: 0 !important;
            left: 14rem !important;
            right: 0 !important;
            width: auto !important;
            height: 4.375rem !important;
            margin: 0 !important;
            z-index: 1030 !important;
        }

        #content-wrapper {
            padding-top: 3.25rem !important;
            margin-top: 3.25rem !important;
        }

        #content-wrapper > #content {
            padding-top: 0 !important;
            margin-top: 0 !important;
        }
    }

    /* Mobile */
    @media (max-width: 767.98px) {
        nav.topbar {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            width: 100% !important;
            height: 3.25rem !important;
            margin: 0 !important;
            z-index: 1030 !important;
        }

        nav.topbar .nav-item .nav-link {
            height: 3.25rem !important;
            padding: 0 0.75rem !important;
        }

        #content-wrapper {
            padding-top: 3.25rem !important;
            margin-top: 3.25rem !important;
        }

        #content-wrapper > #content {
            padding-top: 0 !important;
            margin-top: 0 !important;
        }

        #content > .container-fluid {
            padding-top: 0.75rem !important;
        }
    }

    /* Filter pills — horizontal scroll on mobile */
    @media (max-width: 767.98px) {
        .nav-pills {
            flex-wrap: nowrap;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 4px;
        }
        .nav-pills .nav-item { flex-shrink: 0; }
        .nav-pills::-webkit-scrollbar { height: 4px; }
        .nav-pills::-webkit-scrollbar-thumb {
            background: #d1d3e2;
            border-radius: 4px;
        }
    }

    /* Table actions — compact on mobile */
    @media (max-width: 575.98px) {
        .table .btn-sm { padding: .3rem .5rem; font-size: .8rem; }
        .table .btn-sm i { font-size: .85rem; }
        .table-actions {
            display: flex;
            flex-direction: column;
            gap: .25rem;
            align-items: center;
        }
    }

    /* Sticky save bar */
    @media (max-width: 767.98px) {
        .save-bar {
            padding: .75rem 1rem !important;
            flex-direction: column;
            gap: .5rem;
            text-align: center;
        }
        .save-bar > div { width: 100%; }
        .save-bar .btn {
            width: 100%;
            margin-bottom: .35rem;
        }
        .save-bar .btn:last-child { margin-bottom: 0; }
    }

    /* Page heading on mobile */
    @media (max-width: 767.98px) {
        .d-sm-flex.align-items-center.justify-content-between {
            flex-direction: column;
            align-items: flex-start !important;
        }
        .d-sm-flex.align-items-center.justify-content-between > a,
        .d-sm-flex.align-items-center.justify-content-between > div {
            margin-top: .75rem;
            width: 100%;
        }
        .d-sm-flex.align-items-center.justify-content-between > a {
            text-align: center;
        }
    }

    /* DataTables on mobile */
    @media (max-width: 767.98px) {
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            text-align: left !important;
            margin-bottom: .5rem;
        }
        .dataTables_wrapper .dataTables_filter { width: 100%; }
        .dataTables_wrapper .dataTables_filter input {
            width: 100%;
            margin-left: 0 !important;
        }
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            text-align: center !important;
            margin-top: .5rem;
        }
    }

    /* Card padding on mobile */
    @media (max-width: 575.98px) {
        .card-body { padding: 1rem; }
        .card-header { padding: .65rem 1rem; }
    }

    /* Table typography on mobile */
    @media (max-width: 575.98px) {
        table.dataTable, .table { font-size: 0.85rem; }
        .table td, .table th { padding: .55rem .5rem; }
    }

    /* =========================================================
       MODALS
       ========================================================= */

    /* Vertical center every modal, no matter what markup uses. */
    .modal-dialog {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: calc(100vh - 3.5rem);
        max-width: 60vw;
        width: 60vw;
        margin: 1.75rem auto;
    }

    .modal-dialog > .modal-content {
        margin: 0;
        width: 100%;
    }

    .modal-content {
        max-height: 85vh;
        overflow: hidden;
    }

    .modal-body {
        overflow-y: auto;
        max-height: calc(85vh - 130px);
    }

    .modal .modal-body > iframe {
        display: block;
        width: 100%;
        height: 65vh;
        min-height: 400px;
        border: 0;
        background: #fff;
    }

    @media (max-width: 575.98px) {
        .modal-dialog {
            max-width: 90vw !important;
            width: 90vw !important;
            margin: 1rem auto;
            min-height: calc(100vh - 2rem);
        }
        .modal .modal-body > iframe {
            height: 60vh;
            min-height: 300px;
        }
    }

    /* Input group tight — mobile stacking */
    @media (max-width: 575.98px) {
        .input-group-tight {
            flex-wrap: wrap;
            width: 100%;
        }
        .input-group-tight > .form-control,
        .input-group-tight > select.form-control {
            flex: 1 1 100%;
            width: 100%;
            min-width: 0;
            border-radius: 0.35rem 0.35rem 0 0 !important;
        }
        .input-group-tight > .input-group-append {
            flex: 1 1 100%;
            width: 100%;
            margin-left: 0;
        }
        .input-group-tight > .input-group-append > .btn {
            width: 100%;
            border-radius: 0 0 0.35rem 0.35rem !important;
        }
        select.form-control {
            text-overflow: ellipsis;
            white-space: nowrap;
            overflow: hidden;
        }
    }
</style>