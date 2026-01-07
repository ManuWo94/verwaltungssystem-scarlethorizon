<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/permissions.php';

echo "Test startet...\n<br>";

// Simuliere Login wenn nötig
if (!isset($_SESSION['user_id'])) {
    echo "Keine Session - simuliere Admin-Login...\n<br>";
    $_SESSION['user_id'] = '1';
    $_SESSION['username'] = 'admin';
    $_SESSION['role'] = 'Administrator';
}

echo "Session OK\n<br>";

// Test Funktionen
try {
    echo "Teste getAllUsers()...\n<br>";
    $users = getAllUsers();
    echo "Users geladen: " . count($users) . "\n<br>";
    
    echo "Teste loadJsonData('parties.json')...\n<br>";
    $plaintiffs = loadJsonData('parties.json');
    echo "Plaintiffs geladen: " . count($plaintiffs) . "\n<br>";
    
    echo "Teste loadJsonData('limitations.json')...\n<br>";
    $limitations = loadJsonData('limitations.json');
    echo "Limitations geladen: " . count($limitations) . "\n<br>";
    
    echo "Teste loadJsonData('civil_cases.json')...\n<br>";
    $cases = loadJsonData('civil_cases.json');
    echo "Cases geladen: " . count($cases) . "\n<br>";
    
    echo "Teste checkCaseExpiration...\n<br>";
    if (count($cases) > 0) {
        $testCase = $cases[0];
        $result = checkCaseExpiration($testCase);
        echo "checkCaseExpiration OK\n<br>";
    }
    
    echo "Teste mapStatusToGerman...\n<br>";
    $status = mapStatusToGerman('completed');
    echo "Status mapped: $status\n<br>";
    
    echo "\n<br>Alle Tests erfolgreich!\n<br>";
    
} catch (Exception $e) {
    echo "FEHLER: " . $e->getMessage() . "\n<br>";
    echo "Trace: " . $e->getTraceAsString() . "\n<br>";
}
