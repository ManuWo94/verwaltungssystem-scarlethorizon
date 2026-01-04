<?php
/**
 * Navigationsmenü
 */

// Lade die Funktionen falls noch nicht geladen
if (!function_exists('isLoggedIn')) {
    require_once __DIR__ . '/../includes/functions.php';
}

// Bestimme die aktuelle Seite
$currentPage = basename($_SERVER['SCRIPT_NAME']);
?>

<ul class="navbar-nav me-auto">
    <?php if (isset($_SESSION['user_id'])): // Nur für eingeloggte Benutzer anzeigen ?>
    <li class="nav-item">
        <a class="nav-link <?php if ($currentPage === 'dashboard.php') echo 'active'; ?>" href="dashboard.php">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php if ($currentPage === 'fraktionen.php') echo 'active'; ?>" href="fraktionen.php">
            <i class="fas fa-users"></i> Fraktionen
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php if ($currentPage === 'items.php') echo 'active'; ?>" href="items.php">
            <i class="fas fa-cube"></i> Items
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php if ($currentPage === 'materialien.php') echo 'active'; ?>" href="materialien.php">
            <i class="fas fa-boxes"></i> Materialien
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php if ($currentPage === 'produktionsrouten.php') echo 'active'; ?>" href="produktionsrouten.php">
            <i class="fas fa-route"></i> Produktionsrouten
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php if ($currentPage === 'import-export.php') echo 'active'; ?>" href="import-export.php">
            <i class="fas fa-file-export"></i> Import/Export
        </a>
    </li>
    <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
    <li class="nav-item">
        <a class="nav-link <?php if ($currentPage === 'benutzer.php') echo 'active'; ?>" href="benutzer.php">
            <i class="fas fa-user-cog"></i> Benutzerverwaltung
        </a>
    </li>
    <?php endif; ?>
    <?php endif; ?>
</ul>

<?php if (isset($_SESSION['user_id'])): // Nur für eingeloggte Benutzer anzeigen ?>
<div class="navbar-nav ms-auto">
    <div class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" 
           data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-user-circle"></i> 
            <?php echo htmlspecialchars($_SESSION['username']); ?>
            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
            <span class="badge bg-danger">Admin</span>
            <?php elseif (isset($_SESSION['user_can_edit']) && $_SESSION['user_can_edit']): ?>
            <span class="badge bg-success">Editor</span>
            <?php else: ?>
            <span class="badge bg-secondary">Leser</span>
            <?php endif; ?>
        </a>
        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark" aria-labelledby="userDropdown">
            <li>
                <a class="dropdown-item" href="password_reset.php?change=1">
                    <i class="fas fa-key"></i> Passwort ändern
                </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <a class="dropdown-item" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> Abmelden
                </a>
            </li>
        </ul>
    </div>
</div>
<?php endif; ?>
