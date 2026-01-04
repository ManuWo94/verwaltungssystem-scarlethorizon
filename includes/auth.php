<?php
/**
 * Authentication functions for the Department of Justice application
 */

// Stellen Sie sicher, dass die Datenbankfunktionen verfügbar sind
require_once __DIR__ . '/db.php';

/**
 * Überprüft, ob ein Benutzer angemeldet ist
 * 
 * @return bool True, wenn der Benutzer angemeldet ist, sonst false
 */
function isUserLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Überprüft, ob ein angemeldeter Benutzer noch aktiv ist
 * Wenn der Benutzer inaktiv oder gelöscht wurde, wird er automatisch abgemeldet
 * 
 * @return bool True, wenn der Benutzer aktiv ist, sonst false
 */
function isLoggedInUserActive() {
    if (!isUserLoggedIn()) {
        return false;
    }
    
    $userId = $_SESSION['user_id'];
    $users = loadJsonData('users.json');
    $userFound = false;
    $isActive = true;
    
    foreach ($users as $user) {
        if ($user['id'] === $userId) {
            $userFound = true;
            
            // Wenn der Benutzer inaktiv oder gesperrt ist, abmelden
            if (isset($user['status']) && ($user['status'] === 'inactive' || $user['status'] === 'locked')) {
                $isActive = false;
            }
            
            break;
        }
    }
    
    // Wenn der Benutzer nicht gefunden wurde (gelöscht) oder inaktiv ist, abmelden
    if (!$userFound || !$isActive) {
        logoutUser();
        return false;
    }
    
    return true;
}

/**
 * Attempt to log in a user
 * 
 * @param string $username The username
 * @param string $password The password
 * @return array Result array with success status and user data or error message
 */
function loginUser($username, $password) {
    $users = loadJsonData('users.json');
    $result = [
        'success' => false,
        'error' => 'Ungültiger Benutzername oder Passwort.'
    ];
    
    foreach ($users as $user) {
        if ($user['username'] === $username) {
            // Check if account is locked or inactive
            if (isset($user['status'])) {
                if ($user['status'] === 'locked') {
                    $result['error'] = 'Ihr Konto wurde gesperrt. Bitte kontaktieren Sie einen Administrator.';
                    return $result;
                } elseif ($user['status'] === 'inactive') {
                    $result['error'] = 'Ihr Konto wurde deaktiviert. Bitte kontaktieren Sie einen Administrator.';
                    return $result;
                }
            }
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Hauptrolle und zusätzliche Rollen für den Benutzer ermitteln
                $primaryRole = $user['role'];
                $roleId = isset($user['role_id']) ? $user['role_id'] : '';
                $additionalRoles = isset($user['roles']) ? $user['roles'] : [];
                $allRoles = array_unique(array_merge([$primaryRole], $additionalRoles));
                
                // Überprüfe, ob der Benutzer Administratorrechte hat mit dem neuen Berechtigungssystem
                require_once __DIR__ . '/permissions.php';
                $hasAdminAccess = checkUserPermission($user['id'], 'admin', 'view');
                
                $result = [
                    'success' => true,
                    'user_id' => $user['id'],
                    'username' => $user['username'],
                    'role' => $primaryRole,
                    'role_id' => $roleId,
                    'roles' => $allRoles,
                    'is_admin' => $hasAdminAccess
                ];
                
                // Log successful login
                logLoginActivity($user['id'], true);
                
                return $result;
            }
            
            // Log failed login attempt
            logLoginActivity($user['id'], false);
            
            return $result;
        }
    }
    
    return $result;
}

/**
 * Log login activity
 * 
 * @param string $userId The user ID
 * @param bool $success Whether the login was successful
 */
function logLoginActivity($userId, $success) {
    $logEntry = [
        'id' => generateUniqueId(),
        'user_id' => $userId,
        'timestamp' => date('Y-m-d H:i:s'),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'success' => $success
    ];
    
    $loginLog = loadJsonData('login_log.json') ?: [];
    $loginLog[] = $logEntry;
    
    saveJsonData('login_log.json', $loginLog);
}

/**
 * Create a new user
 * 
 * @param array $userData The user data
 * @return array Result array with success status and message
 */
