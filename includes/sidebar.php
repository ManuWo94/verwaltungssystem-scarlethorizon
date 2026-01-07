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

// Bestimme welcher Sidebar-Bereich aufgeklappt sein soll
$currentPage = getCurrentPage();
$hauptPages = ['modules/duty_log.php', 'modules/calendar.php', 'modules/notes.php', 'modules/public_notes.php', 'modules/todos.php', 'modules/task_assignments.php'];
$aktenPages = ['modules/cases.php', 'modules/case_edit.php', 'modules/case_view.php', 'modules/civil_cases.php', 'modules/civil_case_edit.php', 'modules/civil_case_view.php', 'modules/defendants.php', 'modules/indictments.php', 'modules/enter_verdict.php', 'modules/revisions.php', 'modules/files.php', 'modules/templates.php', 'modules/warrants.php'];
$bueroPages = ['modules/staff.php', 'modules/trainings.php', 'modules/vacation.php', 'modules/evidence.php', 'modules/equipment.php', 'modules/address_book.php', 'modules/justice_references.php', 'modules/business_licenses_new.php'];
$lizenzPages = ['modules/licenses.php', 'modules/license_archive.php', 'modules/license_categories.php'];
$adminPages = ['admin/index.php', 'admin/users.php', 'admin/roles.php', 'admin/database.php', 'admin/delete_cases_by_timeframe.php', 'admin/limitations.php', 'admin/themes.php'];
$hilfePages = ['modules/help.php', 'modules/help_admin.php'];

$openHaupt = in_array($currentPage, $hauptPages);
$openAkten = in_array($currentPage, $aktenPages);
$openBuero = in_array($currentPage, $bueroPages);
$openLizenz = in_array($currentPage, $lizenzPages);
$openAdmin = in_array($currentPage, $adminPages);
$openHilfe = in_array($currentPage, $hilfePages);
?>

