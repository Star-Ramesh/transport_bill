<!-- Custom fonts for this template-->
<link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">

<link
    href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
    rel="stylesheet">

<!-- Custom styles for this template-->
<link href="css/main.min.css" rel="stylesheet">


<!-- =========================================================
     GLOBAL RESPONSIVE TWEAKS
     ========================================================= -->
<style>
    /* =========================================================
       FILTER PILLS — horizontal scroll on mobile
       ========================================================= */
    @media (max-width: 767.98px) {
        .nav-pills {
            flex-wrap: nowrap;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 4px;
        }

        .nav-pills .nav-item {
            flex-shrink: 0;
        }

        .nav-pills::-webkit-scrollbar {
            height: 4px;
        }

        .nav-pills::-webkit-scrollbar-thumb {
            background: #d1d3e2;
            border-radius: 4px;
        }
    }

    /* =========================================================
       TABLE ACTION BUTTONS — compact on mobile
       ========================================================= */
    @media (max-width: 575.98px) {
        .table .btn-sm {
            padding: .3rem .5rem;
            font-size: .8rem;
        }

        .table .btn-sm i {
            font-size: .85rem;
        }

        .table-actions {
            display: flex;
            flex-direction: column;
            gap: .25rem;
            align-items: center;
        }
    }

    /* =========================================================
       STICKY SAVE BAR — stack on mobile
       ========================================================= */
    @media (max-width: 767.98px) {
        .save-bar {
            padding: .75rem 1rem !important;
            flex-direction: column;
            gap: .5rem;
            text-align: center;
        }

        .save-bar>div {
            width: 100%;
        }

        .save-bar .btn {
            width: 100%;
            margin-bottom: .35rem;
        }

        .save-bar .btn:last-child {
            margin-bottom: 0;
        }
    }

    /* =========================================================
       PAGE HEADING — stack on mobile
       ========================================================= */
    @media (max-width: 767.98px) {
        .d-sm-flex.align-items-center.justify-content-between {
            flex-direction: column;
            align-items: flex-start !important;
        }

        .d-sm-flex.align-items-center.justify-content-between>a,
        .d-sm-flex.align-items-center.justify-content-between>div {
            margin-top: .75rem;
            width: 100%;
        }

        .d-sm-flex.align-items-center.justify-content-between>a {
            text-align: center;
        }
    }

    /* =========================================================
       DATATABLES CONTROLS — stack on mobile
       ========================================================= */
    @media (max-width: 767.98px) {

        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            text-align: left !important;
            margin-bottom: .5rem;
        }

        .dataTables_wrapper .dataTables_filter {
            width: 100%;
        }

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

    /* =========================================================
       CARD PADDING — tighter on mobile
       ========================================================= */
    @media (max-width: 575.98px) {
        .card-body {
            padding: 1rem;
        }

        .card-header {
            padding: .65rem 1rem;
        }
    }

    /* =========================================================
       TABLE TYPOGRAPHY — smaller on mobile
       ========================================================= */
    @media (max-width: 575.98px) {

        table.dataTable,
        .table {
            font-size: 0.85rem;
        }

        .table td,
        .table th {
            padding: .55rem .5rem;
        }
    }

    /* =========================================================
       SIDEBAR — fixed & independently scrollable on desktop
       ========================================================= */
    @media (min-width: 768px) {
        .sidebar {
            height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1030;
            transition: all .2s ease;
        }

        #content-wrapper {
            margin-left: 14rem;
            transition: margin-left .2s ease;
        }

        body.sidebar-toggled #content-wrapper {
            margin-left: 6.5rem;
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.25);
            border-radius: 3px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.4);
        }
    }

    /* =========================================================
       SIDEBAR — off-canvas slide-in on mobile
       ========================================================= */
    @media (max-width: 767.98px) {
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 14rem;
            z-index: 1050;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            transform: translate3d(0, 0, 0);
            transition: transform 0.25s cubic-bezier(0.4, 0.0, 0.2, 1);
            will-change: transform;
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
        }

        .sidebar.toggled {
            transform: translate3d(-100%, 0, 0);
        }

        /* Overlay — only active when sidebar is OPEN (not toggled) */
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1040;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.25s ease, visibility 0.25s ease;
            pointer-events: none;
        }

        body:not(.sidebar-toggled)::before {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }

        #content-wrapper {
            margin-left: 0;
        }
    }

    /* =========================================================
       SELECTS IN INPUT GROUPS — mobile fix
       ---------------------------------------------------------
       On small screens, stack the + New button below the
       select instead of trying to fit both on one row.
       This is the correct fix for the "dropdown goes off
       the screen" issue — no overflow hacks needed.
       ========================================================= */
    @media (max-width: 575.98px) {

        .input-group-tight {
            flex-wrap: wrap;
            width: 100%;
        }

        .input-group-tight>.form-control,
        .input-group-tight>select.form-control {
            flex: 1 1 100%;
            width: 100%;
            min-width: 0;
            border-radius: 0.35rem 0.35rem 0 0 !important;
        }

        .input-group-tight>.input-group-append {
            flex: 1 1 100%;
            width: 100%;
            margin-left: 0;
        }

        .input-group-tight>.input-group-append>.btn {
            width: 100%;
            border-radius: 0 0 0.35rem 0.35rem !important;
        }

        /* Native selects: keep text clipped inside the control */
        select.form-control {
            text-overflow: ellipsis;
            white-space: nowrap;
            overflow: hidden;
        }
    }

    @media (max-width: 575.98px) {
        select.form-control {
            max-width: 100%;
            width: 100%;
        }

        /* Prevent the mobile browser from sizing the popup to the widest option */
        .card-body select.form-control,
        .input-group-tight select.form-control {
            overflow: hidden;
            text-overflow: ellipsis;
        }
    }
</style>