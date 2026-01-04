<?php
/**
 * Automatisches Laden der Datenbankeinrichtung
 * Diese Datei wird bei jedem Start der Anwendung geladen und stellt sicher, 
 * dass die Datenbankverbindung korrekt funktioniert.
 */

// Wenn wir bereits in einer Datenbankdatei sind, die Rekursion vermeiden
if (defined('AUTOLOAD_DB_RUNNING')) {
    return;
}

define('AUTOLOAD_DB_RUNNING', true);

// Datenbankverbindungsfehler in die Logs schreiben, wenn vorhanden
set_error_handler(function($severity, $message, $file, $line) {
    if (strpos($message, 'Database') !== false || strpos($message, 'PDO') !== false) {
        error_log("Datenbankfehler: $message in $file on line $line");
    }
    return false;
}, E_WARNING);

// Überprüfe, ob die PostgreSQL-Erweiterungen verfügbar sind
function checkPgsqlExtensions() {
    $required = ['pdo', 'pgsql', 'pdo_pgsql'];
    $missing = [];
    
    foreach ($required as $ext) {
        if (!extension_loaded($ext)) {
            $missing[] = $ext;
        }
    }
    
    if (!empty($missing)) {
        error_log('Fehlende PostgreSQL-Erweiterungen: ' . implode(', ', $missing));
        error_log('Bitte installieren Sie die fehlenden Erweiterungen mit: apt-get install php-pgsql');
        return false;
    }
    
    return true;
}

// Datenbankverbindung testen und Verbindungsfehler protokollieren
try {
    require_once __DIR__ . '/includes/db.php';
    
    // Prüfen, ob PostgreSQL-Erweiterungen vorhanden sind
    $pgsqlAvailable = checkPgsqlExtensions();
    
    if (!$pgsqlAvailable) {
        error_log('PostgreSQL-Unterstützung nicht verfügbar. Verwende JSON-Dateien als Fallback.');
        // Die Anwendung wird automatisch auf JSON-Dateien zurückgreifen
    } else {
        // Datenbankverbindung testen
        $pdo = getPDO();
        
        if ($pdo) {
            // Erfolgreiche Verbindung
            error_log('Datenbankverbindung erfolgreich hergestellt.');
            
            // Prüfen, ob die erforderlichen Tabellen existieren
            $requiredTables = ['cases', 'documents', 'equipment', 'fines', 'court_calendar'];
            $missingTables = [];
            
            foreach ($requiredTables as $table) {
                if (!checkTableExists($table)) {
                    $missingTables[] = $table;
                }
            }
            
            // Wenn Tabellen fehlen, in den Logs darauf hinweisen
            if (!empty($missingTables)) {
                error_log('Fehlende Datenbanktabellen: ' . implode(', ', $missingTables));
                error_log('Bitte führen Sie install_database.php aus, um die Tabellen anzulegen.');
            }
        } else {
            // Keine Verbindung möglich
            error_log('Keine Verbindung zur Datenbank möglich. Verwende JSON-Dateien als Fallback.');
            
            // Überprüfen, ob Umgebungsvariablen vorhanden sind
            $dbVars = ['DATABASE_URL', 'PGHOST', 'PGPORT', 'PGDATABASE', 'PGUSER', 'PGPASSWORD'];
            
            $hasUrl = !empty(getenv('DATABASE_URL'));
            $hasIndividualVars = !empty(getenv('PGHOST')) && !empty(getenv('PGPORT')) && 
                                !empty(getenv('PGDATABASE')) && !empty(getenv('PGUSER')) && 
                                !empty(getenv('PGPASSWORD'));
            
            if (!$hasUrl && !$hasIndividualVars) {
                error_log('Fehlende Datenbankumgebungsvariablen. Entweder DATABASE_URL oder die einzelnen PG* Variablen müssen gesetzt sein.');
            }
        }
    }
} catch (Exception $e) {
    error_log('Fehler bei der Datenbankinitialisierung: ' . $e->getMessage());
}

// Fehlerbehandlung zurücksetzen
restore_error_handler();