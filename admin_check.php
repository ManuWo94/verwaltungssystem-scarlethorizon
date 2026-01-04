<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/permissions.php';

// Diese Datei wird für Authentifizierungsprüfungen im Administrationsbereich eingebunden
// und prüft, ob der aktuelle Benutzer Administratorrechte hat

// Prüfe ob der Benutzer eingeloggt ist und Admin-Berechtigung hat
if (!isset($_SESSION['user_id']) || !currentUserCan('admin', 'view')) {
    header('Location: access_denied.php');
    exit;
}
?>