function createUser($userData) {
    // Check if username already exists
    $users = loadJsonData('users.json');
    
    foreach ($users as $user) {
        if ($user['username'] === $userData['username']) {
            return [
                'success' => false,
                'error' => 'Username already exists.'
            ];
        }
    }
    
    // Hash password
    $userData['password'] = password_hash($userData['password'], PASSWORD_DEFAULT);
    
    // Set default values
    $userData['id'] = generateUniqueId();
    $userData['status'] = $userData['status'] ?? 'active';
    $userData['date_created'] = date('Y-m-d H:i:s');
    
    // Save user
    $users[] = $userData;
    
    if (saveJsonData('users.json', $users)) {
        return [
            'success' => true,
            'message' => 'User created successfully.',
            'user_id' => $userData['id']
        ];
    }
    
    return [
        'success' => false,
        'error' => 'Failed to create user.'
    ];
}

/**
 * Update a user
 * 
 * @param string $userId The user ID
 * @param array $userData The updated user data
 * @return array Result array with success status and message
 */
function updateUser($userId, $userData) {
    $users = loadJsonData('users.json');
    $updated = false;
    
    foreach ($users as $key => $user) {
        if ($user['id'] === $userId) {
            // Check if trying to update username and it already exists
            if (isset($userData['username']) && $userData['username'] !== $user['username']) {
                foreach ($users as $existingUser) {
                    if ($existingUser['id'] !== $userId && $existingUser['username'] === $userData['username']) {
                        return [
                            'success' => false,
                            'error' => 'Username already exists.'
                        ];
                    }
                }
            }
            
            // Update password if provided
            if (isset($userData['password']) && !empty($userData['password'])) {
                $userData['password'] = password_hash($userData['password'], PASSWORD_DEFAULT);
            } else {
                // Keep existing password
                $userData['password'] = $user['password'];
            }
            
            // Preserve ID and creation date
            $userData['id'] = $userId;
            $userData['date_created'] = $user['date_created'];
            $userData['date_updated'] = date('Y-m-d H:i:s');
            
            $users[$key] = $userData;
            $updated = true;
            break;
        }
    }
    
    if ($updated && saveJsonData('users.json', $users)) {
        return [
            'success' => true,
            'message' => 'User updated successfully.'
        ];
    }
    
    return [
        'success' => false,
        'error' => 'Failed to update user.'
    ];
}

/**
 * Toggle user status (lock/unlock)
 * 
 * @param string $userId The user ID
 * @param string $status The new status ('active', 'inactive', or 'locked')
 * @return array Result array with success status and message
 */
function toggleUserStatus($userId, $status) {
    if ($userId === 'admin') {
        return [
            'success' => false,
            'error' => 'Cannot change status of the administrator account.'
        ];
    }
    
    $users = loadJsonData('users.json');
    $updated = false;
    
    foreach ($users as $key => $user) {
        if ($user['id'] === $userId) {
            $users[$key]['status'] = $status;
            $users[$key]['date_updated'] = date('Y-m-d H:i:s');
            $updated = true;
            
            // Wenn der Benutzer deaktiviert oder gesperrt wird und aktuell angemeldet ist,
            // sollte er automatisch ausgeloggt werden
            if (isset($_SESSION['user_id']) && $_SESSION['user_id'] === $userId && $status !== 'active') {
                // Da wir uns mitten in einem AJAX-Request oder einer Formularverarbeitung befinden könnten,
                // können wir nicht direkt eine Weiterleitung durchführen.
                // Wir markieren jedoch die Session für die automatische Abmeldung bei der nächsten Anfrage
                $_SESSION['force_logout'] = true;
            }
            
            break;
        }
    }
    
    if ($updated && saveJsonData('users.json', $users)) {
        // StatusText je nach Status anpassen
        $statusMap = [
            'active' => 'aktiviert',
            'inactive' => 'deaktiviert',
            'locked' => 'gesperrt'
        ];
        
        $statusText = $statusMap[$status] ?? $status;
        
        return [
            'success' => true,
            'message' => "Benutzer erfolgreich {$statusText}."
        ];
    }
    
    return [
        'success' => false,
        'error' => 'Benutzerstatus konnte nicht aktualisiert werden.'
    ];
}

/**
 * Check if current user is an admin based on session
 * This function is updated to use the new permission system
 * 
 * @return bool True if current user is an admin, false otherwise
 */
function isAdminSession() {
    // Verwende die neue Berechtigungsfunktion
    return currentUserCan('admin', 'view');
}

