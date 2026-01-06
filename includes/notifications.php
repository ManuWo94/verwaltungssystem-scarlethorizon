<?php
/**
 * Benachrichtigungssystem
 * Verwaltet Benachrichtigungen für Benutzer
 */

require_once __DIR__ . '/functions.php';

$notificationsFile = __DIR__ . '/../data/notifications.json';

/**
 * Erstellt eine neue Benachrichtigung
 * 
 * @param string $userId Benutzer-ID des Empfängers
 * @param string $type Art der Benachrichtigung (comment, task, case, etc.)
 * @param string $title Titel der Benachrichtigung
 * @param string $message Nachrichtentext
 * @param string $link Verlinkung zum betroffenen Element
 * @param string $relatedId ID des betroffenen Elements
 * @return bool
 */
function createNotification($userId, $type, $title, $message, $link = '', $relatedId = '') {
    global $notificationsFile;
    
    // Validierung
    if (empty($userId) || empty($type) || empty($title)) {
        error_log("Fehler bei Benachrichtigung: userId=$userId, type=$type, title=$title");
        return false;
    }
    
    try {
        $notifications = getJsonData($notificationsFile);
        if ($notifications === false) {
            $notifications = [];
        }
        
        $notification = [
            'id' => generateUniqueId(),
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link' => $link,
            'related_id' => $relatedId,
            'is_read' => false,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        array_unshift($notifications, $notification);
        
        $result = saveJsonData($notificationsFile, $notifications);
        
        if (!$result) {
            error_log("Fehler beim Speichern der Benachrichtigung: " . $notificationsFile);
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Exception bei createNotification: " . $e->getMessage());
        return false;
    }
}

/**
 * Holt alle Benachrichtigungen eines Benutzers
 * 
 * @param string $userId Benutzer-ID
 * @param bool $unreadOnly Nur ungelesene Benachrichtigungen
 * @param int $limit Maximale Anzahl
 * @return array
 */
function getUserNotifications($userId, $unreadOnly = false, $limit = 50) {
    global $notificationsFile;
    
    $notifications = getJsonData($notificationsFile);
    if ($notifications === false) {
        return [];
    }
    
    $userNotifications = [];
    foreach ($notifications as $notification) {
        if ($notification['user_id'] === $userId) {
            if ($unreadOnly && $notification['is_read']) {
                continue;
            }
            $userNotifications[] = $notification;
            
            if (count($userNotifications) >= $limit) {
                break;
            }
        }
    }
    
    return $userNotifications;
}

/**
 * Zählt ungelesene Benachrichtigungen eines Benutzers
 * 
 * @param string $userId Benutzer-ID
 * @param string $type Optional: Filter nach Typ
 * @return int
 */
function countUnreadNotifications($userId, $type = null) {
    global $notificationsFile;
    
    $notifications = getJsonData($notificationsFile);
    if ($notifications === false) {
        return 0;
    }
    
    $count = 0;
    foreach ($notifications as $notification) {
        if ($notification['user_id'] === $userId && !$notification['is_read']) {
            if ($type === null || $notification['type'] === $type) {
                $count++;
            }
        }
    }
    
    return $count;
}

/**
 * Markiert eine Benachrichtigung als gelesen
 * 
 * @param string $notificationId Benachrichtigungs-ID
 * @param string $userId Benutzer-ID (zur Sicherheit)
 * @return bool
 */
function markNotificationAsRead($notificationId, $userId) {
    global $notificationsFile;
    
    $notifications = getJsonData($notificationsFile);
    if ($notifications === false) {
        return false;
    }
    
    foreach ($notifications as $key => $notification) {
        if ($notification['id'] === $notificationId && $notification['user_id'] === $userId) {
            $notifications[$key]['is_read'] = true;
            return saveJsonData($notificationsFile, $notifications);
        }
    }
    
    return false;
}

/**
 * Markiert alle Benachrichtigungen eines Benutzers als gelesen
 * 
 * @param string $userId Benutzer-ID
 * @param string $type Optional: Nur bestimmten Typ markieren
 * @return bool
 */
function markAllNotificationsAsRead($userId, $type = null) {
    global $notificationsFile;
    
    $notifications = getJsonData($notificationsFile);
    if ($notifications === false) {
        return false;
    }
    
    $updated = false;
    foreach ($notifications as $key => $notification) {
        if ($notification['user_id'] === $userId && !$notification['is_read']) {
            if ($type === null || $notification['type'] === $type) {
                $notifications[$key]['is_read'] = true;
                $updated = true;
            }
        }
    }
    
    if ($updated) {
        return saveJsonData($notificationsFile, $notifications);
    }
    
    return true;
}

/**
 * Löscht alte gelesene Benachrichtigungen (älter als 30 Tage)
 * 
 * @return bool
 */
function cleanupOldNotifications() {
    global $notificationsFile;
    
    $notifications = getJsonData($notificationsFile);
    if ($notifications === false) {
        return false;
    }
    
    $thirtyDaysAgo = strtotime('-30 days');
    $filtered = [];
    
    foreach ($notifications as $notification) {
        $createdAt = strtotime($notification['created_at']);
        // Behalte ungelesene oder neue Benachrichtigungen
        if (!$notification['is_read'] || $createdAt > $thirtyDaysAgo) {
            $filtered[] = $notification;
        }
    }
    
    return saveJsonData($notificationsFile, $filtered);
}
