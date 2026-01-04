<?php
/**
 * Department of Justice - Records Management System
 * Dokument-Importer und Parser-Integration
 */

/**
 * Führt einen Python-Parser aus, um Texte aus verschiedenen Dokumenten zu extrahieren
 * 
 * @param string $inputPath Der Pfad oder die URL zum zu parsenden Dokument
 * @param string $mode Der Ausgabemodus ('text', 'sections', 'catalog')
 * @param string $outputPath Optional: Der Pfad zur JSON-Ausgabedatei
 * @return array Das Ergebnis des Parsings als Array oder false bei Fehler
 */
function parseDocument($inputPath, $mode = 'text', $outputPath = null) {
    // Parameter überprüfen
    if (empty($inputPath)) {
        return [
            'success' => false,
            'error' => 'Keine Eingabedatei oder URL angegeben.'
        ];
    }
    
    // Verfügbare Modi überprüfen
    $availableModes = ['text', 'sections', 'catalog'];
    if (!in_array($mode, $availableModes)) {
        return [
            'success' => false,
            'error' => 'Ungültiger Modus. Verfügbare Modi: ' . implode(', ', $availableModes)
        ];
    }
    
    // Kommando konstruieren
    $pythonPath = 'python3';
    $scriptPath = __DIR__ . '/../parsers/document_parser.py';
    
    $cmd = escapeshellcmd($pythonPath) . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($inputPath) . ' --mode=' . escapeshellarg($mode);
    
    if ($outputPath) {
        $cmd .= ' --output=' . escapeshellarg($outputPath);
    }
    
    // Umleitung von stderr nach stdout
    $cmd .= ' 2>&1';
    
    // Führe das Kommando aus und erfasse die Ausgabe
    $output = shell_exec($cmd);
    
    // Überprüfe, ob es einen Fehler gab
    if ($output === null || $output === false) {
        return [
            'success' => false,
            'error' => 'Fehler bei der Ausführung des Parsers.'
        ];
    }
    
    // Wenn eine Ausgabedatei angegeben wurde, lade die Ergebnisse von dort
    if ($outputPath && file_exists($outputPath)) {
        $jsonResult = file_get_contents($outputPath);
    } else {
        // Andernfalls, verwende die Ausgabe des Befehls
        $jsonResult = $output;
    }
    
    // Entferne mögliche Fehlermeldungen, die vor dem JSON-Teil stehen könnten
    $cleanedJson = $jsonResult;
    // Suche nach der ersten öffnenden eckigen Klammer für ein Array
    $jsonStartPos = strpos($jsonResult, '[');
    if ($jsonStartPos !== false) {
        $cleanedJson = substr($jsonResult, $jsonStartPos);
    }
    
    // Versuche, das JSON zu dekodieren
    $result = json_decode($cleanedJson, true);
    
    // Überprüfe, ob das JSON erfolgreich dekodiert wurde
    if ($result === null) {
        // Wenn die JSON-Decodierung fehlgeschlagen ist, erstelle einen Standard-Eintrag
        // damit das Programm nicht abstürzt
        error_log("JSON-Decodierungsfehler: " . json_last_error_msg() . "\nAusgabe: " . $output);
        
        // Standardeintrag als Fallback
        return [
            [
                'category' => 'Allgemein',
                'violation' => 'Importfehler',
                'description' => 'Fehler beim Import des Bußgeldkatalogs',
                'amount' => 0,
                'prison_days' => 0,
                'notes' => 'Parser-Fehler: ' . json_last_error_msg()
            ]
        ];
    }
    
    return $result;
}

/**
 * Importiert Bußgeldkatalog-Einträge aus einem Dokument
 * 
 * @param string $inputPath Der Pfad oder die URL zum zu parsenden Dokument
 * @param bool $merge Ob bestehende Einträge beibehalten werden sollen
 * @return array Statusnachricht und Anzahl der importierten Einträge
 */
