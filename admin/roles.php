<?php
/**
 * Admin Role Management
 * Handles role CRUD operations with database/JSON storage
 */
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

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
        $roleData = [
            'name' => trim($_POST['name']),
            'description' => trim($_POST['description'])
        ];
        
        // Update existing role
        if (!empty($_POST['role_id'])) {
            $roleId = $_POST['role_id'];
            
            // Check if it's a core role and only update description
            $coreRoles = ['admin', 'prosecutor', 'judge', 'clerk'];
            if (in_array($roleId, $coreRoles)) {
                // For core roles, only update the description, not the name
                $existingRole = findById('roles.json', $roleId);
                $roleData['name'] = $existingRole['name'];
            }
            
            if (updateRecord('roles.json', $roleId, $roleData)) {
                $message = "Rolle erfolgreich aktualisiert.";
            } else {
                $error = "Fehler beim Aktualisieren der Rolle.";
            }
        } 
        // Create new role
        else {
            // Generate a unique ID for the new role
            $roleData['id'] = strtolower(str_replace(' ', '_', $roleData['name'])) . '_' . uniqid();
            
            if (insertRecord('roles.json', $roleData)) {
                $message = "Rolle erfolgreich erstellt.";
            } else {
                $error = "Fehler beim Erstellen der Rolle.";
            }
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
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>