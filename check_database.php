<?php
/**
 * Datenbankkonfigurationsüberprüfung
 * Diese Datei prüft die Datenbankverbindung und gibt Statusinformationen aus
 */
require_once 'includes/functions.php';
require_once 'includes/db.php';

// Nur für Administratoren zugänglich machen
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrator') {
    // Datenbankprüfung nur über die Kommandozeile zulassen oder wenn ein Administrator angemeldet ist
    if (php_sapi_name() !== 'cli') {
        header('Location: access_denied.php');
        exit;
    }
}

// HTML-Header, wenn nicht in der Kommandozeile
if (php_sapi_name() !== 'cli') {
    echo '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datenbank-Verbindungsprüfung</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="card">
            <div class="card-header">
                <h2>Datenbank-Verbindungsprüfung</h2>
            </div>
            <div class="card-body">
                <pre class="bg-dark text-light p-3">';
}

// Header
echo "=================================================\n";
echo "Datenbank-Verbindungsprüfung\n";
echo "=================================================\n\n";

// Umgebungsvariablen prüfen
echo "Umgebungsvariablen für Datenbankverbindung:\n";
echo "-------------------------------------------\n";

$dbVars = [
    'DATABASE_URL' => getenv('DATABASE_URL') ? 'Vorhanden (Wert versteckt)' : 'Nicht vorhanden',
    'PGHOST' => getenv('PGHOST') ?: 'Nicht vorhanden',
    'PGPORT' => getenv('PGPORT') ?: 'Nicht vorhanden',
    'PGDATABASE' => getenv('PGDATABASE') ?: 'Nicht vorhanden',
    'PGUSER' => getenv('PGUSER') ? 'Vorhanden (Wert versteckt)' : 'Nicht vorhanden',
    'PGPASSWORD' => getenv('PGPASSWORD') ? 'Vorhanden (Wert versteckt)' : 'Nicht vorhanden',
    'APP_ENV' => getenv('APP_ENV') ?: 'Nicht vorhanden (Standard: production)'
];

foreach ($dbVars as $var => $value) {
    echo " - $var: $value\n";
}

echo "\n";

// Verbindungstest
echo "Datenbankverbindungstest:\n";
echo "------------------------\n";

$pdo = getPDO();
if ($pdo) {
    echo " ✓ Verbindung zur Datenbank erfolgreich hergestellt.\n";
    
    // Server-Informationen abfragen
    try {
        $stmt = $pdo->query("SELECT version()");
        $version = $stmt->fetchColumn();
        echo " ✓ Datenbank-Version: $version\n";
    } catch (Exception $e) {
        echo " ✗ Fehler beim Abfragen der Datenbankversion: " . $e->getMessage() . "\n";
    }
    
    // Tabellenprüfung
    echo "\nTabellenstatus:\n";
    echo "---------------\n";
    
    $tables = [
        'users' => 'Benutzer',
        'roles' => 'Rollen',
        'cases' => 'Fälle',
        'documents' => 'Dokumente',
        'equipment' => 'Ausrüstung',
        'fines' => 'Bußgelder',
        'court_calendar' => 'Gerichtstermine'
    ];
    
    foreach ($tables as $table => $description) {
        $exists = checkTableExists($table);
        $status = $exists ? '✓ Vorhanden' : '✗ Nicht vorhanden';
        echo " - $description ($table): $status\n";
    }
    
    // Migration Check
    echo "\nMigrationsstatus für JSON-Dateien:\n";
    echo "--------------------------------\n";
    
    foreach ($tables as $table => $description) {
        $filename = $table . '.json';
        $migrated = isTableMigratedToDatabase($filename);
        $status = $migrated ? '✓ Nutzt Datenbank' : '✗ Nutzt JSON-Datei';
        echo " - $description ($filename): $status\n";
    }
} else {
    echo " ✗ Keine Verbindung zur Datenbank möglich.\n";
    echo " ✗ Die Anwendung verwendet JSON-Dateien als Datenquelle.\n";
    
    // Prüfen, ob die Datei existiert und lesbar ist
    echo "\nJSON-Datenstatus:\n";
    echo "----------------\n";
    
    $jsonFiles = [
        'users.json' => 'Benutzer',
        'roles.json' => 'Rollen',
        'cases.json' => 'Fälle',
        'documents.json' => 'Dokumente',
        'equipment.json' => 'Ausrüstung',
        'fines.json' => 'Bußgelder',
        'court_calendar.json' => 'Gerichtstermine'
    ];
    
    foreach ($jsonFiles as $file => $description) {
        $path = __DIR__ . '/data/' . $file;
        $exists = file_exists($path);
        $readable = is_readable($path);
        $status = $exists && $readable ? '✓ OK' : '✗ Problem';
        $details = '';
        
        if (!$exists) {
            $details = ' (Datei existiert nicht)';
        } elseif (!$readable) {
            $details = ' (Datei nicht lesbar)';
        } else {
            $count = count(getJsonData($file));
            $details = " ($count Einträge)";
        }
        
        echo " - $description ($file): $status$details\n";
    }
}

echo "\n=================================================\n";
echo "Prüfung abgeschlossen\n";
echo "=================================================\n";

// HTML-Footer, wenn nicht in der Kommandozeile
if (php_sapi_name() !== 'cli') {
    echo '</pre>
                <a href="index.php" class="btn btn-primary">Zurück zum Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>';
}
?>