function importFineCatalog($inputPath, $merge = true) {
    // Datei für den Bußgeldkatalog
    $fineCatalogFile = __DIR__ . '/../data/fine_catalog.json';
    
    // Parsen des Dokuments im Katalog-Modus
    $parseResult = parseDocument($inputPath, 'catalog');
    
    if (!is_array($parseResult) || isset($parseResult['success']) && $parseResult['success'] === false) {
        return [
            'success' => false,
            'message' => 'Fehler beim Parsen des Dokuments: ' . (isset($parseResult['error']) ? $parseResult['error'] : 'Unbekannter Fehler'),
            'count' => 0
        ];
    }
    
    // Wenn keine Einträge gefunden wurden
    if (empty($parseResult)) {
        return [
            'success' => false,
            'message' => 'Keine Bußgeldkatalog-Einträge im Dokument gefunden.',
            'count' => 0
        ];
    }
    
    // Lade den bestehenden Katalog, falls vorhanden
    $existingCatalog = [];
    if (file_exists($fineCatalogFile) && $merge) {
        $existingCatalog = json_decode(file_get_contents($fineCatalogFile), true);
        if (!is_array($existingCatalog)) {
            $existingCatalog = [];
        }
    }
    
    // Finde die höchste ID im bestehenden Katalog
    $maxId = 0;
    foreach ($existingCatalog as $entry) {
        if (isset($entry['id']) && $entry['id'] > $maxId) {
            $maxId = (int)$entry['id'];
        }
    }
    
    // Füge neue Einträge hinzu und setze IDs
    $newEntries = [];
    foreach ($parseResult as $entry) {
        // Ignoriere Einträge ohne 'violation'-Feld
        if (!isset($entry['violation']) || empty($entry['violation'])) {
            continue;
        }
        
        // Stelle sicher, dass alle erforderlichen Felder existieren
        if (!isset($entry['category'])) $entry['category'] = 'Allgemein';
        if (!isset($entry['description'])) $entry['description'] = $entry['violation'];
        if (!isset($entry['amount'])) $entry['amount'] = 0;
        if (!isset($entry['prison_days'])) $entry['prison_days'] = 0;
        if (!isset($entry['notes'])) $entry['notes'] = '';
        
        // Wenn eine ID bereits existiert, behalte sie bei
        if (!isset($entry['id']) || empty($entry['id'])) {
            $maxId++;
            $entry['id'] = $maxId;
        }
        
        $newEntries[] = $entry;
    }
    
    // Protokolliere den Import für die Fehlerbehebung
    error_log(sprintf("Bußgeldkatalog-Import: %d Einträge geparst, %d gültige Einträge importiert", 
                      count($parseResult), count($newEntries)));
    
    // Kombiniere existierende und neue Einträge
    $combinedCatalog = $merge ? array_merge($existingCatalog, $newEntries) : $newEntries;
    
    // Speichere den aktualisierten Katalog
    if (file_put_contents($fineCatalogFile, json_encode($combinedCatalog, JSON_PRETTY_PRINT))) {
        return [
            'success' => true,
            'message' => count($newEntries) . ' Einträge erfolgreich importiert.' . ($merge ? ' Bestehende Einträge wurden beibehalten.' : ' Bestehende Einträge wurden überschrieben.'),
            'count' => count($newEntries)
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Fehler beim Speichern des Bußgeldkatalogs.',
            'count' => 0
        ];
    }
}

/**
 * Speichert eine hochgeladene Datei und gibt den Pfad zurück
 * 
 * @param array $uploadedFile Die hochgeladene Datei aus $_FILES
 * @param string $targetDir Das Zielverzeichnis für die Datei
 * @return array Pfad zur gespeicherten Datei oder Fehlermeldung
 */
function saveUploadedFile($uploadedFile, $targetDir = 'uploads/') {
    // Überprüfe, ob Dateien hochgeladen wurden
    if (!isset($uploadedFile) || !isset($uploadedFile['tmp_name']) || empty($uploadedFile['tmp_name'])) {
        return [
            'success' => false,
            'error' => 'Keine Datei hochgeladen.'
        ];
    }
    
    // Stelle sicher, dass das Zielverzeichnis existiert
    if (!file_exists($targetDir)) {
        if (!mkdir($targetDir, 0755, true)) {
            return [
                'success' => false,
                'error' => 'Zielverzeichnis konnte nicht erstellt werden.'
            ];
        }
    }
    
    // Generiere einen eindeutigen Dateinamen
    $filename = pathinfo($uploadedFile['name'], PATHINFO_FILENAME);
    $extension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
    $uniqueName = $filename . '_' . uniqid() . '.' . $extension;
    $targetPath = $targetDir . $uniqueName;
    
    // Verschiebe die Datei in das Zielverzeichnis
    if (!move_uploaded_file($uploadedFile['tmp_name'], $targetPath)) {
        return [
            'success' => false,
            'error' => 'Fehler beim Speichern der Datei.'
        ];
    }
    
    return [
        'success' => true,
        'path' => $targetPath,
        'name' => $uniqueName,
        'original_name' => $uploadedFile['name'],
        'size' => $uploadedFile['size'],
        'type' => $uploadedFile['type']
    ];
}

