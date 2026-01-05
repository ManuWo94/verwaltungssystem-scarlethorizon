<?php
session_start();
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Enforce view permission for templates
checkPermissionOrDie('templates', 'view');

// Konstante für erlaubten Zugriff
define('ACCESS_ALLOWED', true);

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];
$message = '';
$error = '';

// Ansichtsmodus - entweder 'templates' (Standard) oder 'categories' für Kategorieverwaltung
$viewMode = isset($_GET['mode']) && $_GET['mode'] === 'categories' ? 'categories' : 'templates';

// Handle template actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'create') {
            // Überprüfen, ob eine neue Kategorie erstellt werden soll
            $department = sanitize($_POST['department'] ?? 'Allgemein');
            $category = sanitize($_POST['template_category'] ?? 'Allgemein');
            if ($category === 'new' && !empty($_POST['new_category'])) {
                $category = sanitize($_POST['new_category']);
            }
            
            $templateData = [
                'title' => sanitize($_POST['template_title'] ?? ''),
                'content' => sanitize($_POST['template_content'] ?? ''),
                'department' => $department,
                'category' => $category,
                'is_public' => isset($_POST['is_public']) ? true : false,
                'created_by' => $user_id,
                'created_by_name' => $username,
                'created_date' => date('Y-m-d H:i:s')
            ];
            
            // Validate required fields
            if (empty($templateData['title']) || empty($templateData['content'])) {
                $error = 'Bitte füllen Sie alle erforderlichen Felder aus.';
            } else {
                $templateData['id'] = generateUniqueId();
                
                if (insertRecord('templates.json', $templateData)) {
                    $message = 'Vorlage wurde erfolgreich erstellt.';
                } else {
                    $error = 'Fehler beim Erstellen der Vorlage.';
                }
            }
        } elseif ($action === 'update' && isset($_POST['template_id'])) {
            $templateId = $_POST['template_id'];
            $template = findById('templates.json', $templateId);
            
            // Check if user can edit this template
            if (!$template || ($template['created_by'] !== $user_id && !isAdmin($user_id))) {
                $error = 'Sie haben keine Berechtigung, diese Vorlage zu bearbeiten.';
            } else {
                // Überprüfen, ob eine neue Kategorie erstellt werden soll
                $department = sanitize($_POST['department'] ?? 'Allgemein');
                $category = sanitize($_POST['template_category'] ?? 'Allgemein');
                if ($category === 'new' && !empty($_POST['new_category'])) {
                    $category = sanitize($_POST['new_category']);
                }
                
                // Existierende Felder beibehalten und nur aktualisieren, was geändert wurde
                $templateData = $template;
                $templateData['title'] = sanitize($_POST['template_title'] ?? '');
                $templateData['content'] = sanitize($_POST['template_content'] ?? '');
                $templateData['department'] = $department;
                $templateData['category'] = $category;
                $templateData['is_public'] = isset($_POST['is_public']) ? true : false;
                $templateData['last_updated_by'] = $user_id;
                $templateData['last_updated_by_name'] = $username;
                $templateData['last_updated_date'] = date('Y-m-d H:i:s');
                
                // Validate required fields
                if (empty($templateData['title']) || empty($templateData['content'])) {
                    $error = 'Bitte füllen Sie alle erforderlichen Felder aus.';
                } else {
                    if (updateRecord('templates.json', $templateId, $templateData)) {
                        $message = 'Vorlage wurde erfolgreich aktualisiert.';
                    } else {
                        $error = 'Fehler beim Aktualisieren der Vorlage.';
                    }
                }
            }
        } elseif ($action === 'delete' && isset($_POST['template_id'])) {
            $templateId = $_POST['template_id'];
            $template = findById('templates.json', $templateId);
            
            // Check if user can delete this template
            if (!$template || ($template['created_by'] !== $user_id && !isAdmin($user_id))) {
                $error = 'Sie haben keine Berechtigung, diese Vorlage zu löschen.';
            } else {
                if (deleteRecord('templates.json', $templateId)) {
                    $message = 'Vorlage wurde erfolgreich gelöscht.';
                } else {
                    $error = 'Fehler beim Löschen der Vorlage.';
                }
            }
        } elseif ($action === 'edit_category' && isset($_POST['old_category']) && isset($_POST['new_category']) && isset($_POST['department'])) {
            // Nur Leitungsebene kann Kategorien bearbeiten
            if (!isLeadership($user_id)) {
                $error = 'Sie haben keine Berechtigung, Kategorien zu bearbeiten.';
            } else {
                $oldCategory = sanitize($_POST['old_category']);
                $newCategory = sanitize($_POST['new_category']);
                $department = sanitize($_POST['department']);
                
                if (empty($newCategory)) {
                    $error = 'Der neue Kategoriename darf nicht leer sein.';
                } else {
                    // Lade alle Vorlagen
                    $allTemplates = loadJsonData('templates.json');
                    $updated = false;
                    
                    // Aktualisiere alle Vorlagen mit der alten Kategorie
                    foreach ($allTemplates as $key => $template) {
                        if ($template['category'] === $oldCategory) {
                            $allTemplates[$key]['category'] = $newCategory;
                            $updated = true;
                        }
                    }
                    
                    // Aktualisiere auch den Kategorienamen in der Abteilungsstruktur
                    $categories = loadJsonData('template_categories.json', []);
                    if (isset($categories[$department])) {
                        $index = array_search($oldCategory, $categories[$department]);
                        if ($index !== false) {
                            $categories[$department][$index] = $newCategory;
                            file_put_contents(getDataFilePath('template_categories.json'), json_encode($categories, JSON_PRETTY_PRINT));
                        }
                    }
                    
                    // Speichere die aktualisierte Kategorie unabhängig davon, ob Vorlagen aktualisiert wurden
                    // Kategorie immer als aktualisiert betrachten, auch wenn keine Vorlagen aktualisiert wurden
                    $message = 'Kategorie wurde erfolgreich aktualisiert.';
                    
                    // Wenn Vorlagen aktualisiert wurden, aktualisieren wir die Vorlagendatei
                    if ($updated) {
                        file_put_contents(getDataFilePath('templates.json'), json_encode($allTemplates, JSON_PRETTY_PRINT));
                    }
                }
            }
        } elseif ($action === 'delete_category' && isset($_POST['category']) && isset($_POST['department'])) {
            // Nur Leitungsebene kann Kategorien löschen
            if (!isLeadership($user_id)) {
                $error = 'Sie haben keine Berechtigung, Kategorien zu löschen.';
            } else {
                $categoryToDelete = sanitize($_POST['category']);
                $department = sanitize($_POST['department']);
                
                // Lade alle Vorlagen
                $allTemplates = loadJsonData('templates.json');
                $hasTemplates = false;
                
                // Prüfe, ob die Kategorie in Verwendung ist
                foreach ($allTemplates as $template) {
                    if ($template['category'] === $categoryToDelete) {
                        $hasTemplates = true;
                        break;
                    }
                }
                
                if ($hasTemplates) {
                    $error = 'Diese Kategorie kann nicht gelöscht werden, da sie noch in Verwendung ist.';
                } else {
                    // Entferne die Kategorie aus der Abteilungsstruktur
                    $categories = loadJsonData('template_categories.json', []);
                    if (isset($categories[$department])) {
                        $index = array_search($categoryToDelete, $categories[$department]);
                        if ($index !== false) {
                            unset($categories[$department][$index]);
                            $categories[$department] = array_values($categories[$department]); // Indizes neu ordnen
                            
                            if (file_put_contents(getDataFilePath('template_categories.json'), json_encode($categories, JSON_PRETTY_PRINT))) {
                                $message = 'Kategorie wurde erfolgreich gelöscht.';
                            } else {
                                $error = 'Fehler beim Löschen der Kategorie.';
                            }
                        } else {
                            $message = 'Kategorie wurde nicht in der angegebenen Abteilung gefunden.';
                        }
                    } else {
                        $error = 'Die angegebene Abteilung existiert nicht.';
                    }
                }
            }
        } elseif ($action === 'add_category' && isset($_POST['new_category']) && isset($_POST['department'])) {
            // Nur Leitungsebene kann Kategorien hinzufügen
            if (!isLeadership($user_id)) {
                $error = 'Sie haben keine Berechtigung, neue Kategorien zu erstellen.';
            } else {
                $newCategory = sanitize($_POST['new_category']);
                $department = sanitize($_POST['department']);
                
                if (empty($newCategory)) {
                    $error = 'Der Kategoriename darf nicht leer sein.';
                } else {
                    // Lade die Kategorien
                    $categories = loadJsonData('template_categories.json', []);
                    
                    // Erstelle die Abteilung, falls sie noch nicht existiert
                    if (!isset($categories[$department])) {
                        $categories[$department] = [];
                    }
                    
                    // Prüfe, ob die Kategorie bereits existiert
                    if (in_array($newCategory, $categories[$department])) {
                        $error = 'Diese Kategorie existiert bereits in dieser Abteilung.';
                    } else {
                        // Füge die neue Kategorie hinzu
                        $categories[$department][] = $newCategory;
                        sort($categories[$department]); // Sortiere alphabetisch
                        
                        if (file_put_contents(getDataFilePath('template_categories.json'), json_encode($categories, JSON_PRETTY_PRINT))) {
                            $message = 'Neue Kategorie wurde erfolgreich erstellt.';
                        } else {
                            $error = 'Fehler beim Erstellen der Kategorie.';
                        }
                    }
                }
            }
        }
    }
}

