<?php
/**
 * Aktenverwaltungssystem - Department of Justice
 * Aufgabenverteilungs-Modul
 * 
 * Dieses Modul ermöglicht der Leitungsebene, Direktoren, Commandern und Administrative 
 * Assistants, Aufgaben für andere Benutzer zu erstellen und zu verwalten.
 */

// Lade die Session-Konfiguration
require_once '../includes/session_config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';

// Überprüfen, ob der Benutzer angemeldet ist
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Enforce view permission for task assignments
checkPermissionOrDie('task_assignments', 'view');

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role']; // Hauptrolle
$roles = isset($_SESSION['roles']) ? $_SESSION['roles'] : [$role]; // Alle Rollen

// Prüfe, ob der Benutzer berechtigt ist, Aufgaben zuzuweisen
$canAssignTasks = false;
$managerRoles = [
    'Chief Justice', 'Senior Associate Justice', 'Attorney General', 
    'Director', 'Commander', 'Administrative Assistant', 'System Administrator', 'Administrator'
];

// Neues Kriterium: Berechtigungen aus dem Rollensystem
$hasTaskPerm = checkUserPermission($user_id, 'task_assignments', 'edit') 
    || checkUserPermission($user_id, 'task_assignments', 'create') 
    || checkUserPermission($user_id, 'task_assignments', 'delete');

// System Administrator und Administrator haben vollen Zugriff oder Berechtigung aus Rollen
if ($hasTaskPerm || $role === 'System Administrator' || $role === 'Administrator' || 
    in_array('System Administrator', $roles) || in_array('Administrator', $roles) || 
    in_array('Chief Justice', $roles)) {
    $canAssignTasks = true;
} else {
    foreach ($roles as $userRole) {
        if (in_array($userRole, $managerRoles)) {
            $canAssignTasks = true;
            break;
        }
    }
}

$message = '';
$error = '';

// Verzeichnisse und Dateien für Aufgaben
$tasksDir = '../data/tasks/';
$tasksFile = $tasksDir . 'assigned_tasks.json';
$categoriesFile = $tasksDir . 'task_categories.json';
$usersFile = '../data/users.json';

// Verzeichnisse erstellen, falls sie nicht existieren
if (!file_exists($tasksDir)) {
    mkdir($tasksDir, 0755, true);
}

// Lade Aufgaben
$tasks = getJsonData($tasksFile);
if ($tasks === false) {
    $tasks = [];
}

// Lade Kategorien
$categories = getJsonData($categoriesFile);
if ($categories === false) {
    // Standard-Kategorien, wenn keine definiert sind
    $categories = [
        [
            'id' => generateUniqueId(),
            'name' => 'Dringend',
            'color' => '#e74c3c',
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $user_id
        ],
        [
            'id' => generateUniqueId(),
            'name' => 'Standard',
            'color' => '#6c757d',
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $user_id
        ],
        [
            'id' => generateUniqueId(),
            'name' => 'Langfristig',
            'color' => '#2ecc71',
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $user_id
        ]
    ];
    saveJsonData($categoriesFile, $categories);
}

// Lade Benutzer
$users = getJsonData($usersFile);
if ($users === false) {
    $users = [];
}

// Filtere aktive Benutzer
$activeUsers = [];
foreach ($users as $user) {
    // Unterstütze sowohl is_active als auch status für die Kompatibilität
    if ((isset($user['is_active']) && $user['is_active']) || 
        (isset($user['status']) && $user['status'] === 'active')) {
        $activeUsers[] = $user;
    }
}

// Sortiere Benutzer nach Rolle und Namen
usort($activeUsers, function($a, $b) {
    if ($a['role'] === $b['role']) {
        return strcmp($a['username'], $b['username']);
    }
    return strcmp($a['role'], $b['role']);
});

