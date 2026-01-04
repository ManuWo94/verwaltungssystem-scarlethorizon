<?php
// Replit-spezifische Vorbereitung für Deployment und lokale Entwicklung
if (file_exists('replit_prepare_db.php')) {
    include_once 'replit_prepare_db.php';
}

// Deployment-spezifische Vorbereitungen
if (file_exists('deployment_prep.php')) {
    include_once 'deployment_prep.php';
}

// Stelle sicher, dass alle erforderlichen Umgebungsvariablen gesetzt sind
if (!getenv('PGUSER')) putenv('PGUSER=postgres');
if (!getenv('PGPASSWORD')) putenv('PGPASSWORD=password');
if (!getenv('PGDATABASE')) putenv('PGDATABASE=doj');
if (!getenv('PGHOST')) putenv('PGHOST=localhost');
if (!getenv('PGPORT')) putenv('PGPORT=5432');

// Setze APP_ENV auf 'development', falls nicht anderweitig konfiguriert
if (!getenv('APP_ENV')) putenv('APP_ENV=development');

// Redirect to login or dashboard based on session status
session_start();
require_once 'includes/functions.php';

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    // User is logged in, redirect to dashboard
    header('Location: dashboard.php');
    exit;
} else {
    // User is not logged in, redirect to login
    header('Location: login.php');
    exit;
}
?>
