<?php
/**
 * Admin Role Management
 * Handles role CRUD operations with database/JSON storage
 */
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';

// Debug-Informationen, um zu verstehen, warum Admin-Zugriff nicht funktioniert
$isAdmin = isAdminSession();
$sessionInfo = '';

// Erfasse Session-Informationen für Debugging
if (isset($_SESSION)) {
    $sessionInfo .= 'USER_ID: ' . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'nicht gesetzt') . "\n";
    $sessionInfo .= 'USERNAME: ' . (isset($_SESSION['username']) ? $_SESSION['username'] : 'nicht gesetzt') . "\n";
    $sessionInfo .= 'ROLE: ' . (isset($_SESSION['role']) ? $_SESSION['role'] : 'nicht gesetzt') . "\n";
    $sessionInfo .= 'ROLES: ' . (isset($_SESSION['roles']) ? implode(', ', $_SESSION['roles']) : 'nicht gesetzt') . "\n";
    $sessionInfo .= 'IS_ADMIN: ' . (isset($_SESSION['is_admin']) ? var_export($_SESSION['is_admin'], true) : 'nicht gesetzt') . "\n";
}

// In Log-Datei schreiben
error_log("Admin-Zugriff für admin/roles.php: " . ($isAdmin ? 'ERLAUBT' : 'VERWEIGERT') . "\n" . $sessionInfo);

// Administratorzugriff erzwingen
if (!$isAdmin) {
    header('Location: ../dashboard.php?error=admin_access_denied');
    exit;
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Delete role
    if (isset($_POST['delete_role']) && !empty($_POST['role_id'])) {
        $roleId = $_POST['role_id'];
        
        // Prevent deleting core roles
        $coreRoles = ['admin', 'prosecutor', 'judge', 'clerk'];
        if (in_array($roleId, $coreRoles)) {
            $error = "Systemrollen können nicht gelöscht werden.";
        } else {
            if (deleteRecord('roles.json', $roleId)) {
                $message = "Rolle erfolgreich gelöscht.";
            } else {
                $error = "Fehler beim Löschen der Rolle.";
            }
        }
    } 
    // Create or update role
    else if (isset($_POST['save_role'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);

        // Update existing role
        if (!empty($_POST['role_id'])) {
            $roleId = $_POST['role_id'];

            // Load existing role to preserve fields like permissions
            $existingRole = findById('roles.json', $roleId);
            if (!$existingRole) {
                $error = "Rolle nicht gefunden.";
            } else {
                // Check if it's a core role and only update description
                $coreRoles = ['admin', 'prosecutor', 'judge', 'clerk'];
                if (in_array($roleId, $coreRoles)) {
                    // For core roles, only update the description, not the name
                    $name = $existingRole['name'];
                }

                // Merge changes while preserving permissions and other meta
                $existingRole['name'] = $name;
                $existingRole['description'] = $description;

                if (updateRecord('roles.json', $roleId, $existingRole)) {
                    $message = "Rolle erfolgreich aktualisiert.";
                } else {
                    $error = "Fehler beim Aktualisieren der Rolle.";
                }
            }
        }
        // Create new role
        else {
            // Generate a unique ID for the new role
            $roleId = strtolower(str_replace(' ', '_', $name)) . '_' . uniqid();
            $newRole = [
                'id' => $roleId,
                'name' => $name,
                'description' => $description,
                'permissions' => []
            ];

            if (insertRecord('roles.json', $newRole)) {
                $message = "Rolle erfolgreich erstellt.";
            } else {
                $error = "Fehler beim Erstellen der Rolle.";
            }
        }
    }
    // Save Permissions for a role
    else if (isset($_POST['save_permissions'])) {
        $roleId = $_POST['role_id'] ?? '';
        $permissionsPosted = isset($_POST['permissions']) && is_array($_POST['permissions']) ? $_POST['permissions'] : [];

        // Normalize permissions: ensure arrays of unique strings
        foreach ($permissionsPosted as $module => $acts) {
            $permissionsPosted[$module] = array_values(array_unique(array_map('strval', (array)$acts)));
        }

        $existingRole = findById('roles.json', $roleId);
        if ($existingRole) {
            $existingRole['permissions'] = $permissionsPosted;
            if (updateRecord('roles.json', $roleId, $existingRole)) {
                $message = "Berechtigungen erfolgreich gespeichert.";
            } else {
                $error = "Fehler beim Speichern der Berechtigungen.";
            }
        } else {
            $error = "Rolle nicht gefunden.";
        }
    }
        

}

// Get all roles
$roles = queryRecords('roles.json');

// Get role to edit if ID is provided
$editRole = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $editRole = findById('roles.json', $_GET['edit']);
}

