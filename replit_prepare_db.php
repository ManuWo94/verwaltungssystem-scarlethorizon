<?php
/**
 * Replit-spezifische Datenbankvorbereitungen 
 * Dieses Skript stellt sicher, dass die JSON-Dateien für die Datenspeicherung genutzt werden
 */

// Setze Fehlerberichtsebene auf Maximum für die Entwicklung
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Setze APP_ENV auf 'development', um die Verwendung von JSON-Dateien statt PostgreSQL zu erzwingen
putenv('APP_ENV=development');

// Keine Ausgabe, um Header-Probleme zu vermeiden
error_log("Replit-Datenbankvorbereitungen: Umgebung auf 'development' gesetzt");

// Die Funktion prüft, ob die pgsql-Erweiterung in PHP installiert ist
function checkPgSQLExtension() {
    return extension_loaded('pgsql') && extension_loaded('pdo_pgsql');
}

if (checkPgSQLExtension()) {
    error_log("HINWEIS: Die PostgreSQL-Erweiterungen sind verfügbar. Sie können die Datenbank nutzen, indem Sie APP_ENV=production setzen.");
} else {
    error_log("HINWEIS: Die PostgreSQL-Erweiterungen sind NICHT verfügbar. Die Anwendung wird JSON-Dateien für die Datenspeicherung verwenden.");
}
?>