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
        'civil_cases' => 'Zivilakten',
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
        'public_notes' => 'Öffentliche Notizen',
        'calendar' => 'Kalender',
        'files' => 'Dateien',
        'duty_log' => 'Dienstprotokoll',
        'vacation' => 'Urlaubsanträge',
        'address_book' => 'Adressbuch',
        'seized_assets' => 'Beschlagnahmungen',
        'business_licenses' => 'Gewerbeschein',
        'licenses' => 'Lizenzverwaltung',
        'license_categories' => 'Lizenzkategorien',
        'todos' => 'Aufgabenliste',
        'task_assignments' => 'Aufgabenverteilung',
        'evidence' => 'Beweismittel',
        'revisions' => 'Überarbeitungen',
        'justice_references' => 'Rechtsprechung'
    ];
}

/**
 * Defines all available actions for permission checking (tri-state model)
 */
function getAvailableActions() {
    return [
        'view' => 'Anzeigen',
        'edit' => 'Bearbeiten',
        'delete' => 'Löschen'
    ];
}

/**
 * Normalize action names to the tri-state model
 */
function normalizeAction($action) {
    $action = strtolower(trim((string)$action));
    switch ($action) {
        case 'view':
        case 'read':
        case 'list':
        case 'index':
        case 'show':
            return 'view';
        case 'delete':
        case 'remove':
        case 'destroy':
            return 'delete';
        default:
            return 'edit';
    }
}

/**
 * Reduce arbitrary action lists to the tri-state model
 */
function simplifyPermissionActions($actions) {
    $availableActions = getAvailableActions();
    $normalized = [];

    foreach ((array)$actions as $action) {
        $normalized[] = normalizeAction($action);
    }

    $normalized = array_values(array_unique($normalized));
    return array_values(array_intersect(array_keys($availableActions), $normalized));
}

/**
 * System roles that always receive full access
 */
function getFullAccessRoleIds() {
    return ['admin'];
}

/**
 * Build the permissions map combining system defaults and stored role permissions
 */
function getRolePermissions() {
    $availableModules = array_keys(getAvailableModules());
    $availableActions = getAvailableActions();
    $fullAccessRoles = getFullAccessRoleIds();

    $permissions = [];

    // System roles: grant full access to every module
    foreach ($fullAccessRoles as $sysRoleId) {
        $permissions[$sysRoleId] = [];
        foreach ($availableModules as $module) {
            $permissions[$sysRoleId][$module] = $availableActions;
        }
    }

    // Load stored roles from JSON (if available)
    $storedRoles = function_exists('getJsonData') ? getJsonData('roles.json') : [];
    if (is_array($storedRoles)) {
        foreach ($storedRoles as $role) {
            if (!isset($role['id'])) {
                continue;
            }

            $roleId = $role['id'];

            // Preserve full access for system roles, even if JSON tries to override
            if (in_array($roleId, $fullAccessRoles, true)) {
                continue;
            }

            $normalized = [];
            if (isset($role['permissions']) && is_array($role['permissions'])) {
                foreach ($role['permissions'] as $module => $actions) {
                    if (!in_array($module, $availableModules, true)) {
                        continue;
                    }
                    $normalized[$module] = simplifyPermissionActions($actions);
                }
            }

            $permissions[$roleId] = $normalized;
        }
    }

    return $permissions;
}

/**
 * Check if a user has permission to perform an action on a module
 */
function checkUserPermission($userId, $module, $action) {
    $user = findById('users.json', $userId);
    if (!$user) {
        return false;
    }

    // Admin flag or system role grants full access
    if (!empty($user['is_admin'])) {
        return true;
    }
    if (!empty($user['role_id']) && in_array($user['role_id'], getFullAccessRoleIds(), true)) {
        return true;
    }

    $rolePermissions = getRolePermissions();
    $action = normalizeAction($action);

    // Collect all possible role IDs for this user (primary role_id, roles array, single role)
    $roleIds = [];

    if (!empty($user['role_id'])) {
        $roleIds[] = $user['role_id'];
    }

    if (!empty($user['roles']) && is_array($user['roles'])) {
        foreach ($user['roles'] as $roleName) {
            $roleIds[] = strtolower(str_replace(' ', '_', $roleName));
        }
    }

    if (!empty($user['role'])) {
        $roleIds[] = strtolower(str_replace(' ', '_', $user['role']));
    }

    // Ensure unique list of candidate role IDs
    $roleIds = array_values(array_unique($roleIds));

    foreach ($roleIds as $roleId) {
        if (isset($rolePermissions[$roleId][$module])) {
            $storedActions = array_map(function($a) {
                if (is_numeric($a)) {
                    return ['0' => 'view', '1' => 'edit', '2' => 'delete'][(string)$a] ?? $a;
                }
                return $a;
            }, (array)$rolePermissions[$roleId][$module]);

            if (in_array($action, $storedActions, true)) {
                return true;
            }
        }

        // Legacy mapping for Administrator name to admin ID
        if ($roleId === 'administrator' && isset($rolePermissions['admin'][$module])) {
            $adminActions = array_map(function($a) {
                if (is_numeric($a)) {
                    return ['0' => 'view', '1' => 'edit', '2' => 'delete'][(string)$a] ?? $a;
                }
                return $a;
            }, (array)$rolePermissions['admin'][$module]);

            if (in_array($action, $adminActions, true)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Get all modules a user has at least view permission for
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
 * Check permission and redirect to access denied if missing
 */
function checkPermissionAndRedirect($module, $action) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . getBaseUrl() . 'login.php');
        exit;
    }

    if (!checkUserPermission($_SESSION['user_id'], $module, $action)) {
        header('Location: ' . getBaseUrl() . 'access_denied.php');
        exit;
    }
}

/**
 * Utility function to get the base URL for redirects
 */
function getBaseUrl() {
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    $baseUrl = '';

    if (strpos($scriptDir, '/modules') !== false || strpos($scriptDir, '/admin') !== false) {
        $baseUrl = '../';
    }

    return $baseUrl;
}

/**
 * Generate button attributes for permission checking
 * Returns data attributes for client-side permission validation
 */
function getPermissionButtonAttributes($module, $action) {
    if (!isset($_SESSION['user_id'])) {
        return 'data-requires-permission="' . htmlspecialchars($action) . '" data-has-permission="false" disabled';
    }

    $hasPermission = checkUserPermission($_SESSION['user_id'], $module, $action);
    
    return 'data-requires-permission="' . htmlspecialchars($action) . '" data-has-permission="' . ($hasPermission ? 'true' : 'false') . '"' . 
           (!$hasPermission ? ' disabled' : '');
}
?>