    </div><!-- .app-wrapper -->
    <script>const BASE_URL = '<?= BASE_URL ?>';</script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
    <script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
    <script src="<?= asset('js/theme.js') ?>"></script>
    <script src="<?= asset('js/common.js') ?>"></script>
    <script src="<?= asset('js/notifications.js') ?>"></script>
    <?php if (!empty($extraJs)): ?>
        <?php if (is_array($extraJs)): foreach ($extraJs as $js): ?>
        <script src="<?= sanitize($js) ?>"></script>
        <?php endforeach; else: ?>
        <script src="<?= sanitize($extraJs) ?>"></script>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>