/**
 * Speichert ein Handbuchdokument und gibt den Pfad zurück
 * 
 * @param array $uploadedFile Die hochgeladene Datei aus $_FILES
 * @return array Pfad zur gespeicherten Datei oder Fehlermeldung
 */
function saveHandbookDocument($uploadedFile) {
    return saveUploadedFile($uploadedFile, 'uploads/handbook/');
}

/**
 * Verarbeitet den Import eines Google Docs-Links
 * 
 * @param string $url Die Google Docs-URL
 * @param string $title Ein optionaler Titel für das Dokument
 * @return array Status und Informationen zum importierten Dokument
 */
function processGoogleDocsLink($url, $title = '') {
    // Prüfe, ob es sich um eine Google Docs-URL handelt
    if (strpos($url, 'docs.google.com') === false) {
        return [
            'success' => false,
            'error' => 'Die URL scheint keine gültige Google Docs-URL zu sein.'
        ];
    }
    
    // Speichere den Link in einer JSON-Datei
    $handbookLinksFile = __DIR__ . '/../data/handbook_links.json';
    
    // Lade bestehende Links, falls vorhanden
    $existingLinks = [];
    if (file_exists($handbookLinksFile)) {
        $existingLinks = json_decode(file_get_contents($handbookLinksFile), true);
        if (!is_array($existingLinks)) {
            $existingLinks = [];
        }
    }
    
    // Füge den neuen Link hinzu
    $newLink = [
        'id' => uniqid(),
        'url' => $url,
        'title' => $title ?: 'Google Docs-Dokument',
        'type' => 'google_docs',
        'date_added' => date('Y-m-d H:i:s')
    ];
    
    $existingLinks[] = $newLink;
    
    // Speichere die aktualisierten Links
    if (file_put_contents($handbookLinksFile, json_encode($existingLinks, JSON_PRETTY_PRINT))) {
        return [
            'success' => true,
            'message' => 'Google Docs-Link erfolgreich gespeichert.',
            'link' => $newLink
        ];
    } else {
        return [
            'success' => false,
            'error' => 'Fehler beim Speichern des Google Docs-Links.'
        ];
    }
}

/**
 * Holt die gespeicherten Handbuch-Links
 * 
 * @return array Liste der gespeicherten Handbuch-Links
 */
function getHandbookLinks() {
    $handbookLinksFile = __DIR__ . '/../data/handbook_links.json';
    
    if (file_exists($handbookLinksFile)) {
        $links = json_decode(file_get_contents($handbookLinksFile), true);
        if (is_array($links)) {
            return $links;
        }
    }
    
    return [];
}

/**
 * Löscht einen Handbuch-Link
 * 
 * @param string $linkId Die ID des zu löschenden Links
 * @return bool True bei Erfolg, sonst False
 */
function deleteHandbookLink($linkId) {
    $handbookLinksFile = __DIR__ . '/../data/handbook_links.json';
    
    if (!file_exists($handbookLinksFile)) {
        return false;
    }
    
    $links = json_decode(file_get_contents($handbookLinksFile), true);
    if (!is_array($links)) {
        return false;
    }
    
    $found = false;
    foreach ($links as $key => $link) {
        if (isset($link['id']) && $link['id'] === $linkId) {
            unset($links[$key]);
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        return false;
    }
    
    // Reindexieren des Arrays
    $links = array_values($links);
    
    // Speichere die aktualisierten Links
    return file_put_contents($handbookLinksFile, json_encode($links, JSON_PRETTY_PRINT)) !== false;
}