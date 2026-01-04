<?php
/**
 * Datensicherungsskript für das Justice Department Records Management System
 * Dieses Skript erstellt ein Backup aller Daten im data-Verzeichnis
 */

echo "=================================================\n";
echo "Datensicherung wird durchgeführt...\n";
echo "=================================================\n\n";

// Pfad zum data-Verzeichnis
$dataDir = __DIR__ . '/data';

// Stelle sicher, dass das data-Verzeichnis existiert
if (!is_dir($dataDir)) {
    echo "FEHLER: Das data-Verzeichnis existiert nicht.\n";
    exit(1);
}

// Backup-Verzeichnis erstellen mit Zeitstempel
$backupDir = __DIR__ . '/backups/' . date('Y-m-d_H-i-s');
if (!is_dir($backupDir)) {
    if (!mkdir($backupDir, 0755, true)) {
        echo "FEHLER: Konnte Backup-Verzeichnis nicht erstellen.\n";
        exit(1);
    }
}

// Kopiere alle vorhandenen JSON-Dateien ins Backup-Verzeichnis
$jsonFiles = glob($dataDir . '/*.json');
if (empty($jsonFiles)) {
    echo "WARNUNG: Keine JSON-Dateien im data-Verzeichnis gefunden.\n";
}

foreach ($jsonFiles as $file) {
    $filename = basename($file);
    if (copy($file, $backupDir . '/' . $filename)) {
        echo "✓ Datei gesichert: $filename\n";
    } else {
        echo "✗ FEHLER: Konnte $filename nicht sichern.\n";
    }
}

// Zusätzlich das gesamte data-Verzeichnis als ZIP-Archiv speichern
if (extension_loaded('zip')) {
    $zipFile = $backupDir . '/data_backup.zip';
    
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
        // Füge alle Dateien aus dem data-Verzeichnis zum ZIP hinzu
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dataDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $key => $value) {
            $filePath = $value->getRealPath();
            $relativePath = substr($filePath, strlen($dataDir) + 1);
            
            if (!$value->isDir()) {
                $zip->addFile($filePath, $relativePath);
            } else {
                $zip->addEmptyDir($relativePath);
            }
        }
        
        $zip->close();
        echo "\n✓ ZIP-Archiv erstellt: " . basename($zipFile) . "\n";
    } else {
        echo "\n✗ FEHLER: Konnte ZIP-Archiv nicht erstellen.\n";
    }
} else {
    echo "\n⚠ HINWEIS: ZIP-Erweiterung nicht verfügbar. Kein ZIP-Archiv erstellt.\n";
}

// Erstelle eine Kopie im data_backup-Verzeichnis für einfache Wiederherstellung
$fastRestoreDir = __DIR__ . '/data_backup';
if (is_dir($fastRestoreDir)) {
    // Lösche den vorherigen Schnellwiederherstellungspunkt
    $files = glob($fastRestoreDir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
} else {
    mkdir($fastRestoreDir, 0755, true);
}

// Kopiere alle aktuellen Dateien in das Schnellwiederherstellungsverzeichnis
foreach ($jsonFiles as $file) {
    $filename = basename($file);
    if (copy($file, $fastRestoreDir . '/' . $filename)) {
        echo "✓ Schnellwiederherstellungspunkt erstellt: $filename\n";
    } else {
        echo "✗ FEHLER: Konnte Schnellwiederherstellungspunkt für $filename nicht erstellen.\n";
    }
}

echo "\n=================================================\n";
echo "Datensicherung abgeschlossen.\n";
echo "Die Daten wurden gesichert in:\n";
echo "- $backupDir (Vollständiges Backup)\n";
echo "- $fastRestoreDir (Schnellwiederherstellungspunkt)\n";
echo "=================================================\n";
echo "\nUm die Daten wiederherzustellen, kopieren Sie den Inhalt von 'data_backup' nach 'data':\n";
echo "cp -r data_backup/* data/\n";
echo "=================================================\n";
?>