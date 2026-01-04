<?php
/**
 * Datenbank-Installationsskript
 * Dieses Skript erstellt die erforderlichen Tabellen in der PostgreSQL-Datenbank
 */
require_once 'includes/functions.php';
require_once 'includes/db.php';

// Nur für Administratoren zugänglich machen
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrator') {
    // Datenbankinstallation nur über die Kommandozeile zulassen oder wenn ein Administrator angemeldet ist
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
    <title>Datenbank-Installation</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="card">
            <div class="card-header">
                <h2>Datenbank-Installation</h2>
            </div>
            <div class="card-body">
                <pre class="bg-dark text-light p-3">';
}

// Header
echo "=================================================\n";
echo "Datenbank-Installation\n";
echo "=================================================\n\n";

// Verbindung überprüfen
$pdo = getPDO();
if (!$pdo) {
    echo "Keine Verbindung zur Datenbank möglich.\n";
    echo "Stellen Sie sicher, dass die Datenbankverbindungsvariablen korrekt gesetzt sind.\n";
    echo "Die Installation kann nicht fortgesetzt werden.\n";
    
    if (php_sapi_name() !== 'cli') {
        echo '</pre>
                <div class="alert alert-danger">
                    <strong>Fehler:</strong> Keine Verbindung zur Datenbank möglich.
                </div>
                <a href="index.php" class="btn btn-primary">Zurück zum Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>';
    }
    exit(1);
}

// Tabellen erstellen
$tables = [
    'cases' => [
        'name' => 'Fälle',
        'columns' => [
            'id' => 'TEXT PRIMARY KEY',
            'title' => 'TEXT NOT NULL',
            'case_number' => 'TEXT NOT NULL',
            'description' => 'TEXT',
            'status' => 'TEXT DEFAULT \'offen\'',
            'priority' => 'TEXT DEFAULT \'normal\'',
            'created_by' => 'TEXT',
            'assigned_to' => 'TEXT',
            'date_created' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'date_updated' => 'TIMESTAMP',
            'date_closed' => 'TIMESTAMP',
            'tags' => 'TEXT[]',
            'related_cases' => 'TEXT[]',
            'court_date' => 'TIMESTAMP',
            'court_location' => 'TEXT',
            'prosecutor' => 'TEXT',
            'defense_attorney' => 'TEXT',
            'judge' => 'TEXT',
            'charges' => 'JSONB',
            'evidence' => 'JSONB',
            'notes' => 'TEXT',
            'category' => 'TEXT DEFAULT \'Strafrecht\''
        ]
    ],
    'documents' => [
        'name' => 'Dokumente',
        'columns' => [
            'id' => 'TEXT PRIMARY KEY',
            'filename' => 'TEXT NOT NULL',
            'title' => 'TEXT NOT NULL',
            'description' => 'TEXT',
            'file_path' => 'TEXT NOT NULL',
            'file_size' => 'BIGINT',
            'file_type' => 'TEXT',
            'uploaded_by' => 'TEXT',
            'case_id' => 'TEXT',
            'date_created' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'date_updated' => 'TIMESTAMP',
            'tags' => 'TEXT[]',
            'is_confidential' => 'BOOLEAN DEFAULT FALSE',
            'metadata' => 'JSONB',
            'content_text' => 'TEXT',
            'version' => 'INTEGER DEFAULT 1',
            'status' => 'TEXT DEFAULT \'aktiv\''
        ]
    ],
    'equipment' => [
        'name' => 'Ausrüstung',
        'columns' => [
            'id' => 'TEXT PRIMARY KEY',
            'name' => 'TEXT NOT NULL',
            'type' => 'TEXT NOT NULL',
            'description' => 'TEXT',
            'status' => 'TEXT DEFAULT \'verfügbar\'',
            'assigned_to' => 'TEXT',
            'date_acquired' => 'TIMESTAMP',
            'date_created' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'date_updated' => 'TIMESTAMP',
            'serial_number' => 'TEXT',
            'location' => 'TEXT',
            'notes' => 'TEXT',
            'condition' => 'TEXT DEFAULT \'gut\'',
            'maintenance_due' => 'TIMESTAMP',
            'properties' => 'JSONB',
            'image_path' => 'TEXT'
        ]
    ],
    'fines' => [
        'name' => 'Bußgelder',
        'columns' => [
            'id' => 'TEXT PRIMARY KEY',
            'title' => 'TEXT NOT NULL',
            'description' => 'TEXT',
            'amount' => 'NUMERIC(10,2) NOT NULL',
            'currency' => 'TEXT DEFAULT \'EUR\'',
            'code' => 'TEXT',
            'offense_description' => 'TEXT',
            'category' => 'TEXT',
            'legal_reference' => 'TEXT',
            'penalty_points' => 'INTEGER DEFAULT 0',
            'driving_ban_months' => 'INTEGER DEFAULT 0',
            'date_created' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'date_updated' => 'TIMESTAMP',
            'created_by' => 'TEXT',
            'notes' => 'TEXT',
            'related_case_id' => 'TEXT'
        ]
    ],
    'court_calendar' => [
        'name' => 'Gerichtstermine',
        'columns' => [
            'id' => 'TEXT PRIMARY KEY',
            'title' => 'TEXT NOT NULL',
            'description' => 'TEXT',
            'start_date' => 'TIMESTAMP NOT NULL',
            'end_date' => 'TIMESTAMP',
            'location' => 'TEXT',
            'room' => 'TEXT',
            'case_id' => 'TEXT',
            'case_number' => 'TEXT',
            'type' => 'TEXT',
            'status' => 'TEXT DEFAULT \'geplant\'',
            'judge' => 'TEXT',
            'prosecutor' => 'TEXT',
            'defense_attorney' => 'TEXT',
            'participants' => 'TEXT[]',
            'notes' => 'TEXT',
            'date_created' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'date_updated' => 'TIMESTAMP',
            'created_by' => 'TEXT',
            'is_public' => 'BOOLEAN DEFAULT TRUE'
        ]
    ]
];