/**
 * Check if user has permission to perform an action
 * The implementation is now in permissions.php
 * This function is kept for backward compatibility
 * 
 * @param string $userId The user ID
 * @param string $module The module to check
 * @param string $action The action to check
 * @return bool True if user has permission, false otherwise
 */
function hasPermission($userId, $module, $action = 'view') {
    require_once __DIR__ . '/permissions.php';
    return checkUserPermission($userId, $module, $action);
}

/**
 * Check if user is logged in and redirect to login if not
 * 
 * @return void Redirects to login page if user is not logged in
 */
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        // Determine the base URL
        $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
        $redirectUrl = '';
        
        // If we're in a module directory, go up one level
        if (strpos($scriptDir, '/modules') !== false || strpos($scriptDir, '/admin') !== false) {
            $redirectUrl = '../login.php';
        } else {
            $redirectUrl = 'login.php';
        }
        
        header('Location: ' . $redirectUrl);
        exit;
    }
    
    // Überprüfe, ob der Benutzer inaktiv oder gesperrt ist
    $userId = $_SESSION['user_id'];
    // Verwende findById statt loadJsonData, weil es in der globalen Scope ist
    $user = findById('users.json', $userId);
    
    if ($user && isset($user['status'])) {
        if ($user['status'] === 'inactive' || $user['status'] === 'locked') {
            // Benutzer wurde deaktiviert oder gesperrt, Session löschen
            session_unset();
            session_destroy();
                
            // Bestimme die Basis-URL
            $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
            $redirectUrl = '';
            
            // Wenn wir in einem Modul-Verzeichnis sind, gehe eine Ebene hoch
            if (strpos($scriptDir, '/modules') !== false || strpos($scriptDir, '/admin') !== false) {
                $redirectUrl = '../access_denied.php?reason=inactive';
            } else {
                $redirectUrl = 'access_denied.php?reason=inactive';
            }
            
            header('Location: ' . $redirectUrl);
            exit;
        }
    }
}

/**
 * Überprüft, ob ein Benutzer authentifiziert ist und leitet zur Login-Seite weiter, wenn nicht
 * 
 * @return void Leitet zur Login-Seite weiter, wenn der Benutzer nicht angemeldet ist
 */
function checkUserAuthentication() {
    checkLogin(); // Use the standard checkLogin function
}

/**
 * Benutzer abmelden und Session zerstören
 */
function logoutUser() {
    // Lösche alle Session-Variablen
    $_SESSION = array();
    
    // Lösche das Session-Cookie, falls vorhanden
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Zerstöre die Session
    session_destroy();
}

/**
 * Holt die aktuellen Benutzerinformationen aus der Session
 * 
 * @return array|null Die Benutzerinformationen oder null, wenn nicht angemeldet
 */
function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    $userId = $_SESSION['user_id'];
    // Verwende findById statt direkt loadJsonData/getJsonData
    $user = findById('users.json', $userId);
    
    if ($user) {
        // Passwort aus Sicherheitsgründen entfernen
        unset($user['password']);
        return $user;
    }
    
    return null;
}

/**
 * Check if user has a specific role
 * 
 * @param string $userId The user ID
 * @param string $roleName The role name to check
 * @return bool True if user has the role, false otherwise
 */
function hasRole($userId, $roleName) {
    $user = findById('users.json', $userId);
    
    // Prüfe Hauptrolle
    if ($user && isset($user['role']) && $user['role'] === $roleName) {
        return true;
    }
    
    // Prüfe zusätzliche Rollen, falls vorhanden
    if ($user && isset($user['roles']) && is_array($user['roles'])) {
        return in_array($roleName, $user['roles']);
    }
    
    return false;
}

/**
 * Get all users
 * 
 * @return array Array of all users (with passwords removed)
 */
function getAllUsers() {
    // Use getJsonData which is defined in functions.php
    $users = getJsonData('users.json');
    
    // Remove sensitive data
    foreach ($users as &$user) {
        unset($user['password']);
    }
    
    return $users;
}

/**
 * Redirect to access denied page for users without permission
 * 
 * @param string $module The module being accessed
 * @param string $action The action being performed (default: 'view')
 * @return void Redirects to access denied page if user doesn't have permission
 */
