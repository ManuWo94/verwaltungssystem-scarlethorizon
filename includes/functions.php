<?php
/**
 * General utility functions for the Department of Justice application
 */

/**
 * Get the base path for the application
 * 
 * @return string The base path with trailing slash
 */
function getBasePath() {
    $currentScript = $_SERVER['SCRIPT_NAME'];
    $basePath = '';
    
    // Determine how many directory levels we are from the root
    $levels = substr_count($currentScript, '/');
    
    if (strpos($currentScript, '/admin/') !== false) {
        $basePath = '../';
    } elseif (strpos($currentScript, '/modules/') !== false) {
        $basePath = '../';
    } elseif ($levels > 1) {
        $basePath = str_repeat('../', $levels - 1);
    }
    
    return $basePath;
}

/**
 * Get the current page name
 * 
 * @return string The current page filename
 */
function getCurrentPage() {
    return basename($_SERVER['SCRIPT_NAME']);
}

/**
 * Generate a unique ID
 * 
 * @return string A unique ID
 */
function generateUniqueId() {
    return uniqid('', true);
}

/**
 * Generate a UUID v4
 * 
 * @return string A UUID v4 string
 */
function generateUUID() {
    // Generate 16 bytes (128 bits) of random data
    $data = random_bytes(16);
    
    // Set version to 0100
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    // Set bits 6-7 to 10
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    
    // Output the 36 character UUID
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Hilfsfunktion zum Formatieren eines Datums
 */
function formatDate($date, $format = 'd.m.Y') {
    if (empty($date)) {
        return '';
    }
    return date($format, strtotime($date));
}

/**
 * Hilfsfunktion zum Formatieren eines Datums mit Uhrzeit
 */
// Prüfen, ob die Funktion bereits definiert ist, um Konflikte zu vermeiden
if (!function_exists('formatDateTime')) {
    function formatDateTime($dateTime, $format = 'd.m.Y H:i') {
        if (empty($dateTime)) {
            return '';
        }
        return date($format, strtotime($dateTime));
    }
}

/**
 * Get the next sequential ID from an array
 * 
 * @param array $items The array of items
 * @return int The next ID
 */
function getNextId($items) {
    $maxId = 0;
    foreach ($items as $item) {
        if (isset($item['id']) && is_numeric($item['id']) && $item['id'] > $maxId) {
            $maxId = intval($item['id']);
        }
    }
    return $maxId + 1;
}

/**
 * Format a date for display (Überschrieben durch die deutsche Version oben)
 */
// Funktion wurde oben neu definiert

/**
 * Get recent cases for dashboard
 * 
 * @param int $limit The number of cases to return
 * @return array Array of recent cases
 */
function getRecentCases($limit = 5) {
    $cases = getJsonData('cases.json');
    
    // Sort cases by date (newest first)
    usort($cases, function($a, $b) {
        return strtotime($b['date_created'] ?? '0') - strtotime($a['date_created'] ?? '0');
    });
    
    // Limit to the requested number
    return array_slice($cases, 0, $limit);
}

/**
 * Get upcoming events for dashboard
 * 
 * @param int $limit The number of events to return
 * @return array Array of upcoming events
 */
function getUpcomingEvents($limit = 5) {
    $events = getJsonData('calendar.json');
    $today = date('Y-m-d');
    
    // Filter out past events
    $upcomingEvents = array_filter($events, function($event) use ($today) {
        return $event['date'] >= $today;
    });
    
    // Sort by date (soonest first)
    usort($upcomingEvents, function($a, $b) {
        return strtotime($a['date']) - strtotime($b['date']);
    });
    
    // Limit to the requested number
    return array_slice($upcomingEvents, 0, $limit);
}

/**
 * Get case count
 * 
 * @return int Total number of cases
 */
function getCaseCount() {
    $cases = getJsonData('cases.json');
    return count($cases);
}

/**
 * Findet einen Unterordner in einem Ordner oder seinen Unterordnern
 * 
 * @param array $folder Der Ordner, in dem gesucht werden soll
 * @param string $subfolderId Die ID des gesuchten Unterordners
 * @return array|null Der gefundene Unterordner oder null, wenn nicht gefunden
 */
function findSubfolder($folder, $subfolderId) {
    if (!isset($folder['subfolders']) || empty($folder['subfolders'])) {
        return null;
    }
    
    foreach ($folder['subfolders'] as $subfolder) {
        if ($subfolder['id'] === $subfolderId) {
            return $subfolder;
        }
        
        // Rekursive Suche in Unterordnern
        $result = findSubfolder($subfolder, $subfolderId);
        if ($result) {
            return $result;
        }
    }
    
    return null;
}

/**
 * Aktualisiert einen Unterordner in der Ordnerstruktur
 * 
 * @param array $parentFolder Der übergeordnete Ordner
 * @param string $subfolderId Die ID des zu aktualisierenden Unterordners
 * @param array $updatedSubfolder Die aktualisierten Unterordnerdaten
 * @return array|false Das aktualisierte parentFolder-Array oder false bei Fehler
 */
function updateSubfolder(&$parentFolder, $subfolderId, $updatedSubfolder) {
    if (!isset($parentFolder['subfolders']) || empty($parentFolder['subfolders'])) {
        return false;
    }
    
    // Suche direkten Unterordner
    foreach ($parentFolder['subfolders'] as $key => $subfolder) {
        if ($subfolder['id'] === $subfolderId) {
            // Unterordner gefunden, aktualisiere ihn
            $parentFolder['subfolders'][$key] = $updatedSubfolder;
            return true;
        }
        
        // Rekursive Suche in tieferen Unterordnern
        if (isset($subfolder['subfolders']) && !empty($subfolder['subfolders'])) {
            $result = updateSubfolder($parentFolder['subfolders'][$key], $subfolderId, $updatedSubfolder);
            if ($result) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Findet und aktualisiert einen Unterordner in der gesamten Ordnerstruktur
 * 
 * @param string $subfolderId Die ID des zu aktualisierenden Unterordners
 * @param array $updatedSubfolder Die aktualisierten Unterordnerdaten
 * @return bool Erfolg der Aktualisierung
 */
function updateNestedSubfolder($subfolderId, $updatedSubfolder) {
    $folders = loadJsonData('folders.json');
    $folderUpdated = false;
    
    foreach ($folders as $key => $folder) {
        if (updateSubfolder($folders[$key], $subfolderId, $updatedSubfolder)) {
            $folderUpdated = true;
            break;
        }
    }
    
    if ($folderUpdated) {
        return saveJsonData('folders.json', $folders);
    }
    
    return false;
}

/**
 * Get defendant count
 * 
 * @return int Total number of defendants
 */
function getDefendantCount() {
    $defendants = getJsonData('defendants.json');
    return count($defendants);
}

/**
 * Get open case count
 * 
 * @return int Number of open cases
 */
function getOpenCaseCount() {
    $cases = getJsonData('cases.json');
    $openCases = array_filter($cases, function($case) {
        return strtolower($case['status']) === 'open' || strtolower($case['status']) === 'in progress';
    });
    
    return count($openCases);
}

/**
 * Get upcoming event count
 * 
 * @return int Number of upcoming events
 */
function getUpcomingEventCount() {
    $events = getJsonData('calendar.json');
    $today = date('Y-m-d');
    
    $upcomingEvents = array_filter($events, function($event) use ($today) {
        return $event['date'] >= $today;
    });
    
    return count($upcomingEvents);
}

/**
 * Prüft, ob der aktuelle Benutzer ein Administrator ist
 * 
 * @return bool True wenn Benutzer ein Administrator ist, andernfalls false
 */
function isUserAdmin() {
    return isAdminSession();
}

/**
 * Prüft, ob der aktuelle Benutzer eine bestimmte Rolle hat
 * 
 * @param string $role Die zu prüfende Rolle
 * @return bool True wenn Benutzer die angegebene Rolle hat, andernfalls false
 */
// Diese Funktion wurde in einer Version weiter unten neu implementiert
// Hier wird sie für Abwärtskompatibilität als Alias bereitgestellt
function checkUserRoleOld($role) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // Wenn der Benutzer Admin ist, hat er alle Rollen
    if (isAdminSession()) {
        return true;
    }
    
    // Prüfe Hauptrolle
    if (isset($_SESSION['role']) && $_SESSION['role'] === $role) {
        return true;
    }
    
    // Prüfe zusätzliche Rollen, falls vorhanden
    if (isset($_SESSION['roles']) && is_array($_SESSION['roles'])) {
        return in_array($role, $_SESSION['roles']);
    }
    
    return false;
}

/**
 * Get user duty status
 * 
 * @param string $userId The user ID
 * @return bool True if user is on duty, false otherwise
 */
function getUserDutyStatus($userId) {
    $dutyLog = getJsonData('duty_log.json');
    
    // Find the most recent duty log entry for this user
    $userEntries = array_filter($dutyLog, function($entry) use ($userId) {
        return $entry['user_id'] === $userId;
    });
    
    if (empty($userEntries)) {
        return false;
    }
    
    // Sort by timestamp (newest first)
    usort($userEntries, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    // Get the most recent entry
    $latestEntry = reset($userEntries);
    
    // Return duty status
    return $latestEntry['status'] === 'on_duty';
}

/**
 * Erzwingt die Abmeldung eines Benutzers vom Dienst
 * 
 * @param string $userId Die ID des abzumeldenden Benutzers
 * @param string $adminId Die ID des Administrators, der die Abmeldung durchführt
 * @param string $reason Optional: Grund für die Abmeldung
 * @return bool True bei Erfolg, False bei Fehler
 */
function forceUserLogout($userId, $adminId, $reason = '') {
    // Überprüfen, ob der Benutzer im Dienst ist
    if (!getUserDutyStatus($userId)) {
        return false; // Benutzer ist nicht im Dienst, nichts zu tun
    }
    
    // Benutzerinformationen abrufen
    $user = getUserById($userId);
    $admin = getUserById($adminId);
    
    if (!$user || !$admin) {
        return false;
    }
    
    // Stelle die Zeitzone auf Europe/Berlin für korrekte mitteleuropäische Zeit
    date_default_timezone_set('Europe/Berlin');
    $timestamp = date('Y-m-d H:i:s');
    
    // Neuen Duty-Log-Eintrag erstellen
    $dutyEntry = [
        'id' => generateUniqueId(),
        'user_id' => $userId,
        'username' => $user['username'],
        'status' => 'off_duty',
        'timestamp' => $timestamp,
        'forced_by' => [
            'admin_id' => $adminId,
            'admin_name' => $admin['username'],
            'reason' => $reason
        ]
    ];
    
    // Duty-Log aktualisieren
    $dutyLog = getJsonData('duty_log.json');
    $dutyLog[] = $dutyEntry;
    
    return saveJsonData('duty_log.json', $dutyLog);
}

/**
 * Get user by ID
 * 
 * @param string $userId The user ID
 * @return array|null The user data or null if not found
 */
function getUserById($userId) {
    $users = getJsonData('users.json');
    
    foreach ($users as $user) {
        if ($user['id'] === $userId) {
            return $user;
        }
    }
    
    return null;
}

/**
 * Get user position or title
 * 
 * @param string $userId The user ID
 * @return string The user position/title or empty string if not found
 */
function getUserPosition($userId) {
    $user = getUserById($userId);
    
    if ($user) {
        // Return notes (which contains the position) or title field if available
        if (!empty($user['notes'])) {
            return $user['notes'];
        } elseif (!empty($user['title'])) {
            return $user['title'];
        }
    }
    
    return '';
}

/**
 * Get user signature
 * 
 * @param string $userId The user ID or user array
 * @return string The user's formatted signature
 */
function getUserSignature($userId) {
    // Überprüfen, ob $userId bereits ein Benutzer-Array ist
    if (is_array($userId)) {
        $user = $userId;
    } else {
        $user = getUserById($userId);
    }
    
    if ($user) {
        // Verwende immer den vollständigen Namen (Vor- und Nachname) statt des Benutzernamens
        if (!empty($user['first_name']) && !empty($user['last_name'])) {
            $fullName = $user['first_name'] . ' ' . $user['last_name'];
        } elseif (!empty($user['full_name'])) {
            $fullName = $user['full_name'];
        } else {
            $fullName = $user['username'];
        }
        
        // Position aus verschiedenen möglichen Feldern holen
        $position = '';
        if (!empty($user['notes'])) {
            $position = $user['notes'];
        } elseif (!empty($user['title'])) {
            $position = $user['title'];
        } elseif (!empty($user['position'])) {
            $position = $user['position'];
        } elseif (!empty($user['role'])) {
            $position = $user['role'];
        }
        
        $signature = $fullName;
        if (!empty($position)) {
            $signature .= "\n" . $position;
        }
        
        // Optional Datum hinzufügen
        $signature .= "\n" . date('d.m.Y');
        
        return $signature;
    }
    
    return '';
}

/**
 * Get user signature with year 1899 (specifically for templates)
 * 
 * @param string $userId The user ID or user array
 * @return string The user's formatted signature with year 1899
 */
function getTemplateSignature($userId) {
    // Überprüfen, ob $userId bereits ein Benutzer-Array ist
    if (is_array($userId)) {
        $user = $userId;
    } else {
        $user = getUserById($userId);
    }
    
    if ($user) {
        // Verwende immer den vollständigen Namen (Vor- und Nachname) statt des Benutzernamens
        if (!empty($user['first_name']) && !empty($user['last_name'])) {
            $fullName = $user['first_name'] . ' ' . $user['last_name'];
        } elseif (!empty($user['full_name'])) {
            $fullName = $user['full_name'];
        } else {
            $fullName = $user['username'];
        }
        
        // Position aus verschiedenen möglichen Feldern holen
        $position = '';
        if (!empty($user['notes'])) {
            $position = $user['notes'];
        } elseif (!empty($user['title'])) {
            $position = $user['title'];
        } elseif (!empty($user['position'])) {
            $position = $user['position'];
        } elseif (!empty($user['role'])) {
            $position = $user['role'];
        }
        
        $signature = $fullName;
        if (!empty($position)) {
            $signature .= "\n" . $position;
        }
        
        // Datum mit Jahr 1899 hinzufügen
        $currentDay = date('d');
        $currentMonth = date('m');
        $signature .= "\n" . $currentDay . "." . $currentMonth . ".1899";
        
        return $signature;
    }
    
    return '';
}

/**
 * Get role name by ID
 * 
 * @param string $roleId The role ID
 * @return string The role name or 'Unknown' if not found
 */
function getRoleName($roleId) {
    $roles = getJsonData('roles.json');
    
    foreach ($roles as $role) {
        if ($role['id'] === $roleId) {
            return $role['name'];
        }
    }
    
    return 'Unknown';
}

/**
 * Check if a user is an administrator
 * 
 * @param string $userId The user ID
 * @return bool True if user is an admin, false otherwise
 */
function isAdmin($userId) {
    $user = getUserById($userId);
    return $user && isset($user['is_admin']) && $user['is_admin'];
}

/**
 * Prüft, ob ein Benutzer zur Leitungsebene gehört
 * 
 * @param string $userId Die Benutzer-ID
 * @return bool True, wenn der Benutzer zur Leitungsebene gehört, sonst False
 */
function isLeadership($userId) {
    $user = getUserById($userId);
    if (!$user) return false;
    
    // Prüfe den Hauptrolle des Benutzers
    if (isset($user['role'])) {
        $leadershipRoles = ['Chief Justice', 'Oberster Richter', 'Senior Associate Justice', 
                           'Stellvertretender Oberster Richter', 'Attorney General', 
                           'Generalstaatsanwalt', 'Administrator'];
        if (in_array($user['role'], $leadershipRoles)) {
            return true;
        }
    }
    
    // Prüfe mehrere Rollen, wenn vorhanden
    if (isset($user['roles']) && is_array($user['roles'])) {
        $leadershipRoles = ['Chief Justice', 'Oberster Richter', 'Senior Associate Justice', 
                           'Stellvertretender Oberster Richter', 'Attorney General', 
                           'Generalstaatsanwalt', 'Administrator'];
        foreach ($user['roles'] as $role) {
            if (in_array($role, $leadershipRoles)) {
                return true;
            }
        }
    }
    
    return false;
}

// Die folgende Funktion wurde bereits oben definiert und wird daher hier nicht benötigt

// loadJsonData wurde nach db.php verschoben

// saveJsonData wurde nach db.php verschoben

/**
 * Holt eine Template-Vorlage aus der Datenbank anhand seiner ID
 * 
 * @param string $templateId Die ID der Vorlage
 * @return array|null Die Vorlage oder null, wenn nicht gefunden
 */
function getTemplate($templateId) {
    $templates = loadJsonData('templates.json');
    
    foreach ($templates as $template) {
        if ($template['id'] === $templateId) {
            return $template;
        }
    }
    
    return null;
}

/**
 * Sanitize input data
 * 
 * @param string $data The input to sanitize
 * @return string The sanitized input
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// findById Function wurde in includes/db.php implementiert

// queryRecords Funktion wurde in includes/db.php implementiert

// insertRecord Funktion wurde in includes/db.php implementiert

// updateRecord Funktion wurde in includes/db.php implementiert

// deleteRecord Funktion wurde in includes/db.php implementiert

/**
 * Diese Funktion wird bereits früher in der Datei implementiert
 */

/**
 * Diese Funktion wird bereits früher in der Datei implementiert
 */

/**
 * Diese Funktion wird bereits früher in der Datei implementiert
 */

// Bereits implementiert oben

/**
 * Diese Funktionen wurden bereits früher in der Datei implementiert
 */

/**
 * Überprüft den Status einer Fallakte auf Verjährung
 * Wenn eine Klageschrift eingereicht wurde, wird die Verjährung ausgesetzt
 * 
 * @param array $case Die Fallakte, die überprüft werden soll
 * @return array Die möglicherweise aktualisierte Fallakte
 */

/**
 * Übersetzt den deutschen Status in den entsprechenden englischen Standard-Status
 * 
 * @param string $status Der deutsche Status
 * @return string Der englische Status
 */
function mapStatusToEnglish($status) {
    // Mapping der deutschen Status-Werte zu englischen Status-Werten
    $statusMap = [
        // Offizielle Status-Bezeichnungen
        'Offen' => 'open',
        'In Bearbeitung' => 'in_progress',
        'Klageschrift eingereicht' => 'pending',
        'Klage angenommen' => 'accepted',
        'Terminiert' => 'scheduled',
        'Abgeschlossen' => 'completed',
        'Abgelehnt' => 'rejected',
        'Eingestellt' => 'dismissed',
        'Berufung eingelegt' => 'appealed',
        'Revision beantragt' => 'revision_requested',
        'Revision in Bearbeitung' => 'revision_in_progress',
        'Revision abgeschlossen' => 'revision_completed',
        'Revision abgelehnt' => 'revision_rejected',
        
        // Alternative deutsche Status-Bezeichnungen in der Datenbank
        'ausstehend' => 'pending',
        'angenommen' => 'accepted',
        'abgelehnt' => 'rejected',
        'terminiert' => 'scheduled',
        'abgeschlossen' => 'completed'
    ];
    
    // Wenn der Status bereits ein englischer Wert ist, gibt ihn unverändert zurück
    if (array_key_exists($status, array_flip($statusMap))) {
        return $status;
    }
    
    // Gibt den entsprechenden englischen Status zurück oder den Original-Status, wenn keine Zuordnung vorhanden ist
    return $statusMap[$status] ?? $status;
}

/**
 * Übersetzt den englischen Status in den entsprechenden deutschen Status für die Anzeige
 * 
 * @param string $status Der englische Status
 * @return string Der deutsche Status für die Anzeige
 */
function mapStatusToGerman($status) {
    // Mapping der englischen Status-Werte zu deutschen Status-Werten
    $statusMap = [
        // Fallstatus
        'open' => 'Offen',
        'in_progress' => 'In Bearbeitung',
        'pending' => 'Klageschrift eingereicht',
        'accepted' => 'Klage angenommen',
        'scheduled' => 'Terminiert',
        'completed' => 'Abgeschlossen',
        'rejected' => 'Abgelehnt',
        'dismissed' => 'Eingestellt',
        'appealed' => 'Berufung eingelegt',
        'revision_requested' => 'Revision beantragt',
        'revision_in_progress' => 'Revision in Bearbeitung',
        'revision_completed' => 'Revision abgeschlossen',
        'revision_verdict' => 'Revisionsurteil',
        'plea_deal_offered' => 'Außergerichtlicher Deal angeboten',
        'plea_deal_accepted' => 'Außergerichtlicher Deal angenommen',
        'plea_deal_rejected' => 'Außergerichtlicher Deal abgelehnt',
        
        // Ausrüstungsstatus
        'Available' => 'Verfügbar',
        'Assigned' => 'Zugewiesen',
        'Maintenance' => 'Wartung',
        'Damaged' => 'Beschädigt',
        'Retired' => 'Außer Dienst',
        'revision_rejected' => 'Revision abgelehnt',
        
        // Ausrüstungsstatus
        'available' => 'Verfügbar',
        'assigned' => 'Zugewiesen',
        'in_use' => 'In Benutzung',
        'maintenance' => 'In Wartung',
        'damaged' => 'Beschädigt',
        'lost' => 'Verloren',
        'retired' => 'Ausgemustert'
    ];
    
    // Normalisieren: Umwandlung des Status in Kleinbuchstaben für fallunabhängigen Vergleich
    $normalizedStatus = strtolower($status);
    
    // Gibt den entsprechenden deutschen Status zurück oder den Original-Status, wenn keine Zuordnung vorhanden ist
    return $statusMap[$normalizedStatus] ?? $status;
}

function checkCaseExpiration($case) {
    // Wenn der Fall bereits angenommen, abgeschlossen oder eingestellt ist, keine Verjährung prüfen
    $status = strtolower($case['status']);
    if ($status == 'accepted' || $status == 'klage angenommen' || 
        $status == 'completed' || $status == 'abgeschlossen' || 
        $status == 'dismissed' || $status == 'eingestellt' ||
        $status == 'verjährt' || $status == 'expired' ||
        $status == 'pending' || $status == 'klageschrift eingereicht') {
        return $case;
    }
    
    // Prüfe, ob es eine Klageschrift für diesen Fall gibt
    $indictments = getJsonData('indictments.json');
    $hasIndictment = false;
    
    foreach ($indictments as $indictment) {
        if (isset($indictment['case_id']) && $indictment['case_id'] === $case['id']) {
            $hasIndictment = true;
            break;
        }
    }
    
    // Wenn eine Klageschrift existiert, keine Verjährung
    if ($hasIndictment) {
        return $case;
    }
    
    // Prüfen, ob der Fall bereits in einem Status ist, bei dem keine Verjährung mehr eintreten soll
    if (isset($case['status']) && in_array(strtolower($case['status']), [
        'completed', 'abgeschlossen', 'plea_deal_accepted', 'revision_completed', 'revision abgeschlossen',
        'revision_verdict', 'revisionsurteil', 'pending', 'scheduled', 'terminiert', 'accepted', 'klage angenommen'
    ])) {
        return $case;
    }
    
    // Aktuelles Datum
    $today = time();
    // Sichere Umwandlung des Ablaufdatums, falls es vorhanden ist
    $expirationDate = isset($case['expiration_date']) && !empty($case['expiration_date']) ? 
        strtotime((string)$case['expiration_date']) : 0;
    
    // Prüfe, ob der Fall abgelaufen ist
    if ($expirationDate > 0 && $today > $expirationDate) {
        $case['status'] = 'Verjährt';
        $case['expired_date'] = date('Y-m-d H:i:s');
        $case['notes'] = "Fall automatisch als verjährt markiert am " . date('d.m.Y') . "\n\n" . ($case['notes'] ?? '');
        
        // Speichere die Änderungen
        updateRecord('cases.json', $case['id'], $case);
    }
    
    return $case;
}

/**
 * Berechnet das Rückgabedatum basierend auf dem aktuellen Datum und der Anzahl der Aufbewahrungstage
 * 
 * @param int $days Anzahl der Tage, für die etwas aufbewahrt werden soll
 * @return string Formatiertes Rückgabedatum im Format Y-m-d
 */
function calculateReturnDate($days) {
    return date('Y-m-d', strtotime("+$days days"));
}

/**
 * Zeichnet einen Änderungsverlauf auf für Beschlagnahmeprotokolle
 * 
 * @param array $protocol Das zu aktualisierende Protokoll
 * @param string $action Die durchgeführte Aktion (z.B. 'bearbeitet', 'zurückgegeben')
 * @param string $details Optionale Details zur Änderung
 * @return array Das aktualisierte Protokoll mit Änderungsverlauf
 */
function logProtocolChange($protocol, $action, $details = '') {
    if (!isset($protocol['change_log'])) {
        $protocol['change_log'] = [];
    }
    
    $user = getCurrentUser();
    $timestamp = date('Y-m-d H:i:s');
    
    $protocol['change_log'][] = [
        'user_id' => $user['id'],
        'username' => $user['username'],
        'action' => $action,
        'details' => $details,
        'timestamp' => $timestamp
    ];
    
    return $protocol;
}

/**
 * Prüft, ob der aktuelle Benutzer ein bestimmtes Protokoll löschen darf
 * 
 * @param array $protocol Das zu prüfende Protokoll
 * @return bool True, wenn der Benutzer das Protokoll löschen darf, sonst False
 */
function canDeleteProtocol($protocol) {
    return isAdminSession();
}

/**
 * Prüft, ob ein Benutzer eine bestimmte Rolle hat
 * 
 * @param string $roleName Der Name der zu prüfenden Rolle
 * @return bool True, wenn der Benutzer die Rolle hat, sonst False
 */
function hasUserRole($roleName) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $user = findById('users.json', $_SESSION['user_id']);
    if (!$user) {
        return false;
    }
    
    // Prüfe neue Rollenstruktur (Array von Rollen)
    if (isset($user['roles']) && is_array($user['roles'])) {
        foreach ($user['roles'] as $role) {
            if (strtolower($role) === strtolower($roleName)) {
                return true;
            }
        }
    }
    
    // Prüfe alte Rollenstruktur (einzelne Rolle)
    if (isset($user['role']) && strtolower($user['role']) === strtolower($roleName)) {
        return true;
    }
    
    return false;
}

/**
 * Prüft, ob der aktuell eingeloggte Benutzer die angegebene Berechtigung für das Modul hat
 * 
 * @param string $module Das zu prüfende Modul
 * @param string $action Die zu prüfende Aktion
 * @return bool True, wenn der Benutzer die Berechtigung hat, sonst False
 */
// Diese Funktion wurde nach auth.php verschoben, hier ist nur ein Hinweis für Abwärtskompatibilität
// function currentUserCan($module, $action) {
//     if (!isset($_SESSION['user_id'])) {
//         return false;
//     }
//     
//     return checkUserPermission($_SESSION['user_id'], $module, $action);
// }

/**
 * Holt den vollen Namen eines Benutzers (Vorname + Nachname)
 * 
 * @param string $userId Die ID des Benutzers
 * @return string Der volle Name des Benutzers oder leer, wenn nicht gefunden
 */
function getUserFullName($userId) {
    $user = findById('users.json', $userId);
    if (!$user) {
        return '';
    }
    
    $firstName = isset($user['first_name']) ? $user['first_name'] : '';
    $lastName = isset($user['last_name']) ? $user['last_name'] : '';
    
    return trim($firstName . ' ' . $lastName);
}
