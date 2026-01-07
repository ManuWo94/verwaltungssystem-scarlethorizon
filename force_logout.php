<?php
/**
 * Force Logout und Session-Clear
 * Nutze dies, wenn Berechtigungen nicht aktualisiert werden
 */
session_start();

// Alle Session-Variablen löschen
$_SESSION = array();

// Session-Cookie löschen
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-42000, '/');
}

// Session zerstören
session_destroy();

// Redirect zum Login
header('Location: login.php?msg=session_cleared');
exit;
?>
