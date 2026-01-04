<?php
// Script to add IDs to the fine catalog entries and ensure all required fields exist

// Path to JSON file
$fineCatalogFile = 'data/fine_catalog.json';

// Load the current data
if (!file_exists($fineCatalogFile)) {
    die("Error: File not found: $fineCatalogFile\n");
}

$fines = json_decode(file_get_contents($fineCatalogFile), true);
if (!is_array($fines)) {
    die("Error: Could not decode JSON data from $fineCatalogFile\n");
}

// Find the highest existing ID
$maxId = 0;
foreach ($fines as $fine) {
    if (isset($fine['id']) && (int)$fine['id'] > $maxId) {
        $maxId = (int)$fine['id'];
    }
}

echo "Current max ID: $maxId\n";

// Fix entries
$missingIds = 0;
$missingFields = 0;
$removedEntries = 0;

$validFines = [];

foreach ($fines as $index => $fine) {
    // Skip invalid entries
    if (!isset($fine['violation']) || empty($fine['violation'])) {
        echo "Warning: Skipping entry $index (missing violation field)\n";
        $removedEntries++;
        continue;
    }
    
    // Ensure all required fields exist
    $fieldsFixed = false;
    
    if (!isset($fine['id']) || empty($fine['id'])) {
        $maxId++;
        $fine['id'] = $maxId;
        $missingIds++;
        $fieldsFixed = true;
    }
    
    if (!isset($fine['category']) || empty($fine['category'])) {
        $fine['category'] = 'Allgemein';
        $missingFields++;
        $fieldsFixed = true;
    }
    
    if (!isset($fine['description']) || empty($fine['description'])) {
        $fine['description'] = $fine['violation'];
        $missingFields++;
        $fieldsFixed = true;
    }
    
    if (!isset($fine['amount'])) {
        $fine['amount'] = 0;
        $missingFields++;
        $fieldsFixed = true;
    }
    
    if (!isset($fine['prison_days'])) {
        $fine['prison_days'] = 0;
        $missingFields++;
        $fieldsFixed = true;
    }
    
    if (!isset($fine['notes'])) {
        $fine['notes'] = '';
        $missingFields++;
        $fieldsFixed = true;
    }
    
    if ($fieldsFixed) {
        echo "Fixed entry ID {$fine['id']}: {$fine['violation']}\n";
    }
    
    $validFines[] = $fine;
}

// Save the updated data
if (file_put_contents($fineCatalogFile, json_encode($validFines, JSON_PRETTY_PRINT))) {
    echo "\n=== Reparatur-Report ===\n";
    echo "Einträge gesamt: " . count($validFines) . "\n";
    echo "Einträge mit fehlenden IDs korrigiert: $missingIds\n";
    echo "Einträge mit fehlenden Feldern korrigiert: $missingFields\n";
    echo "Ungültige Einträge entfernt: $removedEntries\n";
    echo "Reparatur erfolgreich abgeschlossen.\n";
} else {
    echo "Error: Could not write to $fineCatalogFile\n";
}