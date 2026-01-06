<?php
/**
 * Automatische Lizenzarchivierung und Benachrichtigungen
 * Dieses Script sollte täglich ausgeführt werden (z.B. via Cron)
 */

require_once __DIR__ . '/includes/functions.php';

// Daten laden
$licenses = loadJsonData('licenses.json');
$today = date('Y-m-d');
$changed = false;

foreach ($licenses as &$license) {
    // Nur aktive Lizenzen prüfen
    if ($license['status'] !== 'active') continue;
    
    // Abgelaufene Lizenzen archivieren
    if ($license['end_date'] < $today) {
        $license['status'] = 'archived';
        $license['archived_at'] = date('Y-m-d H:i:s');
        $changed = true;
        
        echo "Lizenz archiviert: " . $license['license_number'] . "\n";
        continue;
    }
    
    // Benachrichtigungen senden
    if ($license['notification_enabled'] && !($license['notification_sent'] ?? false)) {
        $daysUntilExpiry = (strtotime($license['end_date']) - strtotime($today)) / 86400;
        
        if ($daysUntilExpiry <= $license['notification_days_before'] && $daysUntilExpiry >= 0) {
            // Benachrichtigung erstellen
            $notification = [
                'id' => uniqid('notif_', true),
                'type' => 'license_expiring',
                'title' => 'Lizenz läuft bald ab',
                'message' => 'Die Lizenz ' . $license['license_number'] . ' läuft in ' . ceil($daysUntilExpiry) . ' Tag(en) ab.',
                'link' => 'modules/licenses.php',
                'priority' => 'warning',
                'created_at' => date('Y-m-d H:i:s'),
                'read' => false,
                'user_id' => $license['created_by']
            ];
            
            // Benachrichtigung speichern
            $notifications = loadJsonData('notifications.json');
            $notifications[] = $notification;
            saveJsonData('notifications.json', $notifications);
            
            $license['notification_sent'] = true;
            $changed = true;
            
            echo "Benachrichtigung gesendet für: " . $license['license_number'] . "\n";
        }
    }
}

// Änderungen speichern
if ($changed) {
    if (saveJsonData('licenses.json', $licenses)) {
        echo "\nLizenzdaten aktualisiert.\n";
    } else {
        echo "\nFehler beim Speichern!\n";
    }
} else {
    echo "Keine Änderungen erforderlich.\n";
}