// Page title
$pageTitle = "Rollenverwaltung";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Rollenverwaltung</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#roleModal">
                        <span data-feather="plus-circle"></span> Neue Rolle
                    </button>
                </div>
            </div>

            <?php if (isset($message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Database Connection Status -->
            <div class="mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Datenbankstatus</h5>
                        <?php if (isDatabaseConnected()): ?>
                            <div class="alert alert-success mb-0">
                                <i class="bi bi-check-circle"></i> Datenbankverbindung aktiv. Rollendaten werden in der Datenbank gespeichert.
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning mb-0">
                                <i class="bi bi-exclamation-triangle"></i> Keine Datenbankverbindung. Rollendaten werden in JSON-Dateien gespeichert.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Roles Table -->
            <div class="table-responsive">
                <table class="table table-striped table-sm">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Beschreibung</th>
                            <th>Systemrolle</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roles as $role): 
                            $isCoreRole = in_array($role['id'], ['admin', 'prosecutor', 'judge', 'clerk']);
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($role['name']); ?></td>
                                <td><?php echo htmlspecialchars($role['description']); ?></td>
                                <td>
                                    <?php if ($isCoreRole): ?>
                                        <span class="badge bg-primary">Ja</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Nein</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?edit=<?php echo urlencode($role['id']); ?>" class="btn btn-sm btn-outline-primary">
                                        <span data-feather="edit"></span>
                                    </a>

                                    <!-- Permissions button -->
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#permissionsModal<?php echo $role['id']; ?>">
                                        <span data-feather="shield"></span>
                                    </button>

                                    <!-- Permissions Modal (always available) -->
                                    <div class="modal fade" id="permissionsModal<?php echo $role['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Berechtigungen: <?php echo htmlspecialchars($role['name']); ?></h5>
                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                                <form method="post">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="role_id" value="<?php echo $role['id']; ?>">
                                                        
                                                        <!-- Drag & Drop Permission Editor -->
                                                        <div class="permission-editor">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <h6>Module & Aktionen</h6>
                                                                    <div id="availablePermissions-<?php echo $role['id']; ?>" class="list-group permission-source" data-role-id="<?php echo $role['id']; ?>" style="min-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; padding: 10px;">
                                                                        <?php
                                                                        $availableModules = getAvailableModules();
                                                                        $availableActions = getAvailableActions();
                                                                        $rolePermissionsAll = getRolePermissions();
                                                                        $currentPerms = isset($rolePermissionsAll[$role['id']]) ? $rolePermissionsAll[$role['id']] : [];
                                                                        
                                                                        foreach ($availableModules as $moduleId => $moduleName):
                                                                            foreach ($availableActions as $actionKey => $actionLabel):
                                                                                $permId = $moduleId . ':' . $actionKey;
                                                                                $isGranted = isset($currentPerms[$moduleId]) && in_array($actionKey, $currentPerms[$moduleId]);
                                                                                
                                                                                if (!$isGranted): ?>
                                                                                    <div class="permission-item list-group-item" draggable="true" data-module="<?php echo $moduleId; ?>" data-action="<?php echo $actionKey; ?>" style="cursor: move; user-select: none;">
                                                                                        <small><strong><?php echo htmlspecialchars($moduleName); ?></strong></small><br/>
                                                                                        <small><?php echo htmlspecialchars($actionLabel); ?></small>
                                                                                    </div>
                                                                                <?php endif;
                                                                            endforeach;
                                                                        endforeach; ?>
                                                                    </div>
                                                                </div>
                                                                
                                                                <div class="col-md-6">
                                                                    <h6>Erteilte Berechtigungen</h6>
                                                                    <div id="grantedPermissions-<?php echo $role['id']; ?>" class="list-group permission-target" data-role-id="<?php echo $role['id']; ?>" style="min-height: 300px; overflow-y: auto; border: 2px solid #28a745; border-radius: 4px; padding: 10px;">
                                                                        <?php
                                                                        foreach ($availableModules as $moduleId => $moduleName):
                                                                            foreach ($availableActions as $actionKey => $actionLabel):
                                                                                $isGranted = isset($currentPerms[$moduleId]) && in_array($actionKey, $currentPerms[$moduleId]);
                                                                                
                                                                                if ($isGranted): ?>
                                                                                    <div class="permission-item list-group-item" draggable="true" data-module="<?php echo $moduleId; ?>" data-action="<?php echo $actionKey; ?>" style="cursor: move; user-select: none; background-color: #d4edda;">
                                                                                        <small><strong><?php echo htmlspecialchars($moduleName); ?></strong></small><br/>
                                                                                        <small><?php echo htmlspecialchars($actionLabel); ?></small>
                                                                                    </div>
                                                                                    <input type="hidden" name="permissions[<?php echo $moduleId; ?>][]" value="<?php echo $actionKey; ?>">
                                                                                <?php endif;
                                                                            endforeach;
                                                                        endforeach; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <style>
                                                            .permission-source:hover { background-color: #f5f5f5; }
                                                            .permission-target { background-color: #f0f9f6; }
                                                            .permission-item { padding: 8px; margin: 4px 0; background: white; border: 1px solid #ddd; cursor: move; }
                                                            .permission-item:hover { background-color: #f9f9f9; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                                                            .permission-item.dragging { opacity: 0.5; }
                                                            .permission-target.drag-over { background-color: #e8f5e9 !important; }
                                                            .permission-source.drag-over { background-color: #fce4ec !important; }
                                                        </style>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                                                        <button type="submit" name="save_permissions" class="btn btn-primary">Speichern</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if (!$isCoreRole): // Prevent deleting core roles ?>
                                        <button class="btn btn-sm btn-outline-danger" data-toggle="modal" data-target="#deleteRoleModal<?php echo $role['id']; ?>">
                                            <span data-feather="trash-2"></span>
                                        </button>
                                        
                                        <!-- Delete Confirmation Modal -->
                                        <div class="modal fade" id="deleteRoleModal<?php echo $role['id']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Rolle löschen</h5>
                                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                            <span aria-hidden="true">&times;</span>
                                                        </button>
                                                    </div>
                                                    <div class="modal-body">
                                                        Sind Sie sicher, dass Sie die Rolle <strong><?php echo htmlspecialchars($role['name']); ?></strong> löschen möchten?
                                                        Diese Aktion kann nicht rückgängig gemacht werden.
                                                    </div>
                                                    <div class="modal-footer">
                                                        <form method="post">
                                                            <input type="hidden" name="role_id" value="<?php echo $role['id']; ?>">
                                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                                                            <button type="submit" name="delete_role" class="btn btn-danger">Löschen</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Role Add/Edit Modal -->
            <div class="modal fade" id="roleModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><?php echo $editRole ? 'Rolle bearbeiten' : 'Neue Rolle'; ?></h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <form method="post">
                            <div class="modal-body">
                                <?php 
                                $isCoreRole = $editRole && in_array($editRole['id'], ['admin', 'prosecutor', 'judge', 'clerk']);
                                if ($editRole): 
                                ?>
                                    <input type="hidden" name="role_id" value="<?php echo $editRole['id']; ?>">
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <label for="name" class="form-label">Name</label>
                                    <input type="text" class="form-control" id="name" name="name" required
                                           <?php if ($isCoreRole): ?>readonly<?php endif; ?>
                                           value="<?php echo $editRole ? htmlspecialchars($editRole['name']) : ''; ?>">
                                    <?php if ($isCoreRole): ?>
                                        <div class="form-text text-muted">Systemrollennamen können nicht geändert werden.</div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Beschreibung</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"><?php 
                                        echo $editRole ? htmlspecialchars($editRole['description']) : ''; 
                                    ?></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                                <button type="submit" name="save_role" class="btn btn-primary">Speichern</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Trigger modal to open automatically when editing -->
            <?php if ($editRole): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        $('#roleModal').modal('show');
                    });
                </script>
            <?php endif; ?>
            
            <script>
                // Initialisiere Drag & Drop für alle Permission Modals
                if (typeof jQuery !== 'undefined') {
                    jQuery('[id^="permissionsModal"]').on('shown.bs.modal', function() {
                        initPermissionDragDrop();
                    });
                } else {
                    // Fallback für vanilla JavaScript
                    document.querySelectorAll('[id^="permissionsModal"]').forEach(modal => {
                        modal.addEventListener('shown.bs.modal', function() {
                            initPermissionDragDrop();
                        });
                    });
                }
            </script>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>