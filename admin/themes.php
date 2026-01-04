<?php
/**
 * Aktenverwaltungssystem - Department of Justice
 * Theme-Verwaltung
 * 
 * Dieses Skript ermöglicht Administratoren, die Farbpaletten des Systems zu verwalten.
 */

// Stelle sicher, dass die Sitzung gestartet wurde
session_start();

// Lade die erforderlichen Dateien
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/theme_manager.php';

// Überprüfe, ob der Benutzer angemeldet und Administrator ist
if (!isUserLoggedIn() || !isAdminSession()) {
    header('Location: ' . getBasePath() . 'login.php');
    exit;
}

// Verarbeitung des Formulars
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Neues Theme hinzufügen
        if ($_POST['action'] === 'add_theme') {
            $themeName = sanitize($_POST['theme_name'] ?? '');
            
            if (empty($themeName)) {
                $message = 'Theme-Name darf nicht leer sein.';
                $messageType = 'danger';
            } else {
                // Erstelle ein neues Theme basierend auf dem Standard-Theme
                $defaultTheme = getCurrentTheme();
                $newTheme = [
                    'name' => $themeName,
                    'active' => false,
                    'colors' => $defaultTheme['colors']
                ];
                
                if (saveTheme($newTheme)) {
                    $message = 'Theme wurde erfolgreich hinzugefügt.';
                    $messageType = 'success';
                } else {
                    $message = 'Fehler beim Speichern des Themes.';
                    $messageType = 'danger';
                }
            }
        }
        // Theme aktivieren
        elseif ($_POST['action'] === 'activate_theme') {
            $themeName = sanitize($_POST['theme_name'] ?? '');
            $themes = loadJsonData('themes.json');
            
            foreach ($themes as $key => $theme) {
                if ($theme['name'] === $themeName) {
                    $themes[$key]['active'] = true;
                } else {
                    $themes[$key]['active'] = false;
                }
            }
            
            if (saveJsonData('themes.json', $themes)) {
                $message = 'Theme "' . $themeName . '" wurde aktiviert.';
                $messageType = 'success';
            } else {
                $message = 'Fehler beim Aktivieren des Themes.';
                $messageType = 'danger';
            }
        }
        // Theme löschen
        elseif ($_POST['action'] === 'delete_theme') {
            $themeName = sanitize($_POST['theme_name'] ?? '');
            $themes = loadJsonData('themes.json');
            
            // Finde und entferne das Theme
            $themeIndex = -1;
            $themeActive = false;
            
            foreach ($themes as $key => $theme) {
                if ($theme['name'] === $themeName) {
                    $themeIndex = $key;
                    $themeActive = $theme['active'] ?? false;
                    break;
                }
            }
            
            if ($themeIndex >= 0) {
                // Verhindere das Löschen des letzten oder aktiven Themes
                if (count($themes) <= 1) {
                    $message = 'Das letzte Theme kann nicht gelöscht werden.';
                    $messageType = 'danger';
                } elseif ($themeActive) {
                    $message = 'Ein aktives Theme kann nicht gelöscht werden. Bitte aktivieren Sie zuerst ein anderes Theme.';
                    $messageType = 'danger';
                } else {
                    array_splice($themes, $themeIndex, 1);
                    
                    if (saveJsonData('themes.json', $themes)) {
                        $message = 'Theme "' . $themeName . '" wurde gelöscht.';
                        $messageType = 'success';
                    } else {
                        $message = 'Fehler beim Löschen des Themes.';
                        $messageType = 'danger';
                    }
                }
            } else {
                $message = 'Theme nicht gefunden.';
                $messageType = 'danger';
            }
        }
        // Theme bearbeiten
        elseif ($_POST['action'] === 'update_theme') {
            $themeName = sanitize($_POST['theme_name'] ?? '');
            $themes = loadJsonData('themes.json');
            
            // Finde das Theme
            $themeIndex = -1;
            foreach ($themes as $key => $theme) {
                if ($theme['name'] === $themeName) {
                    $themeIndex = $key;
                    break;
                }
            }
            
            if ($themeIndex >= 0) {
                // Aktualisiere die Farben
                foreach ($_POST as $key => $value) {
                    if (strpos($key, 'color_') === 0) {
                        $colorName = substr($key, 6); // Entferne "color_" vom Namen
                        $themes[$themeIndex]['colors'][$colorName] = sanitize($value);
                    }
                }
                
                if (saveJsonData('themes.json', $themes)) {
                    $message = 'Theme "' . $themeName . '" wurde aktualisiert.';
                    $messageType = 'success';
                } else {
                    $message = 'Fehler beim Aktualisieren des Themes.';
                    $messageType = 'danger';
                }
            } else {
                $message = 'Theme nicht gefunden.';
                $messageType = 'danger';
            }
        }
        // Voreingestelltes Theme importieren
        elseif ($_POST['action'] === 'import_predefined') {
            $templateIndex = (int)($_POST['template_index'] ?? -1);
            $predefinedThemes = getPredefinedThemes();
            
            if (isset($predefinedThemes[$templateIndex])) {
                $predefinedTheme = $predefinedThemes[$templateIndex];
                $predefinedTheme['active'] = false; // Nicht automatisch aktivieren
                
                // Prüfe, ob ein Theme mit diesem Namen bereits existiert
                $themes = loadJsonData('themes.json');
                $exists = false;
                
                foreach ($themes as $theme) {
                    if ($theme['name'] === $predefinedTheme['name']) {
                        $exists = true;
                        break;
                    }
                }
                
                if ($exists) {
                    $predefinedTheme['name'] .= ' (Kopie)';
                }
                
                if (saveTheme($predefinedTheme)) {
                    $message = 'Voreingestelltes Theme "' . $predefinedTheme['name'] . '" wurde importiert.';
                    $messageType = 'success';
                } else {
                    $message = 'Fehler beim Importieren des voreingestellten Themes.';
                    $messageType = 'danger';
                }
            } else {
                $message = 'Voreingestelltes Theme nicht gefunden.';
                $messageType = 'danger';
            }
        }
    }
}

