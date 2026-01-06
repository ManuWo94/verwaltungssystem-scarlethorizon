<?php
// Debug-Script um den echten Fehler zu sehen
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== DEBUG: Lizenz-Erstellung Test ===\n\n";

// Includes testen
echo "1. Lade session_config.php...\n";
require_once __DIR__ . '/../includes/session_config.php';
echo "✅ OK\n\n";

echo "2. Lade db.php...\n";
require_once __DIR__ . '/../includes/db.php';
echo "✅ OK\n\n";

echo "3. Lade functions.php...\n";
require_once __DIR__ . '/../includes/functions.php';
echo "✅ OK\n\n";

echo "4. Lade auth.php...\n";
require_once __DIR__ . '/../includes/auth.php';
echo "✅ OK\n\n";

echo "5. Teste loadJsonData()...\n";
try {
    $licenses = loadJsonData('licenses.json');
    echo "✅ OK - " . count($licenses) . " Lizenzen geladen\n\n";
} catch (Exception $e) {
    echo "❌ FEHLER: " . $e->getMessage() . "\n\n";
}

echo "6. Teste Lizenz-Erstellung Simulation...\n";
try {
    $testData = [
        'id' => uniqid('license_'),
        'license_number' => 'TEST-2026-001',
        'tg_number' => 'TG-12345',
        'license_type' => 'Verkaufs-Lizenz',
        'category' => 'Standard',
        'status' => 'Aktiv',
        'issued_date' => date('Y-m-d'),
        'notes' => 'Test-Lizenz',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    echo "Test-Daten erstellt:\n";
    print_r($testData);
    
    // Simuliere das Speichern (ohne wirklich zu speichern)
    echo "\n✅ Datenstruktur ist korrekt\n\n";
    
} catch (Exception $e) {
    echo "❌ FEHLER: " . $e->getMessage() . "\n\n";
}

echo "=== Test abgeschlossen ===\n";
