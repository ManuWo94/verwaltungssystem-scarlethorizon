<?php
require_once 'includes/functions.php';
require_once 'includes/db.php';

echo "=== Aktualisiere Rollen-Berechtigungen ===" . PHP_EOL . PHP_EOL;

$roles = loadJsonData('roles.json');
$updated = false;

foreach ($roles as &$role) {
    $roleName = $role['name'] ?? $role['id'];
    
    // Wenn die Rolle bereits 'cases' Berechtigung hat, füge 'civil_cases' hinzu
    if (isset($role['permissions']['cases']) && !isset($role['permissions']['civil_cases'])) {
        $role['permissions']['civil_cases'] = $role['permissions']['cases'];
        $updated = true;
        echo "✓ $roleName - civil_cases Berechtigung hinzugefügt" . PHP_EOL;
    }
    
    // Wenn die Rolle bereits 'cases' Berechtigung hat und revisions fehlt, füge es hinzu
    if (isset($role['permissions']['cases']) && !isset($role['permissions']['revisions'])) {
        $role['permissions']['revisions'] = $role['permissions']['cases'];
        $updated = true;
        echo "✓ $roleName - revisions Berechtigung hinzugefügt" . PHP_EOL;
    }
}

if ($updated) {
    saveJsonData('roles.json', $roles);
    echo PHP_EOL . "=== Erfolgreich aktualisiert! ===" . PHP_EOL;
    echo "Bitte melde dich ab und wieder an, damit die Änderungen wirksam werden." . PHP_EOL;
} else {
    echo "Keine Änderungen nötig." . PHP_EOL;
}
?>
