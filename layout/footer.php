<!-- Footer -->
<footer class="sticky-footer bg-white">
    <div class="container my-auto">
        <div class="copyright text-center my-auto">
            <span>Copyright &copy; <?= date('Y') ?> Billing Portal</span>
        </div>
    </div>
</footer>

<!-- Scroll to Top -->
<a class="scroll-to-top rounded" href="#page-top">
    <i class="fas fa-angle-up"></i>
</a>

<!-- Logout Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" role="dialog"
    aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="logoutModalLabel">Ready to Leave?</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                Select "Logout" below if you are ready to end your current session.
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                <a class="btn btn-primary" href="logout.php">Logout</a>
            </div>
        </div>
    </div>
</div>

<!-- jQuery -->
<script src="vendor/jquery/jquery.min.js"></script>

<!-- Bootstrap -->
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- jQuery Easing -->
<script src="vendor/jquery-easing/jquery.easing.min.js"></script>

<!-- SB Admin 2 Theme JS — includes the sidebar toggle behaviour -->
<script src="js/main.min.js"></script>

<!-- =========================================================
     MOBILE SIDEBAR — start closed on every page load
     ---------------------------------------------------------
     On mobile/tablet (< 768px):
       1. Slide the drawer out of view
       2. Close every submenu — including the one for the
          current page (sidebar.php sets .show by default)

     Desktop (>= 768px) is untouched — SB Admin 2's normal
     behaviour applies.
     ========================================================= -->
<script>
    $(function() {
        if (window.innerWidth < 768) {
            $('body').addClass('sidebar-toggled');
            $('.sidebar').addClass('toggled');
            $('.sidebar .collapse').removeClass('show').collapse('hide');
        }
    });
</script>

<!-- =========================================================
     PERMISSION SYNC
     ========================================================= -->
<script>
    (function() {
        var CHECK_URL = 'ajax/check-permissions.php';
        var CHECK_TIMEOUT = 3000;
        var lastCheck = 0;
        var lastResult = null;
        var CACHE_MS = 2000;

        function checkPermissions() {
            var now = Date.now();
            if (lastResult && (now - lastCheck) < CACHE_MS) {
                return $.Deferred().resolve(lastResult).promise();
            }
            var dfd = $.Deferred();
            var t = setTimeout(function() {
                dfd.resolve({ changed: false });
            }, CHECK_TIMEOUT);
            $.ajax({
                    url: CHECK_URL,
                    type: 'GET',
                    dataType: 'json',
                    cache: false
                })
                .done(function(res) {
                    clearTimeout(t);
                    lastCheck = Date.now();
                    lastResult = res;
                    if (res && res.success) dfd.resolve(res);
                    else if (res && res.force_logout) dfd.resolve({ force_logout: true });
                    else dfd.resolve({ changed: false });
                })
                .fail(function() {
                    clearTimeout(t);
                    dfd.resolve({ changed: false });
                });
            return dfd.promise();
        }

        $(document).on('click', 'a', function(e) {
            var $a = $(this);
            if ($a.attr('target') === '_blank') return;
            if ($a.attr('download')) return;
            if ($a.data('no-perm-check')) return;
            if ($a.attr('data-toggle') === 'collapse') return;
            if ($a.attr('data-target')) return;
            var href = $a.attr('href');
            if (!href) return;
            if (href === '#' || href === '') return;
            if (href.charAt(0) === '#') return;
            if (href.indexOf('javascript:') === 0) return;
            if (href.indexOf('mailto:') === 0) return;
            if (href.indexOf('tel:') === 0) return;
            if (href.indexOf('logout.php') !== -1) return;
            if (href.indexOf('?') === -1 && href.indexOf('.php') === -1) return;

            e.preventDefault();
            checkPermissions().then(function(res) {
                if (res.force_logout) { window.location.href = 'login.php'; return; }
                if (res.changed) {
                window.location.href = href;
                return;
            }
        });

        $(document).on('submit', 'form', function(e) {
            var $form = $(this);
            if ($form.data('no-perm-check')) return;
            if ($form.data('perm-checked')) return;
            if ($form.attr('id') === 'expenseForm') return;
            if ($form.attr('id') === 'masterForm') return;

            e.preventDefault();
            checkPermissions().then(function(res) {
                if (res.force_logout) { window.location.href = 'login.php'; return; }
                if (res.changed) { window.location.reload(); return; }
                $form.data('perm-checked', true);
                $form.trigger('submit');
            });
        });

        window.addEventListener('pageshow', function(e) {
            if (e.persisted) {
                checkPermissions().then(function(res) {
                    if (res.force_logout) window.location.href = 'login.php';
                    else if (res.changed) window.location.reload();
                });
            }
        });
    })();
</script>