// Load templates
$templates = loadJsonData('templates.json');

// Filter templates based on visibility and ownership
$userTemplates = array_filter($templates, function($template) use ($user_id) {
    return $template['created_by'] === $user_id || $template['is_public'] || isAdmin($user_id);
});

// Abteilungen definieren
$departments = [
    'Leitungsebene',
    'Richterschaft',
    'Staatsanwaltschaft',
    'Assistenz',
    'U.S. Marshal Service'
];

// Standard-Kategorien für jede Abteilung definieren
$defaultCategories = [
    'Leitungsebene' => ['Richtlinien', 'Direktiven', 'Jahresberichte'],
    'Richterschaft' => ['Urteile', 'Protokolle', 'Vorladungen', 'Gerichtsbeschlüsse'],
    'Staatsanwaltschaft' => ['Anklageschriften', 'Beweisanträge', 'Plädoyers'],
    'Assistenz' => ['Korrespondenz', 'Berichte', 'Formulare'],
    'U.S. Marshal Service' => ['Einsatzberichte', 'Durchsuchungsbefehle', 'Verhaftungsbefehle']
];

// Lade gespeicherte Kategorien oder initialisiere mit Standard-Kategorien
$savedCategories = loadJsonData('template_categories.json', []);
if (empty($savedCategories)) {
    $savedCategories = $defaultCategories;
}

// Sammle alle Kategorien zur Anzeige in Dropdown-Feldern
$allCategories = [];
foreach ($savedCategories as $dept => $categories) {
    foreach ($categories as $category) {
        $allCategories[] = $category;
    }
}

// Extrahiere Abteilung und Kategorie aus bestehenden Vorlagen
foreach ($userTemplates as $template) {
    if (!empty($template['category'])) {
        // Prüfe, ob die Kategorie bereits in einer Abteilung ist
        $found = false;
        foreach ($savedCategories as $dept => $categories) {
            if (in_array($template['category'], $categories)) {
                $found = true;
                break;
            }
        }
        
        // Wenn nicht gefunden, füge zur "Allgemein" Kategorie in der ersten Abteilung hinzu
        if (!$found && !in_array($template['category'], $allCategories)) {
            $allCategories[] = $template['category'];
            
            // Füge zur ersten Abteilung hinzu, falls diese existiert
            if (!empty($departments[0]) && isset($savedCategories[$departments[0]])) {
                $savedCategories[$departments[0]][] = $template['category'];
            } else {
                // Erstelle eine neue Abteilung "Allgemein" falls nötig
                if (!isset($savedCategories['Allgemein'])) {
                    $savedCategories['Allgemein'] = [];
                }
                $savedCategories['Allgemein'][] = $template['category'];
            }
        }
    }
}

