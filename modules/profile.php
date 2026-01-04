<?php
/**
 * Aktenverwaltungssystem - Department of Justice
 * Benutzerprofil
 * 
 * Ermöglicht Benutzern, ihre Profileinstellungen zu verwalten und eine Farbpalette auszuwählen
 */

session_start();
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/theme_manager.php';

// Prüfen, ob der Benutzer angemeldet ist
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];
$message = '';
$error = '';

// Benutzerinformationen abrufen
$user = findById('users.json', $user_id);

if (!$user) {
    $error = 'Benutzer konnte nicht gefunden werden.';
}

// Formularverarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'change_theme') {
        $themeIndex = intval($_POST['theme_index']);
        $themes = loadJsonData('themes.json') ?: getPredefinedThemes();
        
        if (isset($themes[$themeIndex])) {
            $selectedTheme = $themes[$themeIndex];
            
            // Setze alle Themes auf inaktiv
            foreach ($themes as $key => $theme) {
                $themes[$key]['active'] = false;
            }
            
            // Aktiviere das ausgewählte Theme
            $themes[$themeIndex]['active'] = true;
            
            if (saveJsonData('themes.json', $themes)) {
                $message = 'Die Farbpalette wurde erfolgreich geändert.';
            } else {
                $error = 'Fehler beim Ändern der Farbpalette.';
            }
        } else {
            $error = 'Die gewählte Farbpalette wurde nicht gefunden.';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'Bitte füllen Sie alle Passwortfelder aus.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Die neuen Passwörter stimmen nicht überein.';
        } elseif (!password_verify($currentPassword, $user['password'])) {
            $error = 'Das aktuelle Passwort ist nicht korrekt.';
        } else {
            // Aktualisiere das Passwort
            $userData = $user;
            $userData['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
            $userData['date_updated'] = date('Y-m-d H:i:s');
            
            $result = updateUser($user_id, $userData);
            
            if ($result['success']) {
                $message = 'Ihr Passwort wurde erfolgreich aktualisiert.';
            } else {
                $error = 'Fehler beim Aktualisieren des Passworts: ' . $result['error'];
            }
        }
    }
}

// Hole die verfügbaren Themes
$availableThemes = loadJsonData('themes.json') ?: getPredefinedThemes();
$currentTheme = getCurrentTheme();

?>

<?php include '../includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Mein Profil</h1>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h4>Profilinformationen</h4>
                        </div>
                        <div class="card-body">
                            <p><strong>Benutzername:</strong> <?php echo htmlspecialchars($username); ?></p>
                            <p><strong>Rolle:</strong> <?php echo htmlspecialchars($role); ?></p>
                            <?php if (isset($user['roles']) && is_array($user['roles'])): ?>
                                <p><strong>Zusätzliche Rollen:</strong> 
                                <?php 
                                $additionalRoles = array_filter($user['roles'], function($r) use ($role) {
                                    return $r !== $role;
                                });
                                echo htmlspecialchars(implode(', ', $additionalRoles)); 
                                ?>
                                </p>
                            <?php endif; ?>
                            <p><strong>Registriert am:</strong> <?php echo isset($user['date_created']) ? date('d.m.Y H:i', strtotime($user['date_created'])) : 'Nicht verfügbar'; ?></p>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header">
                            <h4>Farbpalette ändern</h4>
                        </div>
                        <div class="card-body">
                            <p>Wählen Sie eine Farbpalette für die Benutzeroberfläche:</p>
                            
                            <form method="post">
                                <input type="hidden" name="action" value="change_theme">
                                
                                <div class="form-group">
                                    <select class="form-control" name="theme_index" id="theme_select">
                                        <?php foreach ($availableThemes as $index => $theme): ?>
                                            <option value="<?php echo $index; ?>" <?php echo ($theme['name'] === $currentTheme['name']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($theme['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="theme-preview mb-3">
                                    <?php foreach ($availableThemes as $index => $theme): ?>
                                        <div class="theme-colors <?php echo ($theme['name'] === $currentTheme['name']) ? 'd-flex' : 'd-none'; ?>" data-theme-index="<?php echo $index; ?>">
                                            <?php foreach (array_slice($theme['colors'], 0, 5) as $colorName => $colorValue): ?>
                                                <div class="color-sample" style="background-color: <?php echo $colorValue; ?>;" title="<?php echo $colorName; ?>"></div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Farbpalette anwenden</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h4>Passwort ändern</h4>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <input type="hidden" name="action" value="update_password">
                                
                                <div class="form-group">
                                    <label for="current_password">Aktuelles Passwort</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="new_password">Neues Passwort</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="confirm_password">Neues Passwort bestätigen</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Passwort ändern</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<style>
    .theme-preview {
        border: 1px solid #ccc;
        border-radius: 5px;
        padding: 10px;
        background-color: #f8f9fa;
    }
    
    .theme-colors {
        display: flex;
        justify-content: space-between;
    }
    
    .color-sample {
        width: 50px;
        height: 50px;
        border-radius: 5px;
        border: 1px solid #ccc;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const themeSelect = document.getElementById('theme_select');
    const themeColors = document.querySelectorAll('.theme-colors');
    
    themeSelect.addEventListener('change', function() {
        const selectedIndex = this.value;
        
        // Verstecke alle Theme-Vorschauen
        themeColors.forEach(function(themeColor) {
            themeColor.classList.add('d-none');
            themeColor.classList.remove('d-flex');
        });
        
        // Zeige die ausgewählte Theme-Vorschau
        const selectedTheme = document.querySelector(`.theme-colors[data-theme-index="${selectedIndex}"]`);
        if (selectedTheme) {
            selectedTheme.classList.remove('d-none');
            selectedTheme.classList.add('d-flex');
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>