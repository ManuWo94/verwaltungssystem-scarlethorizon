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
                </a>
            </li>
            <?php if (currentUserCan('duty_log', 'view')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/duty_log.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/duty_log.php">
                    <span data-feather="clock"></span>
                    Dienstprotokoll
                </a>
            </li>
            <?php endif; ?>
            <?php if (currentUserCan('calendar', 'view')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/calendar.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/calendar.php">
                    <span data-feather="calendar"></span>
                    Kalender
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/notes.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/notes.php">
                    <span data-feather="file-text"></span>
                    Notizen
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
                </a>
            </li>
        </ul>
        
        <!-- Aktenverwaltung -->
        <h6 class="sidebar-heading d-flex align-items-center px-3 mt-3 mb-2 text-muted">
            <span>Aktenverwaltung</span>
        </h6>
        <ul class="nav flex-column">
            <?php if (currentUserCan('cases', 'view')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/cases.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/cases.php">
                    <span data-feather="folder"></span>
                    Fallverwaltung
                </a>
            </li>
            <?php endif; ?>
            <?php if (currentUserCan('defendants', 'view')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/defendants.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/defendants.php">
                    <span data-feather="users"></span>
                    Angeklagte
                </a>
            </li>
            <?php endif; ?>
            <?php if (currentUserCan('indictments', 'view')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/indictments.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/indictments.php">
                    <span data-feather="file"></span>
                    Klageschriften
                </a>
            </li>
            <?php endif; ?>
            <?php if (currentUserCan('appeals', 'view')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/revisions.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/revisions.php">
                    <span data-feather="refresh-cw"></span>
                    Revisionen
                </a>
            </li>
            <?php endif; ?>
            <?php if (currentUserCan('files', 'view')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/files.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/files.php">
                    <span data-feather="file-text"></span>
                    Aktenschrank
                </a>
            </li>
            <?php endif; ?>
            <?php if (currentUserCan('templates', 'view')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/templates.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/templates.php">
                    <span data-feather="copy"></span>
                    Vorlagen
                </a>
            </li>
            <?php endif; ?>
            <?php if (currentUserCan('warrants', 'view')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/warrants.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/warrants.php">
                    <span data-feather="clipboard"></span>
                    Haftbefehle
                </a>
            </li>
            <?php endif; ?>
        </ul>
        
        <!-- Büroverwaltung -->
        <h6 class="sidebar-heading d-flex align-items-center px-3 mt-3 mb-2 text-muted">
            <span>Büroverwaltung</span>
        </h6>
        <ul class="nav flex-column">
            <?php if (currentUserCan('staff', 'view')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/staff.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/staff.php">
                    <span data-feather="user"></span>
                    Personalverwaltung
                </a>
            </li>
            <?php endif; ?>
            <?php if (currentUserCan('trainings', 'view')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/trainings.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/trainings.php">
                    <span data-feather="book-open"></span>
                    Schulungen
                </a>
            </li>
            <?php endif; ?>
            <?php if (currentUserCan('vacation', 'view')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/vacation.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/vacation.php">
                    <span data-feather="sun"></span>
                    Urlaub
                </a>
            </li>
            <?php endif; ?>
            <?php if (currentUserCan('seized_assets', 'view')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/evidence.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/evidence.php">
                    <span data-feather="archive"></span>
                    Beschlagnahmungen
                </a>
            </li>
            <?php endif; ?>
            <?php if (currentUserCan('equipment', 'view')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/equipment.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/equipment.php">
                    <span data-feather="package"></span>
                    Ausrüstung
                </a>
            </li>
            <?php endif; ?>
            <?php if (currentUserCan('address_book', 'view')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/address_book.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/address_book.php">
                    <span data-feather="book"></span>
                    Adressbuch
                </a>
            </li>
            <?php endif; ?>

            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/justice_references.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/justice_references.php">
                    <span data-feather="briefcase"></span>
                    Justizreferenzen
                </a>
            </li>
            
            <?php if (currentUserCan('business_licenses', 'view')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo getCurrentPage() == 'modules/business_licenses_new.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>modules/business_licenses_new.php">
                    <span data-feather="file-text"></span>
                    Gewerbeschein
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
                <a class="nav-link <?php echo getCurrentPage() == 'admin/themes.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>admin/themes.php">
                    <span data-feather="eye"></span>
                    Farbpaletten
                </a>
            </li>
        </ul>
        <?php endif; ?>
    </div>
</nav>
