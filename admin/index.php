<?php
/**
 * Admin Dashboard
 * Main entry point for administrative functions
 */
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Debug-Informationen, um zu verstehen, warum Admin-Zugriff nicht funktioniert
$isAdmin = isAdminSession();
$sessionInfo = '';

// Erfasse Session-Informationen für Debugging
if (isset($_SESSION)) {
    $sessionInfo .= 'USER_ID: ' . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'nicht gesetzt') . "\n";
    $sessionInfo .= 'USERNAME: ' . (isset($_SESSION['username']) ? $_SESSION['username'] : 'nicht gesetzt') . "\n";
    $sessionInfo .= 'ROLE: ' . (isset($_SESSION['role']) ? $_SESSION['role'] : 'nicht gesetzt') . "\n";
    $sessionInfo .= 'ROLES: ' . (isset($_SESSION['roles']) ? implode(', ', $_SESSION['roles']) : 'nicht gesetzt') . "\n";
    $sessionInfo .= 'IS_ADMIN: ' . (isset($_SESSION['is_admin']) ? var_export($_SESSION['is_admin'], true) : 'nicht gesetzt') . "\n";
}

// In Log-Datei schreiben
error_log("Admin-Zugriff für admin/index.php: " . ($isAdmin ? 'ERLAUBT' : 'VERWEIGERT') . "\n" . $sessionInfo);

// Administratorzugriff erzwingen
if (!$isAdmin) {
    header('Location: ../dashboard.php?error=admin_access_denied');
    exit;
}

// Page title
$pageTitle = "Administrationsbereich";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Administrationsbereich</h1>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Datenbankstatus</h5>
                            <?php if (isDatabaseConnected()): ?>
                                <div class="alert alert-success mb-3">
                                    <i class="bi bi-check-circle"></i> Datenbankverbindung aktiv. Daten werden in der PostgreSQL-Datenbank gespeichert.
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning mb-3">
                                    <i class="bi bi-exclamation-triangle"></i> Keine Datenbankverbindung. Daten werden in JSON-Dateien gespeichert.
                                </div>
                            <?php endif; ?>
                            <p class="card-text">
                                Das System verwendet PostgreSQL für die Datenspeicherung, mit Fallback auf JSON-Dateien, 
                                falls die Datenbankverbindung nicht verfügbar ist.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">System-Information</h5>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    PHP Version
                                    <span class="badge bg-primary rounded-pill"><?php echo phpversion(); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Server
                                    <span class="badge bg-secondary rounded-pill"><?php echo $_SERVER['SERVER_SOFTWARE']; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    PostgreSQL
                                    <span class="badge bg-<?php echo function_exists('pg_connect') ? 'success' : 'danger'; ?> rounded-pill">
                                        <?php echo function_exists('pg_connect') ? 'Verfügbar' : 'Nicht verfügbar'; ?>
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <!-- Admin Module Cards -->
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="bi bi-people"></i> Benutzerverwaltung</h5>
                            <p class="card-text">Benutzer hinzufügen, bearbeiten oder löschen. Zugriffsrechte und Rollen verwalten.</p>
                            <a href="users.php" class="btn btn-primary">Benutzer verwalten</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="bi bi-person-badge"></i> Rollenverwaltung</h5>
                            <p class="card-text">System- und benutzerdefinierte Rollen erstellen und konfigurieren.</p>
                            <a href="roles.php" class="btn btn-primary">Rollen verwalten</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="bi bi-database"></i> Datenbankwartung</h5>
                            <p class="card-text">Datenbank-Operationen und Wartungsaufgaben durchführen.</p>
                            <div class="d-flex flex-column gap-2">
                                <a href="cleanup_users.php" class="btn btn-primary">Benutzerdaten bereinigen</a>
                                <a href="#" class="btn btn-secondary disabled">Weitere Funktionen (demnächst)</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Systemaktivität</h5>
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <div class="border rounded p-3 text-center">
                                        <h3><?php echo count(queryRecords('users.json')); ?></h3>
                                        <p class="mb-0">Registrierte Benutzer</p>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="border rounded p-3 text-center">
                                        <h3><?php echo count(queryRecords('cases.json')); ?></h3>
                                        <p class="mb-0">Aktive Fälle</p>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="border rounded p-3 text-center">
                                        <h3><?php echo count(queryRecords('defendants.json')); ?></h3>
                                        <p class="mb-0">Angeklagte</p>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="border rounded p-3 text-center">
                                        <h3><?php echo count(queryRecords('equipment.json')); ?></h3>
                                        <p class="mb-0">Ausrüstungsgegenstände</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>