// Sortiere die Kategorien innerhalb jeder Abteilung
foreach ($savedCategories as $dept => $categories) {
    sort($savedCategories[$dept]);
}

// Speichere aktualisierte Kategorien zurück
file_put_contents(getDataFilePath('template_categories.json'), json_encode($savedCategories, JSON_PRETTY_PRINT));

// Get template by ID if provided
$selectedTemplate = null;
if (isset($_GET['id'])) {
    $templateId = $_GET['id'];
    $selectedTemplate = findById('templates.json', $templateId);
    
    // Check if user can view this template
    if (!$selectedTemplate || (!$selectedTemplate['is_public'] && $selectedTemplate['created_by'] !== $user_id && !isAdmin($user_id))) {
        $selectedTemplate = null;
    }
}

// Filter by department and category if provided
$selectedDepartment = $_GET['department'] ?? '';
$selectedCategory = $_GET['category'] ?? '';

if (!empty($selectedDepartment)) {
    $userTemplates = array_filter($userTemplates, function($template) use ($selectedDepartment) {
        return isset($template['department']) && $template['department'] === $selectedDepartment;
    });
}

if (!empty($selectedCategory)) {
    $userTemplates = array_filter($userTemplates, function($template) use ($selectedCategory) {
        return $template['category'] === $selectedCategory;
    });
}

// Für alte Templates ohne department-Feld, dieses hinzufügen
foreach ($userTemplates as $key => $template) {
    if (!isset($template['department'])) {
        // Die Abteilung anhand der Kategorie bestimmen
        $department = 'Allgemein';
        foreach ($savedCategories as $dept => $categories) {
            if (in_array($template['category'], $categories)) {
                $department = $dept;
                break;
            }
        }
        
        $userTemplates[$key]['department'] = $department;
        
        // Auch in der Hauptdatei aktualisieren
        $templateId = $template['id'];
        $allTemplates = loadJsonData('templates.json');
        foreach ($allTemplates as $mainKey => $mainTemplate) {
            if ($mainTemplate['id'] === $templateId) {
                $allTemplates[$mainKey]['department'] = $department;
                break;
            }
        }
        saveJsonData('templates.json', $allTemplates);
    }
}
?>

<?php include '../includes/header.php'; ?>