// AJAX-Anfragen verarbeiten
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    // Action kann entweder aus POST oder GET kommen
    $action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');
    
    // Aufgabe als erledigt markieren
    if ($action === 'toggle_task_status') {
        $taskId = isset($_POST['task_id']) ? sanitize($_POST['task_id']) : '';
        $taskFound = false;
        
        foreach ($tasks as $key => $task) {
            if ($task['id'] === $taskId) {
                $taskFound = true;
                
                // Prüfe, ob der Benutzer die Aufgabe bearbeiten darf (Zugewiesener, Ersteller oder Berechtigter)
                $canEdit = ($task['assigned_to'] === $user_id || $task['created_by'] === $user_id || $canAssignTasks);
                
                if (!$canEdit) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Sie haben keine Berechtigung, diese Aufgabe zu bearbeiten.'
                    ]);
                    exit;
                }
                
                $tasks[$key]['completed'] = !$tasks[$key]['completed'];
                $tasks[$key]['completed_at'] = $tasks[$key]['completed'] ? date('Y-m-d H:i:s') : null;
                $tasks[$key]['completed_by'] = $tasks[$key]['completed'] ? $user_id : null;
                $tasks[$key]['updated_at'] = date('Y-m-d H:i:s');
                $tasks[$key]['updated_by'] = $user_id;
                
                if (saveJsonData($tasksFile, $tasks)) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Status der Aufgabe wurde aktualisiert.',
                        'task' => $tasks[$key]
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
        
        if (!$taskFound) {
            echo json_encode([
                'success' => false,
                'message' => 'Aufgabe nicht gefunden.'
            ]);
        }
        
        exit;
    }
    
    // Aufgabendetails abrufen
    elseif ($action === 'get_task') {
        $taskId = isset($_GET['task_id']) ? sanitize($_GET['task_id']) : '';
        $taskFound = false;
        
        foreach ($tasks as $task) {
            if ($task['id'] === $taskId) {
                $taskFound = true;
                
                // Prüfe, ob der Benutzer die Aufgabe sehen darf
                $canView = ($task['assigned_to'] === $user_id || $task['created_by'] === $user_id || $canAssignTasks);
                
                if (!$canView) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Sie haben keine Berechtigung, diese Aufgabe zu sehen.'
                    ]);
                    exit;
                }
                
                // Füge Namen des Erstellers hinzu
                $createdByName = "Unbekannt";
                foreach ($users as $u) {
                    if ($u['id'] === $task['created_by']) {
                        $createdByName = isset($u['first_name']) && isset($u['last_name']) ? 
                                     $u['first_name'] . ' ' . $u['last_name'] : $u['username'];
                        break;
                    }
                }
                $task['created_by_name'] = $createdByName;
                
                // Füge Namen des Zugewiesenen hinzu
                $assignedToName = "Unbekannt";
                foreach ($users as $u) {
                    if ($u['id'] === $task['assigned_to']) {
                        $assignedToName = isset($u['first_name']) && isset($u['last_name']) ? 
                                      $u['first_name'] . ' ' . $u['last_name'] : $u['username'];
                        break;
                    }
                }
                $task['assigned_to_name'] = $assignedToName;
                
                // Füge Kategorieinformationen hinzu, falls vorhanden
                if (!empty($task['category_id'])) {
                    foreach ($categories as $category) {
                        if ($category['id'] === $task['category_id']) {
                            $task['category_name'] = $category['name'];
                            $task['category_color'] = $category['color'];
                            break;
                        }
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'task' => $task
                ]);
                break;
            }
        }
        
        if (!$taskFound) {
            echo json_encode([
                'success' => false,
                'message' => 'Aufgabe nicht gefunden.'
            ]);
        }
        
        exit;
    }
    
    // Kommentar hinzufügen
    elseif ($action === 'add_comment') {
        $taskId = isset($_POST['task_id']) ? sanitize($_POST['task_id']) : '';
        $comment = isset($_POST['comment']) ? sanitize($_POST['comment']) : '';
        $taskFound = false;
        
        if (empty($comment)) {
            echo json_encode([
                'success' => false,
                'message' => 'Der Kommentar darf nicht leer sein.'
            ]);
            exit;
        }
        
        foreach ($tasks as $key => $task) {
            if ($task['id'] === $taskId) {
                $taskFound = true;
                
                // Prüfe, ob der Benutzer die Aufgabe kommentieren darf
                $canComment = ($task['assigned_to'] === $user_id || $task['created_by'] === $user_id || $canAssignTasks);
                
                if (!$canComment) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Sie haben keine Berechtigung, diese Aufgabe zu kommentieren.'
                    ]);
                    exit;
                }
                
                // Erstelle ein neues Kommentar-Objekt
                $commentObj = [
                    'id' => generateUniqueId(),
                    'text' => $comment,
                    'created_by' => $user_id,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                // Füge das Kommentar hinzu
                if (!isset($tasks[$key]['comments'])) {
                    $tasks[$key]['comments'] = [];
                }
                
                $tasks[$key]['comments'][] = $commentObj;
                $tasks[$key]['updated_at'] = date('Y-m-d H:i:s');
                $tasks[$key]['updated_by'] = $user_id;
                
                if (saveJsonData($tasksFile, $tasks)) {
                    // Hole den Namen des Kommentators
                    $commentatorName = $username;
                    foreach ($users as $u) {
                        if ($u['id'] === $user_id) {
                            $commentatorName = isset($u['first_name']) && isset($u['last_name']) ? 
                                              $u['first_name'] . ' ' . $u['last_name'] : $u['username'];
                            break;
                        }
                    }
                    
                    $commentObj['created_by_name'] = $commentatorName;
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Kommentar wurde hinzugefügt.',
                        'comment' => $commentObj
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Fehler beim Speichern des Kommentars.'
                    ]);
                }
                
                break;
            }
        }
        
        if (!$taskFound) {
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

    // Enforce create/assign permission for task creation
    if (in_array($action, ['create_task', 'assign_task'])) {
        checkPermissionOrDie('task_assignments', 'create');
    }
    
    // Aufgabe erstellen
    if ($action === 'create_task') {
        // Prüfe, ob der Benutzer Aufgaben zuweisen darf
        if (!$canAssignTasks) {
            $error = 'Sie haben keine Berechtigung, Aufgaben zuzuweisen.';
        } 
        elseif (empty($_POST['title']) || empty($_POST['assigned_to'])) {
            $error = 'Bitte geben Sie einen Titel und einen Benutzer für die Aufgabe an.';
        } else {
            $assignedTo = sanitize($_POST['assigned_to']);
            $categoryId = isset($_POST['category_id']) ? sanitize($_POST['category_id']) : '';
            $dueDate = !empty($_POST['due_date']) ? sanitize($_POST['due_date']) : null;
            $priority = isset($_POST['priority']) ? intval($_POST['priority']) : 2; // 1=niedrig, 2=normal, 3=hoch
            
            // Prüfe, ob der Benutzer existiert
            $userExists = false;
            $assignedToName = '';
            
            foreach ($users as $u) {
                if ($u['id'] === $assignedTo) {
                    $userExists = true;
                    $assignedToName = isset($u['first_name']) && isset($u['last_name']) ? 
                                    $u['first_name'] . ' ' . $u['last_name'] : $u['username'];
                    break;
                }
            }
            
            if (!$userExists) {
                $error = 'Der ausgewählte Benutzer existiert nicht.';
            } else {
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
                    $taskData = [
                        'id' => generateUniqueId(),
                        'title' => sanitize($_POST['title']),
                        'description' => isset($_POST['description']) ? sanitize($_POST['description']) : '',
                        'category_id' => $categoryId,
                        'category_name' => $categoryName,
                        'category_color' => $categoryColor,
                        'priority' => $priority,
                        'assigned_to' => $assignedTo,
                        'assigned_to_name' => $assignedToName,
                        'due_date' => $dueDate,
                        'completed' => false,
                        'completed_at' => null,
                        'completed_by' => null,
                        'comments' => [],
                        'created_by' => $user_id,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_by' => $user_id,
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    // Füge die neue Aufgabe hinzu
                    array_unshift($tasks, $taskData);
                    
                    if (saveJsonData($tasksFile, $tasks)) {
                        $message = 'Aufgabe wurde erfolgreich erstellt und ' . $assignedToName . ' zugewiesen.';
                    } else {
                        $error = 'Fehler beim Speichern der Aufgabe.';
                    }
                }
            }
        }
    }
    
    // Aufgabe bearbeiten
    elseif ($action === 'edit_task' && isset($_POST['task_id'])) {
        $taskId = sanitize($_POST['task_id']);
        $taskFound = false;
        
        if (!$canAssignTasks) {
            $error = 'Sie haben keine Berechtigung, Aufgaben zu bearbeiten.';
        } 
        elseif (empty($_POST['title']) || empty($_POST['assigned_to'])) {
            $error = 'Bitte geben Sie einen Titel und einen Benutzer für die Aufgabe an.';
        } else {
            $assignedTo = sanitize($_POST['assigned_to']);
            $categoryId = isset($_POST['category_id']) ? sanitize($_POST['category_id']) : '';
            $dueDate = !empty($_POST['due_date']) ? sanitize($_POST['due_date']) : null;
            $priority = isset($_POST['priority']) ? intval($_POST['priority']) : 2;
            
            // Prüfe, ob der Benutzer existiert
            $userExists = false;
            $assignedToName = '';
            
            foreach ($users as $u) {
                if ($u['id'] === $assignedTo) {
                    $userExists = true;
                    $assignedToName = isset($u['first_name']) && isset($u['last_name']) ? 
                                    $u['first_name'] . ' ' . $u['last_name'] : $u['username'];
                    break;
                }
            }
            
            if (!$userExists) {
                $error = 'Der ausgewählte Benutzer existiert nicht.';
            } else {
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
                    foreach ($tasks as $key => $task) {
                        if ($task['id'] === $taskId) {
                            $taskFound = true;
                            
                            // Aktualisiere die Aufgabe
                            $tasks[$key]['title'] = sanitize($_POST['title']);
                            $tasks[$key]['description'] = isset($_POST['description']) ? sanitize($_POST['description']) : '';
                            $tasks[$key]['category_id'] = $categoryId;
                            $tasks[$key]['category_name'] = $categoryName;
                            $tasks[$key]['category_color'] = $categoryColor;
                            $tasks[$key]['priority'] = $priority;
                            $tasks[$key]['assigned_to'] = $assignedTo;
                            $tasks[$key]['assigned_to_name'] = $assignedToName;
                            $tasks[$key]['due_date'] = $dueDate;
                            $tasks[$key]['updated_by'] = $user_id;
                            $tasks[$key]['updated_at'] = date('Y-m-d H:i:s');
                            
                            if (saveJsonData($tasksFile, $tasks)) {
                                $message = 'Aufgabe wurde erfolgreich aktualisiert.';
                            } else {
                                $error = 'Fehler beim Speichern der Aufgabe.';
                            }
                            
                            break;
                        }
                    }
                    
                    if (!$taskFound) {
                        $error = 'Die zu bearbeitende Aufgabe wurde nicht gefunden.';
                    }
                }
            }
        }
    }
    
    // Aufgabe löschen
    elseif ($action === 'delete_task' && isset($_POST['task_id'])) {
        $taskId = sanitize($_POST['task_id']);
        $taskFound = false;
        
        if (!$canAssignTasks) {
            $error = 'Sie haben keine Berechtigung, Aufgaben zu löschen.';
        } else {
            foreach ($tasks as $key => $task) {
                if ($task['id'] === $taskId) {
                    $taskFound = true;
                    
                    // Lösche die Aufgabe
                    array_splice($tasks, $key, 1);
                    
                    if (saveJsonData($tasksFile, $tasks)) {
                        $message = 'Aufgabe wurde erfolgreich gelöscht.';
                    } else {
                        $error = 'Fehler beim Löschen der Aufgabe.';
                    }
                    
                    break;
                }
            }
            
            if (!$taskFound) {
                $error = 'Die zu löschende Aufgabe wurde nicht gefunden.';
            }
        }
    }
    
    // Kategorie erstellen
    elseif ($action === 'create_category') {
        if (!$canAssignTasks) {
            $error = 'Sie haben keine Berechtigung, Kategorien zu erstellen.';
        } 
        elseif (empty($_POST['category_name'])) {
            $error = 'Bitte geben Sie einen Namen für die Kategorie an.';
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
                    'created_by' => $user_id,
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
        
        if (!$canAssignTasks) {
            $error = 'Sie haben keine Berechtigung, Kategorien zu bearbeiten.';
        } 
        elseif (empty($_POST['category_name'])) {
            $error = 'Bitte geben Sie einen Namen für die Kategorie an.';
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
                        
                        // Aktualisiere die Kategorie
                        $categories[$key]['name'] = $categoryName;
                        $categories[$key]['color'] = $categoryColor;
                        
                        if (saveJsonData($categoriesFile, $categories)) {
                            // Aktualisiere auch die Kategorieinfos in allen Aufgaben
                            foreach ($tasks as $taskKey => $task) {
                                if ($task['category_id'] === $categoryId) {
                                    $tasks[$taskKey]['category_name'] = $categoryName;
                                    $tasks[$taskKey]['category_color'] = $categoryColor;
                                }
                            }
                            
                            if (saveJsonData($tasksFile, $tasks)) {
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
        
        if (!$canAssignTasks) {
            $error = 'Sie haben keine Berechtigung, Kategorien zu löschen.';
        } else {
            foreach ($categories as $key => $category) {
                if ($category['id'] === $categoryId) {
                    $categoryFound = true;
                    
                    // Lösche die Kategorie
                    array_splice($categories, $key, 1);
                    
                    if (saveJsonData($categoriesFile, $categories)) {
                        // Setze die Kategorie aller betroffenen Aufgaben auf leer
                        foreach ($tasks as $taskKey => $task) {
                            if ($task['category_id'] === $categoryId) {
                                $tasks[$taskKey]['category_id'] = '';
                                $tasks[$taskKey]['category_name'] = '';
                                $tasks[$taskKey]['category_color'] = '';
                            }
                        }
                        
                        if (saveJsonData($tasksFile, $tasks)) {
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
}

// Bestimme die sichtbaren Aufgaben für den Benutzer
$visibleTasks = [];
foreach ($tasks as $task) {
    // System Administrator und Chief Justice sehen alles
    // Manager können alle Aufgaben sehen
    // Andere Benutzer sehen nur Aufgaben, die ihnen zugewiesen wurden oder die sie erstellt haben
    if (in_array('System Administrator', $roles) || in_array('Chief Justice', $roles) || $role === 'System Administrator' || $canAssignTasks || $task['assigned_to'] === $user_id || $task['created_by'] === $user_id) {
        $visibleTasks[] = $task;
    }
}

// Sortiere Aufgaben: offene zuerst (nach Priorität), dann nach Fälligkeit und dann nach Erstellungsdatum
usort($visibleTasks, function($a, $b) {
    // Zuerst nach Abschluss (nicht abgeschlossene zuerst)
    if ($a['completed'] !== $b['completed']) {
        return $a['completed'] ? 1 : -1;
    }
    
    // Dann nach Priorität, aber nur für nicht abgeschlossene Aufgaben
    if (!$a['completed'] && !$b['completed']) {
        $priorityA = isset($a['priority']) ? $a['priority'] : 2;
        $priorityB = isset($b['priority']) ? $b['priority'] : 2;
        
        if ($priorityA !== $priorityB) {
            return $priorityB - $priorityA; // Höhere Priorität zuerst
        }
        
        // Dann nach Fälligkeit
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

// Seitentitel und Beschreibung
$pageTitle = 'Aufgabenverteilung';
$pageDescription = 'Aufgaben zuweisen und verwalten';

// HTML-Template einbinden
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include_once '../includes/sidebar.php'; ?>
        
        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Aufgabenverteilung</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <?php if ($canAssignTasks): ?>
                        <button type="button" class="btn btn-sm btn-outline-primary mr-2" data-toggle="modal" data-target="#createTaskModal">
                            <i class="fas fa-plus"></i> Neue Aufgabe zuweisen
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#manageCategoriesModal">
                            <i class="fas fa-tags"></i> Kategorien verwalten
                        </button>
                    <?php endif; ?>
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
            
            <!-- Benutzer-Navigation oben -->
            <ul class="nav nav-tabs mb-3">
                <?php if ($canAssignTasks): ?>
                <li class="nav-item">
                    <a class="nav-link active" href="#" data-user="all">
                        <i class="fas fa-users"></i> Alle Benutzer
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo !$canAssignTasks ? 'active' : ''; ?>" href="#" data-user="<?php echo htmlspecialchars($user_id); ?>">
                        <i class="fas fa-user-check"></i> Mir zugewiesen
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#" data-user="created_by_me">
                        <i class="fas fa-pencil-alt"></i> Von mir erstellt
                    </a>
                </li>
                
                <?php if ($canAssignTasks): ?>
                    <?php 
                    // Gruppiere Benutzer nach Abteilungen/Rollen
                    $usersByRole = [];
                    foreach ($activeUsers as $u) {
                        if ($u['id'] !== $user_id) {
                            $role = $u['role'];
                            if (!isset($usersByRole[$role])) {
                                $usersByRole[$role] = [];
                            }
                            $usersByRole[$role][] = $u;
                        }
                    }
                    
                    // Sortiere nach Rollennamen
                    ksort($usersByRole);
                    ?>
                    
                    <?php foreach ($usersByRole as $role => $roleUsers): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-users"></i> <?php echo htmlspecialchars($role); ?>
                            </a>
                            <div class="dropdown-menu">
                                <?php foreach ($roleUsers as $u): ?>
                                    <a class="dropdown-item" href="#" data-user="<?php echo htmlspecialchars($u['id']); ?>">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars(isset($u['first_name']) && isset($u['last_name']) ? 
                                            $u['first_name'] . ' ' . $u['last_name'] : $u['username']); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
            
            <!-- Kategorie-Navigation -->
            <ul class="nav nav-pills mb-3">
                <li class="nav-item">
                    <a class="nav-link active" href="#" data-category="all">Alle Kategorien</a>
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
                        <input type="text" id="searchTasks" class="form-control" placeholder="Aufgaben durchsuchen...">
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
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-tasks"></i> Aufgabenliste
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($visibleTasks)): ?>
                        <div class="p-3">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> 
                                <?php if ($canAssignTasks): ?>
                                    Es wurden noch keine Aufgaben erstellt. Klicken Sie auf "Neue Aufgabe zuweisen", um eine Aufgabe zu erstellen.
                                <?php else: ?>
                                    Ihnen wurden noch keine Aufgaben zugewiesen.
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover task-table mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;">Status</th>
                                        <th style="width: 40px;">Prio</th>
                                        <th>Titel</th>
                                        <th>Kategorie</th>
                                        <th>Zugewiesen an</th>
                                        <th>Fälligkeit</th>
                                        <th>Erstellt</th>
                                        <th style="width: 100px;">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($visibleTasks as $task): ?>
                                        <?php
                                        // Berechne die Prioitätsklasse
                                        $priorityClass = '';
                                        $priorityText = 'Normal';
                                        
                                        if (isset($task['priority'])) {
                                            switch ($task['priority']) {
                                                case 1:
                                                    $priorityClass = 'badge-info';
                                                    $priorityText = 'Niedrig';
                                                    break;
                                                case 2:
                                                    $priorityClass = 'badge-primary';
                                                    $priorityText = 'Normal';
                                                    break;
                                                case 3:
                                                    $priorityClass = 'badge-danger';
                                                    $priorityText = 'Hoch';
                                                    break;
                                            }
                                        }
                                        
                                        // Berechne Fälligkeitsklasse
                                        $dueDateClass = '';
                                        if (!$task['completed'] && !empty($task['due_date'])) {
                                            $today = new DateTime();
                                            $dueDate = new DateTime($task['due_date']);
                                            $todayFormatted = $today->format('Y-m-d');
                                            $dueDateFormatted = $dueDate->format('Y-m-d');
                                            
                                            if ($dueDateFormatted < $todayFormatted) {
                                                $dueDateClass = 'text-danger font-weight-bold';
                                            } elseif ($dueDateFormatted === $todayFormatted) {
                                                $dueDateClass = 'text-warning font-weight-bold';
                                            }
                                        }
                                        ?>
                                        <tr class="task-item <?php echo $task['completed'] ? 'task-completed text-muted' : ''; ?>" 
                                            data-task-id="<?php echo htmlspecialchars($task['id']); ?>"
                                            data-category-id="<?php echo htmlspecialchars($task['category_id']); ?>"
                                            data-assigned-to="<?php echo htmlspecialchars($task['assigned_to']); ?>"
                                            data-created-by="<?php echo htmlspecialchars($task['created_by']); ?>"
                                            data-completed="<?php echo $task['completed'] ? 'true' : 'false'; ?>">
                                            
                                            <td class="text-center">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input task-checkbox" 
                                                           id="task-<?php echo htmlspecialchars($task['id']); ?>"
                                                           <?php echo $task['completed'] ? 'checked' : ''; ?>>
                                                    <label class="custom-control-label" for="task-<?php echo htmlspecialchars($task['id']); ?>"></label>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $priorityClass; ?>" title="<?php echo $priorityText; ?>">
                                                    <?php for ($i = 0; $i < $task['priority']; $i++): ?>
                                                        <i class="fas fa-star"></i>
                                                    <?php endfor; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="#" class="view-task-btn" data-task-id="<?php echo htmlspecialchars($task['id']); ?>">
                                                    <?php echo htmlspecialchars($task['title']); ?>
                                                </a>
                                                <?php if (isset($task['comments']) && count($task['comments']) > 0): ?>
                                                    <span class="badge badge-info ml-2" title="<?php echo count($task['comments']); ?> Kommentare">
                                                        <i class="fas fa-comments"></i> <?php echo count($task['comments']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($task['category_id'])): ?>
                                                    <span class="badge" style="background-color: <?php echo htmlspecialchars($task['category_color']); ?>; color: white;">
                                                        <?php echo htmlspecialchars($task['category_name']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($task['assigned_to_name']); ?>
                                                <?php if ($task['assigned_to'] === $user_id): ?>
                                                    <span class="badge badge-secondary">Sie</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($task['due_date'])): ?>
                                                    <span class="<?php echo $dueDateClass; ?>">
                                                        <?php echo date('d.m.Y', strtotime($task['due_date'])); ?>
                                                        <?php if ($dueDateClass === 'text-danger font-weight-bold' && !$task['completed']): ?>
                                                            <span class="badge badge-danger">Überfällig</span>
                                                        <?php endif; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small>
                                                    <?php echo date('d.m.Y', strtotime($task['created_at'])); ?>
                                                    <?php 
                                                    // Finde den Namen des Erstellers
                                                    $creatorName = 'Unbekannt';
                                                    foreach ($users as $u) {
                                                        if ($u['id'] === $task['created_by']) {
                                                            $creatorName = isset($u['first_name']) && isset($u['last_name']) ? 
                                                                         $u['first_name'] . ' ' . $u['last_name'] : $u['username'];
                                                            break;
                                                        }
                                                    }
                                                    ?>
                                                    <span class="text-muted">(<?php echo htmlspecialchars($creatorName); ?>)</span>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <button class="btn btn-sm btn-outline-primary view-task-btn" data-task-id="<?php echo htmlspecialchars($task['id']); ?>" title="Details anzeigen">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    
                                                    <?php if ($canAssignTasks): ?>
                                                        <button class="btn btn-sm btn-outline-secondary edit-task-btn" data-task-id="<?php echo htmlspecialchars($task['id']); ?>" title="Bearbeiten">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger delete-task-btn" 
                                                                data-task-id="<?php echo htmlspecialchars($task['id']); ?>" 
                                                                data-task-title="<?php echo htmlspecialchars($task['title']); ?>"
                                                                title="Löschen">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Aufgabe ansehen Modal -->
<div class="modal fade" id="viewTaskModal" tabindex="-1" role="dialog" aria-labelledby="viewTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewTaskModalLabel">Aufgabendetails</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Schließen">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            
            <div class="modal-body">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="sr-only">Laden...</span>
                    </div>
                </div>
                
                <div id="taskDetails" style="display: none;">
                    <div class="mb-3">
                        <h4 id="taskTitle" class="mb-1">Aufgabentitel</h4>
                        <div class="d-flex flex-wrap align-items-center text-muted mb-2">
                            <div id="taskCategory" class="mr-3"></div>
                            <div id="taskPriority" class="mr-3"></div>
                            <div id="taskAssignee" class="mr-3"></div>
                            <div id="taskDueDate" class="mr-3"></div>
                            <div id="taskStatus"></div>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="mb-0">Beschreibung</h5>
                        </div>
                        <div class="card-body">
                            <p id="taskDescription" class="mb-0">Keine Beschreibung verfügbar.</p>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Kommentare</h5>
                            <span id="commentCount" class="badge badge-info">0</span>
                        </div>
                        <div class="card-body">
                            <div id="taskComments" class="mb-3">
                                <div class="text-center text-muted" id="noCommentsMessage">
                                    <p>Keine Kommentare vorhanden.</p>
                                </div>
                            </div>
                            
                            <form id="addCommentForm" action="task_assignments.php" method="post" onsubmit="return false;">
                                <input type="hidden" id="comment_task_id" name="task_id" value="">
                                
                                <div class="form-group">
                                    <label for="comment_text">Neuer Kommentar</label>
                                    <textarea class="form-control" id="comment_text" name="comment" rows="3" required></textarea>
                                </div>
                                
                                <button type="button" id="addCommentBtn" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> Kommentar hinzufügen
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Aufgabendetails</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Erstellt:</strong> <span id="taskCreatedAt"></span></p>
                                    <p><strong>Erstellt von:</strong> <span id="taskCreatedBy"></span></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Zuletzt aktualisiert:</strong> <span id="taskUpdatedAt"></span></p>
                                    <p><strong>Abgeschlossen:</strong> <span id="taskCompletedAt">-</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="toggleTaskStatusBtn">
                    <i class="fas fa-check"></i> Als erledigt markieren
                </button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Schließen</button>
            </div>
        </div>
    </div>
</div>

<?php if ($canAssignTasks): ?>
<!-- Neue Aufgabe Modal -->
<div class="modal fade" id="createTaskModal" tabindex="-1" role="dialog" aria-labelledby="createTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="task_assignments.php" method="post">
                <input type="hidden" name="action" value="create_task">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="createTaskModalLabel">Neue Aufgabe zuweisen</h5>
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
                        <label for="assigned_to">Zuweisen an *</label>
                        <select class="form-control" id="assigned_to" name="assigned_to" required>
                            <option value="">-- Bitte auswählen --</option>
                            <?php foreach ($activeUsers as $u): ?>
                                <option value="<?php echo htmlspecialchars($u['id']); ?>">
                                    <?php echo htmlspecialchars(isset($u['first_name']) && isset($u['last_name']) ? 
                                          $u['first_name'] . ' ' . $u['last_name'] . ' (' . $u['role'] . ')' : 
                                          $u['username'] . ' (' . $u['role'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="category_id">Kategorie</label>
                        <select class="form-control" id="category_id" name="category_id">
                            <option value="">Keine Kategorie</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['id']); ?>" 
                                        style="color: <?php echo htmlspecialchars($category['color']); ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="priority">Priorität</label>
                        <select class="form-control" id="priority" name="priority">
                            <option value="1">Niedrig</option>
                            <option value="2" selected>Normal</option>
                            <option value="3">Hoch</option>
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
<div class="modal fade" id="editTaskModal" tabindex="-1" role="dialog" aria-labelledby="editTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="task_assignments.php" method="post">
                <input type="hidden" name="action" value="edit_task">
                <input type="hidden" id="edit_task_id" name="task_id" value="">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="editTaskModalLabel">Aufgabe bearbeiten</h5>
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
                        <label for="edit_assigned_to">Zuweisen an *</label>
                        <select class="form-control" id="edit_assigned_to" name="assigned_to" required>
                            <option value="">-- Bitte auswählen --</option>
                            <?php foreach ($activeUsers as $u): ?>
                                <option value="<?php echo htmlspecialchars($u['id']); ?>">
                                    <?php echo htmlspecialchars(isset($u['first_name']) && isset($u['last_name']) ? 
                                          $u['first_name'] . ' ' . $u['last_name'] . ' (' . $u['role'] . ')' : 
                                          $u['username'] . ' (' . $u['role'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_category_id">Kategorie</label>
                        <select class="form-control" id="edit_category_id" name="category_id">
                            <option value="">Keine Kategorie</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['id']); ?>" 
                                        style="color: <?php echo htmlspecialchars($category['color']); ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_priority">Priorität</label>
                        <select class="form-control" id="edit_priority" name="priority">
                            <option value="1">Niedrig</option>
                            <option value="2">Normal</option>
                            <option value="3">Hoch</option>
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
<div class="modal fade" id="deleteTaskModal" tabindex="-1" role="dialog" aria-labelledby="deleteTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="task_assignments.php" method="post">
                <input type="hidden" name="action" value="delete_task">
                <input type="hidden" id="delete_task_id" name="task_id" value="">
                
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteTaskModalLabel">Aufgabe löschen</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Schließen">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                
                <div class="modal-body">
                    <p>Sind Sie sicher, dass Sie die Aufgabe <strong id="delete_task_title"></strong> löschen möchten?</p>
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
                                $taskCount = 0;
                                foreach ($tasks as $task) {
                                    if ($task['category_id'] === $category['id']) {
                                        $taskCount++;
                                    }
                                }
                                ?>
                                <tr>
                                    <td>
                                        <span class="category-color-display" style="display: inline-block; width: 20px; height: 20px; background-color: <?php echo htmlspecialchars($category['color']); ?>; border-radius: 3px;"></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                                    <td><?php echo $taskCount; ?></td>
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
                                                data-task-count="<?php echo $taskCount; ?>">
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
            <form action="task_assignments.php" method="post">
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
            <form action="task_assignments.php" method="post">
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
            <form action="task_assignments.php" method="post">
                <input type="hidden" name="action" value="delete_category">
                <input type="hidden" id="delete_category_id" name="category_id" value="">
                
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteCategoryModalLabel">Kategorie löschen</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Schließen">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                
                <div class="modal-body">
                    <p>Sind Sie sicher, dass Sie die Kategorie <strong id="delete_category_name"></strong> löschen möchten?</p>
                    <div id="delete_category_warning" class="alert alert-warning d-none">
                        <strong>Achtung:</strong> Es gibt <span id="delete_category_task_count"></span> Aufgaben in dieser Kategorie. 
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
<?php endif; ?>

<style>
.task-completed {
    background-color: rgba(0, 0, 0, 0.05);
}
.comment-item {
    border-left: 4px solid #3498db;
    padding: 10px 15px;
    margin-bottom: 10px;
    background-color: #f8f9fa;
}
</style>

<script>
$(document).ready(function() {
    // Initialisiere Tooltips
    $('[data-toggle="tooltip"]').tooltip();
    
    // Standard-Filter: Zeige nur offene Aufgaben
    filterTasksByStatus('open');
    
    // Nicht-Manager sehen standardmäßig nur "Mir zugewiesen"
    <?php if (!$canAssignTasks): ?>
    // Initial die Aufgaben nach "Mir zugewiesen" filtern
    filterTasksByAssignee('<?php echo $user_id; ?>');
    <?php endif; ?>
    
    // Suche in Aufgaben
    $('#searchTasks').on('keyup', function() {
        const searchText = $(this).val().toLowerCase();
        $('.task-item').each(function() {
            const rowText = $(this).text().toLowerCase();
            $(this).toggle(rowText.indexOf(searchText) > -1);
        });
        
        updateNoTasksMessage();
    });
    
    // Filtern nach Benutzer-Tabs und Dropdown-Items
    $('.nav-tabs .nav-link, .dropdown-item[data-user]').on('click', function(e) {
        e.preventDefault();
        
        const userId = '<?php echo $user_id; ?>';
        const assigneeId = $(this).data('user');
        
        // Aktiviere den ausgewählten Tab
        if ($(this).hasClass('dropdown-item')) {
            // Wenn Dropdown-Item geklickt wurde
            $('.nav-tabs .nav-link').removeClass('active');
            $('.nav-tabs .dropdown-toggle').removeClass('active');
            
            // Den dropdown-toggle aktualisieren, um den ausgewählten Benutzer anzuzeigen
            const userName = $(this).text().trim();
            const dropdownToggle = $(this).closest('.dropdown').find('.dropdown-toggle');
            
            // Wir ersetzen nur den Text nach dem Icon
            const icon = dropdownToggle.find('i').prop('outerHTML');
            dropdownToggle.html(icon + ' ' + userName);
            
            // Speichere den ausgewählten Benutzer im data-Attribut
            dropdownToggle.data('selected-user', assigneeId);
            
            // Den Dropdown-Toggle-Link als aktiv markieren
            dropdownToggle.addClass('active');
        } else {
            // Wenn normaler Tab geklickt wurde
            $('.nav-tabs .nav-link').removeClass('active');
            $('.nav-tabs .dropdown-toggle').removeClass('active');
            $(this).addClass('active');
        }
        
        if (assigneeId === 'all') {
            $('.task-item').show();
        } else if (assigneeId === 'created_by_me') {
            $('.task-item').each(function() {
                const createdBy = $(this).data('created-by');
                $(this).toggle(createdBy === userId);
            });
        } else {
            $('.task-item').each(function() {
                const taskAssignee = $(this).data('assigned-to');
                $(this).toggle(taskAssignee === assigneeId);
            });
        }
        
        updateVisibilityBasedOnOtherFilters();
        updateNoTasksMessage();
    });
    
    // Filtern nach Kategorie-Pills
    $('.nav-pills .nav-link').on('click', function(e) {
        e.preventDefault();
        
        // Aktiviere den ausgewählten Tab
        $('.nav-pills .nav-link').removeClass('active');
        $(this).addClass('active');
        
        const categoryId = $(this).data('category');
        
        if (categoryId === 'all') {
            $('.task-item').show();
        } else if (categoryId === 'uncategorized') {
            $('.task-item').each(function() {
                const taskCategory = $(this).data('category-id');
                $(this).toggle(!taskCategory);
            });
        } else {
            $('.task-item').each(function() {
                const taskCategory = $(this).data('category-id');
                $(this).toggle(taskCategory === categoryId);
            });
        }
        
        updateVisibilityBasedOnOtherFilters();
        updateNoTasksMessage();
    });
    
    // Filtern nach Status
    $('#filterStatus').on('change', function() {
        filterTasksByStatus($(this).val());
        updateVisibilityBasedOnOtherFilters();
        updateNoTasksMessage();
    });
    
    function filterTasksByStatus(status) {
        if (status === 'all') {
            $('.task-item').show();
        } else if (status === 'open') {
            $('.task-item').each(function() {
                const isCompleted = $(this).data('completed') === 'true';
                $(this).toggle(!isCompleted);
            });
        } else if (status === 'completed') {
            $('.task-item').each(function() {
                const isCompleted = $(this).data('completed') === 'true';
                $(this).toggle(isCompleted);
            });
        }
    }
    
    function updateVisibilityBasedOnOtherFilters() {
        // Berücksichtige andere Filter
        const status = $('#filterStatus').val();
        const userId = '<?php echo $user_id; ?>';
        
        // Ermittle aktuelle Filtereinstellungen aus aktiven Tabs/Pills
        const categoryId = $('.nav-pills .nav-link.active').data('category') || 'all';
        
        // Finde den ausgewählten Benutzer (entweder über aktiven Tab oder aktives Dropdown-Item)
        let assigneeId = 'all';
        const activeTab = $('.nav-tabs .nav-link.active');
        
        if (activeTab.length > 0) {
            assigneeId = activeTab.data('user') || 'all';
        } else {
            // Suche nach einem aktiven Dropdown
            const activeDropdown = $('.nav-tabs .dropdown-toggle.active');
            if (activeDropdown.length > 0) {
                // Hier müssen wir den letzten ausgewählten Benutzer ermitteln
                assigneeId = activeDropdown.data('selected-user') || 'all';
            }
        }
        
        $('.task-item:visible').each(function() {
            const taskCategory = $(this).data('category-id');
            const taskAssignee = $(this).data('assigned-to');
            const createdBy = $(this).data('created-by');
            const isCompleted = $(this).data('completed') === 'true';
            
            let visible = true;
            
            // Kategoriefilter
            if (categoryId !== 'all') {
                if (categoryId === 'uncategorized') {
                    visible = visible && !taskCategory;
                } else {
                    visible = visible && taskCategory === categoryId;
                }
            }
            
            // Benutzerfilter
            if (assigneeId !== 'all') {
                if (assigneeId === 'created_by_me') {
                    visible = visible && createdBy === userId;
                } else {
                    visible = visible && taskAssignee === assigneeId;
                }
            }
            
            // Statusfilter
            if (status !== 'all') {
                if (status === 'open') {
                    visible = visible && !isCompleted;
                } else if (status === 'completed') {
                    visible = visible && isCompleted;
                }
            }
            
            $(this).toggle(visible);
        });
    }
    
    function updateNoTasksMessage() {
        const visibleTasks = $('.task-item:visible').length;
        if (visibleTasks === 0) {
            if ($('.no-tasks-message').length === 0) {
                $('.task-table tbody').append(
                    '<tr class="no-tasks-message"><td colspan="8" class="text-center py-3">' +
                    '<div class="alert alert-info mb-0">Keine Aufgaben gefunden, die den Filterkriterien entsprechen.</div>' +
                    '</td></tr>'
                );
            }
        } else {
            $('.no-tasks-message').remove();
        }
    }
    
    // Erledigt-Status umschalten
    $('.task-checkbox').on('change', function() {
        const checkbox = $(this);
        const taskId = checkbox.closest('.task-item').data('task-id');
        const isChecked = checkbox.prop('checked');
        
        $.ajax({
            url: 'task_assignments.php',
            method: 'POST',
            data: {
                action: 'toggle_task_status',
                task_id: taskId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const taskItem = $('.task-item[data-task-id="' + taskId + '"]');
                    taskItem.data('completed', isChecked ? 'true' : 'false');
                    
                    if (isChecked) {
                        taskItem.addClass('task-completed text-muted');
                    } else {
                        taskItem.removeClass('task-completed text-muted');
                    }
                    
                    // Aktualisiere Filter
                    const currentStatus = $('#filterStatus').val();
                    if (currentStatus !== 'all') {
                        filterTasksByStatus(currentStatus);
                        updateVisibilityBasedOnOtherFilters();
                        updateNoTasksMessage();
                    }
                } else {
                    alert('Fehler: ' + response.message);
                    // Checkbox zurücksetzen
                    checkbox.prop('checked', !isChecked);
                }
            },
            error: function() {
                alert('Fehler bei der Kommunikation mit dem Server.');
                // Checkbox zurücksetzen
                checkbox.prop('checked', !isChecked);
            }
        });
    });
    
    // Aufgabendetails anzeigen (funktioniert für Links und Buttons)
    $(document).on('click', '.view-task-btn', function(e) {
        e.preventDefault();
        const taskId = $(this).data('task-id');
        
        // Modal öffnen und Spinner anzeigen
        $('#viewTaskModal').modal('show');
        $('#taskDetails').hide();
        $('.spinner-border').show();
        
        // Lade Aufgabendetails
        $.ajax({
            url: 'task_assignments.php',
            method: 'GET',
            data: {
                action: 'get_task',
                task_id: taskId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const task = response.task;
                    
                    // Formular füllen
                    $('#taskTitle').text(task.title);
                    $('#taskDescription').text(task.description || 'Keine Beschreibung verfügbar.');
                    
                    // Kategorie
                    if (task.category_id) {
                        $('#taskCategory').html('<span class="badge" style="background-color: ' + task.category_color + '; color: white;">' + task.category_name + '</span>');
                    } else {
                        $('#taskCategory').html('<span class="text-muted">Keine Kategorie</span>');
                    }
                    
                    // Priorität
                    let priorityText = 'Normal';
                    let priorityClass = 'badge-primary';
                    
                    if (task.priority) {
                        switch (task.priority) {
                            case 1:
                                priorityText = 'Niedrig';
                                priorityClass = 'badge-info';
                                break;
                            case 2:
                                priorityText = 'Normal';
                                priorityClass = 'badge-primary';
                                break;
                            case 3:
                                priorityText = 'Hoch';
                                priorityClass = 'badge-danger';
                                break;
                        }
                    }
                    
                    $('#taskPriority').html('<span class="badge ' + priorityClass + '">' + priorityText + '</span>');
                    
                    // Zugewiesen an
                    $('#taskAssignee').html('<i class="fas fa-user mr-1"></i> ' + task.assigned_to_name);
                    
                    // Fälligkeitsdatum
                    if (task.due_date) {
                        const dueDate = new Date(task.due_date);
                        const today = new Date();
                        today.setHours(0, 0, 0, 0);
                        dueDate.setHours(0, 0, 0, 0);
                        
                        let dueDateClass = '';
                        if (!task.completed && dueDate < today) {
                            dueDateClass = 'text-danger font-weight-bold';
                        }
                        
                        $('#taskDueDate').html('<i class="far fa-calendar-alt mr-1"></i> <span class="' + dueDateClass + '">' + formatDate(task.due_date) + '</span>');
                    } else {
                        $('#taskDueDate').html('<i class="far fa-calendar-alt mr-1"></i> <span class="text-muted">Kein Fälligkeitsdatum</span>');
                    }
                    
                    // Status
                    if (task.completed) {
                        $('#taskStatus').html('<span class="badge badge-success">Erledigt</span>');
                        $('#toggleTaskStatusBtn').html('<i class="fas fa-times"></i> Als unerledigt markieren');
                    } else {
                        $('#taskStatus').html('<span class="badge badge-secondary">Offen</span>');
                        $('#toggleTaskStatusBtn').html('<i class="fas fa-check"></i> Als erledigt markieren');
                    }
                    
                    // Erstellt/Aktualisiert-Info
                    $('#taskCreatedAt').text(formatDateTime(task.created_at));
                    $('#taskUpdatedAt').text(formatDateTime(task.updated_at));
                    $('#taskCompletedAt').text(task.completed_at ? formatDateTime(task.completed_at) : '-');
                    
                    // Setze Namen des Erstellers direkt aus den Aufgabendetails
                    $('#taskCreatedBy').text(task.created_by_name || "Unbekannt");
                    
                    // Kommentare
                    $('#taskComments').empty();
                    $('#comment_task_id').val(task.id);
                    
                    if (task.comments && task.comments.length > 0) {
                        $('#noCommentsMessage').hide();
                        $('#commentCount').text(task.comments.length);
                        
                        // Sortiere Kommentare nach Erstellungsdatum (älteste zuerst)
                        task.comments.sort(function(a, b) {
                            return new Date(a.created_at) - new Date(b.created_at);
                        });
                        
                        // Zeige Kommentare
                        task.comments.forEach(function(comment) {
                            getCreatorName(comment.created_by, function(name) {
                                const commentHtml = `
                                    <div class="comment-item">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div>
                                                <strong>${name}</strong>
                                                <small class="text-muted ml-2">${formatDateTime(comment.created_at)}</small>
                                            </div>
                                        </div>
                                        <div>${comment.text.replace(/\n/g, '<br>')}</div>
                                    </div>
                                `;
                                $('#taskComments').append(commentHtml);
                            });
                        });
                    } else {
                        $('#noCommentsMessage').show();
                        $('#commentCount').text('0');
                    }
                    
                    // Toggle-Button-Aktion
                    $('#toggleTaskStatusBtn').off('click').on('click', function() {
                        toggleTaskStatus(task.id, !task.completed);
                    });
                    
                    // Kommentar-Button-Aktion
                    $('#addCommentBtn').off('click').on('click', function() {
                        addComment(task.id);
                    });
                    
                    // Verstecke Spinner und zeige Details
                    $('.spinner-border').hide();
                    $('#taskDetails').show();
                } else {
                    alert('Fehler: ' + response.message);
                    $('#viewTaskModal').modal('hide');
                }
            },
            error: function() {
                alert('Fehler bei der Kommunikation mit dem Server.');
                $('#viewTaskModal').modal('hide');
            }
        });
    });
    
    // Funktion zum Umschalten des Aufgabenstatus
    function toggleTaskStatus(taskId, newStatus) {
        $.ajax({
            url: 'task_assignments.php',
            method: 'POST',
            data: {
                action: 'toggle_task_status',
                task_id: taskId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Aktualisiere Checkbox in der Aufgabenliste
                    $('#task-' + taskId).prop('checked', newStatus);
                    
                    const taskItem = $('.task-item[data-task-id="' + taskId + '"]');
                    taskItem.data('completed', newStatus ? 'true' : 'false');
                    
                    if (newStatus) {
                        taskItem.addClass('task-completed text-muted');
                        $('#taskStatus').html('<span class="badge badge-success">Erledigt</span>');
                        $('#toggleTaskStatusBtn').html('<i class="fas fa-times"></i> Als unerledigt markieren');
                        $('#taskCompletedAt').text(formatDateTime(new Date().toISOString()));
                    } else {
                        taskItem.removeClass('task-completed text-muted');
                        $('#taskStatus').html('<span class="badge badge-secondary">Offen</span>');
                        $('#toggleTaskStatusBtn').html('<i class="fas fa-check"></i> Als erledigt markieren');
                        $('#taskCompletedAt').text('-');
                    }
                    
                    // Aktualisiere Filter
                    const currentStatus = $('#filterStatus').val();
                    if (currentStatus !== 'all') {
                        filterTasksByStatus(currentStatus);
                        updateVisibilityBasedOnOtherFilters();
                        updateNoTasksMessage();
                    }
                } else {
                    alert('Fehler: ' + response.message);
                }
            },
            error: function() {
                alert('Fehler bei der Kommunikation mit dem Server.');
            }
        });
    }
    
    // Funktion zum Hinzufügen eines Kommentars
    function addComment(taskId) {
        const commentText = $('#comment_text').val().trim();
        
        if (!commentText) {
            alert('Bitte geben Sie einen Kommentar ein.');
            return;
        }
        
        $.ajax({
            url: 'task_assignments.php',
            method: 'POST',
            data: {
                action: 'add_comment',
                task_id: taskId,
                comment: commentText
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Füge den neuen Kommentar zur Liste hinzu
                    const comment = response.comment;
                    const commentHtml = `
                        <div class="comment-item">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <strong>${comment.created_by_name}</strong>
                                    <small class="text-muted ml-2">${formatDateTime(comment.created_at)}</small>
                                </div>
                            </div>
                            <div>${comment.text.replace(/\n/g, '<br>')}</div>
                        </div>
                    `;
                    
                    // Verstecke die "Keine Kommentare"-Nachricht
                    $('#noCommentsMessage').hide();
                    
                    // Füge den Kommentar hinzu und aktualisiere den Zähler
                    $('#taskComments').append(commentHtml);
                    const commentCount = parseInt($('#commentCount').text()) + 1;
                    $('#commentCount').text(commentCount);
                    
                    // Aktualisiere auch den Kommentarzähler in der Aufgabenliste
                    const commentBadge = $(`.view-task-btn[data-task-id="${taskId}"]`).siblings('.badge');
                    if (commentBadge.length) {
                        commentBadge.text(commentCount);
                    } else {
                        $(`.view-task-btn[data-task-id="${taskId}"]`).after(
                            `<span class="badge badge-info ml-2" title="${commentCount} Kommentare">
                                <i class="fas fa-comments"></i> ${commentCount}
                            </span>`
                        );
                    }
                    
                    // Setze das Eingabefeld zurück
                    $('#comment_text').val('');
                } else {
                    alert('Fehler: ' + response.message);
                }
            },
            error: function() {
                alert('Fehler bei der Kommunikation mit dem Server.');
            }
        });
    }
    
    <?php if ($canAssignTasks): ?>
    // Aufgabe bearbeiten
    $('.edit-task-btn').on('click', function(e) {
        e.preventDefault();
        const taskId = $(this).data('task-id');
        
        // Finde die Aufgabe in der Tabelle
        const taskItem = $('.task-item[data-task-id="' + taskId + '"]');
        const taskTitle = taskItem.find('.view-task-btn').text().trim();
        
        // Hol weitere Details über AJAX (oder aus dem DOM, wenn verfügbar)
        $.ajax({
            url: 'task_assignments.php',
            method: 'GET',
            data: {
                action: 'get_task',
                task_id: taskId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const task = response.task;
                    
                    // Formular füllen
                    $('#edit_task_id').val(task.id);
                    $('#edit_title').val(task.title);
                    $('#edit_description').val(task.description);
                    $('#edit_assigned_to').val(task.assigned_to);
                    $('#edit_category_id').val(task.category_id);
                    $('#edit_priority').val(task.priority || 2);
                    $('#edit_due_date').val(task.due_date);
                    
                    // Modal öffnen
                    $('#editTaskModal').modal('show');
                } else {
                    alert('Fehler: ' + response.message);
                }
            },
            error: function() {
                alert('Fehler beim Laden der Aufgabendetails.');
            }
        });
    });
    
    // Aufgabe löschen
    $('.delete-task-btn').on('click', function(e) {
        e.preventDefault();
        const taskId = $(this).data('task-id');
        const taskTitle = $(this).data('task-title');
        
        $('#delete_task_id').val(taskId);
        $('#delete_task_title').text(taskTitle);
        $('#deleteTaskModal').modal('show');
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
        const taskCount = parseInt($(this).data('task-count'));
        
        $('#delete_category_id').val(categoryId);
        $('#delete_category_name').text(categoryName);
        
        if (taskCount > 0) {
            $('#delete_category_task_count').text(taskCount);
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
    <?php endif; ?>
    
    // Hilfsfunktionen
    function formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('de-DE');
    }
    
    function formatDateTime(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('de-DE') + ' ' + date.toLocaleTimeString('de-DE');
    }
    
    function getCreatorName(userId, callback) {
        // Hier könnte man eine AJAX-Anfrage machen, um den Namen des Erstellers zu holen
        // Stattdessen verwenden wir eine vereinfachte Version
        const users = <?php echo json_encode($users); ?>;
        let name = 'Unbekannter Benutzer';
        
        for (const user of users) {
            if (user.id === userId) {
                if (user.first_name && user.last_name) {
                    name = user.first_name + ' ' + user.last_name;
                } else {
                    name = user.username;
                }
                break;
            }
        }
        
        callback(name);
    }
});
</script>

<?php
include_once '../includes/footer.php';
?>