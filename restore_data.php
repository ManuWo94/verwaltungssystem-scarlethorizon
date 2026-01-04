<?php
/**
 * Datenwiederherstellungsskript für das Justice Department Records Management System
 * Dieses Skript stellt die gesicherten Daten aus dem data_backup-Verzeichnis wieder her
 */

echo "=================================================\n";
echo "Datenwiederherstellung wird durchgeführt...\n";
echo "=================================================\n\n";

// Pfade zu den Verzeichnissen
$dataDir = __DIR__ . '/data';
$backupDir = __DIR__ . '/data_backup';

// Überprüfen, ob das Backup-Verzeichnis existiert
if (!is_dir($backupDir)) {
    echo "FEHLER: Das Backup-Verzeichnis 'data_backup' existiert nicht.\n";
    echo "Stellen Sie sicher, dass Sie zuvor 'backup_data.php' ausgeführt haben.\n";
    exit(1);
}

// Überprüfen, ob das data-Verzeichnis existiert
if (!is_dir($dataDir)) {
    echo "Das Verzeichnis 'data' existiert nicht und wird erstellt...\n";
    if (!mkdir($dataDir, 0755, true)) {
        echo "FEHLER: Konnte das Verzeichnis 'data' nicht erstellen.\n";
        exit(1);
    }
}

// Überprüfen, ob Backup-Dateien vorhanden sind
$backupFiles = glob($backupDir . '/*.json');
if (empty($backupFiles)) {
    echo "FEHLER: Keine JSON-Dateien im Backup-Verzeichnis gefunden.\n";
    exit(1);
}

// Optional: Sichern Sie aktuelle Daten vor der Wiederherstellung
$currentDataFiles = glob($dataDir . '/*.json');
if (!empty($currentDataFiles)) {
    $preRestoreBackupDir = __DIR__ . '/backups/pre_restore_' . date('Y-m-d_H-i-s');
    if (!is_dir($preRestoreBackupDir)) {
        mkdir($preRestoreBackupDir, 0755, true);
    }
    
    echo "Sichere aktuelle Daten vor der Wiederherstellung...\n";
    foreach ($currentDataFiles as $file) {
        $filename = basename($file);
        if (copy($file, $preRestoreBackupDir . '/' . $filename)) {
            echo "✓ Aktuelle Datei gesichert: $filename\n";
        } else {
            echo "✗ WARNUNG: Konnte aktuelle Datei $filename nicht sichern.\n";
        }
    }
    echo "\n";
}

// Dateien aus dem Backup-Verzeichnis wiederherstellen
echo "Starte Wiederherstellung aus Backup...\n";
foreach ($backupFiles as $file) {
    $filename = basename($file);
    $destFile = $dataDir . '/' . $filename;
    
    if (copy($file, $destFile)) {
        echo "✓ Wiederhergestellt: $filename\n";
    } else {
        echo "✗ FEHLER: Konnte $filename nicht wiederherstellen.\n";
    }
}

echo "\n=================================================\n";
echo "Datenwiederherstellung abgeschlossen.\n";
if (!empty($currentDataFiles)) {
    echo "Die vorherigen Daten wurden gesichert in:\n";
    echo "- $preRestoreBackupDir\n";
}
echo "=================================================\n\n";

// Überprüfen, ob alle erwarteten Dateien vorhanden sind
$expectedFiles = ['users.json', 'roles.json', 'cases.json', 'indictments.json'];
$missingFiles = [];

foreach ($expectedFiles as $expected) {
    if (!file_exists($dataDir . '/' . $expected)) {
        $missingFiles[] = $expected;
    }
}

if (!empty($missingFiles)) {
    echo "WARNUNG: Die folgenden wichtigen Dateien fehlen im Datenverzeichnis:\n";
    foreach ($missingFiles as $missing) {
        echo "- $missing\n";
    }
    echo "\nDas System könnte nicht korrekt funktionieren. Überprüfen Sie Ihre Backups.\n";
} else {
    echo "✓ Alle wichtigen Datendateien sind vorhanden.\n";
    echo "Das System sollte jetzt mit den wiederhergestellten Daten funktionieren.\n";
}
?>