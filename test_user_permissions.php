<?php
session_start();
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/permissions.php';

echo "=== BENUTZER BERECHTIGUNGS-TEST ===" . PHP_EOL . PHP_EOL;

// Prüfe ob Benutzer eingeloggt ist
if (!isset($_SESSION['user_id'])) {
    echo "❌ FEHLER: Kein Benutzer eingeloggt!" . PHP_EOL;
    echo "Bitte erst einloggen, dann diese Seite aufrufen." . PHP_EOL;
    exit;
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Unbekannt';
$role = $_SESSION['role'] ?? 'Unbekannt';

echo "Eingeloggter Benutzer:" . PHP_EOL;
echo "  ID: $userId" . PHP_EOL;
echo "  Username: $username" . PHP_EOL;
echo "  Rolle: $role" . PHP_EOL;
echo PHP_EOL;

// Lade Benutzerdaten
$user = findById('users.json', $userId);
if (!$user) {
    echo "❌ Benutzer nicht in Datenbank gefunden!" . PHP_EOL;
    exit;
}

echo "Benutzer-Details:" . PHP_EOL;
echo "  role_id: " . ($user['role_id'] ?? 'nicht gesetzt') . PHP_EOL;
echo "  is_admin: " . ($user['is_admin'] ?? 'false') . PHP_EOL;
echo PHP_EOL;

// Teste die wichtigsten Berechtigungen
$testModules = ['cases', 'civil_cases', 'revisions'];

echo "=== BERECHTIGUNGEN ===" . PHP_EOL . PHP_EOL;

foreach ($testModules as $module) {
    echo "Modul: $module" . PHP_EOL;
    
    foreach (['view', 'edit', 'delete'] as $action) {
        $hasPermission = checkUserPermission($userId, $module, $action);
        $icon = $hasPermission ? '✓' : '✗';
        echo "  $icon $action: " . ($hasPermission ? 'JA' : 'NEIN') . PHP_EOL;
    }
    
    echo PHP_EOL;
}

// Prüfe ob die Funktion currentUserCan verfügbar ist
echo "=== FUNKTION currentUserCan() TEST ===" . PHP_EOL . PHP_EOL;

if (function_exists('currentUserCan')) {
    echo "✓ Funktion currentUserCan() ist verfügbar" . PHP_EOL;
    
    echo "  currentUserCan('civil_cases', 'view'): " . (currentUserCan('civil_cases', 'view') ? 'true' : 'false') . PHP_EOL;
    echo "  currentUserCan('revisions', 'view'): " . (currentUserCan('revisions', 'view') ? 'true' : 'false') . PHP_EOL;
    echo "  currentUserCan('cases', 'edit'): " . (currentUserCan('cases', 'edit') ? 'true' : 'false') . PHP_EOL;
} else {
    echo "✗ Funktion currentUserCan() NICHT verfügbar!" . PHP_EOL;
}

echo PHP_EOL;

// Lade und zeige Rollen-Berechtigungen
echo "=== ROLLEN-BERECHTIGUNGEN ===" . PHP_EOL . PHP_EOL;

$roleId = $user['role_id'] ?? null;
if ($roleId) {
    $roles = loadJsonData('roles.json');
    $userRole = null;
    
    foreach ($roles as $r) {
        if ($r['id'] === $roleId) {
            $userRole = $r;
            break;
        }
    }
    
    if ($userRole) {
        echo "Rolle: " . ($userRole['name'] ?? $roleId) . PHP_EOL;
        echo "Berechtigungen in der Rolle:" . PHP_EOL;
        
        if (isset($userRole['permissions'])) {
            foreach (['cases', 'civil_cases', 'revisions'] as $mod) {
                if (isset($userRole['permissions'][$mod])) {
                    echo "  ✓ $mod: " . implode(', ', $userRole['permissions'][$mod]) . PHP_EOL;
                } else {
                    echo "  ✗ $mod: NICHT VORHANDEN" . PHP_EOL;
                }
            }
        } else {
            echo "  ✗ Keine Berechtigungen definiert!" . PHP_EOL;
        }
    } else {
        echo "✗ Rolle mit ID '$roleId' nicht gefunden!" . PHP_EOL;
    }
} else {
    echo "✗ Benutzer hat keine role_id!" . PHP_EOL;
}

echo PHP_EOL;
echo "=== TEST ABGESCHLOSSEN ===" . PHP_EOL;
?>
