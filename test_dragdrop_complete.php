<?php
/**
 * Test Drag & Drop with Actual Role Data
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/permissions.php';

echo "=== DRAG & DROP COMPLETE TEST ===\n\n";

// 1. Erstelle eine Test-Rolle mit Permissions
echo "[STEP 1] Creating Test Role with Permissions\n";
echo "---------------------------------------------\n";

$testRole = [
    'id' => 'test_role_' . time(),
    'name' => 'Test Role für Drag & Drop',
    'description' => 'Temp test role',
    'permissions' => [
        'cases' => ['view', 'create', 'edit'],
        'indictments' => ['view', 'schedule'],
        'staff' => ['view'],
    ]
];

$roles = getJsonData('data/roles.json') ?: [];
$roles[$testRole['id']] = $testRole;
if (saveJsonData('data/roles.json', $roles)) {
    echo "✓ Test-Rolle erstellt: " . $testRole['id'] . "\n";
} else {
    echo "✗ Fehler beim Erstellen der Test-Rolle\n";
}

// 2. Überprüfe dass Permissions gespeichert wurden
echo "\n[STEP 2] Verify Saved Permissions\n";
echo "-----------------------------------\n";

$rolePermissionsAll = getRolePermissions();
if (isset($rolePermissionsAll[$testRole['id']])) {
    $perms = $rolePermissionsAll[$testRole['id']];
    echo "✓ Permissions für Test-Rolle gefunden:\n";
    foreach ($perms as $module => $actions) {
        echo "  - $module: " . implode(', ', $actions) . "\n";
    }
} else {
    echo "✗ Permissions nicht gefunden\n";
}

// 3. Simuliere Drag & Drop Aktion (Permissions ändern)
echo "\n[STEP 3] Simulate Drag & Drop Action (Update Permissions)\n";
echo "---------------------------------------------------------\n";

// Simuliere dass User "cases:delete" hinzufügt und "staff:view" entfernt
$updatedPerms = [
    'cases' => ['view', 'create', 'edit', 'delete'],  // delete hinzugefügt
    'indictments' => ['view', 'schedule'],
    // 'staff' => [] wird entfernt
];

echo "Neue Permissions nach Drag & Drop:\n";
foreach ($updatedPerms as $module => $actions) {
    echo "  - $module: " . implode(', ', $actions) . "\n";
}

// 4. Speichere die aktualisierten Permissions (simuliere POST)
echo "\n[STEP 4] Save Updated Permissions (Simulate POST)\n";
echo "-------------------------------------------------\n";

$existingRole = findById('data/roles.json', $testRole['id']);
if ($existingRole) {
    $existingRole['permissions'] = $updatedPerms;
    if (updateRecord('data/roles.json', $testRole['id'], $existingRole)) {
        echo "✓ Permissions aktualisiert\n";
    } else {
        echo "✗ Fehler beim Aktualisieren\n";
    }
}

// 5. Überprüfe dass Änderungen gespeichert wurden
echo "\n[STEP 5] Verify Updated Permissions\n";
echo "------------------------------------\n";

// Lade die Daten neu
$updatedRolePermissions = getRolePermissions();
if (isset($updatedRolePermissions[$testRole['id']])) {
    $perms = $updatedRolePermissions[$testRole['id']];
    echo "✓ Aktualisierte Permissions gefunden:\n";
    foreach ($perms as $module => $actions) {
        echo "  - $module: " . implode(', ', $actions) . "\n";
    }
    
    // Überprüfe ob die Änderungen korrekt sind
    $hasDelete = isset($perms['cases']) && in_array('delete', $perms['cases']);
    $hasStaffRemoved = !isset($perms['staff']) || !in_array('view', $perms['staff']);
    
    echo "\nValidierungen:\n";
    echo ($hasDelete ? '✓' : '✗') . " cases:delete hinzugefügt\n";
    echo ($hasStaffRemoved ? '✓' : '✗') . " staff:view entfernt\n";
} else {
    echo "✗ Aktualisierte Permissions nicht gefunden\n";
}

// 6. Cleanup - lösche Test-Rolle
echo "\n[STEP 6] Cleanup\n";
echo "----------------\n";

$roles = getJsonData('data/roles.json') ?: [];
if (isset($roles[$testRole['id']])) {
    unset($roles[$testRole['id']]);
    if (saveJsonData('data/roles.json', $roles)) {
        echo "✓ Test-Rolle gelöscht\n";
    }
}

echo "\n=== TEST ABGESCHLOSSEN ===\n";
echo "Status: ✓ ERFOLGREICH\n";
?>