function checkPermissionOrDie($module, $action = 'view') {
    // Make sure permissions.php is included
    require_once __DIR__ . '/permissions.php';
    
    if (!isset($_SESSION['user_id'])) {
        checkLogin();
        exit;
    }
    
    if (!hasPermission($_SESSION['user_id'], $module, $action)) {
        // Determine the base URL
        $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
        $redirectUrl = '';
        
        // If we're in a module directory, go up one level
        if (strpos($scriptDir, '/modules') !== false || strpos($scriptDir, '/admin') !== false) {
            $redirectUrl = '../access_denied.php';
        } else {
            $redirectUrl = 'access_denied.php';
        }
        
        header('Location: ' . $redirectUrl);
        exit;
    }
}

/**
 * Check if the current user has permission for a module/action
 * Useful for conditional display of UI elements
 * 
 * @param string $module The module to check
 * @param string $action The action to check
 * @return bool True if current user has permission, false otherwise
 */
function currentUserCan($module, $action = 'view') {
    // Make sure permissions.php is included
    require_once __DIR__ . '/permissions.php';
    
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // Systemadministratoren und Administrator haben IMMER Zugriff auf alles!
    if (isset($_SESSION['role']) && ($_SESSION['role'] === 'System Administrator' || $_SESSION['role'] === 'Administrator')) {
        return true;
    }
    
    // Prüfe auf Systemadministrator oder Administrator in den zusätzlichen Rollen
    if (isset($_SESSION['roles']) && is_array($_SESSION['roles']) && 
        (in_array('System Administrator', $_SESSION['roles']) || in_array('Administrator', $_SESSION['roles']))) {
        return true;
    }
    
    return checkUserPermission($_SESSION['user_id'], $module, $action);
}

/**
 * Prüft, ob ein Benutzer einen bestimmten Rollentyp hat
 *
 * @param string $role Die primäre Benutzerrolle
 * @param string $roleType Der zu prüfende Rollentyp (judge, prosecutor, leadership, etc.)
 * @return bool True, wenn der Benutzer die Rolle hat, sonst false
 */
function checkUserHasRoleType($role, $roleType) {
    $role = strtolower($role);
    // Normalisieren: Leerzeichen durch Unterstriche ersetzen und alles in Kleinbuchstaben umwandeln
    $normalizedRole = str_replace(' ', '_', $role);
    $roleType = strtolower($roleType);
    
    // Rollen nach Typen gruppieren
    $judgeRoles = ['richter', 'judge', 'magistrate', 'junior_magistrate', 'magistratsrichter', 'chief_justice', 'district_court_judge', 'chief justice', 'oberster richter', 'senior_associate_justice', 'senior associate justice'];
    $prosecutorRoles = ['staatsanwalt', 'prosecutor', 'junior_prosecutor', 'junior prosecutor', 'senior_prosecutor', 'senior prosecutor', 'district_attorney', 'district attorney', 'bezirksstaatsanwalt', 'attorney_general', 'attorney general', 'generalstaatsanwalt'];
    $leadershipRoles = ['chief_justice', 'chief justice', 'oberster richter', 'senior_associate_justice', 'senior associate justice', 'stellvertretender oberster richter', 'attorney_general', 'attorney general', 'generalstaatsanwalt', 'district_attorney', 'district attorney'];
    $marshalRoles = ['marshal', 'us marshal', 'deputy_marshal', 'deputy marshal', 'marshal director', 'director', 'commander', 'senior_deputy', 'senior deputy'];
    
    // Debug-Logging für Rolenanpassung
    error_log("Rollenprüfung - Original: '$role', Normalisiert: '$normalizedRole', Typ: $roleType");
    
    // Prüfen, zu welchem Typ die Rolle gehört
    // Wir prüfen sowohl die normalisierte als auch die originale Form der Rolle
    if ($roleType === 'judge' && (in_array($role, $judgeRoles) || in_array($normalizedRole, $judgeRoles))) {
        return true;
    } else if ($roleType === 'prosecutor' && (in_array($role, $prosecutorRoles) || in_array($normalizedRole, $prosecutorRoles))) {
        return true;
    } else if ($roleType === 'leadership' && (in_array($role, $leadershipRoles) || in_array($normalizedRole, $leadershipRoles))) {
        return true;
    } else if ($roleType === 'marshal' && (in_array($role, $marshalRoles) || in_array($normalizedRole, $marshalRoles))) {
        return true;
    }
    
    return false;
}
