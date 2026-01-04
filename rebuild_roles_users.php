<?php
/**
 * Datenbasis für Benutzer und Rollen neu aufbauen
 * Dieses Skript sollte nur ausgeführt werden, wenn Sie die Benutzer- und Rollendaten zurücksetzen möchten
 */
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Hilfsfunktion zum Laden von JSON-Daten
function loadJsonData($filename) {
    if (!file_exists($filename)) {
        return [];
    }
    
    $content = file_get_contents($filename);
    if (!$content) {
        return [];
    }
    
    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "JSON-Fehler: " . json_last_error_msg() . "\n";
        return [];
    }
    
    return $data;
}

// Header anzeigen
echo "===================================\n";
echo "Datenbasis-Wiederaufbau gestartet\n";
echo "===================================\n\n";

// Rollenliste aus Datenquelle laden und Berechtigungen hinzufügen
echo "Lade vorhandene Rollen...\n";
$roles = loadJsonData('data/roles.json');

if (!$roles) {
    echo "Fehler: Rollen konnten nicht geladen werden.\n";
    exit(1);
}

echo count($roles) . " Rollen geladen.\n\n";

// Stellen Sie sicher, dass jede Rolle die ID enthält
foreach ($roles as &$role) {
    if (!isset($role['id']) && isset($role['name'])) {
        $role['id'] = strtolower(str_replace(' ', '_', $role['name']));
        echo "Rolle '" . $role['name'] . "' mit ID '" . $role['id'] . "' aktualisiert.\n";
    }
    
    if (!isset($role['category'])) {
        // Ordne jede Rolle einer Kategorie zu basierend auf Namenskonventionen
        if (strpos($role['name'], 'Justice') !== false || $role['name'] === 'Administrator') {
            $role['category'] = 'Leitungsebene';
        } elseif (strpos($role['name'], 'Judge') !== false || strpos($role['name'], 'Magistrate') !== false) {
            $role['category'] = 'Richterebene';
        } elseif (strpos($role['name'], 'Attorney') !== false || strpos($role['name'], 'Prosecutor') !== false) {
            $role['category'] = 'Staatsanwaltschaft';
        } elseif (strpos($role['name'], 'Deputy') !== false || strpos($role['name'], 'Director') !== false || strpos($role['name'], 'Commander') !== false) {
            $role['category'] = 'U.S. Marshal Service';
        } else {
            $role['category'] = 'Andere';
        }
        
        echo "Kategorie '" . $role['category'] . "' zu Rolle '" . $role['name'] . "' hinzugefügt.\n";
    }
}

// Rollen speichern
echo "\nSpeichere aktualisierte Rollen...\n";
if (file_put_contents('data/roles.json', json_encode($roles, JSON_PRETTY_PRINT))) {
    echo "Rollen erfolgreich gespeichert.\n\n";
} else {
    echo "Fehler: Rollen konnten nicht gespeichert werden.\n";
    exit(1);
}

// Benutzer laden und aktualisieren
echo "Lade vorhandene Benutzer...\n";
$users = loadJsonData('data/users.json');

if (!$users) {
    echo "Fehler: Benutzer konnten nicht geladen werden.\n";
    exit(1);
}

echo count($users) . " Benutzer geladen.\n\n";

// Jetzt aktualisieren wir die Benutzer mit korrekten Rollen-IDs basierend auf den Rollennamen
$updatedCount = 0;
foreach ($users as &$user) {
    $updated = false;
    
    // Rolle aus dem 'role'-Feld ermitteln, falls vorhanden
    if (isset($user['role'])) {
        $roleName = $user['role'];
        
        // Suche die passende Rolle und deren ID
        $matchedRole = null;
        
        // Exakte Übereinstimmung der Namen suchen
        foreach ($roles as $role) {
            if ($role['name'] === $roleName && isset($role['id'])) {
                $matchedRole = $role;
                break;
            }
        }
        
        // Wenn keine exakte Übereinstimmung, suche nach ähnlichen Namen
        if (!$matchedRole) {
            // Deutsche zu englische Rollennamen-Mapping
            $germanToEnglish = [
                'Richter' => 'Judge',
                'Staatsanwalt' => 'Prosecutor',
                'Staatsanwältin' => 'Prosecutor',
                'Oberster Richter' => 'District Court Judge',
                'Administrator' => 'Administrator',
                'Chief Justice' => 'Chief Justice',
                'Direktor' => 'Director',
                'Kommandant' => 'Commander',
                'Deputy' => 'Deputy'
            ];
            
            if (isset($germanToEnglish[$roleName])) {
                $englishName = $germanToEnglish[$roleName];
                
                foreach ($roles as $role) {
                    if ($role['name'] === $englishName && isset($role['id'])) {
                        $matchedRole = $role;
                        break;
                    }
                }
            }
        }
        
        // Rolle gefunden, jetzt die IDs aktualisieren
        if ($matchedRole) {
            if (!isset($user['role_id']) || $user['role_id'] !== $matchedRole['id']) {
                $user['role_id'] = $matchedRole['id'];
                $updated = true;
                echo "Benutzer '" . $user['username'] . "' mit Rolle '" . $roleName . "' erhält role_id '" . $matchedRole['id'] . "'.\n";
            }
            
            // Stellen Sie sicher, dass roles-Array vorhanden ist
            if (!isset($user['roles']) || !is_array($user['roles']) || !in_array($matchedRole['name'], $user['roles'])) {
                $user['roles'] = [$matchedRole['name']];
                $updated = true;
                echo "Benutzer '" . $user['username'] . "' mit Rollenarray aktualisiert.\n";
            }
        } else {
            echo "Warnung: Keine passende Rolle für '" . $roleName . "' gefunden für Benutzer '" . $user['username'] . "'.\n";
        }
    }
    
    if ($updated) {
        $updatedCount++;
    }
}

echo "\n" . $updatedCount . " von " . count($users) . " Benutzern aktualisiert.\n";

// Benutzer speichern
echo "Speichere aktualisierte Benutzer...\n";
if (file_put_contents('data/users.json', json_encode($users, JSON_PRETTY_PRINT))) {
    echo "Benutzer erfolgreich gespeichert.\n\n";
} else {
    echo "Fehler: Benutzer konnten nicht gespeichert werden.\n";
    exit(1);
}

echo "===================================\n";
echo "Datenbasis-Wiederaufbau abgeschlossen\n";
echo "===================================\n";
?>