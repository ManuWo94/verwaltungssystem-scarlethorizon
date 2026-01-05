<?php
/**
 * Permission system for the Department of Justice application
 * This file defines permissions for different user roles
 */

/**
 * Defines all available modules and their human-readable names
 */
function getAvailableModules() {
    return [
        'admin' => 'Administrationsbereich',
        'users' => 'Benutzerverwaltung',
        'roles' => 'Rollenverwaltung',
        'cases' => 'Fallakten',
        'indictments' => 'Klageschriften',
        'appeals' => 'Revisionen',
        'defendants' => 'Angeklagte',
        'hearings' => 'Verhandlungen',
        'templates' => 'Vorlagen',
        'warrants' => 'Haftbefehle',
        'staff' => 'Mitarbeiter',
        'trainings' => 'Schulungen',
        'equipment' => 'Ausrüstung',
        'notes' => 'Notizen',
        'calendar' => 'Kalender',
        'files' => 'Dateien',
        'duty_log' => 'Dienstprotokoll',
        'vacation' => 'Urlaubsanträge',
        'address_book' => 'Adressbuch',
        'seized_assets' => 'Beschlagnahmungen',
        'business_licenses' => 'Gewerbeschein',
        'notes' => 'Notizen',
        'todos' => 'Aufgabenliste',
        'task_assignments' => 'Aufgabenverteilung'
    ];
}