// Lade alle Themes
$themes = loadJsonData('themes.json');
$currentTheme = getCurrentTheme();
$predefinedThemes = getPredefinedThemes();

// Lade den Header
require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once '../includes/sidebar.php'; ?>
        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Theme-Verwaltung</h1>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>Vorschau des aktuellen Themes</h5>
                        </div>
                        <div class="card-body">
                            <div class="theme-preview">
                                <div class="preview-box" style="background-color: var(--content-bg); padding: 15px; border-radius: 4px;">
                                    <h3 style="color: var(--primary);">Dokumententitel</h3>
                                    <p style="color: var(--ink);">Beispieltext für ein Dokument im Department of Justice, 1899.</p>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div style="background-color: var(--paper); padding: 10px; color: var(--ink); border-radius: 4px;">
                                                Notiz auf Papier
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <button class="btn btn-sm" style="background-color: var(--primary); color: var(--light);">Primärer Button</button>
                                            <button class="btn btn-sm" style="background-color: var(--secondary); color: var(--light);">Sekundärer Button</button>
                                            <button class="btn btn-sm" style="background-color: var(--accent); color: var(--light);">Akzent-Button</button>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <span class="badge" style="background-color: var(--success); color: var(--light);">Erfolg</span>
                                        <span class="badge" style="background-color: var(--danger); color: var(--light);">Gefahr</span>
                                        <span class="badge" style="background-color: var(--warning); color: var(--light);">Warnung</span>
                                        <span class="badge" style="background-color: var(--info); color: var(--light);">Info</span>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <p><strong>Aktives Theme:</strong> <?php echo htmlspecialchars($currentTheme['name']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>Themes verwalten</h5>
                        </div>
                        <div class="card-body">
                            <h6>Vorhandene Themes</h6>
                            <div class="list-group mb-3">
                                <?php foreach ($themes as $theme): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <?php echo htmlspecialchars($theme['name']); ?>
                                        <?php if (isset($theme['active']) && $theme['active']): ?>
                                            <span class="badge badge-primary">Aktiv</span>
                                        <?php else: ?>
                                            <div>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="action" value="activate_theme">
                                                    <input type="hidden" name="theme_name" value="<?php echo htmlspecialchars($theme['name']); ?>">
                                                    <button type="submit" class="btn btn-sm btn-success">Aktivieren</button>
                                                </form>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="action" value="delete_theme">
                                                    <input type="hidden" name="theme_name" value="<?php echo htmlspecialchars($theme['name']); ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Sind Sie sicher, dass Sie dieses Theme löschen möchten?');">Löschen</button>
                                                </form>
                                                <a href="#" class="btn btn-sm btn-info edit-theme-btn" 
                                                   data-theme-name="<?php echo htmlspecialchars($theme['name']); ?>"
                                                   data-theme-colors="<?php echo htmlspecialchars(json_encode($theme['colors'])); ?>">
                                                    Bearbeiten
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="mb-3">
                                <h6>Neues Theme hinzufügen</h6>
                                <form method="post">
                                    <input type="hidden" name="action" value="add_theme">
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="theme_name" placeholder="Theme-Name" required>
                                        <div class="input-group-append">
                                            <button type="submit" class="btn btn-primary">Hinzufügen</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            
                            <div>
                                <h6>Voreingestelltes Theme importieren</h6>
                                <form method="post">
                                    <input type="hidden" name="action" value="import_predefined">
                                    <div class="input-group">
                                        <select class="form-control" name="template_index" required>
                                            <?php foreach ($predefinedThemes as $index => $theme): ?>
                                                <option value="<?php echo $index; ?>"><?php echo htmlspecialchars($theme['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="input-group-append">
                                            <button type="submit" class="btn btn-primary">Importieren</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Farb-Editor-Modal -->
            <div class="modal fade" id="colorEditorModal" tabindex="-1" role="dialog" aria-labelledby="colorEditorModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="colorEditorModalLabel">Theme bearbeiten: <span id="editThemeName"></span></h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <form id="editThemeForm" method="post">
                                <input type="hidden" name="action" value="update_theme">
                                <input type="hidden" name="theme_name" id="editThemeNameInput">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="color_primary">Primärfarbe</label>
                                            <input type="color" class="form-control" name="color_primary" id="color_primary">
                                        </div>
                                        <div class="form-group">
                                            <label for="color_secondary">Sekundärfarbe</label>
                                            <input type="color" class="form-control" name="color_secondary" id="color_secondary">
                                        </div>
                                        <div class="form-group">
                                            <label for="color_accent">Akzentfarbe</label>
                                            <input type="color" class="form-control" name="color_accent" id="color_accent">
                                        </div>
                                        <div class="form-group">
                                            <label for="color_light">Helle Farbe</label>
                                            <input type="color" class="form-control" name="color_light" id="color_light">
                                        </div>
                                        <div class="form-group">
                                            <label for="color_dark">Dunkle Farbe</label>
                                            <input type="color" class="form-control" name="color_dark" id="color_dark">
                                        </div>
                                        <div class="form-group">
                                            <label for="color_paper">Papierfarbe</label>
                                            <input type="color" class="form-control" name="color_paper" id="color_paper">
                                        </div>
                                        <div class="form-group">
                                            <label for="color_ink">Tintenfarbe (Text)</label>
                                            <input type="color" class="form-control" name="color_ink" id="color_ink">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="color_success">Erfolgsfarbe</label>
                                            <input type="color" class="form-control" name="color_success" id="color_success">
                                        </div>
                                        <div class="form-group">
                                            <label for="color_danger">Gefahrenfarbe</label>
                                            <input type="color" class="form-control" name="color_danger" id="color_danger">
                                        </div>
                                        <div class="form-group">
                                            <label for="color_warning">Warnfarbe</label>
                                            <input type="color" class="form-control" name="color_warning" id="color_warning">
                                        </div>
                                        <div class="form-group">
                                            <label for="color_info">Infofarbe</label>
                                            <input type="color" class="form-control" name="color_info" id="color_info">
                                        </div>
                                        <div class="form-group">
                                            <label for="color_sidebar-bg">Seitenleiste-Hintergrund</label>
                                            <input type="color" class="form-control" name="color_sidebar-bg" id="color_sidebar-bg">
                                        </div>
                                        <div class="form-group">
                                            <label for="color_sidebar-color">Seitenleiste-Textfarbe</label>
                                            <input type="color" class="form-control" name="color_sidebar-color" id="color_sidebar-color">
                                        </div>
                                        <div class="form-group">
                                            <label for="color_content-bg">Inhalt-Hintergrundfarbe</label>
                                            <input type="color" class="form-control" name="color_content-bg" id="color_content-bg">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-center mt-3">
                                    <button type="submit" class="btn btn-primary">Speichern</button>
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Theme-Bearbeitung
    const editBtns = document.querySelectorAll('.edit-theme-btn');
    
    editBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const themeName = this.getAttribute('data-theme-name');
            const themeColors = JSON.parse(this.getAttribute('data-theme-colors'));
            
            document.getElementById('editThemeName').textContent = themeName;
            document.getElementById('editThemeNameInput').value = themeName;
            
            // Setze die Farbwerte in die Eingabefelder
            for (const [key, value] of Object.entries(themeColors)) {
                const input = document.getElementById('color_' + key);
                if (input) {
                    input.value = value;
                }
            }
            
            // Öffne das Modal
            $('#colorEditorModal').modal('show');
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>