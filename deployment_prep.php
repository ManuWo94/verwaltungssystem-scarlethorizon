<?php
/**
 * Deployment-Vorbereitungsskript für Replit
 * Dieses Skript wird vor dem Deployment ausgeführt, um die Umgebung vorzubereiten
 */

// Setze APP_ENV auf 'development', um die Verwendung von JSON-Dateien statt PostgreSQL zu erzwingen
putenv('APP_ENV=development');

// Setze Datenbank-Umgebungsvariablen
putenv('PGUSER=postgres');
putenv('PGPASSWORD=password');
putenv('PGDATABASE=doj');
putenv('PGHOST=localhost');
putenv('PGPORT=5432');

// Keine Ausgabe, um Header-Probleme zu vermeiden
error_log("Replit-Deployment-Vorbereitungen: Umgebung auf 'development' gesetzt");
error_log("Dummy-Datenbankparameter gesetzt, um Umgebungsvariablen-Fehler zu vermeiden");
error_log("Die Anwendung wird JSON-Dateien für die Datenspeicherung verwenden");
?>