/**
 * Defines all available actions for permission checking
    $permissions = [];

    // Administrator Zusatzrolle mit Vollzugriff (tri-state model)
    $availableActions = getAvailableActions();
    $permissions['admin'] = [];
    foreach (array_keys(getAvailableModules()) as $module) {
        $permissions['admin'][$module] = $availableActions;
    }
    // Junior Prosecutor
    $permissions['junior_prosecutor'] = array_merge($commonAccess, [
        'cases' => ['view', 'create', 'edit'],
        'indictments' => ['view', 'create', 'edit'],
        'appeals' => ['view', 'create', 'edit', 'appeal'],
        'defendants' => ['view', 'create', 'edit'],
        'hearings' => ['view'],
        'templates' => ['view'],
        'warrants' => ['view', 'edit'],
        'staff' => ['view'],
        'trainings' => ['view'],
        // Kein Zugriff auf Ausrüstung
        'calendar' => ['view', 'create', 'edit'],
        'files' => ['view', 'create', 'edit'],
        'vacation' => ['view', 'create'],
        'address_book' => ['view', 'create', 'edit'],
        'seized_assets' => ['view', 'create', 'edit']
    ]);
    
    // Student
    $permissions['student'] = array_merge($commonAccess, [
        // Kein Zugriff auf Fallverwaltung, Klageschriften, Angeklagte, Aktenschrank und Vorlagen
        // Nur Änderungen im Dienstprotokoll und bei Notizen erlaubt
        'staff' => ['view'],
        'trainings' => ['view'], // Nur ansehen, nicht bearbeiten
        'address_book' => ['view'], // Nur ansehen, nicht bearbeiten
        'notes' => ['view', 'create', 'edit'],
        'duty_log' => ['view', 'create', 'edit']
    ]);
    
    // Director (U.S. Marshal Service)
    $permissions['director'] = array_merge($commonAccess, [
        'staff' => ['view', 'create', 'edit'],
        'trainings' => ['view', 'create', 'edit', 'assign'],
        'equipment' => ['view', 'create', 'edit', 'assign'],
        'calendar' => ['view', 'create', 'edit'],
        'cases' => ['view'],
        'warrants' => ['view', 'edit'],
        'seized_assets' => ['view', 'create', 'edit'],
        'vacation' => ['view', 'create', 'approve', 'reject'],
        'address_book' => ['view', 'create', 'edit']
    ]);
    
    // Commander (U.S. Marshal Service)
    $permissions['commander'] = array_merge($commonAccess, [
        'staff' => ['view', 'create', 'edit'],
        'trainings' => ['view', 'create', 'edit', 'assign'],
        'equipment' => ['view', 'create', 'edit', 'assign'],
        'calendar' => ['view', 'create', 'edit'],
        'cases' => ['view'],
        'warrants' => ['view', 'edit'],
        'seized_assets' => ['view', 'create', 'edit'],
        'vacation' => ['view', 'create', 'approve', 'reject'],
        'address_book' => ['view', 'create', 'edit']
    ]);
    
    // Senior Deputy (U.S. Marshal Service)
    $permissions['senior_deputy'] = array_merge($commonAccess, [
        'staff' => ['view', 'create', 'edit'],
        'trainings' => ['view', 'create', 'edit', 'assign'],
        'equipment' => ['view', 'create', 'edit', 'assign'],
        'calendar' => ['view', 'create', 'edit'],
        'cases' => ['view'],
        'warrants' => ['view', 'edit'],
        'seized_assets' => ['view', 'create', 'edit'],
        'vacation' => ['view', 'create'],
        'address_book' => ['view', 'create', 'edit']
    ]);
    
    // Deputy (U.S. Marshal Service)
    $permissions['deputy'] = array_merge($commonAccess, [
        'staff' => ['view'],
        'trainings' => ['view'],
        // Kein Zugriff auf Ausrüstung
        'calendar' => ['view', 'create', 'edit'],
        'cases' => ['view'],
        'warrants' => ['view', 'edit'],
        'seized_assets' => ['view', 'create', 'edit'],
        'vacation' => ['view', 'create'],
        'address_book' => ['view', 'create', 'edit']
    ]);
    
    // Junior Deputy (U.S. Marshal Service)
    $permissions['junior_deputy'] = array_merge($commonAccess, [
        'staff' => ['view'],
        'trainings' => ['view'], // Nur ansehen, nicht bearbeiten
        // Kein Zugriff auf Ausrüstung
        'calendar' => ['view', 'create', 'edit'],
        'cases' => ['view'],
        'warrants' => ['view', 'edit'],
        'seized_assets' => ['view'],
        'vacation' => ['view', 'create'],
        'address_book' => ['view'] // Nur ansehen, nicht bearbeiten
    ]);
    
    // Trainee (U.S. Marshal Service)
    $permissions['trainee'] = array_merge($commonAccess, [
        // Kein Zugriff auf Fallverwaltung, Klageschriften, Angeklagte, Aktenschrank und Vorlagen
        // Nur Änderungen im Dienstprotokoll und bei Notizen erlaubt
        'staff' => ['view'],
        'trainings' => ['view'], // Nur ansehen, nicht bearbeiten
        // Kein Zugriff auf Ausrüstung
        'calendar' => ['view'],
        'vacation' => ['view', 'create'],
        'address_book' => ['view'], // Nur ansehen, nicht bearbeiten
        'notes' => ['view', 'create', 'edit'],
        'duty_log' => ['view', 'create', 'edit']
    ]);
    
    // U.S. President (External)
    $permissions['president'] = [
        'cases' => ['view'],
        'indictments' => ['view'],
        'appeals' => ['view'],
        'defendants' => ['view'],
        'hearings' => ['view'],
        'warrants' => ['view', 'edit'],
        'staff' => ['view'],
        'trainings' => ['view'],
        // Kein Zugriff auf Ausrüstung
        'calendar' => ['view'],
        'files' => ['view'],
        'address_book' => ['view'],
        'seized_assets' => ['view']
    ];
    
    // Staatssekretär (External)
    $permissions['secretary'] = [
        'cases' => ['view'],
        'indictments' => ['view'],
        'appeals' => ['view'],
        'defendants' => ['view'],
        'hearings' => ['view'],
        'warrants' => ['view', 'edit'],
        'staff' => ['view'],
        'trainings' => ['view'],
        // Kein Zugriff auf Ausrüstung
        'calendar' => ['view'],
        'files' => ['view'],
        'address_book' => ['view'],
        'seized_assets' => ['view']
    ];
    
    // Army (External)
    $permissions['army'] = [
        'cases' => ['view'],
        'calendar' => ['view'],
        'warrants' => ['view', 'edit'],
        'address_book' => ['view'],
        'seized_assets' => ['view'],
        'staff' => ['view']
    ];
    
    // Sheriff (External)
    $permissions['sheriff'] = [
        'cases' => ['view'],
        'calendar' => ['view'],
        'warrants' => ['view', 'edit'],
        'address_book' => ['view'],
        'seized_assets' => ['view'],
        'staff' => ['view']
    ];
    
    // Administrator (System-Administrator mit vollen Rechten)
    $permissions['administrator'] = [];
    $permissions['system_administrator'] = []; // Alias für System Administrator
    $availableActions = getAvailableActions();
    
    // Gib dem Administrator und System Administrator Vollzugriff auf alle Module
    foreach (array_keys(getAvailableModules()) as $module) {
        $permissions['administrator'][$module] = $availableActions;
        $permissions['system_administrator'][$module] = $availableActions;
    }
    
    // Spezielle Berechtigungen für Benutzerabmeldung hinzufügen
    $permissions['administrator']['users'][] = 'force_logout';
    $permissions['system_administrator']['users'][] = 'force_logout';
    
    // Simplify defaults to the tri-state model
    foreach ($permissions as $roleId => $modules) {
        foreach ($modules as $module => $actions) {
            $permissions[$roleId][$module] = simplifyPermissionActions($actions);
        }
    }

    // Merge with stored/overridden permissions from data/roles.json (if present)
    if (function_exists('getJsonData')) {
        $storedRoles = getJsonData('data/roles.json');
        if (is_array($storedRoles)) {
            foreach ($storedRoles as $r) {
                if (isset($r['id']) && isset($r['permissions']) && is_array($r['permissions'])) {
                    // Normalize stored permissions to tri-state
                    $normalized = [];
                    foreach ($r['permissions'] as $module => $actions) {
                        $normalized[$module] = simplifyPermissionActions($actions);
                    }
                    $permissions[$r['id']] = $normalized;
                }
            }
        }
    }

    return $permissions;
}

/**
 * Check if a user has permission to perform an action on a module
 * 
 * @param string $userId The user ID
 * @param string $module The module to check
 * @param string $action The action to check
 * @return bool True if user has permission, false otherwise
 */
