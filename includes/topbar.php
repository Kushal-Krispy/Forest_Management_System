<div class="topbar">
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle menu">
        <i class="fas fa-bars"></i>
    </button>
    <h1 class="topbar-title"><?= sanitize($pageTitle ?? 'Dashboard') ?></h1>
    <div class="topbar-actions">
        <div class="notification-dropdown dropdown">
            <button class="notification-btn" id="notificationBtn" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                <i class="fas fa-bell"></i>
                <span class="notification-badge" id="notificationCount" style="display:none;">0</span>
            </button>
            <div class="dropdown-menu dropdown-menu-end notification-menu" id="notificationMenu">
                <div class="notification-header">
                    <strong>Notifications</strong>
                    <button class="btn btn-link btn-sm" id="markAllRead" style="display:none;">Mark all read</button>
                </div>
                <div id="notificationList" class="notification-list">
                    <div class="notification-empty">Loading...</div>
                </div>
            </div>
        </div>
        <button class="theme-toggle" id="themeToggle" title="Switch dark/light theme">
            <i class="fas fa-moon" id="themeIcon"></i>
        </button>
        <div class="user-badge">
            <span class="user-name"><?= sanitize($currentUser) ?></span>
            <span class="user-role role-<?= sanitize($role) ?>"><?= sanitize($currentRole) ?></span>
        </div>
    </div>
</div>
