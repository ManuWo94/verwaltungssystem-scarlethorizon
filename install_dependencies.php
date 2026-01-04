<?php
/**
 * Dependencies-Installationsskript
 * Dieses Skript überprüft und installiert benötigte PHP-Erweiterungen
 */

// Nur für CLI-Modus verfügbar
if (php_sapi_name() !== 'cli') {
    echo "Dieses Skript kann nur über die Kommandozeile ausgeführt werden.";
    exit(1);
}

// Header
echo "=================================================\n";
echo "Abhängigkeiten-Installation\n";
echo "=================================================\n\n";

// PostgreSQL-Erweiterung überprüfen
echo "Überprüfe PostgreSQL-Erweiterung...\n";
if (extension_loaded('pgsql') && extension_loaded('pdo_pgsql')) {
    echo "✓ PostgreSQL-Erweiterungen sind bereits installiert.\n";
} else {
    echo "✗ PostgreSQL-Erweiterungen fehlen.\n";
    
    // Betriebssystem erkennen
    $os = php_uname('s');
    
    echo "Betriebssystem: $os\n";
    echo "Installiere benötigte Erweiterungen...\n";
    
    if (stripos($os, 'Linux') !== false) {
        echo "Linux-Umgebung erkannt.\n";
        
        // Passe die folgenden Befehle je nach Distribution an
        $commands = [
            'apt-get update',
            'apt-get install -y php-pgsql'
        ];
        
        foreach ($commands as $command) {
            echo "Führe aus: $command\n";
            passthru($command, $return);
            
            if ($return !== 0) {
                echo "✗ Fehler bei der Ausführung von: $command\n";
                echo "Bitte führen Sie folgenden Befehl manuell aus:\n";
                echo "apt-get install -y php-pgsql\n";
                exit(1);
            }
        }
        
        echo "✓ PostgreSQL-Erweiterungen installiert. PHP muss neu gestartet werden.\n";
    } else if (stripos($os, 'Darwin') !== false) {
        echo "macOS-Umgebung erkannt.\n";
        echo "Installiere via Homebrew...\n";
        
        $commands = [
            'brew install php-pgsql'
        ];
        
        foreach ($commands as $command) {
            echo "Führe aus: $command\n";
            passthru($command, $return);
            
            if ($return !== 0) {
                echo "✗ Fehler bei der Ausführung von: $command\n";
                echo "Bitte führen Sie folgenden Befehl manuell aus:\n";
                echo "brew install php-pgsql\n";
                exit(1);
            }
        }
        
        echo "✓ PostgreSQL-Erweiterungen installiert. PHP muss neu gestartet werden.\n";
    } else if (stripos($os, 'Windows') !== false) {
        echo "Windows-Umgebung erkannt.\n";
        echo "Bitte aktivieren Sie die Erweiterungen in php.ini:\n";
        echo "1. Öffnen Sie php.ini\n";
        echo "2. Entfernen Sie das Semikolon vor extension=pgsql und extension=pdo_pgsql\n";
        echo "3. Starten Sie den Webserver neu\n";
    } else if (stripos($os, 'Replit') !== false || stripos($os, 'Cloud') !== false) {
        echo "Cloud/Replit-Umgebung erkannt.\n";
        echo "Führe Cloud-Installation aus...\n";
        
        $commands = [
            'apt-get update',
            'apt-get install -y postgresql-client php-pgsql'
        ];
        
        foreach ($commands as $command) {
            echo "Führe aus: $command\n";
            passthru($command, $return);
            
            if ($return !== 0) {
                echo "✗ Fehler bei der Ausführung von: $command\n";
                exit(1);
            }
        }
        
        echo "✓ PostgreSQL-Erweiterungen installiert.\n";
        echo "✓ Erstelle PHP-Info-Datei für die Überprüfung der Installation...\n";
        
        // Erstelle phpinfo.php Datei
        file_put_contents('phpinfo.php', '<?php phpinfo(); ?>');
        echo "✓ phpinfo.php erstellt. Bitte rufen Sie diese Datei auf, um die PHP-Konfiguration zu überprüfen.\n";
    } else {
        echo "Unbekanntes Betriebssystem. Bitte installieren Sie die PostgreSQL-Erweiterungen manuell.\n";
    }
}

// Überprüfe weitere Abhängigkeiten
echo "\nÜberprüfe weitere benötigte Erweiterungen...\n";

$requiredExtensions = [
    'PDO', 'json', 'mbstring', 'session', 'fileinfo'
];

$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded(strtolower($ext))) {
        $missingExtensions[] = $ext;
        echo "✗ $ext fehlt\n";
    } else {
        echo "✓ $ext ist installiert\n";
    }
}

if (!empty($missingExtensions)) {
    echo "\nEs fehlen folgende Erweiterungen: " . implode(', ', $missingExtensions) . "\n";
    echo "Bitte installieren Sie diese Erweiterungen für den fehlerfreien Betrieb der Anwendung.\n";
} else {
    echo "\n✓ Alle erforderlichen Erweiterungen sind installiert.\n";
}

echo "\n=================================================\n";
echo "Installation abgeschlossen\n";
echo "=================================================\n";

?>