function checkUserPermission($userId, $module, $action) {
    // Benutzer abrufen
    $user = findById('users.json', $userId);
    if (!$user) {
        return false;
    }
    
    // System Administrator, Administrator oder Chief Justice haben vollen Zugriff
    if (isset($user['role']) && ($user['role'] === 'System Administrator' || $user['role'] === 'Administrator' || $user['role'] === 'Chief Justice')) {
        return true;
    }
    
    // Prüfe auf Systemadministrator, Administrator oder Chief Justice in den zusätzlichen Rollen
    if (isset($user['roles']) && (in_array('Chief Justice', $user['roles']) || in_array('System Administrator', $user['roles']) || 
        in_array('Administrator', $user['roles']))) {
        return true;
    }
    
    // Get role permissions
    $rolePermissions = getRolePermissions();
    
    // Normalize requested action to tri-state model
        $action = normalizeAction($action); // Normalize action variable
    
    // Check permissions for each role the user has
    if (isset($user['roles']) && is_array($user['roles'])) {
        foreach ($user['roles'] as $roleName) {
            // Zuerst prüfen, ob eine role_id direkt im Benutzer gespeichert ist
            $roleId = isset($user['role_id']) ? $user['role_id'] : strtolower(str_replace(' ', '_', $roleName));
            
            // Check if role exists in permissions
            if (isset($rolePermissions[$roleId])) {
                // Check if module exists for this role
                if (isset($rolePermissions[$roleId][$module])) {
                    // Check if action is allowed for this module
                    if (in_array($action, $rolePermissions[$roleId][$module])) {
                        return true;
                    }
                }
            }
        }
    }
    
    // If single role is set (legacy support)
    if (isset($user['role'])) {
        // Zuerst prüfen, ob eine role_id direkt im Benutzer gespeichert ist
        $roleId = isset($user['role_id']) ? $user['role_id'] : strtolower(str_replace(' ', '_', $user['role']));
        
        if (isset($rolePermissions[$roleId])) {
            if (isset($rolePermissions[$roleId][$module])) {
                if (in_array($action, $rolePermissions[$roleId][$module])) {
                    return true;
                }
            }
        }
    }
    
    return false;
}

/**
 * Get all modules a user has access to (at least view permission)
 * 
 * @param string $userId The user ID
 * @return array List of module IDs the user can access
 */
function getAccessibleModules($userId) {
    $modules = getAvailableModules();
    $accessibleModules = [];
    
    foreach (array_keys($modules) as $moduleId) {
        if (checkUserPermission($userId, $moduleId, 'view')) {
            $accessibleModules[] = $moduleId;
        }
    }
    
    return $accessibleModules;
}

/**
 * Check if user has permission and redirect to access denied if not
 * 
 * @param string $module The module to check
 * @param string $action The action to check
 * @return void Redirects to access_denied.php if user doesn't have permission
 */
function checkPermissionAndRedirect($module, $action) {
    if (!isset($_SESSION['user_id'])) {
        // Not logged in, redirect to login
        header('Location: ' . getBaseUrl() . 'login.php');
        exit;
    }
    
    if (!checkUserPermission($_SESSION['user_id'], $module, $action)) {
        // No permission, redirect to access denied
        header('Location: ' . getBaseUrl() . 'access_denied.php');
        exit;
    }
}

/**
 * Utility function to get the base URL for redirects
 * 
 * @return string The base URL with trailing slash
 */
function getBaseUrl() {
    // Check if we're in a subdirectory
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    $baseUrl = '';
    
    // If we're in a module directory, go up one level
    if (strpos($scriptDir, '/modules') !== false || strpos($scriptDir, '/admin') !== false) {
        $baseUrl = '../';
    }
    
    return $baseUrl;
}
?>