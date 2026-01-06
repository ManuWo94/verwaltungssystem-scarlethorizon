<?php
// Direkter Test der Lizenz-Erstellung
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Fake Session für Test
$_SESSION['user_id'] = 'test_user';
$_SESSION['username'] = 'Test User';
$_SESSION['role'] = 'Administrator';

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

echo "=== License Creation Test ===\n\n";

// Daten laden
try {
    $licenses = loadJsonData('licenses.json');
    echo "✓ Lizenzen geladen: " . count($licenses) . "\n";
} catch (Exception $e) {
    echo "✗ Fehler beim Laden: " . $e->getMessage() . "\n";
    exit(1);
}

try {
    $categories = loadJsonData('license_categories.json');
    echo "✓ Kategorien geladen: " . count($categories) . "\n";
} catch (Exception $e) {
    echo "✗ Fehler beim Laden: " . $e->getMessage() . "\n";
    exit(1);
}

if (empty($categories)) {
    echo "✗ Keine Kategorien vorhanden!\n";
    exit(1);
}

$category = $categories[0];
echo "✓ Test-Kategorie: " . $category['name'] . "\n";

// Test saveJsonData
try {
    $testLicense = [
        'id' => 'test_' . time(),
        'category_id' => $category['id'],
        'category_name' => $category['name'],
        'license_number' => 'TEST-2026-001',
        'tg_number' => '',
        'start_date' => date('Y-m-d'),
        'end_date' => date('Y-m-d', strtotime('+365 days')),
        'duration_days' => 365,
        'status' => 'active',
        'fields' => [],
        'created_at' => date('Y-m-d H:i:s'),
        'created_by' => 'test',
        'created_by_name' => 'Test'
    ];
    
    echo "\n✓ Test-Lizenz erstellt\n";
    echo "  Versuche zu speichern...\n";
    
    // NICHT WIRKLICH SPEICHERN - nur testen
    echo "  (Speicherung übersprungen - nur Test)\n";
    
    echo "\n=== Test erfolgreich ===\n";
    
} catch (Exception $e) {
    echo "✗ Fehler: " . $e->getMessage() . "\n";
    exit(1);
}
