<?php
/**
 * Session-Konfiguration für das Justiz-Aktenverwaltungssystem
 * Diese Datei konfiguriert die PHP-Session-Einstellungen
 */

// Diese Session-Konfigurationen müssen vor dem Start der Session festgelegt werden
// Sie werden nur beim erstmaligen Aufruf der Anwendung wirksam
if (session_status() == PHP_SESSION_NONE) {
    // Session-Lebensdauer auf 8 Stunden (28800 Sekunden) setzen
    ini_set('session.gc_maxlifetime', 28800);
    
    // Cookie-Lebensdauer auf 8 Stunden setzen
    ini_set('session.cookie_lifetime', 28800);
    
    // Session starten
    session_start();
}

// Erhöhen des Upload-Limits auf 64MB (kann jederzeit gesetzt werden)
ini_set('post_max_size', '64M');
ini_set('upload_max_filesize', '64M');