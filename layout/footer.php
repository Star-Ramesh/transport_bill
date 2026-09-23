<!-- Footer -->
<footer class="sticky-footer bg-white">

    <div class="container my-auto">

        <div class="copyright text-center my-auto">

            <span>
                Copyright &copy; <?= date('Y') ?> Billing Portal
            </span>

        </div>

    </div>

</footer>
<!-- End of Footer -->


<!-- Scroll to Top Button -->
<a class="scroll-to-top rounded" href="#page-top">
    <i class="fas fa-angle-up"></i>
</a>


<!-- Logout Modal -->
<div class="modal fade"
    id="logoutModal"
    tabindex="-1"
    role="dialog"
    aria-labelledby="logoutModalLabel"
    aria-hidden="true">

    <div class="modal-dialog" role="document">

        <div class="modal-content">

            <!-- Header -->
            <div class="modal-header">

                <h5 class="modal-title" id="logoutModalLabel">
                    Ready to Leave?
                </h5>

                <button class="close"
                    type="button"
                    data-dismiss="modal"
                    aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>

            </div>

            <!-- Body -->
            <div class="modal-body">
                Select "Logout" below if you are ready to end your current session.
            </div>

            <!-- Footer -->
            <div class="modal-footer">

                <button class="btn btn-secondary"
                    type="button"
                    data-dismiss="modal">
                    Cancel
                </button>

                <a class="btn btn-primary" href="logout.php">
                    Logout
                </a>

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

<!-- Main Theme JS -->
<script src="js/main.min.js"></script>


<!-- Sidebar auto-close on mobile -->
<script>
    $(function() {

        var isMobile = function() {
            return window.innerWidth < 768;
        };

        function closeSidebar() {
            $("body").addClass("sidebar-toggled");
            $(".sidebar").addClass("toggled");
            $(".sidebar .collapse").collapse('hide');
        }

        function openSidebar() {
            $("body").removeClass("sidebar-toggled");
            $(".sidebar").removeClass("toggled");
        }

        /* ---------------------------------------------
           1) On page load: force-close on mobile
           --------------------------------------------- */
        if (isMobile()) {
            closeSidebar();
        }

        /* ---------------------------------------------
           2) Tap a leaf link inside sidebar → close it
           --------------------------------------------- */
        $(document).on('click', '.sidebar .nav-link, .sidebar .collapse-item', function() {

            if (!isMobile()) return;

            // Skip parent submenu toggles
            if ($(this).attr('data-toggle') === 'collapse') return;
            if ($(this).attr('data-target')) return;

            closeSidebar();
        });

        /* ---------------------------------------------
           3) Tap outside sidebar → close it
           --------------------------------------------- */
        $(document).on('click', function(e) {

            if (!isMobile()) return;

            var sidebarIsOpen = !$('.sidebar').hasClass('toggled');
            if (!sidebarIsOpen) return;

            // If tap was inside the sidebar, ignore
            if ($(e.target).closest('.sidebar').length) return;

            // If tap was on the hamburger, ignore
            if ($(e.target).closest('#sidebarToggleTop').length) return;

            closeSidebar();
        });

        /* ---------------------------------------------
           4) Resize back to desktop → make sure visible
           --------------------------------------------- */
        $(window).on('resize', function() {
            if (!isMobile()) {
                openSidebar();
            }
        });

    });
</script>