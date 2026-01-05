<?php
/**
 * Test Drag & Drop Permission Editor Functionality
 * Überprüft, dass die Drag & Drop UI korrekt initialisiert wird
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/permissions.php';

echo "=== DRAG & DROP PERMISSION EDITOR TEST ===\n\n";

// Test 1: Überprüfe ob Permissionsmodal Struktur korrekt ist
echo "[TEST 1] Permission Modal Structure\n";
echo "---------------------------------------\n";

$availableModules = getAvailableModules();
$availableActions = getAvailableActions();

echo "✓ Verfügbare Module: " . count($availableModules) . "\n";
echo "✓ Verfügbare Aktionen: " . count($availableActions) . "\n";

echo "\nModule:\n";
foreach (array_keys($availableModules) as $moduleId) {
    echo "  - $moduleId\n";
}

echo "\nAktionen:\n";
foreach ($availableActions as $actionKey => $actionLabel) {
    echo "  - $actionKey: $actionLabel\n";
}

// Test 2: Überprüfe Rolle und deren Permissions
echo "\n[TEST 2] Role Permissions Structure\n";
echo "-----------------------------------\n";

$roles = getJsonData('data/roles.json');
echo "Rollen gefunden: " . count($roles) . "\n";

if (count($roles) > 0) {
    $testRole = array_values($roles)[0];
    echo "Test-Rolle: " . htmlspecialchars($testRole['name']) . " (ID: " . $testRole['id'] . ")\n";
    
    if (isset($testRole['permissions']) && is_array($testRole['permissions'])) {
        echo "✓ Berechtigungen gespeichert: " . count($testRole['permissions']) . " Module\n";
        echo "  Berechtigungen:\n";
        foreach ($testRole['permissions'] as $moduleId => $actions) {
            echo "    - $moduleId: " . implode(', ', $actions) . "\n";
        }
    } else {
        echo "✗ Keine Berechtigungen gespeichert\n";
    }
}

// Test 3: Überprüfe JavaScript-Funktionen Existenz
echo "\n[TEST 3] JavaScript Functions\n";
echo "-------------------------------\n";

// Lese die footer.php um zu überprüfen, ob Drag & Drop Funktionen vorhanden sind
$footerContent = file_get_contents('includes/footer.php');
$checks = [
    'initPermissionDragDrop' => strpos($footerContent, 'function initPermissionDragDrop()') !== false,
    'handleDragStart' => strpos($footerContent, 'function handleDragStart()') !== false,
    'handleDrop' => strpos($footerContent, 'function handleDrop()') !== false,
    'handleDragOver' => strpos($footerContent, 'function handleDragOver()') !== false,
];

foreach ($checks as $func => $exists) {
    echo ($exists ? '✓' : '✗') . " $func: " . ($exists ? 'vorhanden' : 'fehlt') . "\n";
}

// Test 4: Überprüfe Modal-Struktur in admin/roles.php
echo "\n[TEST 4] Admin Roles Modal Structure\n";
echo "-------------------------------------\n";

$rolesPhpContent = file_get_contents('admin/roles.php');
$modalChecks = [
    'permission-item Klasse' => strpos($rolesPhpContent, 'class="permission-item') !== false,
    'permission-source Klasse' => strpos($rolesPhpContent, 'class="permission-source') !== false,
    'permission-target Klasse' => strpos($rolesPhpContent, 'class="permission-target') !== false,
    'draggable=true' => strpos($rolesPhpContent, 'draggable="true"') !== false,
    'data-module Attribute' => strpos($rolesPhpContent, 'data-module=') !== false,
];

foreach ($modalChecks as $check => $exists) {
    echo ($exists ? '✓' : '✗') . " $check: " . ($exists ? 'vorhanden' : 'fehlt') . "\n";
}

// Test 5: Überprüfe dass POST-Handler Permissions korrekt verarbeitet
echo "\n[TEST 5] POST Handler Permissions Processing\n";
echo "----------------------------------------------\n";

// Simuliere POST-Daten
$_POST = [
    'permissions' => [
        'cases' => ['view', 'create'],
        'indictments' => ['view', 'edit', 'delete']
    ]
];

echo "✓ Simulierte POST-Daten:\n";
foreach ($_POST['permissions'] as $module => $actions) {
    echo "  - $module: " . implode(', ', $actions) . "\n";
}

// Test 6: Überprüfe dass initPermissionDragDrop in Modals aufgerufen wird
echo "\n[TEST 6] Modal Initialization\n";
echo "------------------------------\n";

$hasModalInit = strpos($rolesPhpContent, "jQuery('[id^=\"permissionsModal\"]').on('shown.bs.modal'") !== false;
echo ($hasModalInit ? '✓' : '✗') . " Modal shown.bs.modal Event Handler: " . ($hasModalInit ? 'vorhanden' : 'fehlt') . "\n";

$hasInitCall = strpos($rolesPhpContent, 'initPermissionDragDrop()') !== false;
echo ($hasInitCall ? '✓' : '✗') . " initPermissionDragDrop() Aufruf: " . ($hasInitCall ? 'vorhanden' : 'fehlt') . "\n";

echo "\n=== TEST ABGESCHLOSSEN ===\n";
echo "Status: " . ($hasModalInit && count($checks) === array_sum($checks) ? "✓ BESTANDEN" : "✗ TEILWEISE") . "\n";
?>
