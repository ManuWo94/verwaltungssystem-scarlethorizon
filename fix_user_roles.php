<?php
/**
 * Dieses Skript fügt allen Benutzern role_id basierend auf ihrem Rollennamen hinzu
 */

require_once 'includes/db.php';
require_once 'includes/functions.php';

// Alle Benutzer und Rollen laden
$users = loadJsonData('users.json');
$roles = loadJsonData('roles.json');

// Funktion um die Rollen-ID aus dem Namen zu ermitteln
function getRoleIdFromName($roleName, $roles) {
    // Direkter Match nach Namen
    foreach ($roles as $role) {
        if ($role['name'] === $roleName) {
            return $role['id'];
        }
    }
    
    // Mappings für deutsche Rollennamen
    $germanRoleMap = [
        'Staatsanwalt' => 'prosecutor',
        'Richter' => 'judge',
        'Administrator' => 'administrator',
        'Vorsitzender Richter' => 'chief_justice',
        'Oberjustizinspektor' => 'chief_justice',
        'Oberstaatsanwalt' => 'senior_prosecutor',
        'Juniorstaatsanwalt' => 'junior_prosecutor',
        'Amtsrichter' => 'district_court_judge',
        'District Attorney' => 'district_attorney',
        'Senior Prosecutor' => 'senior_prosecutor',
        'Junior Prosecutor' => 'junior_prosecutor',
        'District Court Judge' => 'district_court_judge'
    ];
    
    if (isset($germanRoleMap[$roleName])) {
        return $germanRoleMap[$roleName];
    }
    
    // Standardmäßige Umwandlung von Namen zu ID (z.B. "Chief Justice" -> "chief_justice")
    $standardId = strtolower(str_replace(' ', '_', $roleName));
    
    // Prüfen ob diese ID existiert
    foreach ($roles as $role) {
        if ($role['id'] === $standardId) {
            return $standardId;
        }
    }
    
    return null;
}

// Alle Benutzer aktualisieren
$updated = false;
foreach ($users as &$user) {
    $roleName = $user['role'];
    $roleId = isset($user['role_id']) ? $user['role_id'] : getRoleIdFromName($roleName, $roles);
    
    if ($roleId) {
        $user['role_id'] = $roleId;
        $updated = true;
        echo "Benutzer {$user['username']} aktualisiert: Rolle {$roleName} -> ID {$roleId}\n";
    } else {
        echo "FEHLER: Keine passende Rollen-ID für Benutzer {$user['username']} mit Rolle {$roleName} gefunden!\n";
    }
}

// Änderungen speichern, wenn Aktualisierungen vorgenommen wurden
if ($updated) {
    if (saveJsonData('users.json', $users)) {
        echo "\nAlle Benutzer erfolgreich aktualisiert!\n";
    } else {
        echo "\nFEHLER: Konnte Änderungen nicht speichern.\n";
    }
} else {
    echo "\nKeine Änderungen vorgenommen.\n";
}