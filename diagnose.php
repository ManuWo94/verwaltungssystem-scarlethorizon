<!DOCTYPE html>
<html>
<head>
    <title>Diagnose - Verwaltungssystem</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .section { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { border-bottom: 2px solid #333; padding-bottom: 10px; }
        pre { background: #f9f9f9; padding: 10px; border-left: 3px solid #007bff; overflow-x: auto; }
        .check { display: inline-block; width: 20px; }
    </style>
</head>
<body>
<?php
session_start();
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/permissions.php';
require_once 'includes/auth.php';

echo "<h1>🔍 System-Diagnose</h1>";

// 1. Session-Check
echo "<div class='section'>";
echo "<h2>1. Session-Status</h2>";
if (!isset($_SESSION['user_id'])) {
    echo "<p class='error'>❌ FEHLER: Kein Benutzer eingeloggt!</p>";
    echo "<p>Bitte <a href='login.php'>hier einloggen</a>, dann diese Seite erneut aufrufen.</p>";
    echo "</div></body></html>";
    exit;
}

echo "<p class='success'>✓ Benutzer ist eingeloggt</p>";
echo "<pre>";
echo "User ID: " . $_SESSION['user_id'] . "\n";
echo "Username: " . ($_SESSION['username'] ?? 'nicht gesetzt') . "\n";
echo "Rolle: " . ($_SESSION['role'] ?? 'nicht gesetzt') . "\n";
echo "Role ID: " . ($_SESSION['role_id'] ?? 'nicht gesetzt') . "\n";
echo "</pre>";
echo "</div>";

// 2. Benutzer-Daten
$userId = $_SESSION['user_id'];
$user = findById('users.json', $userId);

echo "<div class='section'>";
echo "<h2>2. Benutzer-Daten aus Datenbank</h2>";
if (!$user) {
    echo "<p class='error'>❌ Benutzer nicht in Datenbank gefunden!</p>";
} else {
    echo "<p class='success'>✓ Benutzer gefunden</p>";
    echo "<pre>";
    echo "ID: " . ($user['id'] ?? 'N/A') . "\n";
    echo "Username: " . ($user['username'] ?? 'N/A') . "\n";
    echo "role_id: " . ($user['role_id'] ?? 'NICHT GESETZT') . "\n";
    echo "role: " . ($user['role'] ?? 'NICHT GESETZT') . "\n";
    echo "is_admin: " . (isset($user['is_admin']) && $user['is_admin'] ? 'true' : 'false') . "\n";
    echo "</pre>";
}
echo "</div>";

// 3. Verfügbare Module
echo "<div class='section'>";
echo "<h2>3. Verfügbare Module</h2>";
$modules = getAvailableModules();
$relevantModules = ['cases', 'civil_cases', 'revisions'];
echo "<pre>";
foreach ($relevantModules as $mod) {
    if (isset($modules[$mod])) {
        echo "<span class='success'>✓</span> $mod => " . $modules[$mod] . "\n";
    } else {
        echo "<span class='error'>✗</span> $mod => NICHT VORHANDEN\n";
    }
}
echo "</pre>";
echo "</div>";

// 4. Rollen-Berechtigungen
echo "<div class='section'>";
echo "<h2>4. Rollen-Berechtigungen</h2>";
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
        echo "<p class='success'>✓ Rolle gefunden: " . ($userRole['name'] ?? $roleId) . "</p>";
        echo "<pre>";
        foreach ($relevantModules as $mod) {
            if (isset($userRole['permissions'][$mod])) {
                echo "<span class='success'>✓</span> $mod: " . implode(', ', $userRole['permissions'][$mod]) . "\n";
            } else {
                echo "<span class='error'>✗</span> $mod: KEINE BERECHTIGUNG\n";
            }
        }
        echo "</pre>";
    } else {
        echo "<p class='error'>❌ Rolle mit ID '$roleId' nicht in Datenbank gefunden!</p>";
    }
} else {
    echo "<p class='error'>❌ Benutzer hat keine role_id!</p>";
}
echo "</div>";

// 5. Berechtigungs-Tests
echo "<div class='section'>";
echo "<h2>5. Live Berechtigungs-Tests</h2>";
echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Modul</th><th>view</th><th>edit</th><th>delete</th></tr>";

foreach ($relevantModules as $mod) {
    echo "<tr><td><strong>$mod</strong></td>";
    
    foreach (['view', 'edit', 'delete'] as $action) {
        $result = currentUserCan($mod, $action);
        $class = $result ? 'success' : 'error';
        $icon = $result ? '✓' : '✗';
        echo "<td class='$class'>$icon " . ($result ? 'JA' : 'NEIN') . "</td>";
    }
    
    echo "</tr>";
}
echo "</table>";
echo "</div>";

// 6. Sidebar-Sichtbarkeit
echo "<div class='section'>";
echo "<h2>6. Sidebar-Sichtbarkeits-Test</h2>";
echo "<p>Diese Menüpunkte sollten in der Sidebar sichtbar sein:</p>";
echo "<ul>";

if (currentUserCan('civil_cases', 'view')) {
    echo "<li class='success'>✓ Zivilakten (sichtbar)</li>";
} else {
    echo "<li class='error'>✗ Zivilakten (NICHT sichtbar - keine Berechtigung)</li>";
}

if (currentUserCan('revisions', 'view')) {
    echo "<li class='success'>✓ Revisionen (sichtbar)</li>";
} else {
    echo "<li class='error'>✗ Revisionen (NICHT sichtbar - keine Berechtigung)</li>";
}

echo "</ul>";
echo "</div>";

// 7. Revisionsbutton-Test
echo "<div class='section'>";
echo "<h2>7. Revisionsbutton-Verfügbarkeit</h2>";
if (currentUserCan('cases', 'edit')) {
    echo "<p class='success'>✓ Revisionsbutton SOLLTE angezeigt werden (bei abgeschlossenen Fällen)</p>";
    echo "<p>Bedingung: currentUserCan('cases', 'edit') && Fall hat Status 'completed', 'rejected', 'dismissed' oder 'abgeschlossen'</p>";
} else {
    echo "<p class='error'>✗ Revisionsbutton wird NICHT angezeigt</p>";
    echo "<p>Grund: Keine Edit-Berechtigung für 'cases'</p>";
}
echo "</div>";

// 8. Empfehlungen
echo "<div class='section'>";
echo "<h2>8. Empfehlungen</h2>";

$issues = [];

if (!currentUserCan('civil_cases', 'view')) {
    $issues[] = "Du siehst keine Zivilakten, weil deine Rolle keine 'civil_cases' Berechtigung hat.";
}

if (!currentUserCan('revisions', 'view')) {
    $issues[] = "Du siehst keine Revisionen, weil deine Rolle keine 'revisions' Berechtigung hat.";
}

if (!currentUserCan('cases', 'edit')) {
    $issues[] = "Du siehst keinen Revisionsbutton, weil deine Rolle keine Edit-Berechtigung für 'cases' hat.";
}

if (empty($issues)) {
    echo "<p class='success'>✓ Alle Berechtigungen sind korrekt! Falls du die Menüpunkte nicht siehst:</p>";
    echo "<ul>";
    echo "<li>Lösche deinen Browser-Cache (Strg+F5)</li>";
    echo "<li>Melde dich ab und wieder an</li>";
    echo "<li>Prüfe die Browser-Konsole auf JavaScript-Fehler</li>";
    echo "</ul>";
} else {
    echo "<p class='warning'>⚠ Folgende Probleme wurden gefunden:</p>";
    echo "<ul>";
    foreach ($issues as $issue) {
        echo "<li class='warning'>$issue</li>";
    }
    echo "</ul>";
    echo "<p><strong>Lösung:</strong> Gehe zur <a href='admin/roles.php'>Rollen-Verwaltung</a> und füge die fehlenden Berechtigungen hinzu.</p>";
    echo "<p>Oder führe das Update-Script aus: <a href='update_role_permissions.php'>update_role_permissions.php</a></p>";
}

echo "</div>";

// 9. Git-Status
echo "<div class='section'>";
echo "<h2>9. Letzte Code-Änderungen</h2>";
echo "<pre>";
exec('cd ' . __DIR__ . ' && git log --oneline -3', $output);
foreach ($output as $line) {
    echo htmlspecialchars($line) . "\n";
}
echo "</pre>";
echo "</div>";

?>
<div class='section'>
    <h2>Test abgeschlossen</h2>
    <p>Wenn Probleme bestehen, sende bitte einen Screenshot dieser Seite.</p>
    <p><a href="dashboard.php">Zurück zum Dashboard</a></p>
</div>
</body>
</html>