<!-- Hauptansicht nur im normalen Vorlagen-Modus anzeigen -->
<?php if ($viewMode === 'templates'): ?>
<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Vorlagen</h1>
                <div>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addTemplateModal">
                        <span data-feather="plus"></span> Neue Vorlage erstellen
                    </button>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="row mb-3">
                <div class="col-md-6">
                    <form class="form-inline">
                        <label class="mr-2" for="department-filter">Abteilung:</label>
                        <select class="form-control mr-2" id="department-filter" name="department">
                            <option value="">Alle Abteilungen</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo isset($_GET['department']) && $_GET['department'] === $dept ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <label class="mx-2" for="category-filter">Kategorie:</label>
                        <select class="form-control mr-2" id="category-filter" name="category">
                            <option value="">Alle Kategorien</option>
                            <?php foreach ($allCategories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $selectedCategory === $category ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-outline-secondary">Filtern</button>
                    </form>
                </div>
                <div class="col-md-6 text-right">
                    <span class="text-muted"><?php echo count($userTemplates); ?> Vorlagen gefunden</span>
                    <?php if (isAdmin($user_id)): ?>
                    <a href="templates.php?mode=categories" class="btn btn-sm btn-outline-primary ml-2">
                        <span data-feather="settings"></span> Kategorien verwalten
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4>Vorlagen</h4>
                            <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#addTemplateModal">
                                <span data-feather="plus"></span> Neue Vorlage
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <a href="templates.php" class="list-group-item list-group-item-action <?php echo empty($selectedCategory) ? 'active' : ''; ?>">
                                    <strong>Alle Vorlagen</strong>
                                    <span class="badge badge-pill badge-light float-right"><?php echo count($userTemplates); ?></span>
                                </a>
                            </div>
                            
                            <!-- Vorlage-Abteilungen mit Akkordeon-Struktur -->
                            <div class="accordion" id="departmentAccordion">
                                <?php 
                                // Kategorien mit Anzahl der Vorlagen anzeigen
                                $categoryCount = [];
                                foreach ($allCategories as $category) {
                                    $categoryCount[$category] = 0;
                                }
                                
                                foreach ($templates as $template) {
                                    if (isset($categoryCount[$template['category']]) && 
                                        ($template['created_by'] === $user_id || $template['is_public'] || isAdmin($user_id))) {
                                        $categoryCount[$template['category']]++;
                                    }
                                }
                                
                                // Department-Zähler
                                $deptCounter = 0;

                                // Für jedes Department ein Akkordeon erstellen
                                foreach ($departments as $department): 
                                    $deptCounter++;
                                    $deptId = 'dept-' . $deptCounter;
                                    $deptCategories = $savedCategories[$department] ?? [];
                                    
                                    // Überprüfen, ob dieses Department Kategorien mit Vorlagen hat
                                    $hasCategoriesWithTemplates = false;
                                    foreach ($deptCategories as $category) {
                                        if (isset($categoryCount[$category]) && $categoryCount[$category] > 0) {
                                            $hasCategoriesWithTemplates = true;
                                            break;
                                        }
                                    }
                                    
                                    if ($hasCategoriesWithTemplates || isLeadership($user_id)):
                                ?>
                                <div class="card">
                                    <div class="card-header p-0" id="heading-<?php echo $deptId; ?>">
                                        <h2 class="mb-0">
                                            <button class="btn btn-link btn-block text-left d-flex justify-content-between align-items-center" 
                                                    type="button" 
                                                    data-toggle="collapse" 
                                                    data-target="#collapse-<?php echo $deptId; ?>" 
                                                    aria-expanded="true" 
                                                    aria-controls="collapse-<?php echo $deptId; ?>">
                                                <span><?php echo htmlspecialchars($department); ?></span>
                                                <span class="fas fa-chevron-down"></span>
                                            </button>
                                        </h2>
                                    </div>
                                    
                                    <div id="collapse-<?php echo $deptId; ?>" 
                                         class="collapse" 
                                         aria-labelledby="heading-<?php echo $deptId; ?>" 
                                         data-parent="#departmentAccordion">
                                        <div class="list-group list-group-flush">
                                            <?php foreach ($deptCategories as $category): 
                                                $hasTemplates = isset($categoryCount[$category]) && $categoryCount[$category] > 0;
                                                if ($hasTemplates || isLeadership($user_id)):
                                            ?>
                                                <a href="templates.php?category=<?php echo urlencode($category); ?>&department=<?php echo urlencode($department); ?>" 
                                                   class="list-group-item list-group-item-action pl-4 <?php echo $selectedCategory === $category ? 'active' : ''; ?>">
                                                    <?php echo htmlspecialchars($category); ?>
                                                    <?php if ($hasTemplates): ?>
                                                    <span class="badge badge-pill badge-light float-right"><?php echo $categoryCount[$category]; ?></span>
                                                    <?php endif; ?>
                                                </a>
                                            <?php 
                                                endif;
                                            endforeach; 
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </div>
                            
                            <?php if (isLeadership($user_id)): ?>
                            <div class="card-footer bg-light">
                                <a href="templates.php?mode=categories" class="btn btn-sm btn-outline-secondary btn-block">
                                    <span data-feather="settings"></span> Kategorien verwalten
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-9">
                    <?php if ($selectedTemplate): ?>
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h4><?php echo htmlspecialchars($selectedTemplate['title']); ?></h4>
                                <div>
                                    <?php if ($selectedTemplate['created_by'] === $user_id || isAdmin($user_id)): ?>
                                        <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#editTemplateModal">
                                            <span data-feather="edit"></span> Bearbeiten
                                        </button>
                                        <form method="post" action="templates.php" class="d-inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="template_id" value="<?php echo $selectedTemplate['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger btn-delete">
                                                <span data-feather="trash-2"></span> Löschen
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <?php 
                                    // Ersetzen der Platzhalter mit den tatsächlichen Werten des Benutzers
                                    $templateContent = $selectedTemplate['content'];
                                    $userPosition = getUserPosition($user_id);
                                    $userSignature = getTemplateSignature($user_id);
                                    
                                    $templateContent = str_replace('{{POSITION}}', $userPosition, $templateContent);
                                    $templateContent = str_replace('{{SIGNATURE}}', $userSignature, $templateContent);
                                    ?>
                                    <button type="button" class="btn btn-sm btn-secondary" id="copy-template-btn" data-content="<?php echo htmlspecialchars($templateContent); ?>">
                                        <span data-feather="copy"></span> In Zwischenablage kopieren
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <small class="text-muted">
                                        Abteilung: <?php echo htmlspecialchars($selectedTemplate['department'] ?? 'Allgemein'); ?> |
                                        Kategorie: <?php echo htmlspecialchars($selectedTemplate['category']); ?> |
                                        Erstellt von: <?php echo htmlspecialchars($selectedTemplate['created_by_name']); ?>
                                        <?php if (isset($selectedTemplate['last_updated_by'])): ?>
                                            | Zuletzt aktualisiert von: <?php echo htmlspecialchars($selectedTemplate['last_updated_by_name']); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div class="template-content border p-3 bg-light">
                                    <?php 
                                    // Vorbereiten der Platzhalter-Ersetzung für die Anzeige
                                    $templateContent = $selectedTemplate['content'];
                                    $userPosition = getUserPosition($user_id);
                                    $userSignature = getTemplateSignature($user_id);
                                    
                                    // Zuerst Platzhalter durch "sichere" Marker ersetzen
                                    $templateContent = str_replace('{{POSITION}}', '###POSITION###', $templateContent);
                                    $templateContent = str_replace('{{SIGNATURE}}', '###SIGNATURE###', $templateContent);
                                    
                                    // HTML-Entitäten codieren
                                    $templateContent = htmlspecialchars($templateContent);
                                    
                                    // Marker durch formatierte HTML-Werte ersetzen
                                    $templateContent = str_replace('###POSITION###', '<span class="text-primary">' . htmlspecialchars($userPosition) . '</span>', $templateContent);
                                    $templateContent = str_replace('###SIGNATURE###', '<span class="text-primary">' . nl2br(htmlspecialchars($userSignature)) . '</span>', $templateContent);
                                    
                                    // Ausgabe mit Zeilenumbrüchen
                                    echo nl2br($templateContent);
                                    ?>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h4>
                                    <?php if (!empty($selectedCategory)): ?>
                                        Vorlagen: <?php echo htmlspecialchars($selectedCategory); ?>
                                    <?php else: ?>
                                        Alle Vorlagen
                                    <?php endif; ?>
                                </h4>
                                <?php if (!empty($selectedCategory)): ?>
                                <button type="button" class="btn btn-sm btn-success" data-toggle="modal" data-target="#addTemplateModal" data-category="<?php echo htmlspecialchars($selectedCategory); ?>">
                                    <span data-feather="plus"></span> Vorlage in dieser Kategorie
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if (count($userTemplates) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Titel</th>
                                                    <?php if (empty($selectedCategory)): ?>
                                                    <th>Kategorie</th>
                                                    <?php endif; ?>
                                                    <th>Erstellt von</th>
                                                    <th class="text-center">Aktionen</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($userTemplates as $template): 
                                                    // Vorbereiten der Platzhalter-Ersetzung für die Kopier-Funktion
                                                    $templateContent = $template['content'];
                                                    $userPosition = getUserPosition($user_id);
                                                    $userSignature = getTemplateSignature($user_id);
                                                    
                                                    $templateContent = str_replace('{{POSITION}}', $userPosition, $templateContent);
                                                    $templateContent = str_replace('{{SIGNATURE}}', $userSignature, $templateContent);
                                                ?>
                                                <tr>
                                                    <td class="align-middle">
                                                        <a href="templates.php?id=<?php echo $template['id']; ?><?php echo !empty($selectedCategory) ? '&category=' . urlencode($selectedCategory) : ''; ?>" class="font-weight-bold">
                                                            <?php echo htmlspecialchars($template['title']); ?>
                                                        </a>
                                                        <?php if ($template['created_by'] === $user_id): ?>
                                                            <span class="badge badge-primary ml-2">Ihre Vorlage</span>
                                                        <?php elseif ($template['is_public']): ?>
                                                            <span class="badge badge-success ml-2">Öffentlich</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <?php if (empty($selectedCategory)): ?>
                                                    <td class="align-middle"><?php echo htmlspecialchars($template['category']); ?></td>
                                                    <?php endif; ?>
                                                    <td class="align-middle"><?php echo htmlspecialchars($template['created_by_name']); ?></td>
                                                    <td class="text-center">
                                                        <div class="btn-group">
                                                            <button type="button" class="btn btn-sm btn-outline-secondary copy-template-btn" data-content="<?php echo htmlspecialchars($templateContent); ?>" title="In Zwischenablage kopieren">
                                                                <span data-feather="copy"></span>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-outline-primary view-template-btn" title="Anzeigen" 
                                                                  data-id="<?php echo $template['id']; ?>" 
                                                                  data-title="<?php echo htmlspecialchars($template['title']); ?>"
                                                                  data-content="<?php echo htmlspecialchars($template['content']); ?>"
                                                                  data-category="<?php echo htmlspecialchars($template['category']); ?>"
                                                                  data-department="<?php echo htmlspecialchars($template['department'] ?? 'Allgemein'); ?>"
                                                                  data-creator="<?php echo htmlspecialchars($template['created_by_name']); ?>">
                                                                <span data-feather="eye"></span>
                                                            </button>
                                                            <?php if ($template['created_by'] === $user_id || isAdmin($user_id)): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-primary edit-template-btn" title="Bearbeiten" 
                                                                    data-id="<?php echo $template['id']; ?>"
                                                                    data-title="<?php echo htmlspecialchars($template['title']); ?>"
                                                                    data-content="<?php echo htmlspecialchars($template['content']); ?>"
                                                                    data-category="<?php echo htmlspecialchars($template['category']); ?>"
                                                                    data-department="<?php echo htmlspecialchars($template['department'] ?? 'Allgemein'); ?>"
                                                                    data-public="<?php echo $template['is_public'] ? '1' : '0'; ?>">
                                                                <span data-feather="edit"></span>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-outline-danger" title="Löschen" onclick="if(confirm('Möchten Sie die Vorlage wirklich löschen?')) { document.getElementById('deleteForm-<?php echo $template['id']; ?>').submit(); }">
                                                                <span data-feather="trash-2"></span>
                                                            </button>
                                                            <form id="deleteForm-<?php echo $template['id']; ?>" method="post" action="templates.php" class="d-none">
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="template_id" value="<?php echo $template['id']; ?>">
                                                            </form>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <?php if (!empty($selectedCategory)): ?>
                                            <p class="lead">Keine Vorlagen in der Kategorie "<?php echo htmlspecialchars($selectedCategory); ?>" gefunden.</p>
                                            <button type="button" class="btn btn-primary mt-3" data-toggle="modal" data-target="#addTemplateModal" data-category="<?php echo htmlspecialchars($selectedCategory); ?>">
                                                <span data-feather="plus"></span> Erste Vorlage in dieser Kategorie erstellen
                                            </button>
                                        <?php else: ?>
                                            <h3 class="mb-3">Vorlagenverwaltung</h3>
                                            <p class="lead">Erstellen Sie Ihre erste Vorlage oder wählen Sie eine Kategorie aus.</p>
                                            <p>Vorlagen ermöglichen es Ihnen, häufig verwendete Texte in Dokumenten, Anklageschriften und Notizen schnell wiederzuverwenden.</p>
                                            <button type="button" class="btn btn-lg btn-primary mt-3" data-toggle="modal" data-target="#addTemplateModal">
                                                <span data-feather="plus"></span> Neue Vorlage erstellen
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>
<?php endif; ?>

<!-- Add Template Modal -->
<div class="modal fade" id="addTemplateModal" tabindex="-1" role="dialog" aria-labelledby="addTemplateModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addTemplateModalLabel">Neue Vorlage erstellen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="templates.php">
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="template_title">Titel *</label>
                        <input type="text" class="form-control" id="template_title" name="template_title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="template_department">Abteilung</label>
                        <select class="form-control" id="template_department" name="department">
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>">
                                    <?php echo htmlspecialchars($dept); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="template_category">Kategorie</label>
                        <select class="form-control" id="template_category" name="template_category">
                            <?php 
                            // Erste Abteilung als Standard anzeigen
                            $firstDept = !empty($departments) ? $departments[0] : '';
                            $deptCategories = $savedCategories[$firstDept] ?? [];
                            foreach ($deptCategories as $category): 
                            ?>
                                <option value="<?php echo htmlspecialchars($category); ?>" data-department="<?php echo htmlspecialchars($firstDept); ?>">
                                    <?php echo htmlspecialchars($category); ?>
                                </option>
                            <?php endforeach; ?>
                            
                            <?php 
                            // Kategorien für jede weitere Abteilung
                            foreach ($departments as $dept):
                                if ($dept === $firstDept) continue;
                                $deptCategories = $savedCategories[$dept] ?? [];
                                foreach ($deptCategories as $category): 
                            ?>
                                <option value="<?php echo htmlspecialchars($category); ?>" data-department="<?php echo htmlspecialchars($dept); ?>" style="display: none;">
                                    <?php echo htmlspecialchars($category); ?>
                                </option>
                            <?php 
                                endforeach;
                            endforeach; 
                            ?>
                            <option value="new">+ Neue Kategorie hinzufügen</option>
                        </select>
                    </div>
                    
                    <div class="form-group d-none" id="new_category_group">
                        <label for="new_category">Name der neuen Kategorie</label>
                        <input type="text" class="form-control" id="new_category" name="new_category">
                    </div>
                    <div class="form-group">
                        <label for="template_content">Inhalt *</label>
                        <textarea class="form-control" id="template_content" name="template_content" rows="10" required></textarea>
                        <small class="form-text text-muted">
                            Verwenden Sie <code>{{POSITION}}</code> für Ihre Position/Titel und <code>{{SIGNATURE}}</code> für Ihre vollständige Signatur.
                        </small>
                    </div>
                    <div class="form-group form-check">
                        <input type="checkbox" class="form-check-input" id="is_public" name="is_public">
                        <label class="form-check-label" for="is_public">Diese Vorlage für alle Benutzer verfügbar machen</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Vorlage erstellen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Template Modal -->
<div class="modal fade" id="viewTemplateModal" tabindex="-1" role="dialog" aria-labelledby="viewTemplateModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewTemplateModalLabel">Vorlage anzeigen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <small class="text-muted view-template-meta"></small>
                </div>
                <div class="template-content border p-3 bg-light view-template-content"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" id="view-copy-template-btn">
                    <span data-feather="copy"></span> In Zwischenablage kopieren
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary edit-from-view-btn" style="display: none;">
                    <span data-feather="edit"></span> Bearbeiten
                </button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Schließen</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Template Modal -->
<div class="modal fade" id="editTemplateModal" tabindex="-1" role="dialog" aria-labelledby="editTemplateModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editTemplateModalLabel">Vorlage bearbeiten</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="templates.php" id="editTemplateForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="template_id" id="edit_template_id" value="">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_template_title">Titel *</label>
                        <input type="text" class="form-control" id="edit_template_title" name="template_title" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_template_department">Abteilung</label>
                        <select class="form-control" id="edit_template_department" name="department">
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>">
                                    <?php echo htmlspecialchars($dept); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_template_category">Kategorie</label>
                        <select class="form-control" id="edit_template_category" name="template_category">
                            <?php 
                            // Erste Abteilung als Standard anzeigen
                            $firstDept = !empty($departments) ? $departments[0] : '';
                            $deptCategories = $savedCategories[$firstDept] ?? [];
                            foreach ($deptCategories as $category): 
                            ?>
                                <option value="<?php echo htmlspecialchars($category); ?>" data-department="<?php echo htmlspecialchars($firstDept); ?>">
                                    <?php echo htmlspecialchars($category); ?>
                                </option>
                            <?php endforeach; ?>
                            
                            <?php 
                            // Kategorien für jede weitere Abteilung
                            foreach ($departments as $dept):
                                if ($dept === $firstDept) continue;
                                $deptCategories = $savedCategories[$dept] ?? [];
                                foreach ($deptCategories as $category): 
                            ?>
                                <option value="<?php echo htmlspecialchars($category); ?>" data-department="<?php echo htmlspecialchars($dept); ?>" style="display: none;">
                                    <?php echo htmlspecialchars($category); ?>
                                </option>
                            <?php 
                                endforeach;
                            endforeach; 
                            ?>
                            <option value="new">+ Neue Kategorie hinzufügen</option>
                        </select>
                    </div>
                    
                    <div class="form-group d-none" id="edit_new_category_group">
                        <label for="edit_new_category">Name der neuen Kategorie</label>
                        <input type="text" class="form-control" id="edit_new_category" name="new_category">
                    </div>
                    <div class="form-group">
                        <label for="edit_template_content">Inhalt *</label>
                        <textarea class="form-control" id="edit_template_content" name="template_content" rows="10" required></textarea>
                        <small class="form-text text-muted">
                            Verwenden Sie <code>{{POSITION}}</code> für Ihre Position/Titel und <code>{{SIGNATURE}}</code> für Ihre vollständige Signatur.
                        </small>
                    </div>
                    <div class="form-group form-check">
                        <input type="checkbox" class="form-check-input" id="edit_is_public" name="is_public">
                        <label class="form-check-label" for="edit_is_public">Diese Vorlage für alle Benutzer verfügbar machen</label>
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle category filter change
        const categoryFilter = document.getElementById('category-filter');
        if (categoryFilter) {
            categoryFilter.addEventListener('change', function() {
                this.form.submit();
            });
        }
        
        // Handle department change to update categories
        const templateDepartment = document.getElementById('template_department');
        const templateCategory = document.getElementById('template_category');
        
        if (templateDepartment && templateCategory) {
            templateDepartment.addEventListener('change', function() {
                const selectedDepartment = this.value;
                
                // Verstecke alle Kategorien
                Array.from(templateCategory.options).forEach(option => {
                    if (option.value === 'new') return; // "Neue Kategorie" Option immer behalten
                    
                    const department = option.getAttribute('data-department');
                    if (!department) return;
                    
                    option.style.display = department === selectedDepartment ? '' : 'none';
                });
                
                // Wähle erste sichtbare Option aus
                const firstVisibleOption = Array.from(templateCategory.options).find(option => {
                    return option.style.display !== 'none';
                });
                
                if (firstVisibleOption) {
                    templateCategory.value = firstVisibleOption.value;
                } else {
                    templateCategory.value = 'new'; // Wenn keine passende Kategorie, dann "Neue Kategorie"
                }
                
                // Kategorie-Änderung Event auslösen, um ggf. "Neue Kategorie" Feld anzuzeigen
                const event = new Event('change');
                templateCategory.dispatchEvent(event);
            });
        }
        
        // Handle new category field toggle
        const newCategoryGroup = document.getElementById('new_category_group');
        
        if (templateCategory && newCategoryGroup) {
            templateCategory.addEventListener('change', function() {
                if (this.value === 'new') {
                    newCategoryGroup.classList.remove('d-none');
                } else {
                    newCategoryGroup.classList.add('d-none');
                }
            });
        }
        
        // Vorauswahl der Kategorie, wenn man über "Vorlage in dieser Kategorie" klickt
        $('#addTemplateModal').on('show.bs.modal', function (event) {
            const button = $(event.relatedTarget);
            const category = button.data('category');
            if (category) {
                $('#template_category').val(category);
                
                // Finde die Abteilung, zu der diese Kategorie gehört
                const categoryOption = Array.from($('#template_category option')).find(option => 
                    option.value === category && option.getAttribute('data-department')
                );
                
                if (categoryOption) {
                    const department = categoryOption.getAttribute('data-department');
                    $('#template_department').val(department);
                }
                
                // Kategorie-Änderung Event auslösen, um ggf. "Neue Kategorie" Feld anzuzeigen
                const changeEvent = new Event('change');
                document.getElementById('template_category').dispatchEvent(changeEvent);
            }
        });
        
        // Handle edit department change
        const editTemplateDepartment = document.getElementById('edit_template_department');
        const editTemplateCategory = document.getElementById('edit_template_category');
        
        if (editTemplateDepartment && editTemplateCategory) {
            editTemplateDepartment.addEventListener('change', function() {
                const selectedDepartment = this.value;
                
                // Verstecke alle Kategorien
                Array.from(editTemplateCategory.options).forEach(option => {
                    if (option.value === 'new') return; // "Neue Kategorie" Option immer behalten
                    
                    const department = option.getAttribute('data-department');
                    if (!department) return;
                    
                    option.style.display = department === selectedDepartment ? '' : 'none';
                });
                
                // Wähle erste sichtbare Option aus
                const firstVisibleOption = Array.from(editTemplateCategory.options).find(option => {
                    return option.style.display !== 'none';
                });
                
                if (firstVisibleOption) {
                    editTemplateCategory.value = firstVisibleOption.value;
                } else {
                    editTemplateCategory.value = 'new'; // Wenn keine passende Kategorie, dann "Neue Kategorie"
                }
                
                // Kategorie-Änderung Event auslösen, um ggf. "Neue Kategorie" Feld anzuzeigen
                const event = new Event('change');
                editTemplateCategory.dispatchEvent(event);
            });
        }
        
        // Handle edit new category field toggle
        const editNewCategoryGroup = document.getElementById('edit_new_category_group');
        
        if (editTemplateCategory && editNewCategoryGroup) {
            editTemplateCategory.addEventListener('change', function() {
                if (this.value === 'new') {
                    editNewCategoryGroup.classList.remove('d-none');
                } else {
                    editNewCategoryGroup.classList.add('d-none');
                }
            });
        }
        
        // Handle view template buttons
        const viewButtons = document.querySelectorAll('.view-template-btn');
        let currentTemplateId = null;
        
        viewButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const id = this.dataset.id;
                const title = this.dataset.title;
                const content = this.dataset.content;
                const category = this.dataset.category;
                const creator = this.dataset.creator;
                currentTemplateId = id;
                
                // Titel im Modal setzen
                document.getElementById('viewTemplateModalLabel').textContent = title;
                
                // Metainformationen setzen
                document.querySelector('.view-template-meta').textContent = 
                    `Kategorie: ${category} | Erstellt von: ${creator}`;
                
                // Inhalt mit Zeilenumbrüchen anzeigen
                document.querySelector('.view-template-content').innerHTML = 
                    content.replace(/\n/g, '<br>');
                
                // Copy-Button initialisieren
                document.getElementById('view-copy-template-btn').dataset.content = content;
                
                // Prüfen, ob der Benutzer die Vorlage bearbeiten darf und ggf. Button anzeigen
                const editButton = document.querySelector('.edit-from-view-btn');
                const canEdit = <?php echo isAdmin($user_id) ? 'true' : 'false'; ?> || 
                               this.closest('.btn-group').querySelector('.edit-template-btn') !== null;
                
                if (canEdit) {
                    editButton.style.display = 'inline-block';
                    editButton.dataset.id = id;
                } else {
                    editButton.style.display = 'none';
                }
                
                // Modal öffnen
                $('#viewTemplateModal').modal('show');
            });
        });
        
        // Handle edit template buttons
        const editButtons = document.querySelectorAll('.edit-template-btn');
        
        editButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const id = this.dataset.id;
                const title = this.dataset.title;
                const content = this.dataset.content;
                const category = this.dataset.category;
                const department = this.dataset.department || 'Allgemein';
                const isPublic = this.dataset.public === '1';
                
                // Form-Felder füllen
                document.getElementById('edit_template_id').value = id;
                document.getElementById('edit_template_title').value = title;
                document.getElementById('edit_template_content').value = content;
                document.getElementById('edit_template_department').value = department;
                
                // Zuerst die Abteilung setzen, damit die richtigen Kategorien angezeigt werden
                const deptEvent = new Event('change');
                document.getElementById('edit_template_department').dispatchEvent(deptEvent);
                
                // Dann die Kategorie auswählen
                setTimeout(() => {
                    document.getElementById('edit_template_category').value = category;
                    document.getElementById('edit_is_public').checked = isPublic;
                }, 100);
                
                // Modal öffnen
                $('#editTemplateModal').modal('show');
                
                // Wenn View-Modal geöffnet ist, dieses schließen
                $('#viewTemplateModal').modal('hide');
            });
        });
        
        // Handle edit from view button
        document.querySelector('.edit-from-view-btn').addEventListener('click', function() {
            const id = this.dataset.id;
            // Finde den passenden Edit-Button und klicke ihn
            const editButton = document.querySelector(`.edit-template-btn[data-id="${id}"]`);
            if (editButton) {
                editButton.click();
            }
        });
        
        // Handle copy from view button
        document.getElementById('view-copy-template-btn').addEventListener('click', function() {
            const content = this.dataset.content;
            copyToClipboard(content);
        });
        
        // Handle copy buttons in template list
        const copyButtons = document.querySelectorAll('.copy-template-btn');
        copyButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const content = this.dataset.content;
                copyToClipboard(content);
            });
        });
        
        // Helper function to copy content to clipboard
        function copyToClipboard(content) {
            const textarea = document.createElement('textarea');
            textarea.value = content;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            
            alert('Vorlageninhalt in die Zwischenablage kopiert.');
        }
        
        // Alte Vorlage-Ansicht-Links verhindern
        document.querySelectorAll('a[href*="templates.php?id="]').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                // Finde den passenden View-Button in derselben Zeile und klicke ihn
                const row = this.closest('tr');
                if (row) {
                    const viewButton = row.querySelector('.view-template-btn');
                    if (viewButton) {
                        viewButton.click();
                    }
                }
            });
        });
    });