// Tabellen erstellen oder aktualisieren
foreach ($tables as $tableName => $tableInfo) {
    echo "Verarbeite Tabelle '{$tableInfo['name']}' ($tableName)...\n";
    
    // Prüfen, ob die Tabelle bereits existiert
    try {
        $stmt = $pdo->prepare("SELECT to_regclass('public.$tableName')");
        $stmt->execute();
        $tableExists = $stmt->fetchColumn();
        
        if ($tableExists) {
            echo " - Tabelle existiert bereits.\n";
            echo " - Überprüfe Tabellenspalten auf notwendige Aktualisierungen...\n";
            
            // Bestehende Spalten abrufen
            $stmt = $pdo->prepare("
                SELECT column_name, data_type, character_maximum_length, is_nullable
                FROM information_schema.columns
                WHERE table_schema = 'public' AND table_name = :table_name
            ");
            $stmt->bindParam(':table_name', $tableName);
            $stmt->execute();
            $existingColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $existingColumnMap = [];
            
            foreach ($existingColumns as $column) {
                $existingColumnMap[$column['column_name']] = $column;
            }
            
            // Fehlende Spalten hinzufügen
            foreach ($tableInfo['columns'] as $columnName => $columnDef) {
                if (!isset($existingColumnMap[$columnName])) {
                    try {
                        $pdo->exec("ALTER TABLE \"$tableName\" ADD COLUMN \"$columnName\" $columnDef");
                        echo " - Spalte '$columnName' hinzugefügt.\n";
                    } catch (Exception $e) {
                        echo " ✗ Fehler beim Hinzufügen der Spalte '$columnName': " . $e->getMessage() . "\n";
                    }
                }
            }
            
            echo " ✓ Tabellenaktualisierung abgeschlossen.\n";
        } else {
            // Tabelle erstellen
            echo " - Tabelle wird erstellt...\n";
            
            $createTableSQL = "CREATE TABLE \"$tableName\" (";
            $columnDefs = [];
            
            foreach ($tableInfo['columns'] as $columnName => $columnDef) {
                $columnDefs[] = "\"$columnName\" $columnDef";
            }
            
            $createTableSQL .= implode(", ", $columnDefs) . ")";
            
            try {
                $pdo->exec($createTableSQL);
                echo " ✓ Tabelle erfolgreich erstellt.\n";
            } catch (Exception $e) {
                echo " ✗ Fehler beim Erstellen der Tabelle: " . $e->getMessage() . "\n";
            }
        }
    } catch (Exception $e) {
        echo " ✗ Fehler bei der Tabellenprüfung: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

echo "=================================================\n";
echo "Datenbank-Installation abgeschlossen\n";
echo "=================================================\n";

// HTML-Footer, wenn nicht in der Kommandozeile
if (php_sapi_name() !== 'cli') {
    echo '</pre>
                <div class="alert alert-success">
                    <strong>Erfolg:</strong> Die Datenbank wurde erfolgreich installiert oder aktualisiert.
                </div>
                <a href="index.php" class="btn btn-primary">Zurück zum Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>';
}
?>