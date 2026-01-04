<?php
/**
 * Aktenverwaltungssystem - Department of Justice
 * Benutzerverwaltungsmodul
 * 
 * Dieses Modul ermöglicht Administratoren die Verwaltung von Benutzern,
 * einschließlich Erstellung, Bearbeitung und Löschung von Benutzerkonten.
 */

session_start();
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Debug-Ausgabe für Administratorzugriff
error_log("Admin-Zugriff für admin/users.php: " . (isAdminSession() ? "ERLAUBT" : "VERWEIGERT"));
error_log("USER_ID: " . ($_SESSION['user_id'] ?? 'nicht gesetzt'));
error_log("USERNAME: " . ($_SESSION['username'] ?? 'nicht gesetzt'));
error_log("ROLE: " . ($_SESSION['role'] ?? 'nicht gesetzt'));
error_log("ROLES: " . (isset($_SESSION['roles']) ? implode(', ', $_SESSION['roles']) : 'nicht gesetzt'));
error_log("IS_ADMIN: " . (isset($_SESSION['is_admin']) ? ($_SESSION['is_admin'] ? 'true' : 'false') : 'nicht gesetzt'));

// Stelle sicher, dass nur Administratoren Zugriff haben
if (!isAdminSession()) {
    header('Location: ../dashboard.php');
    exit;
}

$message = '';
$error = '';

// Zugriff auf Session-Benachrichtigungen
if (isset($_SESSION['admin_message'])) {
    $message = $_SESSION['admin_message'];
    unset($_SESSION['admin_message']);
}

if (isset($_SESSION['admin_error'])) {
    $error = $_SESSION['admin_error'];
    unset($_SESSION['admin_error']);
}

// Alle Benutzer laden
$users = getJsonData('users.json');

// Alle Rollen laden
$roles = getJsonData('roles.json');

/**
 * Hilfsfunktion zum Finden der Rollen-ID anhand des Rollennamens
 * 
 * @param string $roleName Der Rollenname
 * @return string|null Die Rollen-ID oder null, wenn nicht gefunden
 */
function getRoleIdByName($roleName) {
    global $roles;
    
    foreach ($roles as $role) {
        if ($role['name'] === $roleName) {
            return $role['id'];
        }
    }
    
    // Standardwert für Legacy-Rollen zurückgeben
    if ($roleName === 'Administrator') {
        return 'administrator';
    }
    
    // Konvertieren zu einem verwandten Format (z.B. "Chief Justice" -> "chief_justice")
    $standardizedId = strtolower(str_replace(' ', '_', $roleName));
    
    // Prüfen, ob eine solche ID existiert
    foreach ($roles as $role) {
        if ($role['id'] === $standardizedId) {
            return $standardizedId;
        }
    }
    
    return null;
}