</script>

<?php if ($viewMode === 'categories'): ?>

<!-- Die neue Kategorienverwaltung ohne Modal, mit anderer Container-Struktur -->
<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="categories-view" id="categoriesView">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Kategorien verwalten</h1>
        <div class="action-buttons">
            <a href="templates.php" class="btn btn-secondary">
                <span data-feather="arrow-left"></span> Zurück zu Vorlagen
            </a>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Nav-Tabs für die verschiedenen Abteilungen -->
    <ul class="nav nav-tabs" id="departmentTabsNonModal" role="tablist">
        <?php 
        $tabCounter = 0;
        foreach ($departments as $department): 
            $tabCounter++;
            $tabId = 'dept-tab-nonmodal-' . $tabCounter;
            $active = $tabCounter === 1 ? 'active' : '';
        ?>
        <li class="nav-item">
            <a class="nav-link <?php echo $active; ?>" id="<?php echo $tabId; ?>-tab" data-toggle="tab" href="#<?php echo $tabId; ?>" role="tab" aria-controls="<?php echo $tabId; ?>" aria-selected="<?php echo $active === 'active' ? 'true' : 'false'; ?>">
                <?php echo htmlspecialchars($department); ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
    
    <!-- Tab-Inhalte für die verschiedenen Abteilungen -->
    <div class="tab-content mt-3" id="departmentTabsContentNonModal">
        <?php 
        $tabCounter = 0;
        foreach ($departments as $department): 
            $tabCounter++;
            $tabId = 'dept-tab-nonmodal-' . $tabCounter;
            $active = $tabCounter === 1 ? 'show active' : '';
            $departmentCategories = $savedCategories[$department] ?? [];
        ?>
        <div class="tab-pane fade <?php echo $active; ?>" id="<?php echo $tabId; ?>" role="tabpanel" aria-labelledby="<?php echo $tabId; ?>-tab">
            <div class="categories-manager-wrap">
                <h5 class="mb-3"><?php echo htmlspecialchars($department); ?> - Kategorien</h5>
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th style="width: 40%">Kategoriename</th>
                                        <th style="width: 30%">Verwendung</th>
                                        <th style="width: 30%">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if (empty($departmentCategories)):
                                    ?>
                                    <tr>
                                        <td colspan="3" class="text-center">Keine Kategorien in dieser Abteilung.</td>
                                    </tr>
                                    <?php 
                                    else:
                                        foreach ($departmentCategories as $category): 
                                            // Zähle Vorlagen in dieser Kategorie
                                            $categoryUsage = 0;
                                            foreach ($templates as $template) {
                                                if ($template['category'] === $category) {
                                                    $categoryUsage++;
                                                }
                                            }
                                    ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($category); ?>
                                        </td>
                                        <td>
                                            <?php echo $categoryUsage; ?> Vorlagen
                                        </td>
                                        <td>
                                            <!-- Inline-Formular zur direkten Bearbeitung ohne Modal -->
                                            <form method="post" action="templates.php?mode=categories" class="edit-category-form">
                                                <div class="form-row">
                                                    <div class="col-md-8">
                                                        <input type="hidden" name="action" value="edit_category">
                                                        <input type="hidden" name="old_category" value="<?php echo htmlspecialchars($category); ?>">
                                                        <input type="hidden" name="department" value="<?php echo htmlspecialchars($department); ?>">
                                                        <input type="text" class="form-control form-control-sm mb-1" name="new_category" 
                                                               value="<?php echo htmlspecialchars($category); ?>" 
                                                               id="edit_category_<?php echo md5($category . $department); ?>"
                                                               aria-label="Neuer Kategoriename">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <button type="submit" class="btn btn-sm btn-outline-primary mb-1 w-100">
                                                            <span data-feather="save"></span> Speichern
                                                        </button>
                                                    </div>
                                                </div>
                                                <?php if ($categoryUsage > 0): ?>
                                                <small class="form-text text-muted">
                                                    Diese Kategorie wird in <?php echo $categoryUsage; ?> Vorlagen verwendet.
                                                </small>
                                                <?php endif; ?>
                                            </form>
                                            
                                            <?php if ($categoryUsage === 0): ?>
                                            <form method="post" action="templates.php?mode=categories" class="d-inline mt-1">
                                                <input type="hidden" name="action" value="delete_category">
                                                <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                                                <input type="hidden" name="department" value="<?php echo htmlspecialchars($department); ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger w-100" onclick="return confirm('Sind Sie sicher, dass Sie diese Kategorie löschen möchten?');">
                                                    <span data-feather="trash-2"></span> Löschen
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php 
                                        endforeach;
                                    endif;
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <hr class="my-4">
                        
                        <h5>Neue Kategorie in <?php echo htmlspecialchars($department); ?> erstellen</h5>
                        <form method="post" action="templates.php?mode=categories">
                            <input type="hidden" name="action" value="add_category">
                            <input type="hidden" name="department" value="<?php echo htmlspecialchars($department); ?>">
                            <div class="form-row align-items-end">
                                <div class="col-md-8 mb-3">
                                    <label for="new_category_name_<?php echo md5($department); ?>">Kategoriename</label>
                                    <input type="text" class="form-control" id="new_category_name_<?php echo md5($department); ?>" name="new_category" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <button type="submit" class="btn btn-success w-100">Kategorie erstellen</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
            </main>
        </div>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
