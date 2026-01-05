<?php
/**
 * Aktenverwaltungssystem - Department of Justice
 * Persönliches Notizensystem
 * 
 * Dieses Modul ermöglicht die Verwaltung von persönlichen Notizen, die nur für den
 * erstellenden Benutzer sichtbar sind. Benutzer können Notizen kategorisieren
 * und eigene Kategorien anlegen.
 */

// Startet die Session vor jeglicher Ausgabe
session_start();
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';

// Überprüfen, ob der Benutzer angemeldet ist
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Enforce view permission for notes
checkPermissionOrDie('notes', 'view');

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role']; // Hauptrolle
$roles = isset($_SESSION['roles']) ? $_SESSION['roles'] : [$role]; // Alle Rollen
$message = '';
$error = '';

// Verzeichnisse und Dateien für Notizen
$notesDir = '../data/notes/';
$notesFile = $notesDir . 'notes_' . $user_id . '.json';
$categoriesFile = $notesDir . 'categories_' . $user_id . '.json';
$uploadsDir = '../uploads/notes/';

// Verzeichnisse erstellen, falls sie nicht existieren
if (!file_exists($notesDir)) {
    mkdir($notesDir, 0755, true);
}

if (!file_exists($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

// Lade Notizen des Benutzers
$notes = getJsonData($notesFile);
if ($notes === false) {
    $notes = [];
}

// Lade Kategorien des Benutzers
$categories = getJsonData($categoriesFile);
if ($categories === false) {
    // Standard-Kategorien, wenn keine definiert sind
    $categories = [
        [
            'id' => generateUniqueId(),
            'name' => 'Allgemein',
            'color' => '#6c757d', // Grau statt Blau
            'created_at' => date('Y-m-d H:i:s')
        ],
        [
            'id' => generateUniqueId(),
            'name' => 'Wichtig',
            'color' => '#e74c3c',
            'created_at' => date('Y-m-d H:i:s')
        ],
        [
            'id' => generateUniqueId(),
            'name' => 'Ideen',
            'color' => '#2ecc71',
            'created_at' => date('Y-m-d H:i:s')
        ]
    ];
    saveJsonData($categoriesFile, $categories);
}

// AJAX-Anfragen verarbeiten
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    // Enforce permissions for AJAX actions
    $ajaxAction = isset($_POST['action']) ? $_POST['action'] : '';
    if (in_array($ajaxAction, ['create_note', 'update_note', 'delete_note', 'toggle_note_status'])) {
        $required = $ajaxAction === 'delete_note' ? 'delete' : ($ajaxAction === 'toggle_note_status' ? 'edit' : 'create');
        if (!checkUserPermission($_SESSION['user_id'], 'notes', $required)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Zugriff verweigert']);
            exit;
        }
    }
    header('Content-Type: application/json');
    
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $action = isset($_GET['action']) ? $_GET['action'] : $action;
    
    // Notizdetails abrufen
    if ($action === 'get_note') {
        $noteId = isset($_GET['note_id']) ? sanitize($_GET['note_id']) : '';
        $noteFound = false;
        
        foreach ($notes as $note) {
            if ($note['id'] === $noteId) {
                $noteFound = true;
                echo json_encode([
                    'success' => true,
                    'note' => $note
                ]);
                break;
            }
        }
        
        if (!$noteFound) {
            echo json_encode([
                'success' => false,
                'message' => 'Notiz nicht gefunden.'
            ]);
        }
        
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Ungültige Aktion']);
    exit;
}

// Verarbeitung von Formularübermittlungen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    // Notiz erstellen
    if ($action === 'create_note') {
        if (empty($_POST['title']) || empty($_POST['content'])) {
            $error = 'Bitte gib einen Titel und Inhalt für die Notiz an.';
        } else {
            $categoryId = sanitize($_POST['category_id']);
            
            // Überprüfe, ob die Kategorie existiert
            $categoryExists = false;
            $categoryName = '';
            $categoryColor = '#6c757d'; // Grau statt Blau
            
            foreach ($categories as $category) {
                if ($category['id'] === $categoryId) {
                    $categoryExists = true;
                    $categoryName = $category['name'];
                    $categoryColor = $category['color'];
                    break;
                }
            }
            
            if (!$categoryExists && $categoryId !== '') {
                $error = 'Die ausgewählte Kategorie existiert nicht.';
            } else {
                $noteData = [
                    'id' => generateUniqueId(),
                    'title' => sanitize($_POST['title']),
                    'content' => sanitize($_POST['content']),
                    'category_id' => $categoryId,
                    'category_name' => $categoryName,
                    'category_color' => $categoryColor,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                // Füge die neue Notiz hinzu
                array_unshift($notes, $noteData);
                
                if (saveJsonData($notesFile, $notes)) {
                    $message = 'Notiz wurde erfolgreich erstellt.';
                } else {
                    $error = 'Fehler beim Speichern der Notiz.';
                }
            }
        }
    }
    
    // Notiz bearbeiten
    elseif ($action === 'edit_note' && isset($_POST['note_id'])) {
        $noteId = sanitize($_POST['note_id']);
        $noteFound = false;
        
        if (empty($_POST['title']) || empty($_POST['content'])) {
            $error = 'Bitte gib einen Titel und Inhalt für die Notiz an.';
        } else {
            $categoryId = sanitize($_POST['category_id']);
            
            // Überprüfe, ob die Kategorie existiert
            $categoryExists = false;
            $categoryName = '';
            $categoryColor = '#6c757d'; // Grau statt Blau
            
            foreach ($categories as $category) {
                if ($category['id'] === $categoryId) {
                    $categoryExists = true;
                    $categoryName = $category['name'];
                    $categoryColor = $category['color'];
                    break;
                }
            }
            
            if (!$categoryExists && $categoryId !== '') {
                $error = 'Die ausgewählte Kategorie existiert nicht.';
            } else {
                foreach ($notes as $key => $note) {
                    if ($note['id'] === $noteId) {
                        $noteFound = true;
                        
                        // Aktualisiere die Notiz
                        $notes[$key]['title'] = sanitize($_POST['title']);
                        $notes[$key]['content'] = sanitize($_POST['content']);
                        $notes[$key]['category_id'] = $categoryId;
                        $notes[$key]['category_name'] = $categoryName;
                        $notes[$key]['category_color'] = $categoryColor;
                        $notes[$key]['updated_at'] = date('Y-m-d H:i:s');
                        
                        if (saveJsonData($notesFile, $notes)) {
                            $message = 'Notiz wurde erfolgreich aktualisiert.';
                        } else {
                            $error = 'Fehler beim Speichern der Notiz.';
                        }
                        
                        break;
                    }
                }
                
                if (!$noteFound) {
                    $error = 'Die zu bearbeitende Notiz wurde nicht gefunden.';
                }
            }
        }
    }
    
    // Notiz löschen
    elseif ($action === 'delete_note' && isset($_POST['note_id'])) {
        $noteId = sanitize($_POST['note_id']);
        $noteFound = false;
        
        foreach ($notes as $key => $note) {
            if ($note['id'] === $noteId) {
                $noteFound = true;
                
                // Lösche die Notiz
                array_splice($notes, $key, 1);
                
                if (saveJsonData($notesFile, $notes)) {
                    $message = 'Notiz wurde erfolgreich gelöscht.';
                } else {
                    $error = 'Fehler beim Löschen der Notiz.';
                }
                
                break;
            }
        }
        
        if (!$noteFound) {
            $error = 'Die zu löschende Notiz wurde nicht gefunden.';
        }
    }
    
    // Kategorie erstellen
    elseif ($action === 'create_category') {
        if (empty($_POST['category_name'])) {
            $error = 'Bitte gib einen Namen für die Kategorie an.';
        } else {
            $categoryName = sanitize($_POST['category_name']);
            $categoryColor = !empty($_POST['category_color']) ? sanitize($_POST['category_color']) : '#6c757d'; // Grau statt Blau
            
            // Überprüfe, ob die Kategorie bereits existiert
            $categoryExists = false;
            foreach ($categories as $category) {
                if (strcasecmp($category['name'], $categoryName) === 0) {
                    $categoryExists = true;
                    break;
                }
            }
            
            if ($categoryExists) {
                $error = 'Eine Kategorie mit diesem Namen existiert bereits.';
            } else {
                $categoryData = [
                    'id' => generateUniqueId(),
                    'name' => $categoryName,
                    'color' => $categoryColor,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                // Füge die neue Kategorie hinzu
                array_push($categories, $categoryData);
                
                if (saveJsonData($categoriesFile, $categories)) {
                    $message = 'Kategorie wurde erfolgreich erstellt.';
                } else {
                    $error = 'Fehler beim Speichern der Kategorie.';
                }
            }
        }
    }
    
    // Kategorie bearbeiten
    elseif ($action === 'edit_category' && isset($_POST['category_id'])) {
        $categoryId = sanitize($_POST['category_id']);
        $categoryFound = false;
        
        if (empty($_POST['category_name'])) {
            $error = 'Bitte gib einen Namen für die Kategorie an.';
        } else {
            $categoryName = sanitize($_POST['category_name']);
            $categoryColor = !empty($_POST['category_color']) ? sanitize($_POST['category_color']) : '#6c757d'; // Grau statt Blau
            
            // Überprüfe, ob eine andere Kategorie mit demselben Namen existiert
            $duplicateNameExists = false;
            foreach ($categories as $category) {
                if ($category['id'] !== $categoryId && strcasecmp($category['name'], $categoryName) === 0) {
                    $duplicateNameExists = true;
                    break;
                }
            }
            
            if ($duplicateNameExists) {
                $error = 'Eine andere Kategorie mit diesem Namen existiert bereits.';
            } else {
                foreach ($categories as $key => $category) {
                    if ($category['id'] === $categoryId) {
                        $categoryFound = true;
                        $oldName = $category['name'];
                        $oldColor = $category['color'];
                        
                        // Aktualisiere die Kategorie
                        $categories[$key]['name'] = $categoryName;
                        $categories[$key]['color'] = $categoryColor;
                        
                        if (saveJsonData($categoriesFile, $categories)) {
                            // Aktualisiere auch die Kategorieinfos in allen Notizen
                            foreach ($notes as $noteKey => $note) {
                                if ($note['category_id'] === $categoryId) {
                                    $notes[$noteKey]['category_name'] = $categoryName;
                                    $notes[$noteKey]['category_color'] = $categoryColor;
                                }
                            }
                            
                            if (saveJsonData($notesFile, $notes)) {
                                $message = 'Kategorie wurde erfolgreich aktualisiert.';
                            } else {
                                $error = 'Kategorie aktualisiert, aber Fehler beim Aktualisieren der Notizen.';
                            }
                        } else {
                            $error = 'Fehler beim Speichern der Kategorie.';
                        }
                        
                        break;
                    }
                }
                
                if (!$categoryFound) {
                    $error = 'Die zu bearbeitende Kategorie wurde nicht gefunden.';
                }
            }
        }
    }
    
    // Kategorie löschen
    elseif ($action === 'delete_category' && isset($_POST['category_id'])) {
        $categoryId = sanitize($_POST['category_id']);
        $categoryFound = false;
        
        foreach ($categories as $key => $category) {
            if ($category['id'] === $categoryId) {
                $categoryFound = true;
                
                // Lösche die Kategorie
                array_splice($categories, $key, 1);
                
                if (saveJsonData($categoriesFile, $categories)) {
                    // Setze die Kategorie aller betroffenen Notizen auf leer
                    foreach ($notes as $noteKey => $note) {
                        if ($note['category_id'] === $categoryId) {
                            $notes[$noteKey]['category_id'] = '';
                            $notes[$noteKey]['category_name'] = '';
                            $notes[$noteKey]['category_color'] = '';
                        }
                    }
                    
                    if (saveJsonData($notesFile, $notes)) {
                        $message = 'Kategorie wurde erfolgreich gelöscht.';
                    } else {
                        $error = 'Kategorie gelöscht, aber Fehler beim Aktualisieren der Notizen.';
                    }
                } else {
                    $error = 'Fehler beim Löschen der Kategorie.';
                }
                
                break;
            }
        }
        
        if (!$categoryFound) {
            $error = 'Die zu löschende Kategorie wurde nicht gefunden.';
        }
    }
}

// Sortiere nach Erstellungsdatum, neueste zuerst
usort($notes, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Bereite nach Kategorien sortierte Notizen vor
$groupedNotes = [];
foreach ($notes as $note) {
    $categoryId = $note['category_id'] ?: 'uncategorized';
    if (!isset($groupedNotes[$categoryId])) {
        $groupedNotes[$categoryId] = [
            'id' => $categoryId,
            'name' => $note['category_name'] ?: 'Nicht kategorisiert',
            'color' => $note['category_color'] ?: '#999999',
            'notes' => []
        ];
    }
    $groupedNotes[$categoryId]['notes'][] = $note;
}

// Sortiere die Kategorien nach Name
uasort($groupedNotes, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

// Seitentitel und Beschreibung
$pageTitle = 'Meine Notizen';
$pageDescription = 'Persönliche Notizen verwalten';

// HTML-Template einbinden
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include_once '../includes/sidebar.php'; ?>
        
        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Persönliche Notizen</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-sm btn-outline-primary mr-2" data-toggle="modal" data-target="#createNoteModal">
                        <i class="fas fa-plus"></i> Neue Notiz
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#manageCategoriesModal">
                        <i class="fas fa-tags"></i> Kategorien verwalten
                    </button>
                </div>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Schließen">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Schließen">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            
            <!-- Kategorie-Tabs oben -->
            <ul class="nav nav-tabs mb-3">
                <li class="nav-item">
                    <a class="nav-link active" href="#" data-category="all">Alle Notizen</a>
                </li>
                <?php foreach ($categories as $category): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-category="<?php echo htmlspecialchars($category['id']); ?>">
                            <span class="category-color-marker" style="display: inline-block; width: 12px; height: 12px; background-color: <?php echo htmlspecialchars($category['color']); ?>; border-radius: 50%; margin-right: 5px;"></span>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
                <li class="nav-item">
                    <a class="nav-link" href="#" data-category="uncategorized">Nicht kategorisiert</a>
                </li>
            </ul>
            
            <!-- Suchleiste -->
            <div class="row mb-3">
                <div class="col-md-12">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                        </div>
                        <input type="text" id="searchNotes" class="form-control" placeholder="Notizen durchsuchen...">
                    </div>
                </div>
            </div>
            
            <!-- Notizenliste -->
            <div class="row" id="notesContainer">
                <?php if (empty($notes)): ?>
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Du hast noch keine Notizen erstellt. Klicke auf "Neue Notiz", um deine erste Notiz zu erstellen.
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($notes as $note): ?>
                        <div class="col-lg-4 col-md-6 mb-4 note-item" 
                             data-category-id="<?php echo !empty($note['category_id']) ? htmlspecialchars($note['category_id']) : 'uncategorized'; ?>">
                            <div class="card h-100 note-card">
                                <?php if (!empty($note['category_id'])): ?>
                                    <div class="card-header" style="background-color: <?php echo htmlspecialchars($note['category_color']); ?>; color: white;">
                                        <?php echo htmlspecialchars($note['category_name']); ?>
                                    </div>
                                <?php else: ?>
                                    <div class="card-header bg-light">
                                        Nicht kategorisiert
                                    </div>
                                <?php endif; ?>
                                
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($note['title']); ?></h5>
                                    <p class="card-text note-content">
                                        <?php 
                                        $content = htmlspecialchars($note['content']);
                                        $maxLength = 150;
                                        
                                        if (mb_strlen($content) > $maxLength) {
                                            echo mb_substr($content, 0, $maxLength) . '...';
                                        } else {
                                            echo $content;
                                        }
                                        ?>
                                    </p>
                                </div>
                                
                                <div class="card-footer d-flex justify-content-between align-items-center bg-transparent">
                                    <small class="text-muted">
                                        <?php echo date('d.m.Y H:i', strtotime($note['created_at'])); ?>
                                    </small>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-primary view-note-btn" 
                                                data-note-id="<?php echo htmlspecialchars($note['id']); ?>"
                                                data-note-title="<?php echo htmlspecialchars($note['title']); ?>"
                                                data-note-content="<?php echo htmlspecialchars($note['content']); ?>"
                                                data-category-id="<?php echo htmlspecialchars($note['category_id']); ?>"
                                                data-toggle="modal" data-target="#viewNoteModal">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary edit-note-btn" 
                                                data-note-id="<?php echo htmlspecialchars($note['id']); ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-note-btn"
                                                data-note-id="<?php echo htmlspecialchars($note['id']); ?>"
                                                data-note-title="<?php echo htmlspecialchars($note['title']); ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<!-- Notiz ansehen Modal -->
<div class="modal fade" id="viewNoteModal" tabindex="-1" role="dialog" aria-labelledby="viewNoteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewNoteModalLabel"></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Schließen">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            
            <div class="modal-body">
                <div id="viewNoteContent"></div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Schließen</button>
                <button type="button" class="btn btn-primary edit-viewed-note">Bearbeiten</button>
            </div>
        </div>
    </div>
</div>

<!-- Neue Notiz Modal -->
<div class="modal fade" id="createNoteModal" tabindex="-1" role="dialog" aria-labelledby="createNoteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="notes.php" method="post">
                <input type="hidden" name="action" value="create_note">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="createNoteModalLabel">Neue Notiz erstellen</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Schließen">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                
                <div class="modal-body">
                    <div class="form-group">
                        <label for="title">Titel *</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="category_id">Kategorie</label>
                        <select class="form-control" id="category_id" name="category_id">
                            <option value="">Keine Kategorie</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['id']); ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="content">Inhalt *</label>
                        <textarea class="form-control" id="content" name="content" rows="10" required></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Notiz bearbeiten Modal -->
<div class="modal fade" id="editNoteModal" tabindex="-1" role="dialog" aria-labelledby="editNoteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="notes.php" method="post">
                <input type="hidden" name="action" value="edit_note">
                <input type="hidden" id="edit_note_id" name="note_id" value="">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="editNoteModalLabel">Notiz bearbeiten</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Schließen">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_title">Titel *</label>
                        <input type="text" class="form-control" id="edit_title" name="title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_category_id">Kategorie</label>
                        <select class="form-control" id="edit_category_id" name="category_id">
                            <option value="">Keine Kategorie</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['id']); ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_content">Inhalt *</label>
                        <textarea class="form-control" id="edit_content" name="content" rows="10" required></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Notiz löschen Modal -->
<div class="modal fade" id="deleteNoteModal" tabindex="-1" role="dialog" aria-labelledby="deleteNoteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="notes.php" method="post">
                <input type="hidden" name="action" value="delete_note">
                <input type="hidden" id="delete_note_id" name="note_id" value="">
                
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteNoteModalLabel">Notiz löschen</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Schließen">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                
                <div class="modal-body">
                    <p>Bist du sicher, dass du die Notiz <strong id="delete_note_title"></strong> löschen möchtest?</p>
                    <p>Diese Aktion kann nicht rückgängig gemacht werden.</p>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-danger">Löschen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Kategorien verwalten Modal -->
<div class="modal fade" id="manageCategoriesModal" tabindex="-1" role="dialog" aria-labelledby="manageCategoriesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="manageCategoriesModalLabel">Kategorien verwalten</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Schließen">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-12">
                        <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#createCategoryModal">
                            <i class="fas fa-plus"></i> Neue Kategorie
                        </button>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Farbe</th>
                                <th>Name</th>
                                <th>Anzahl Notizen</th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <?php
                                // Zähle Notizen in dieser Kategorie
                                $noteCount = 0;
                                foreach ($notes as $note) {
                                    if ($note['category_id'] === $category['id']) {
                                        $noteCount++;
                                    }
                                }
                                ?>
                                <tr>
                                    <td>
                                        <span class="category-color-display" style="display: inline-block; width: 20px; height: 20px; background-color: <?php echo htmlspecialchars($category['color']); ?>; border-radius: 3px;"></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                                    <td><?php echo $noteCount; ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary edit-category-btn" 
                                                data-category-id="<?php echo htmlspecialchars($category['id']); ?>"
                                                data-category-name="<?php echo htmlspecialchars($category['name']); ?>"
                                                data-category-color="<?php echo htmlspecialchars($category['color']); ?>">
                                            <i class="fas fa-edit"></i> Bearbeiten
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-category-btn"
                                                data-category-id="<?php echo htmlspecialchars($category['id']); ?>"
                                                data-category-name="<?php echo htmlspecialchars($category['name']); ?>"
                                                data-note-count="<?php echo $noteCount; ?>">
                                            <i class="fas fa-trash"></i> Löschen
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Schließen</button>
            </div>
        </div>
    </div>
</div>

<!-- Kategorie erstellen Modal -->
<div class="modal fade" id="createCategoryModal" tabindex="-1" role="dialog" aria-labelledby="createCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="notes.php" method="post">
                <input type="hidden" name="action" value="create_category">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="createCategoryModalLabel">Neue Kategorie erstellen</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Schließen">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                
                <div class="modal-body">
                    <div class="form-group">
                        <label for="category_name">Name *</label>
                        <input type="text" class="form-control" id="category_name" name="category_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="category_color">Farbe</label>
                        <input type="color" class="form-control" id="category_color" name="category_color" value="#6c757d">
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Kategorie bearbeiten Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" role="dialog" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="notes.php" method="post">
                <input type="hidden" name="action" value="edit_category">
                <input type="hidden" id="edit_category_id" name="category_id" value="">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="editCategoryModalLabel">Kategorie bearbeiten</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Schließen">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_category_name">Name *</label>
                        <input type="text" class="form-control" id="edit_category_name" name="category_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_category_color">Farbe</label>
                        <input type="color" class="form-control" id="edit_category_color" name="category_color" value="#6c757d">
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Kategorie löschen Modal -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1" role="dialog" aria-labelledby="deleteCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="notes.php" method="post">
                <input type="hidden" name="action" value="delete_category">
                <input type="hidden" id="delete_category_id" name="category_id" value="">
                
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteCategoryModalLabel">Kategorie löschen</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Schließen">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                
                <div class="modal-body">
                    <p>Bist du sicher, dass du die Kategorie <strong id="delete_category_name"></strong> löschen möchtest?</p>
                    <div id="delete_category_warning" class="alert alert-warning d-none">
                        <strong>Achtung:</strong> Es gibt <span id="delete_category_note_count"></span> Notizen in dieser Kategorie. 
                        Beim Löschen wird die Kategorie von diesen Notizen entfernt.
                    </div>
                    <p>Diese Aktion kann nicht rückgängig gemacht werden.</p>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-danger">Löschen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.note-card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    transition: transform 0.2s, box-shadow 0.2s;
}
.note-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}
.note-content {
    white-space: pre-line;
    max-height: 150px;
    overflow: hidden;
}
</style>

<script>
$(document).ready(function() {
    // Initialisiere Tooltips
    $('[data-toggle="tooltip"]').tooltip();
    
    // Notiz ansehen
    $('.view-note-btn').on('click', function() {
        const noteTitle = $(this).data('note-title');
        const noteContent = $(this).data('note-content');
        
        // Fülle das Modal mit den Notizdetails
        $('#viewNoteModalLabel').text(noteTitle);
        $('#viewNoteContent').html(noteContent.replace(/\n/g, '<br>'));
        
        // Speichere die Notiz-ID für das Bearbeiten-Button
        $('.edit-viewed-note').data('note-id', $(this).data('note-id'));
    });
    
    // Bearbeiten-Button im Ansichtsmodal
    $('.edit-viewed-note').on('click', function() {
        const noteId = $(this).data('note-id');
        $('#viewNoteModal').modal('hide');
        
        // AJAX-Anfrage, um die Notizdetails zu laden
        $.ajax({
            url: 'notes.php',
            method: 'GET',
            data: {
                action: 'get_note',
                note_id: noteId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const note = response.note;
                    
                    // Fülle das Formular mit den Notizdetails
                    $('#edit_note_id').val(note.id);
                    $('#edit_title').val(note.title);
                    $('#edit_content').val(note.content);
                    $('#edit_category_id').val(note.category_id);
                    
                    // Öffne das Modal
                    $('#editNoteModal').modal('show');
                } else {
                    alert('Fehler: ' + response.message);
                }
            },
            error: function() {
                alert('Fehler beim Laden der Notizdetails.');
            }
        });
    });
    
    // Notiz bearbeiten
    $('.edit-note-btn').on('click', function() {
        const noteId = $(this).data('note-id');
        
        // AJAX-Anfrage, um die Notizdetails zu laden
        $.ajax({
            url: 'notes.php',
            method: 'GET',
            data: {
                action: 'get_note',
                note_id: noteId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const note = response.note;
                    
                    // Fülle das Formular mit den Notizdetails
                    $('#edit_note_id').val(note.id);
                    $('#edit_title').val(note.title);
                    $('#edit_content').val(note.content);
                    $('#edit_category_id').val(note.category_id);
                    
                    // Öffne das Modal
                    $('#editNoteModal').modal('show');
                } else {
                    alert('Fehler: ' + response.message);
                }
            },
            error: function() {
                alert('Fehler beim Laden der Notizdetails.');
            }
        });
    });
    
    // Notiz löschen
    $('.delete-note-btn').on('click', function() {
        const noteId = $(this).data('note-id');
        const noteTitle = $(this).data('note-title');
        
        $('#delete_note_id').val(noteId);
        $('#delete_note_title').text(noteTitle);
        $('#deleteNoteModal').modal('show');
    });
    
    // Kategorie bearbeiten
    $('.edit-category-btn').on('click', function() {
        const categoryId = $(this).data('category-id');
        const categoryName = $(this).data('category-name');
        const categoryColor = $(this).data('category-color');
        
        $('#edit_category_id').val(categoryId);
        $('#edit_category_name').val(categoryName);
        $('#edit_category_color').val(categoryColor);
        
        $('#manageCategoriesModal').modal('hide');
        $('#editCategoryModal').modal('show');
    });
    
    // Kategorie löschen
    $('.delete-category-btn').on('click', function() {
        const categoryId = $(this).data('category-id');
        const categoryName = $(this).data('category-name');
        const noteCount = parseInt($(this).data('note-count'));
        
        $('#delete_category_id').val(categoryId);
        $('#delete_category_name').text(categoryName);
        
        if (noteCount > 0) {
            $('#delete_category_note_count').text(noteCount);
            $('#delete_category_warning').removeClass('d-none');
        } else {
            $('#delete_category_warning').addClass('d-none');
        }
        
        $('#manageCategoriesModal').modal('hide');
        $('#deleteCategoryModal').modal('show');
    });
    
    // Wenn ein Modal geschlossen wird, öffne das Eltern-Modal wieder (für verschachtelte Modals)
    $('#createCategoryModal, #editCategoryModal, #deleteCategoryModal').on('hidden.bs.modal', function() {
        $('#manageCategoriesModal').modal('show');
    });
    
    // Suche in Notizen
    $('#searchNotes').on('keyup', function() {
        const searchText = $(this).val().toLowerCase();
        $('.note-item').each(function() {
            const cardText = $(this).text().toLowerCase();
            $(this).toggle(cardText.indexOf(searchText) > -1);
        });
    });
    
    // Filtern nach Kategorie-Tabs
    $('.nav-tabs .nav-link').on('click', function(e) {
        e.preventDefault();
        
        // Aktiviere den ausgewählten Tab
        $('.nav-tabs .nav-link').removeClass('active');
        $(this).addClass('active');
        
        const categoryId = $(this).data('category');
        
        if (categoryId === 'all') {
            $('.note-item').show();
        } else {
            $('.note-item').each(function() {
                const noteCategoryId = $(this).data('category-id');
                $(this).toggle(noteCategoryId === categoryId);
            });
        }
    });
});
</script>

<?php
include_once '../includes/footer.php';
?>