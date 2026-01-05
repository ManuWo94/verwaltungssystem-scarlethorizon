<?php
/**
 * Aktenverwaltungssystem - Department of Justice
 * Persönliches Aufgabenlisten-Modul
 * 
 * Dieses Modul ermöglicht die Verwaltung von persönlichen Aufgaben (To-Dos),
 * die nur für den erstellenden Benutzer sichtbar sind. Benutzer können
 * Aufgaben kategorisieren, Fälligkeitsdaten festlegen und als erledigt markieren.
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

// Enforce todos view permission
checkPermissionOrDie('todos', 'view');

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role']; // Hauptrolle
$roles = isset($_SESSION['roles']) ? $_SESSION['roles'] : [$role]; // Alle Rollen
$message = '';
$error = '';

// Verzeichnisse und Dateien für Aufgaben
$todosDir = '../data/todos/';
$todosFile = $todosDir . 'todos_' . $user_id . '.json';
$categoriesFile = $todosDir . 'todo_categories_' . $user_id . '.json';

// Verzeichnisse erstellen, falls sie nicht existieren
if (!file_exists($todosDir)) {
    mkdir($todosDir, 0755, true);
}

// Lade Aufgaben des Benutzers
$todos = getJsonData($todosFile);
if ($todos === false) {
    $todos = [];
}

// Lade Kategorien des Benutzers
$categories = getJsonData($categoriesFile);
if ($categories === false) {
    // Standard-Kategorien, wenn keine definiert sind
    $categories = [
        [
            'id' => generateUniqueId(),
            'name' => 'Arbeit',
            'color' => '#6c757d', // Grau statt Blau
            'created_at' => date('Y-m-d H:i:s')
        ],
        [
            'id' => generateUniqueId(),
            'name' => 'Persönlich',
            'color' => '#e74c3c',
            'created_at' => date('Y-m-d H:i:s')
        ],
        [
            'id' => generateUniqueId(),
            'name' => 'Wichtig',
            'color' => '#f39c12',
            'created_at' => date('Y-m-d H:i:s')
        ]
    ];
    saveJsonData($categoriesFile, $categories);
}

// AJAX-Anfragen verarbeiten
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    // Aufgabe als erledigt markieren
    if ($action === 'toggle_todo_status') {
        // Permission check for modifying todos (AJAX)
        if (!checkUserPermission($_SESSION['user_id'], 'todos', 'edit')) {
            echo json_encode(['success' => false, 'message' => 'Zugriff verweigert']);
            exit;
        }

        $todoId = isset($_POST['todo_id']) ? sanitize($_POST['todo_id']) : '';
        $todoFound = false;
        
        foreach ($todos as $key => $todo) {
            if ($todo['id'] === $todoId) {
                $todoFound = true;
                $todos[$key]['completed'] = !$todos[$key]['completed'];
                $todos[$key]['completed_at'] = $todos[$key]['completed'] ? date('Y-m-d H:i:s') : null;
                $todos[$key]['updated_at'] = date('Y-m-d H:i:s');
                
                if (saveJsonData($todosFile, $todos)) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Status der Aufgabe wurde aktualisiert.',
                        'todo' => $todos[$key]
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Fehler beim Speichern des Aufgabenstatus.'
                    ]);
                }
                
                break;
            }
        }
        
        if (!$todoFound) {
            echo json_encode([
                'success' => false,
                'message' => 'Aufgabe nicht gefunden.'
            ]);
        }
        
        exit;
    }
    
    // Aufgabendetails abrufen
    elseif ($action === 'get_todo') {
        $todoId = isset($_GET['todo_id']) ? sanitize($_GET['todo_id']) : '';
        $todoFound = false;
        
        foreach ($todos as $todo) {
            if ($todo['id'] === $todoId) {
                $todoFound = true;
                echo json_encode([
                    'success' => true,
                    'todo' => $todo
                ]);
                break;
            }
        }
        
        if (!$todoFound) {
            echo json_encode([
                'success' => false,
                'message' => 'Aufgabe nicht gefunden.'
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
    
    // Aufgabe erstellen
    if ($action === 'create_todo') {
        if (empty($_POST['title'])) {
            $error = 'Bitte gib einen Titel für die Aufgabe an.';
        } else {
            $categoryId = isset($_POST['category_id']) ? sanitize($_POST['category_id']) : '';
            $dueDate = !empty($_POST['due_date']) ? sanitize($_POST['due_date']) : null;
            
            // Überprüfe, ob die Kategorie existiert
            $categoryExists = false;
            $categoryName = '';
            $categoryColor = '#999999'; // Standardfarbe
            
            if (!empty($categoryId)) {
                foreach ($categories as $category) {
                    if ($category['id'] === $categoryId) {
                        $categoryExists = true;
                        $categoryName = $category['name'];
                        $categoryColor = $category['color'];
                        break;
                    }
                }
                
                if (!$categoryExists) {
                    $error = 'Die ausgewählte Kategorie existiert nicht.';
                }
            }
            
            if (empty($error)) {
                $todoData = [
                    'id' => generateUniqueId(),
                    'title' => sanitize($_POST['title']),
                    'description' => isset($_POST['description']) ? sanitize($_POST['description']) : '',
                    'category_id' => $categoryId,
                    'category_name' => $categoryName,
                    'category_color' => $categoryColor,
                    'due_date' => $dueDate,
                    'completed' => false,
                    'completed_at' => null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                // Überprüfen, ob die Aufgabe bereits existiert (doppelte Einträge verhindern)
                $taskExists = false;
                foreach ($todos as $existingTodo) {
                    if (isset($existingTodo['title']) && 
                        $existingTodo['title'] === $todoData['title'] && 
                        isset($existingTodo['created_at']) && 
                        abs(strtotime($existingTodo['created_at']) - strtotime($todoData['created_at'])) < 60) { // Innerhalb einer Minute
                        $taskExists = true;
                        break;
                    }
                }
                
                if (!$taskExists) {
                    // Füge die neue Aufgabe hinzu
                    array_unshift($todos, $todoData);
                    
                    if (saveJsonData($todosFile, $todos)) {
                        $message = 'Aufgabe wurde erfolgreich erstellt.';
                    } else {
                        $error = 'Fehler beim Speichern der Aufgabe.';
                    }
                } else {
                    $message = 'Aufgabe wurde bereits erstellt.';
                }
            }
        }
    }
    
    // Aufgabe bearbeiten
    elseif ($action === 'edit_todo' && isset($_POST['todo_id'])) {
        $todoId = sanitize($_POST['todo_id']);
        $todoFound = false;
        
        if (empty($_POST['title'])) {
            $error = 'Bitte gib einen Titel für die Aufgabe an.';
        } else {
            $categoryId = isset($_POST['category_id']) ? sanitize($_POST['category_id']) : '';
            $dueDate = !empty($_POST['due_date']) ? sanitize($_POST['due_date']) : null;
            
            // Überprüfe, ob die Kategorie existiert
            $categoryExists = false;
            $categoryName = '';
            $categoryColor = '#999999'; // Standardfarbe
            
            if (!empty($categoryId)) {
                foreach ($categories as $category) {
                    if ($category['id'] === $categoryId) {
                        $categoryExists = true;
                        $categoryName = $category['name'];
                        $categoryColor = $category['color'];
                        break;
                    }
                }
                
                if (!$categoryExists) {
                    $error = 'Die ausgewählte Kategorie existiert nicht.';
                }
            }
            
            if (empty($error)) {
                foreach ($todos as $key => $todo) {
                    if ($todo['id'] === $todoId) {
                        $todoFound = true;
                        
                        // Aktualisiere die Aufgabe
                        $todos[$key]['title'] = sanitize($_POST['title']);
                        $todos[$key]['description'] = isset($_POST['description']) ? sanitize($_POST['description']) : '';
                        $todos[$key]['category_id'] = $categoryId;
                        $todos[$key]['category_name'] = $categoryName;
                        $todos[$key]['category_color'] = $categoryColor;
                        $todos[$key]['due_date'] = $dueDate;
                        $todos[$key]['updated_at'] = date('Y-m-d H:i:s');
                        
                        if (saveJsonData($todosFile, $todos)) {
                            $message = 'Aufgabe wurde erfolgreich aktualisiert.';
                        } else {
                            $error = 'Fehler beim Speichern der Aufgabe.';
                        }
                        
                        break;
                    }
                }
                
                if (!$todoFound) {
                    $error = 'Die zu bearbeitende Aufgabe wurde nicht gefunden.';
                }
            }
        }
    }
    
    // Aufgabe löschen
    elseif ($action === 'delete_todo' && isset($_POST['todo_id'])) {
        $todoId = sanitize($_POST['todo_id']);
        $todoFound = false;
        
        foreach ($todos as $key => $todo) {
            if ($todo['id'] === $todoId) {
                $todoFound = true;
                
                // Lösche die Aufgabe
                array_splice($todos, $key, 1);
                
                if (saveJsonData($todosFile, $todos)) {
                    $message = 'Aufgabe wurde erfolgreich gelöscht.';
                } else {
                    $error = 'Fehler beim Löschen der Aufgabe.';
                }
                
                break;
            }
        }
        
        if (!$todoFound) {
            $error = 'Die zu löschende Aufgabe wurde nicht gefunden.';
        }
    }
    
    // Kategorie erstellen
    elseif ($action === 'create_category') {
        if (empty($_POST['category_name'])) {
            $error = 'Bitte gib einen Namen für die Kategorie an.';
        } else {
            $categoryName = sanitize($_POST['category_name']);
            $categoryColor = !empty($_POST['category_color']) ? sanitize($_POST['category_color']) : '#3498db';
            
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
            $categoryColor = !empty($_POST['category_color']) ? sanitize($_POST['category_color']) : '#3498db';
            
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
                            // Aktualisiere auch die Kategorieinfos in allen Aufgaben
                            foreach ($todos as $todoKey => $todo) {
                                if ($todo['category_id'] === $categoryId) {
                                    $todos[$todoKey]['category_name'] = $categoryName;
                                    $todos[$todoKey]['category_color'] = $categoryColor;
                                }
                            }
                            
                            if (saveJsonData($todosFile, $todos)) {
                                $message = 'Kategorie wurde erfolgreich aktualisiert.';
                            } else {
                                $error = 'Kategorie aktualisiert, aber Fehler beim Aktualisieren der Aufgaben.';
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
                    // Setze die Kategorie aller betroffenen Aufgaben auf leer
                    foreach ($todos as $todoKey => $todo) {
                        if ($todo['category_id'] === $categoryId) {
                            $todos[$todoKey]['category_id'] = '';
                            $todos[$todoKey]['category_name'] = '';
                            $todos[$todoKey]['category_color'] = '';
                        }
                    }
                    
                    if (saveJsonData($todosFile, $todos)) {
                        $message = 'Kategorie wurde erfolgreich gelöscht.';
                    } else {
                        $error = 'Kategorie gelöscht, aber Fehler beim Aktualisieren der Aufgaben.';
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

// Sortiere Aufgaben: offene zuerst, dann nach Fälligkeit und dann nach Erstellungsdatum
usort($todos, function($a, $b) {
    // Zuerst nach Abschluss (nicht abgeschlossene zuerst)
    if ($a['completed'] !== $b['completed']) {
        return $a['completed'] ? 1 : -1;
    }
    
    // Dann nach Fälligkeit, aber nur für nicht abgeschlossene Aufgaben
    if (!$a['completed'] && !$b['completed']) {
        if (empty($a['due_date']) && !empty($b['due_date'])) {
            return 1; // Ohne Fälligkeit kommen nach denen mit Fälligkeit
        } elseif (!empty($a['due_date']) && empty($b['due_date'])) {
            return -1; // Mit Fälligkeit kommen vor denen ohne Fälligkeit
        } elseif (!empty($a['due_date']) && !empty($b['due_date'])) {
            return strtotime($a['due_date']) - strtotime($b['due_date']); // Nach Fälligkeit sortieren
        }
    }
    
    // Zuletzt nach Erstellungsdatum, neuere zuerst
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Gruppiere Aufgaben nach Kategorie
$groupedTodos = [];
foreach ($todos as $todo) {
    $categoryId = $todo['category_id'] ?: 'uncategorized';
    if (!isset($groupedTodos[$categoryId])) {
        $groupedTodos[$categoryId] = [
            'id' => $categoryId,
            'name' => $todo['category_name'] ?: 'Ohne Kategorie',
            'color' => $todo['category_color'] ?: '#999999',
            'todos' => []
        ];
    }
    $groupedTodos[$categoryId]['todos'][] = $todo;
}

// Sortiere die Kategorien nach Name
uasort($groupedTodos, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

// Seitentitel und Beschreibung
$pageTitle = 'Meine Aufgaben';
$pageDescription = 'Persönliche Aufgabenliste verwalten';

// HTML-Template einbinden
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include_once '../includes/sidebar.php'; ?>
        
        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Aufgabenliste</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-sm btn-outline-primary mr-2" data-toggle="modal" data-target="#createTodoModal">
                        <i class="fas fa-plus"></i> Neue Aufgabe
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
                    <a class="nav-link active" href="#" data-category="all">Alle Aufgaben</a>
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
                    <a class="nav-link" href="#" data-category="uncategorized">Ohne Kategorie</a>
                </li>
            </ul>
            
            <!-- Filter und Suche -->
            <div class="row mb-3">
                <div class="col-md-8">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                        </div>
                        <input type="text" id="searchTodos" class="form-control" placeholder="Aufgaben durchsuchen...">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-filter"></i></span>
                        </div>
                        <select id="filterStatus" class="form-control">
                            <option value="all">Alle Aufgaben</option>
                            <option value="open" selected>Offene Aufgaben</option>
                            <option value="completed">Erledigte Aufgaben</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Aufgabenliste -->
            <div id="todosContainer">
                <?php if (empty($todos)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Du hast noch keine Aufgaben erstellt. Klicke auf "Neue Aufgabe", um deine erste Aufgabe zu erstellen.
                    </div>
                <?php else: ?>
                    <?php foreach ($groupedTodos as $categoryId => $category): ?>
                        <div class="card mb-3">
                            <div class="card-body p-0">
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($category['todos'] as $todo): ?>
                                        <li class="list-group-item todo-item <?php echo $todo['completed'] ? 'todo-completed' : ''; ?>" 
                                            data-todo-id="<?php echo htmlspecialchars($todo['id']); ?>"
                                            data-category-id="<?php echo htmlspecialchars($todo['category_id']); ?>"
                                            data-completed="<?php echo $todo['completed'] ? 'true' : 'false'; ?>">
                                            
                                            <div class="d-flex align-items-center">
                                                <div class="mr-3">
                                                    <div class="custom-control custom-checkbox">
                                                        <input type="checkbox" class="custom-control-input todo-checkbox" 
                                                               id="todo-<?php echo htmlspecialchars($todo['id']); ?>"
                                                               <?php echo $todo['completed'] ? 'checked' : ''; ?>>
                                                        <label class="custom-control-label" for="todo-<?php echo htmlspecialchars($todo['id']); ?>"></label>
                                                    </div>
                                                </div>
                                                
                                                <div class="todo-content flex-grow-1">
                                                    <h5 class="mb-1 <?php echo $todo['completed'] ? 'text-muted text-decoration-line-through' : ''; ?>">
                                                        <?php echo htmlspecialchars($todo['title']); ?>
                                                    </h5>
                                                    
                                                    <?php if (!empty($todo['description'])): ?>
                                                        <p class="mb-1 text-muted todo-description">
                                                            <?php echo nl2br(htmlspecialchars($todo['description'])); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    
                                                    <div class="d-flex align-items-center mt-2">
                                                        <?php if (!empty($todo['due_date'])): ?>
                                                            <div class="mr-3">
                                                                <i class="far fa-calendar-alt"></i>
                                                                <?php 
                                                                $dueDate = new DateTime($todo['due_date']);
                                                                $today = new DateTime();
                                                                $todayFormatted = $today->format('Y-m-d');
                                                                $dueDateFormatted = $dueDate->format('Y-m-d');
                                                                $interval = $today->diff($dueDate);
                                                                $isOverdue = $dueDateFormatted < $todayFormatted;
                                                                
                                                                if ($isOverdue && !$todo['completed']) {
                                                                    echo '<span class="text-danger">' . $dueDate->format('d.m.Y') . ' (überfällig)</span>';
                                                                } else {
                                                                    echo '<span>' . $dueDate->format('d.m.Y') . '</span>';
                                                                }
                                                                ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <small class="text-muted">
                                                            Erstellt: <?php echo date('d.m.Y', strtotime($todo['created_at'])); ?>
                                                            <?php if ($todo['completed'] && $todo['completed_at']): ?>
                                                                | Erledigt: <?php echo date('d.m.Y', strtotime($todo['completed_at'])); ?>
                                                            <?php endif; ?>
                                                        </small>
                                                        
                                                        <?php if (!empty($todo['category_id']) && !empty($todo['category_name'])): ?>
                                                            <div class="ml-auto">
                                                                <span class="badge badge-pill" style="background-color: <?php echo htmlspecialchars($todo['category_color']); ?>; color: white;">
                                                                    <?php echo htmlspecialchars($todo['category_name']); ?>
                                                                </span>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="ml-3">
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-light" type="button" id="dropdownMenuButton-<?php echo htmlspecialchars($todo['id']); ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                            <i class="fas fa-ellipsis-v"></i>
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuButton-<?php echo htmlspecialchars($todo['id']); ?>">
                                                            <button class="dropdown-item edit-todo-btn" data-todo-id="<?php echo htmlspecialchars($todo['id']); ?>">
                                                                <i class="fas fa-edit"></i> Bearbeiten
                                                            </button>
                                                            <button class="dropdown-item delete-todo-btn" data-todo-id="<?php echo htmlspecialchars($todo['id']); ?>" data-todo-title="<?php echo htmlspecialchars($todo['title']); ?>">
                                                                <i class="fas fa-trash"></i> Löschen
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<!-- Neue Aufgabe Modal -->
<div class="modal fade" id="createTodoModal" tabindex="-1" role="dialog" aria-labelledby="createTodoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="todos.php" method="post">
                <input type="hidden" name="action" value="create_todo">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="createTodoModalLabel">Neue Aufgabe erstellen</h5>
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
                        <label for="description">Beschreibung</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="category_id">Kategorie</label>
                        <select class="form-control" id="category_id" name="category_id">
                            <option value="">Keine Kategorie</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['id']); ?>" style="color: <?php echo htmlspecialchars($category['color']); ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="due_date">Fälligkeitsdatum (optional)</label>
                        <input type="date" class="form-control" id="due_date" name="due_date" min="<?php echo date('Y-m-d'); ?>">
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

<!-- Aufgabe bearbeiten Modal -->
<div class="modal fade" id="editTodoModal" tabindex="-1" role="dialog" aria-labelledby="editTodoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="todos.php" method="post">
                <input type="hidden" name="action" value="edit_todo">
                <input type="hidden" id="edit_todo_id" name="todo_id" value="">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="editTodoModalLabel">Aufgabe bearbeiten</h5>
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
                        <label for="edit_description">Beschreibung</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_category_id">Kategorie</label>
                        <select class="form-control" id="edit_category_id" name="category_id">
                            <option value="">Keine Kategorie</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['id']); ?>" style="color: <?php echo htmlspecialchars($category['color']); ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_due_date">Fälligkeitsdatum (optional)</label>
                        <input type="date" class="form-control" id="edit_due_date" name="due_date">
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

<!-- Aufgabe löschen Modal -->
<div class="modal fade" id="deleteTodoModal" tabindex="-1" role="dialog" aria-labelledby="deleteTodoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="todos.php" method="post">
                <input type="hidden" name="action" value="delete_todo">
                <input type="hidden" id="delete_todo_id" name="todo_id" value="">
                
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteTodoModalLabel">Aufgabe löschen</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Schließen">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                
                <div class="modal-body">
                    <p>Bist du sicher, dass du die Aufgabe <strong id="delete_todo_title"></strong> löschen möchtest?</p>
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
                                <th>Anzahl Aufgaben</th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <?php
                                // Zähle Aufgaben in dieser Kategorie
                                $todoCount = 0;
                                foreach ($todos as $todo) {
                                    if ($todo['category_id'] === $category['id']) {
                                        $todoCount++;
                                    }
                                }
                                ?>
                                <tr>
                                    <td>
                                        <span class="category-color-display" style="display: inline-block; width: 20px; height: 20px; background-color: <?php echo htmlspecialchars($category['color']); ?>; border-radius: 3px;"></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                                    <td><?php echo $todoCount; ?></td>
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
                                                data-todo-count="<?php echo $todoCount; ?>">
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
            <form action="todos.php" method="post">
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
                        <input type="color" class="form-control" id="category_color" name="category_color" value="#3498db">
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
            <form action="todos.php" method="post">
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
                        <input type="color" class="form-control" id="edit_category_color" name="category_color" value="#3498db">
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
            <form action="todos.php" method="post">
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
                        <strong>Achtung:</strong> Es gibt <span id="delete_category_todo_count"></span> Aufgaben in dieser Kategorie. 
                        Beim Löschen wird die Kategorie von diesen Aufgaben entfernt.
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
.todo-completed .todo-content {
    opacity: 0.7;
}
.todo-description {
    max-height: 100px;
    overflow-y: auto;
}
.text-decoration-line-through {
    text-decoration: line-through;
}
.card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    transition: transform 0.15s, box-shadow 0.15s;
}
/* Klinischere Todo-Liste */
.list-group-item.todo-item {
    border-left: none;
    border-right: none;
    padding: 12px 20px;
    transition: background-color 0.2s;
    border-bottom: 1px solid rgba(0,0,0,0.075);
}
.list-group-item.todo-item:last-child {
    border-bottom: none;
}
.list-group-item.todo-item:hover {
    background-color: rgba(0,0,0,0.01);
}
.badge.badge-pill {
    font-weight: normal;
    padding: 5px 10px;
    font-size: 0.75rem;
}
.card:hover {
    transform: translateY(-3px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}
</style>

<script>
$(document).ready(function() {
    // Initialisiere Tooltips
    $('[data-toggle="tooltip"]').tooltip();
    
    // Standard-Filter: Zeige nur offene Aufgaben
    filterTodosByStatus('open');
    
    // Suche in Aufgaben
    $('#searchTodos').on('keyup', function() {
        const searchText = $(this).val().toLowerCase();
        $('.todo-item').each(function() {
            const cardText = $(this).text().toLowerCase();
            $(this).toggle(cardText.indexOf(searchText) > -1);
        });
        
        // Verstecke Kategorien ohne sichtbare Aufgaben
        updateCategoriesVisibility();
    });
    
    // Filtern nach Kategorie-Tabs
    $('.nav-tabs .nav-link').on('click', function(e) {
        e.preventDefault();
        
        // Aktiviere den ausgewählten Tab
        $('.nav-tabs .nav-link').removeClass('active');
        $(this).addClass('active');
        
        const categoryId = $(this).data('category');
        
        if (categoryId === 'all') {
            $('.todo-item').show();
        } else {
            $('.todo-item').each(function() {
                const todoCategory = $(this).data('category-id') || 'uncategorized';
                $(this).toggle(todoCategory === categoryId);
            });
        }
        
        // Verstecke Kategorien ohne sichtbare Aufgaben
        updateCategoriesVisibility();
    });
    
    // Filtern nach Status
    $('#filterStatus').on('change', function() {
        filterTodosByStatus($(this).val());
        updateCategoriesVisibility();
    });
    
    function filterTodosByStatus(status) {
        if (status === 'all') {
            $('.todo-item').show();
        } else if (status === 'open') {
            $('.todo-item').each(function() {
                const isCompleted = $(this).data('completed') === 'true';
                $(this).toggle(!isCompleted);
            });
        } else if (status === 'completed') {
            $('.todo-item').each(function() {
                const isCompleted = $(this).data('completed') === 'true';
                $(this).toggle(isCompleted);
            });
        }
    }
    
    function updateCategoriesVisibility() {
        $('.category-section').each(function() {
            const visibleTodos = $(this).find('.todo-item:visible').length;
            $(this).toggle(visibleTodos > 0);
        });
    }
    
    // Erledigt-Status umschalten
    $('.todo-checkbox').on('change', function() {
        const todoId = $(this).closest('.todo-item').data('todo-id');
        const isChecked = $(this).prop('checked');
        
        $.ajax({
            url: 'todos.php',
            method: 'POST',
            data: {
                action: 'toggle_todo_status',
                todo_id: todoId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const todoItem = $('.todo-item[data-todo-id="' + todoId + '"]');
                    todoItem.data('completed', isChecked ? 'true' : 'false');
                    
                    if (isChecked) {
                        todoItem.addClass('todo-completed');
                        todoItem.find('h5').addClass('text-muted text-decoration-line-through');
                        
                        // Hinzufügen des Erledigt-Datums
                        const completedDate = new Date().toLocaleDateString('de-DE');
                        todoItem.find('small.text-muted').append(' | Erledigt: ' + completedDate);
                    } else {
                        todoItem.removeClass('todo-completed');
                        todoItem.find('h5').removeClass('text-muted text-decoration-line-through');
                        
                        // Entfernen des Erledigt-Datums
                        todoItem.find('small.text-muted').text('Erstellt: ' + new Date(response.todo.created_at).toLocaleDateString('de-DE'));
                    }
                    
                    // Aktualisiere Filter
                    const currentStatus = $('#filterStatus').val();
                    if (currentStatus !== 'all') {
                        filterTodosByStatus(currentStatus);
                        updateCategoriesVisibility();
                    }
                } else {
                    alert('Fehler: ' + response.message);
                    // Checkbox zurücksetzen
                    $(this).prop('checked', !isChecked);
                }
            },
            error: function() {
                alert('Fehler bei der Kommunikation mit dem Server.');
                // Checkbox zurücksetzen
                $(this).prop('checked', !isChecked);
            }
        });
    });
    
    // Aufgabe bearbeiten
    $('.edit-todo-btn').on('click', function() {
        const todoId = $(this).data('todo-id');
        
        // Hol die Aufgabe aus dem DOM (vereinfachte Version)
        const todoItem = $('.todo-item[data-todo-id="' + todoId + '"]');
        const title = todoItem.find('h5').text().trim();
        const description = todoItem.find('.todo-description').length > 0 ? 
                            todoItem.find('.todo-description').text().trim() : '';
        const categoryId = todoItem.data('category-id');
        
        // Datumsfeld extrahieren, wenn vorhanden
        let dueDate = '';
        const dateText = todoItem.find('.fa-calendar-alt').parent().text().trim();
        if (dateText) {
            const match = dateText.match(/(\d{2})\.(\d{2})\.(\d{4})/);
            if (match) {
                dueDate = match[3] + '-' + match[2] + '-' + match[1]; // YYYY-MM-DD
            }
        }
        
        // Formular füllen
        $('#edit_todo_id').val(todoId);
        $('#edit_title').val(title);
        $('#edit_description').val(description);
        $('#edit_category_id').val(categoryId);
        $('#edit_due_date').val(dueDate);
        
        // Modal öffnen
        $('#editTodoModal').modal('show');
    });
    
    // Aufgabe löschen
    $('.delete-todo-btn').on('click', function() {
        const todoId = $(this).data('todo-id');
        const todoTitle = $(this).data('todo-title');
        
        $('#delete_todo_id').val(todoId);
        $('#delete_todo_title').text(todoTitle);
        $('#deleteTodoModal').modal('show');
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
        const todoCount = parseInt($(this).data('todo-count'));
        
        $('#delete_category_id').val(categoryId);
        $('#delete_category_name').text(categoryName);
        
        if (todoCount > 0) {
            $('#delete_category_todo_count').text(todoCount);
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
});
</script>

<?php
include_once '../includes/footer.php';
?>