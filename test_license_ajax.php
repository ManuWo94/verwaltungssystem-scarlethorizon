<?php
// Test-Script um AJAX-Probleme zu debuggen
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== License AJAX Debug Test ===\n\n";

// Session starten
session_start();

// Includes laden
require_once __DIR__ . '/includes/functions.php';

echo "1. Functions geladen\n";

// Daten laden
try {
    $licenses = loadJsonData('licenses.json');
    echo "2. Lizenzen geladen: " . count($licenses) . " Einträge\n";
} catch (Exception $e) {
    echo "2. FEHLER beim Laden von Lizenzen: " . $e->getMessage() . "\n";
}

try {
    $categories = loadJsonData('license_categories.json');
    echo "3. Kategorien geladen: " . count($categories) . " Einträge\n";
} catch (Exception $e) {
    echo "3. FEHLER beim Laden von Kategorien: " . $e->getMessage() . "\n";
}

// Test: Kategorie finden
if (!empty($categories)) {
    $testCategory = $categories[0];
    echo "4. Test-Kategorie: " . ($testCategory['name'] ?? 'N/A') . "\n";
    echo "   - ID: " . ($testCategory['id'] ?? 'N/A') . "\n";
    echo "   - Schema: " . ($testCategory['number_schema'] ?? 'N/A') . "\n";
    echo "   - Fields: " . (isset($testCategory['fields']) ? count($testCategory['fields']) : 0) . "\n";
}

echo "\n=== Test abgeschlossen ===\n";
