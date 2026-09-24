<?php
$role = getUserRole();
$currentPage = basename($_SERVER['PHP_SELF']);
$nav = function(string $page, string $icon, string $label, bool $show = true) use ($currentPage): string {
    if (!$show) return '';
    $active = $currentPage === $page ? 'active' : '';
    return '<a href="' . url('pages/' . $page) . '" class="nav-item ' . $active . '">
        <i class="fas ' . $icon . '"></i><span>' . $label . '</span></a>';
};
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-tree"></i>
        <span>Forest Management</span>
    </div>
    <nav class="sidebar-nav">
        <?= $nav('dashboard.php', 'fa-gauge-high', 'Dashboard') ?>
        <?= $nav('forest_map.php', 'fa-map-location-dot', 'Forest GIS Map', canViewAnalytics()) ?>
        <?= $nav('forest_info.php', 'fa-mountain-sun', 'Forest Information') ?>
        <?= $nav('animals.php', 'fa-paw', 'Animals') ?>
        <?= $nav('wildlife_analytics.php', 'fa-chart-line', 'Animal Population Analytics', canViewAnalytics()) ?>
        <?php if (canManageAnimals()): ?>
        <?= $nav('report_birth_death.php', 'fa-notes-medical', 'Report Birth & Death') ?>
        <?= $nav('qr_tracking.php', 'fa-qrcode', 'QR Tracking') ?>
        <?= $nav('update_animal.php', 'fa-plus-circle', 'Update Animal') ?>
        <?php endif; ?>
        <?= $nav('incidents.php', 'fa-triangle-exclamation', 'Incidents') ?>
        <?php if (canManageIncidents()): ?>
        <?= $nav('add_incident.php', 'fa-file-circle-plus', 'Add Incident') ?>
        <?= $nav('officer_workflow.php', 'fa-clipboard-list', 'Officer Workflow') ?>
        <?php endif; ?>
        <?= $nav('reports.php', 'fa-chart-pie', 'Analytical Reports') ?>
        <?= $nav('advanced_reports.php', 'fa-file-lines', 'Report Generator') ?>
        <?php if (isPublic()): ?>
        <?= $nav('report_incident.php', 'fa-camera', 'Report Incident') ?>
        <?php endif; ?>
        <?php if (canManageIncidents()): ?>
        <?= $nav('pending_reports.php', 'fa-clock', 'Pending Reports') ?>
        <?php endif; ?>
        <?php if (canManageUsers()): ?>
        <?= $nav('users.php', 'fa-users', 'Users List') ?>
        <?php endif; ?>
        <?= $nav('audit_log.php', 'fa-list-check', 'Logs', canManageIncidents()) ?>
        <hr class="nav-divider">
        <a href="<?= url('actions/logout.php') ?>" class="nav-item nav-logout">
            <i class="fas fa-right-from-bracket"></i><span>Logout</span>
        </a>
    </nav>
</aside>
