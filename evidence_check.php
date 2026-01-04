<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/permissions.php';

// Diese Datei implementiert eine spezielle Zugriffssteuerung für das Beschlagnahme-Modul
// Ausgeschlossene Rollen: Trainee, Student, Sheriff, Army, U.S. Präsident und Staatssekretär

// Prüfe ob der Benutzer eingeloggt ist
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Liste der Rollen, die keinen Zugriff haben sollen
$restrictedRoles = [
    'trainee', 
    'student', 
    'sheriff', 
    'army', 
    'president', 
    'secretary'
];

$userRole = strtolower($_SESSION['role']);

// Wenn die Rolle des Benutzers in der Liste der eingeschränkten Rollen ist, verweigere Zugriff
if (in_array($userRole, $restrictedRoles)) {
    header('Location: access_denied.php');
    exit;
}
?>