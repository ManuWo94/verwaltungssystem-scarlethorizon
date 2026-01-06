<?php 
// Benachrichtigungen laden (nur wenn Datei existiert)
$unreadCounts = [
    'public_notes' => 0,
    'task_assignments' => 0,
    'cases' => 0,
    'total' => 0
];

if (isset($_SESSION['user_id']) && file_exists(__DIR__ . '/notifications.php')) {
    require_once __DIR__ . '/notifications.php';
    
    try {
        $unreadCounts = [
            'public_notes' => countUnreadNotifications($_SESSION['user_id'], 'public_note_comment'),
            'task_assignments' => countUnreadNotifications($_SESSION['user_id'], 'task'),
            'cases' => countUnreadNotifications($_SESSION['user_id'], 'case'),
            'total' => countUnreadNotifications($_SESSION['user_id'])
        ];
    } catch (Exception $e) {
        // Fehler beim Laden ignorieren und Standardwerte verwenden
        error_log("Fehler beim Laden der Benachrichtigungen: " . $e->getMessage());
    }
}
?>

<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar collapse">
    <div class="sidebar-sticky pt-3">
        <!-- Hauptfunktionen -->
        <h6 class="sidebar-heading d-flex align-items-center px-3 mb-2 text-muted">
            <span>Hauptfunktionen</span>
        </h6>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'dashboard.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>dashboard.php">
                    <span data-feather="home"></span>
                    Übersicht
                    <?php if (!empty($unreadCounts['total']) && $unreadCounts['total'] > 0): ?>
                        <span class="badge badge-danger ml-2 notification-badge"><?php echo $unreadCounts['total']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/duty_log.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/duty_log.php">
                    <span data-feather="clock"></span>
                    Dienstprotokoll
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/calendar.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/calendar.php">
                    <span data-feather="calendar"></span>
                    Kalender
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/notes.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/notes.php">
                    <span data-feather="file-text"></span>
                    Notizen
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/public_notes.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/public_notes.php">
                    <span data-feather="message-square"></span>
                    Öffentliche Notizen
                    <?php if (!empty($unreadCounts['public_notes']) && $unreadCounts['public_notes'] > 0): ?>
                        <span class="badge badge-info ml-2 notification-badge" data-type="public_notes"><?php echo $unreadCounts['public_notes']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/todos.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/todos.php">
                    <span data-feather="check-square"></span>
                    Aufgabenliste
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/task_assignments.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/task_assignments.php">
                    <span data-feather="clipboard"></span>
                    Aufgabenverteilung
                    <?php if (!empty($unreadCounts['task_assignments']) && $unreadCounts['task_assignments'] > 0): ?>
                        <span class="badge badge-warning ml-2 notification-badge" data-type="task_assignments"><?php echo $unreadCounts['task_assignments']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>
        
        <!-- Aktenverwaltung -->
        <h6 class="sidebar-heading d-flex align-items-center px-3 mt-3 mb-2 text-muted">
            <span>Aktenverwaltung</span>
        </h6>
        <ul class="nav flex-column">
        <!-- Fallverwaltung Dropdown -->
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#fallverwaltungSubmenu" aria-expanded="false">
                    <span data-feather="folder"></span>
                    Fallverwaltung
                    <?php if (!empty($unreadCounts['cases']) && $unreadCounts['cases'] > 0): ?>
                        <span class="badge badge-primary ml-2 notification-badge" data-type="cases"><?php echo $unreadCounts['cases']; ?></span>
                    <?php endif; ?>
                    <span class="ml-auto" data-feather="chevron-down"></span>
                </a>
                <div class="collapse" id="fallverwaltungSubmenu">
                    <ul class="nav flex-column ml-3">
                        <li class="nav-item">
                            <a class="nav-link <?php echo getCurrentPage() == 'modules/cases.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/cases.php">
                                <span data-feather="alert-triangle"></span>
                                Strafakten
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo getCurrentPage() == 'modules/civil_cases.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/civil_cases.php">
                                <span data-feather="briefcase"></span>
                                Zivilakten
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/defendants.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/defendants.php">
                    <span data-feather="users"></span>
                    Angeklagte
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/indictments.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/indictments.php">
                    <span data-feather="file"></span>
                    Klageschriften
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/revisions.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/revisions.php">
                    <span data-feather="refresh-cw"></span>
                    Revisionen
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/files.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/files.php">
                    <span data-feather="file-text"></span>
                    Aktenschrank
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/templates.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/templates.php">
                    <span data-feather="copy"></span>
                    Vorlagen
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/warrants.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/warrants.php">
                    <span data-feather="clipboard"></span>
                    Haftbefehle
                </a>
            </li>
        </ul>
        
        <!-- Büroverwaltung -->
        <h6 class="sidebar-heading d-flex align-items-center px-3 mt-3 mb-2 text-muted">
            <span>Büroverwaltung</span>
        </h6>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/staff.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/staff.php">
                    <span data-feather="user"></span>
                    Personalverwaltung
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/trainings.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/trainings.php">
                    <span data-feather="book-open"></span>
                    Schulungen
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/vacation.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/vacation.php">
                    <span data-feather="sun"></span>
                    Urlaub
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/evidence.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/evidence.php">
                    <span data-feather="archive"></span>
                    Beschlagnahmungen
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/equipment.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/equipment.php">
                    <span data-feather="package"></span>
                    Ausrüstung
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/address_book.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/address_book.php">
                    <span data-feather="book"></span>
                    Adressbuch
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/justice_references.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/justice_references.php">
                    <span data-feather="briefcase"></span>
                    Justizreferenzen
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/business_licenses_new.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/business_licenses_new.php">
                    <span data-feather="file-text"></span>
                    Gewerbeschein
                </a>
            </li>
        </ul>
        
        <!-- Lizenzverwaltung -->
        <h6 class="sidebar-heading d-flex align-items-center px-3 mt-3 mb-2 text-muted">
            <span>Lizenzverwaltung</span>
        </h6>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/licenses.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/licenses.php">
                    <span data-feather="award"></span>
                    Lizenzen
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/license_archive.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/license_archive.php">
                    <span data-feather="archive"></span>
                    Lizenzarchiv
                </a>
            </li>
            <?php if (currentUserCan('admin', 'view') || $_SESSION['role'] === 'Administrator'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/license_categories.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/license_categories.php">
                    <span data-feather="settings"></span>
                    Kategorien
                </a>
            </li>
            <?php endif; ?>
        </ul>

        <?php if (currentUserCan('admin', 'view') || $_SESSION['role'] === 'Administrator'): ?>
        <!-- Administration -->
        <h6 class="sidebar-heading d-flex align-items-center px-3 mt-3 mb-2 text-muted">
            <span>Administration</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'admin/index.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>admin/index.php">
                    <span data-feather="settings"></span>
                    Admin-Übersicht
                </a>
            </li>
            <?php if (currentUserCan('users', 'view') || $_SESSION['role'] === 'Administrator'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'admin/users.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>admin/users.php">
                    <span data-feather="users"></span>
                    Benutzerverwaltung
                </a>
            </li>
            <?php endif; ?>
            <?php if (currentUserCan('roles', 'view') || $_SESSION['role'] === 'Administrator'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'admin/roles.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>admin/roles.php">
                    <span data-feather="award"></span>
                    Rollenverwaltung
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'admin/database.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>admin/database.php">
                    <span data-feather="database"></span>
                    Datenbank
                </a>
            </li>
            <?php if (currentUserCan('cases', 'delete') || $_SESSION['role'] === 'Administrator'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'admin/delete_cases_by_timeframe.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>admin/delete_cases_by_timeframe.php">
                    <span data-feather="trash-2"></span>
                    Akten löschen
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'admin/limitations.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>admin/limitations.php">
                    <span data-feather="clock"></span>
                    Verjährungsfristen
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'admin/themes.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>admin/themes.php">
                    <span data-feather="eye"></span>
                    Farbpaletten
                </a>
            </li>
        </ul>
        <?php endif; ?>

        <!-- Hilfe & Support -->
        <h6 class="sidebar-heading d-flex align-items-center px-3 mt-4 mb-2 text-muted">
            <span>Hilfe & Support</span>
        </h6>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/help.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/help.php">
                    <span data-feather="book-open"></span>
                    Hilfe & Anleitungen
                </a>
            </li>
            <?php if (currentUserCan('admin', 'view') || $_SESSION['role'] === 'Administrator'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/help_admin.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/help_admin.php">
                    <span data-feather="edit"></span>
                    Hilfe bearbeiten
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>
</nav>

<script>
// Preserve sidebar scroll position across page loads; also save before navigation clicks
document.addEventListener('DOMContentLoaded', function() {
    var storageKey = 'sidebarScrollTop';

    // Determine the actual scrollable sidebar element
    var sidebarNav = document.getElementById('sidebarMenu');
    if (!sidebarNav) return;
    var sticky = sidebarNav.querySelector('.sidebar-sticky');
    var scrollEl = sidebarNav;
    if (sticky && sticky.scrollHeight > sticky.clientHeight) {
        scrollEl = sticky;
    } else if (sidebarNav.scrollHeight <= sidebarNav.clientHeight && sticky) {
        // If nav itself isn't scrollable but sticky is, prefer sticky
        scrollEl = sticky;
    }

    // Sicher auf sessionStorage zugreifen (Tracking Prevention-kompatibel)
    try {
        var saved = sessionStorage.getItem(storageKey);
        if (saved !== null) {
            scrollEl.scrollTop = parseInt(saved, 10) || 0;
        }

        var saveScroll = function() {
            try {
                sessionStorage.setItem(storageKey, scrollEl.scrollTop);
            } catch (e) {
                // SessionStorage nicht verfügbar - ignorieren
            }
        };

        scrollEl.addEventListener('scroll', saveScroll);
        window.addEventListener('beforeunload', saveScroll);

        // Save immediately when a sidebar link is clicked (before navigation unloads)
        sidebarNav.addEventListener('click', function(ev) {
            var target = ev.target;
            if (target.tagName !== 'A') {
                target = target.closest('a');
            }
            if (target && target.matches('a.nav-link')) {
                saveScroll();
            }
        });
    } catch (e) {
        // SessionStorage nicht verfügbar - weiterhin funktionieren
        console.warn('SessionStorage nicht verfügbar:', e);
    }
});
</script>