<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar collapse">
    <div class="sidebar-sticky pt-3">
        <!-- Dashboard -->
        <ul class="nav flex-column mb-3">
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'dashboard.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>dashboard.php">
                    <span data-feather="home"></span>
                    <strong>Übersicht</strong>
                    <?php if (!empty($unreadCounts['total']) && $unreadCounts['total'] > 0): ?>
                        <span class="badge badge-danger ml-2 notification-badge"><?php echo $unreadCounts['total']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>

        <!-- Hauptfunktionen - Collapsible -->
        <div class="sidebar-section">
            <a class="sidebar-heading d-flex align-items-center px-3 mb-1 <?php echo $openHaupt ? '' : 'collapsed'; ?>" data-toggle="collapse" href="#hauptMenu" role="button">
                <span data-feather="zap" class="mr-2"></span>
                <span class="flex-grow-1">Hauptfunktionen</span>
                <span data-feather="chevron-down" class="toggle-icon"></span>
            </a>
            <div class="collapse <?php echo $openHaupt ? 'show' : ''; ?>" id="hauptMenu" data-parent="#sidebarMenu">
                <ul class="nav flex-column">
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
                                <span class="badge badge-info ml-2 notification-badge"><?php echo $unreadCounts['public_notes']; ?></span>
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
                                <span class="badge badge-warning ml-2 notification-badge"><?php echo $unreadCounts['task_assignments']; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Aktenverwaltung - Collapsible -->
        <div class="sidebar-section">
            <a class="sidebar-heading d-flex align-items-center px-3 mb-1 <?php echo $openAkten ? '' : 'collapsed'; ?>" data-toggle="collapse" href="#aktenMenu" role="button">
                <span data-feather="folder" class="mr-2"></span>
                <span class="flex-grow-1">Aktenverwaltung</span>
                <span data-feather="chevron-down" class="toggle-icon"></span>
            </a>
            <div class="collapse <?php echo $openAkten ? 'show' : ''; ?>" id="aktenMenu" data-parent="#sidebarMenu">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link <?php echo getCurrentPage() == 'modules/cases.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/cases.php">
                            <span data-feather="alert-triangle"></span>
                            Strafakten
                            <?php if (!empty($unreadCounts['cases']) && $unreadCounts['cases'] > 0): ?>
                                <span class="badge badge-primary ml-2 notification-badge"><?php echo $unreadCounts['cases']; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php if (currentUserCan('civil_cases', 'view')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo getCurrentPage() == 'modules/civil_cases.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/civil_cases.php">
                            <span data-feather="briefcase"></span>
                            Zivilakten
                        </a>
                    </li>
                    <?php endif; ?>
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
                    <?php if (currentUserCan('revisions', 'view')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo getCurrentPage() == 'modules/revisions.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/revisions.php">
                            <span data-feather="refresh-cw"></span>
                            Revisionen
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo getCurrentPage() == 'modules/files.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/files.php">
                            <span data-feather="archive"></span>
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
                            <span data-feather="shield"></span>
                            Haftbefehle
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Büroverwaltung - Collapsible -->
        <div class="sidebar-section">
            <a class="sidebar-heading d-flex align-items-center px-3 mb-1 <?php echo $openBuero ? '' : 'collapsed'; ?>" data-toggle="collapse" href="#bueroMenu" role="button">
                <span data-feather="grid" class="mr-2"></span>
                <span class="flex-grow-1">Büroverwaltung</span>
                <span data-feather="chevron-down" class="toggle-icon"></span>
            </a>
            <div class="collapse <?php echo $openBuero ? 'show' : ''; ?>" id="bueroMenu" data-parent="#sidebarMenu">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link <?php echo getCurrentPage() == 'modules/staff.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/staff.php">
                            <span data-feather="user"></span>
                            Personal
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
                            <span data-feather="package"></span>
                            Beschlagnahmungen
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo getCurrentPage() == 'modules/equipment.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/equipment.php">
                            <span data-feather="box"></span>
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
                            <span data-feather="bookmark"></span>
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
            </div>
        </div>
        
        <!-- Lizenzverwaltung - Collapsible -->
        <div class="sidebar-section">
            <a class="sidebar-heading d-flex align-items-center px-3 mb-1 <?php echo $openLizenz ? '' : 'collapsed'; ?>" data-toggle="collapse" href="#lizenzMenu" role="button">
                <span data-feather="award" class="mr-2"></span>
                <span class="flex-grow-1">Lizenzverwaltung</span>
                <span data-feather="chevron-down" class="toggle-icon"></span>
            </a>
            <div class="collapse <?php echo $openLizenz ? 'show' : ''; ?>" id="lizenzMenu" data-parent="#sidebarMenu">
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
                            Archiv
                        </a>
                    </li>
                    <?php if (currentUserCan('license_categories', 'view')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo getCurrentPage() == 'modules/license_categories.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/license_categories.php">
                            <span data-feather="settings"></span>
                            Kategorien
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <?php if (currentUserCan('admin', 'view') || $_SESSION['role'] === 'Administrator'): ?>
        <!-- Administration - Collapsible -->
        <div class="sidebar-section">
            <a class="sidebar-heading d-flex align-items-center px-3 mb-1 <?php echo $openAdmin ? '' : 'collapsed'; ?>" data-toggle="collapse" href="#adminMenu" role="button">
                <span data-feather="settings" class="mr-2"></span>
                <span class="flex-grow-1">Administration</span>
                <span data-feather="chevron-down" class="toggle-icon"></span>
            </a>
            <div class="collapse <?php echo $openAdmin ? 'show' : ''; ?>" id="adminMenu" data-parent="#sidebarMenu">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link <?php echo getCurrentPage() == 'admin/index.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>admin/index.php">
                            <span data-feather="sliders"></span>
                            Übersicht
                        </a>
                    </li>
                    <?php if (currentUserCan('users', 'view') || $_SESSION['role'] === 'Administrator'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo getCurrentPage() == 'admin/users.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>admin/users.php">
                            <span data-feather="users"></span>
                            Benutzer
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (currentUserCan('roles', 'view') || $_SESSION['role'] === 'Administrator'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo getCurrentPage() == 'admin/roles.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>admin/roles.php">
                            <span data-feather="shield"></span>
                            Rollen
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
                            <span data-feather="droplet"></span>
                            Farbpaletten
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <!-- Hilfe - Collapsible -->
        <div class="sidebar-section">
            <a class="sidebar-heading d-flex align-items-center px-3 mb-1 <?php echo $openHilfe ? '' : 'collapsed'; ?>" data-toggle="collapse" href="#hilfeMenu" role="button">
                <span data-feather="help-circle" class="mr-2"></span>
                <span class="flex-grow-1">Hilfe</span>
                <span data-feather="chevron-down" class="toggle-icon"></span>
            </a>
            <div class="collapse <?php echo $openHilfe ? 'show' : ''; ?>" id="hilfeMenu" data-parent="#sidebarMenu">
                <ul class="nav flex-column mb-4">
                    <li class="nav-item">
                        <a class="nav-link <?php echo getCurrentPage() == 'modules/help.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/help.php">
                            <span data-feather="book-open"></span>
                            Anleitungen
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
        </div>
    </div>
</nav>

<script>
// Sidebar-Initialisierung mit erweitertem Debugging
(function() {
    'use strict';
    
    console.log('[Sidebar] Initialisierung gestartet');
    
    document.addEventListener('DOMContentLoaded', function() {
        console.log('[Sidebar] DOM geladen');
        
        var sidebarNav = document.getElementById('sidebarMenu');
        if (!sidebarNav) {
            console.error('[Sidebar] Element #sidebarMenu nicht gefunden!');
            return;
        }
        
        console.log('[Sidebar] Sidebar-Element gefunden');
        
        // Stelle sicher, dass das korrekte Accordion-Panel offen bleibt
        // Die PHP-Seite setzt bereits die 'show'-Klasse basierend auf der aktuellen Seite
        try {
            var collapseElements = document.querySelectorAll('.sidebar-section .collapse');
            console.log('[Sidebar] Gefundene Collapse-Elemente:', collapseElements.length);
            
            var openPanels = [];
            
            // Finde das Element, das die 'show'-Klasse hat (von PHP gesetzt)
            collapseElements.forEach(function(el) {
                if (el.classList.contains('show')) {
                    openPanels.push(el.id);
                    console.log('[Sidebar] Panel geöffnet:', el.id);
                    
                    // Stelle sicher, dass der zugehörige Toggle-Button nicht 'collapsed' ist
                    var toggleBtn = document.querySelector('[href="#' + el.id + '"]');
                    if (toggleBtn) {
                        toggleBtn.classList.remove('collapsed');
                        toggleBtn.setAttribute('aria-expanded', 'true');
                        console.log('[Sidebar] Toggle-Button für', el.id, 'aktualisiert');
                    } else {
                        console.warn('[Sidebar] Kein Toggle-Button für', el.id, 'gefunden');
                    }
                }
            });
            
            console.log('[Sidebar] Initialisierung erfolgreich. Offene Panels:', openPanels.join(', '));
            
        } catch (e) {
            console.error('[Sidebar] Fehler bei Initialisierung:', e);
        }
    });
    
    // Debugging: Zeige an, wenn Sidebar-Elemente geklickt werden
    document.addEventListener('click', function(e) {
        var target = e.target.closest('[data-toggle="collapse"]');
        if (target) {
            var targetId = target.getAttribute('href');
            console.log('[Sidebar] Collapse-Toggle geklickt:', targetId);
        }
    });
    
})();
</script>
