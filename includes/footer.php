            </div><!-- /.admin-content -->
            
            <!-- Footer -->
            <footer class="admin-footer">
                <span><?= __('app.name') ?> &copy; <?= date('Y') ?> - <?= __('app.admin_panel') ?></span>
                <a href="<?= BASE_URL ?>/" target="_blank">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <?= __('app.view_public_site') ?>
                </a>
            </footer>
        </main><!-- /.admin-main -->
    </div><!-- /.admin-wrapper -->

    <!-- Bootstrap JS Bundle (includes Popper) - Local -->
    <script src="<?= ASSETS_URL ?>/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery - Local -->
    <script src="<?= ASSETS_URL ?>/vendor/jquery/jquery.min.js"></script>
    
    <!-- Admin JS -->
    <script src="<?= ASSETS_URL ?>/js/admin.js?v=<?= $version ?>"></script>
</body>
</html>