// Verarbeitung von Formularübermittlungen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Benutzer hinzufügen oder aktualisieren
    if (isset($_POST['action']) && ($_POST['action'] === 'add' || $_POST['action'] === 'update')) {
        $isUpdate = ($_POST['action'] === 'update');
        $userId = $isUpdate ? sanitize($_POST['user_id']) : generateUniqueId();
        
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $role = sanitize($_POST['role'] ?? '');
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName = sanitize($_POST['last_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $badgeNumber = sanitize($_POST['badge_number'] ?? '');
        $title = sanitize($_POST['title'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');
        $status = sanitize($_POST['status'] ?? 'active');
        
        // Validierung
        if (empty($username)) {
            $error = 'Der Benutzername darf nicht leer sein.';
        } elseif (!$isUpdate && empty($password)) {
            $error = 'Das Passwort darf nicht leer sein.';
        } elseif (!empty($password) && $password !== $confirmPassword) {
            $error = 'Die Passwörter stimmen nicht überein.';
        } elseif (empty($role)) {
            $error = 'Bitte eine Rolle auswählen.';
        } else {
            // Überprüfen, ob der Benutzername bereits existiert (bei Neuanlage)
            if (!$isUpdate) {
                $usernameExists = false;
                foreach ($users as $existingUser) {
                    if ($existingUser['username'] === $username) {
                        $usernameExists = true;
                        break;
                    }
                }
                
                if ($usernameExists) {
                    $error = 'Dieser Benutzername existiert bereits.';
                }
            }
            
            if (empty($error)) {
                // Bestehendes Benutzerobjekt für Updates finden
                $userData = null;
                if ($isUpdate) {
                    foreach ($users as $existingUser) {
                        if ($existingUser['id'] === $userId) {
                            $userData = $existingUser;
                            break;
                        }
                    }
                    
                    if (!$userData) {
                        $error = 'Benutzer nicht gefunden.';
                    }
                }
                
                if (empty($error)) {
                    // Benutzer erstellen oder aktualisieren
                    if (!$isUpdate) {
                        $userData = [
                            'id' => $userId,
                            'username' => $username,
                            'date_created' => date('Y-m-d H:i:s')
                        ];
                    }
                    
                    // Gemeinsame Felder für Erstellung und Aktualisierung
                    // Suche den Rollennamen anhand der Rollen-ID
                    $roleName = "";
                    foreach ($roles as $roleItem) {
                        if ($roleItem['id'] === $role) {
                            $roleName = $roleItem['name'];
                            break;
                        }
                    }
                    
                    if (empty($roleName)) {
                        $error = 'Ungültige Rolle ausgewählt.';
                    } else {
                        // Nur fortfahren, wenn ein gültiger Rollenname gefunden wurde
                        $userData['role'] = $roleName; // Speichere den Rollennamen für Abwärtskompatibilität
                        $userData['roles'] = [$roleName]; // Für zukünftige Mehrfachrollen-Unterstützung
                        $userData['role_id'] = $role; // Speichere auch die Rollen-ID
                        
                        // Prüfe auf Administratorzugriff anhand der Berechtigungen
                        $hasAdminAccess = false;
                        foreach ($roles as $r) {
                            if ($r['id'] === $role && isset($r['permissions']['admin']['view']) && $r['permissions']['admin']['view'] === true) {
                                $hasAdminAccess = true;
                                break;
                            }
                        }
                        $userData['is_admin'] = $hasAdminAccess;
                        $userData['status'] = $status;
                        $userData['first_name'] = $firstName;
                        $userData['last_name'] = $lastName;
                        $userData['email'] = $email;
                        $userData['badge_number'] = $badgeNumber;
                        $userData['title'] = $title;
                        $userData['notes'] = $notes;
                        
                        // Wenn ein neues Passwort angegeben wurde, aktualisiere es
                        if (!empty($password)) {
                            $userData['password'] = password_hash($password, PASSWORD_DEFAULT);
                        }
                        
                        // Speichere den Benutzer
                        if ($isUpdate) {
                            if (updateRecord('users.json', $userId, $userData)) {
                                $message = 'Benutzer wurde erfolgreich aktualisiert.';
                                
                                // Benutzer neu laden, um die Änderung anzuzeigen
                                $users = getJsonData('users.json');
                            } else {
                                $error = 'Fehler beim Aktualisieren des Benutzers.';
                            }
                        } else {
                            if (insertRecord('users.json', $userData)) {
                                $message = 'Benutzer wurde erfolgreich erstellt.';
                                
                                // Benutzer neu laden, um den neuen Benutzer anzuzeigen
                                $users = getJsonData('users.json');
                            } else {
                                $error = 'Fehler beim Erstellen des Benutzers.';
                            }
                        }
                    }
                }
            }
        }
    }
    // Benutzer löschen
    elseif (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['user_id'])) {
        $userId = sanitize($_POST['user_id']);
        
        // Benutzerinformationen abrufen
        $userToDelete = null;
        foreach ($users as $userItem) {
            if ($userItem['id'] === $userId) {
                $userToDelete = $userItem;
                break;
            }
        }

        // Verhindere das Löschen des eigenen Kontos
        if ($userId === $_SESSION['user_id']) {
            $error = 'Sie können Ihr eigenes Konto nicht löschen.';
        } 
        // Verhindere das Löschen des Chief Justice
        elseif ($userToDelete && isset($userToDelete['role_id']) && $userToDelete['role_id'] === 'chief_justice') {
            $error = 'Der Chief Justice kann nicht gelöscht werden.';
        } else {
            if (deleteRecord('users.json', $userId)) {
                $message = 'Benutzer wurde erfolgreich gelöscht.';
                
                // Benutzer neu laden, um die Änderung anzuzeigen
                $users = getJsonData('users.json');
            } else {
                $error = 'Fehler beim Löschen des Benutzers.';
            }
        }
    }
}

// Seitentitel
$pageTitle = "Benutzerverwaltung";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Benutzerverwaltung</h1>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Benutzer</h4>
                        <button type="button" class="btn btn-success" data-toggle="modal" data-target="#addUserModal">
                            <i class="fa fa-plus"></i> Neuen Benutzer hinzufügen
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="roleFilter" class="form-label">Nach Rollenkategorie filtern:</label>
                                <select class="form-control" id="roleFilter">
                                    <option value="">Alle Kategorien anzeigen</option>
                                    <?php
                                    $categories = [];
                                    foreach ($roles as $role) {
                                        if (isset($role['category']) && !in_array($role['category'], $categories)) {
                                            $categories[] = $role['category'];
                                        }
                                    }
                                    sort($categories);
                                    foreach ($categories as $category): 
                                    ?>
                                        <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="statusFilter" class="form-label">Nach Status filtern:</label>
                                <select class="form-control" id="statusFilter">
                                    <option value="">Alle Status anzeigen</option>
                                    <option value="active">Aktiv</option>
                                    <option value="inactive">Inaktiv</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="searchInput" class="form-label">Suche:</label>
                                <input type="text" class="form-control" id="searchInput" placeholder="Benutzername, Name oder TG-Nummer...">
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="usersTable">
                            <thead>
                                <tr>
                                    <th>Benutzername</th>
                                    <th>Name</th>
                                    <th>Rolle</th>
                                    <th>TG-Nummer</th>
                                    <th>Status</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td>
                                            <?php 
                                                $fullName = '';
                                                if (isset($user['first_name']) && isset($user['last_name'])) {
                                                    $fullName = $user['first_name'] . ' ' . $user['last_name'];
                                                } elseif (isset($user['full_name'])) {
                                                    $fullName = $user['full_name'];
                                                }
                                                echo htmlspecialchars($fullName);
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                                // Finde die Rolle anhand der role_id
                                                $roleName = $user['role'];
                                                $roleCategory = '';
                                                
                                                if (isset($user['role_id'])) {
                                                    foreach ($roles as $role) {
                                                        if ($role['id'] === $user['role_id']) {
                                                            $roleName = $role['name'];
                                                            $roleCategory = $role['category'] ?? '';
                                                            break;
                                                        }
                                                    }
                                                }
                                                
                                                // Zeige Rolle mit Kategorie und optionalem Badge an
                                                echo htmlspecialchars($roleName);
                                                if (!empty($roleCategory)) {
                                                    echo ' <span class="badge badge-info">' . htmlspecialchars($roleCategory) . '</span>';
                                                }
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['badge_number'] ?? ''); ?></td>
                                        <td>
                                            <?php if (($user['status'] ?? 'active') === 'active'): ?>
                                                <span class="badge badge-success">Aktiv</span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">Inaktiv</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <?php 
                                                // Ermitteln, ob es sich um den Chief Justice handelt
                                                $isChiefJustice = isset($user['role_id']) && $user['role_id'] === 'chief_justice';
                                                
                                                // Prüfen, ob es der aktuelle Benutzer ist
                                                $isCurrentUser = $user['id'] === $_SESSION['user_id'];
                                                
                                                // Chief Justice kann nur von sich selbst bearbeitet werden
                                                if (!$isChiefJustice || ($isChiefJustice && $isCurrentUser)): 
                                                ?>
                                                <button type="button" class="btn btn-sm btn-primary edit-user-btn" 
                                                        data-id="<?php echo htmlspecialchars($user['id']); ?>"
                                                        data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                        data-role="<?php echo htmlspecialchars($user['role_id'] ?? ''); ?>"
                                                        data-firstname="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>"
                                                        data-lastname="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>"
                                                        data-email="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                                                        data-badge="<?php echo htmlspecialchars($user['badge_number'] ?? ''); ?>"
                                                        data-title="<?php echo htmlspecialchars($user['title'] ?? ''); ?>"
                                                        data-notes="<?php echo htmlspecialchars($user['notes'] ?? ''); ?>"
                                                        data-status="<?php echo htmlspecialchars($user['status'] ?? 'active'); ?>"
                                                        data-toggle="modal" data-target="#editUserModal">
                                                    <i class="fa fa-edit"></i> Bearbeiten
                                                </button>
                                                <?php endif; ?>
                                                
                                                <?php 
                                                // Löschen-Button nur anzeigen, wenn es nicht der aktuelle Benutzer ist und nicht der Chief Justice
                                                if (!$isCurrentUser && !$isChiefJustice): 
                                                ?>
                                                    <button type="button" class="btn btn-sm btn-danger delete-user-btn"
                                                            data-id="<?php echo htmlspecialchars($user['id']); ?>"
                                                            data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                            data-toggle="modal" data-target="#deleteUserModal">
                                                        <i class="fa fa-trash"></i> Löschen
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($isChiefJustice && !$isCurrentUser): ?>
                                                    <span class="badge badge-warning">Geschützt</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Modal: Benutzer hinzufügen -->
<div class="modal fade" id="addUserModal" tabindex="-1" role="dialog" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addUserModalLabel">Neuen Benutzer hinzufügen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Schließen">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="add_username">Benutzername *</label>
                            <input type="text" class="form-control" id="add_username" name="username" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="add_role">Rolle *</label>
                            <select class="form-control" id="add_role" name="role" required>
                                <option value="">-- Bitte wählen --</option>
                                <?php 
                                // Rollen nach Kategorien gruppieren
                                $rolesByCategory = [];
                                foreach ($roles as $role) {
                                    $category = $role['category'] ?? 'Andere';
                                    $rolesByCategory[$category][] = $role;
                                }
                                
                                // Nach Kategorien sortiert anzeigen
                                foreach ($rolesByCategory as $category => $categoryRoles): 
                                ?>
                                    <optgroup label="<?php echo htmlspecialchars($category); ?>">
                                        <?php foreach ($categoryRoles as $role): ?>
                                            <option value="<?php echo htmlspecialchars($role['id']); ?>"><?php echo htmlspecialchars($role['name'] . ' - ' . $role['description']); ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="add_password">Passwort *</label>
                            <input type="password" class="form-control" id="add_password" name="password" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="add_confirm_password">Passwort bestätigen *</label>
                            <input type="password" class="form-control" id="add_confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="add_first_name">Vorname</label>
                            <input type="text" class="form-control" id="add_first_name" name="first_name">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="add_last_name">Nachname</label>
                            <input type="text" class="form-control" id="add_last_name" name="last_name">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="add_email">E-Mail</label>
                            <input type="email" class="form-control" id="add_email" name="email">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="add_badge_number">TG-Nummer</label>
                            <input type="text" class="form-control" id="add_badge_number" name="badge_number">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="add_title">Titel</label>
                            <input type="text" class="form-control" id="add_title" name="title">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="add_status">Status</label>
                            <select class="form-control" id="add_status" name="status">
                                <option value="active">Aktiv</option>
                                <option value="inactive">Inaktiv</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="add_notes">Notizen</label>
                        <textarea class="form-control" id="add_notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-success">Benutzer hinzufügen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Benutzer bearbeiten -->
<div class="modal fade" id="editUserModal" tabindex="-1" role="dialog" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel">Benutzer bearbeiten</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Schließen">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="edit_username">Benutzername *</label>
                            <input type="text" class="form-control" id="edit_username" name="username" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="edit_role">Rolle *</label>
                            <select class="form-control" id="edit_role" name="role" required>
                                <option value="">-- Bitte wählen --</option>
                                <?php 
                                // Wenn die Rollen nach Kategorien noch nicht gruppiert wurden, tun wir das jetzt
                                if (!isset($rolesByCategory)) {
                                    $rolesByCategory = [];
                                    foreach ($roles as $role) {
                                        $category = $role['category'] ?? 'Andere';
                                        $rolesByCategory[$category][] = $role;
                                    }
                                }
                                
                                // Nach Kategorien sortiert anzeigen
                                foreach ($rolesByCategory as $category => $categoryRoles): 
                                ?>
                                    <optgroup label="<?php echo htmlspecialchars($category); ?>">
                                        <?php foreach ($categoryRoles as $role): ?>
                                            <option value="<?php echo htmlspecialchars($role['id']); ?>"><?php echo htmlspecialchars($role['name'] . ' - ' . $role['description']); ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="edit_password">Neues Passwort <small class="text-muted">(leer lassen, um nicht zu ändern)</small></label>
                            <input type="password" class="form-control" id="edit_password" name="password">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="edit_confirm_password">Passwort bestätigen</label>
                            <input type="password" class="form-control" id="edit_confirm_password" name="confirm_password">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="edit_first_name">Vorname</label>
                            <input type="text" class="form-control" id="edit_first_name" name="first_name">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="edit_last_name">Nachname</label>
                            <input type="text" class="form-control" id="edit_last_name" name="last_name">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="edit_email">E-Mail</label>
                            <input type="email" class="form-control" id="edit_email" name="email">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="edit_badge_number">TG-Nummer</label>
                            <input type="text" class="form-control" id="edit_badge_number" name="badge_number">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="edit_title">Titel</label>
                            <input type="text" class="form-control" id="edit_title" name="title">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="edit_status">Status</label>
                            <select class="form-control" id="edit_status" name="status">
                                <option value="active">Aktiv</option>
                                <option value="inactive">Inaktiv</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_notes">Notizen</label>
                        <textarea class="form-control" id="edit_notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Änderungen speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Benutzer löschen -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" role="dialog" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteUserModalLabel">Benutzer löschen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Schließen">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Sind Sie sicher, dass Sie den Benutzer <strong id="delete_username"></strong> löschen möchten?</p>
                <p class="text-danger">Diese Aktion kann nicht rückgängig gemacht werden.</p>
            </div>
            <div class="modal-footer">
                <form method="post" action="">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" id="delete_user_id">
                    
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-danger">Benutzer löschen</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Bearbeiten-Modal mit Benutzerdaten füllen
    var editButtons = document.querySelectorAll('.edit-user-btn');
    editButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            document.getElementById('edit_user_id').value = this.getAttribute('data-id');
            document.getElementById('edit_username').value = this.getAttribute('data-username');
            document.getElementById('edit_role').value = this.getAttribute('data-role');
            document.getElementById('edit_first_name').value = this.getAttribute('data-firstname');
            document.getElementById('edit_last_name').value = this.getAttribute('data-lastname');
            document.getElementById('edit_email').value = this.getAttribute('data-email');
            document.getElementById('edit_badge_number').value = this.getAttribute('data-badge');
            document.getElementById('edit_title').value = this.getAttribute('data-title');
            document.getElementById('edit_notes').value = this.getAttribute('data-notes');
            document.getElementById('edit_status').value = this.getAttribute('data-status');
        });
    });
    
    // Löschen-Modal mit Benutzerdaten füllen
    var deleteButtons = document.querySelectorAll('.delete-user-btn');
    deleteButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            document.getElementById('delete_user_id').value = this.getAttribute('data-id');
            document.getElementById('delete_username').textContent = this.getAttribute('data-username');
        });
    });
    
    // Filter- und Suchfunktionalität
    const roleFilter = document.getElementById('roleFilter');
    const statusFilter = document.getElementById('statusFilter');
    const searchInput = document.getElementById('searchInput');
    const usersTable = document.getElementById('usersTable');
    
    // Funktion zum Filtern der Tabelle
    function filterTable() {
        const roleValue = roleFilter.value.toLowerCase();
        const statusValue = statusFilter.value.toLowerCase();
        const searchValue = searchInput.value.toLowerCase();
        
        const rows = usersTable.querySelectorAll('tbody tr');
        
        rows.forEach(function(row) {
            const username = row.querySelector('td:nth-child(1)').textContent.toLowerCase();
            const name = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
            const roleCell = row.querySelector('td:nth-child(3)');
            const roleCellText = roleCell.textContent.toLowerCase();
            const categorySpan = roleCell.querySelector('.badge');
            const roleCategory = categorySpan ? categorySpan.textContent.toLowerCase() : '';
            const badge = row.querySelector('td:nth-child(4)').textContent.toLowerCase();
            const status = row.querySelector('td:nth-child(5)').textContent.toLowerCase();
            
            // Filter nach Rollenkategorie
            const roleMatch = roleValue === '' || roleCategory.includes(roleValue);
            
            // Filter nach Status
            const statusMatch = statusValue === '' || status.includes(statusValue);
            
            // Suche nach Benutzername, Name oder TG-Nummer
            const searchMatch = searchValue === '' || 
                               username.includes(searchValue) || 
                               name.includes(searchValue) || 
                               badge.includes(searchValue) ||
                               roleCellText.includes(searchValue);
            
            // Zeile anzeigen oder ausblenden
            if (roleMatch && statusMatch && searchMatch) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
    
    // Event-Listener für Filter und Suche
    roleFilter.addEventListener('change', filterTable);
    statusFilter.addEventListener('change', filterTable);
    searchInput.addEventListener('input', filterTable);
});
</script>

<?php include '../includes/footer.php'; ?>