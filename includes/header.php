<?php
// Session-Konfiguration laden
require_once __DIR__ . '/session_config.php';

// Ensure the functions file is included
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// Polyfill für das inert-Attribut für bessere Barrierefreiheit

// Überprüfen, ob ein Force-Logout angefordert wurde (z.B. wenn ein Admin einen Benutzer deaktiviert hat)
if (isset($_SESSION['force_logout']) && $_SESSION['force_logout'] === true) {
    // Session-Variable zurücksetzen
    $_SESSION['force_logout'] = false;
    
    // Benutzer abmelden
    if (function_exists('logoutUser')) {
        logoutUser();
    } else {
        // Fallback, falls die Funktion nicht existiert
        session_unset();
        session_destroy();
    }
    
    // Zur Access Denied Seite mit dem Grund 'inactive' weiterleiten
    $basePath = getBasePath();
    header("Location: {$basePath}access_denied.php?reason=inactive");
    exit;
}

// Überprüfen, ob der angemeldete Benutzer noch aktiv ist
// Diese Funktion wird den Benutzer automatisch abmelden, wenn der Account inaktiv oder gelöscht wurde
if (function_exists('isLoggedInUserActive') && isset($_SESSION['user_id'])) {
    // Nur ausführen, wenn der Benutzer angemeldet ist und wir nicht auf der Login-Seite sind
    $currentScript = $_SERVER['SCRIPT_NAME'];
    if (strpos($currentScript, 'login.php') === false && 
        strpos($currentScript, 'logout.php') === false &&
        strpos($currentScript, 'access_denied.php') === false) {
        
        if (!isLoggedInUserActive()) {
            // Wenn der Benutzer inaktiv ist, leiten wir ihn zur Access Denied Seite weiter
            $basePath = getBasePath();
            header("Location: {$basePath}access_denied.php?reason=inactive");
            exit;
        }
    }
}

// Get current script path
$currentScript = $_SERVER['SCRIPT_NAME'];
$isAdminPage = strpos($currentScript, '/admin/') !== false;
$pageClass = $isAdminPage ? 'admin-page' : '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aktenverwaltungssystem - Department of Justice</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/feather-icons/4.28.0/feather.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="<?php echo getBasePath(); ?>assets/css/style.css">
    <link rel="stylesheet" href="<?php echo getBasePath(); ?>assets/css/accordion.css">
    
    <?php 
    // Lade das Theme Manager Skript und füge das aktuelle Theme CSS ein
    if (file_exists(__DIR__ . '/theme_manager.php')) {
        require_once __DIR__ . '/theme_manager.php';
        echo '<style id="custom-theme">' . generateThemeCSS() . '</style>';
    }
    ?>
    
    <!-- JavaScript Dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/wicg-inert@3.1.2/dist/inert.min.js"></script>
    
    <!-- CKEditor für Rich-Text-Bearbeitung -->
    <script src="https://cdn.ckeditor.com/4.16.2/standard/ckeditor.js"></script>
</head>
<body class="<?php echo $pageClass; ?>">
    <nav class="navbar navbar-dark fixed-top bg-dark flex-md-nowrap p-0 shadow">
        <a class="navbar-brand col-md-3 col-lg-2 mr-0 px-3 d-flex align-items-center" href="<?php echo getBasePath(); ?>dashboard.php">
            <img src="<?php echo getBasePath(); ?>assets/images/doj-logo-original.png" alt="Department of Justice Logo" class="mr-2" style="height: 40px; width: auto;">
            <span>Aktenverwaltungssystem</span>
        </a>
        <button class="navbar-toggler position-absolute d-md-none collapsed" type="button" data-toggle="collapse" data-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <ul class="navbar-nav px-3 ml-auto">
            <li class="nav-item text-nowrap d-flex align-items-center navbar-user">
                <!-- Theme Toggle-Button hier eingefügt und via JavaScript gesteuert -->
                <span class="text-light mr-3">
                    <span class="badge badge-pill badge-secondary"><?php echo htmlspecialchars($_SESSION['role'] ?? 'User'); ?></span>
                    <?php echo htmlspecialchars($_SESSION['username'] ?? 'Guest'); ?>
                </span>
                <a class="nav-link" href="<?php echo getBasePath(); ?>modules/profile.php">
                    <span data-feather="user"></span>
                </a>
                <a class="nav-link" href="<?php echo getBasePath(); ?>logout.php">Abmelden</a>
            </li>
        </ul>
    </nav>
