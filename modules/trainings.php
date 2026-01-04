<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/permissions.php';

// Überprüfen, ob der Benutzer angemeldet ist
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Benutzer-ID und -Name
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Überprüfen, ob der Benutzer Zugriff auf das Modul hat
// Jeder Benutzer darf Schulungen ansehen
if (!checkUserPermission($user_id, 'trainings', 'view')) {
    header('Location: ../access_denied.php');
    exit;
}

// Initialisiere Meldungen
$message = '';
$error = '';

// Upload-Ordner für Schulungsmaterialien
$uploadDir = '../uploads/trainings/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Action-Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Kategorie hinzufügen
    if ($action === 'add_category') {
        // Überprüfen, ob der Benutzer Berechtigung zum Hinzufügen von Kategorien hat
        if (!checkUserPermission($user_id, 'trainings', 'create')) {
            header('Location: ../access_denied.php');
            exit;
        }
        if (empty($_POST['category_name'])) {
            $error = 'Der Kategoriename darf nicht leer sein.';
        } else {
            $categoryName = sanitize($_POST['category_name']);
            $categoryDescription = sanitize($_POST['category_description'] ?? '');
            
            // Überprüfen, ob Kategorie schon existiert
            $categories = loadJsonData('training_categories.json');
            $categoryExists = false;
            
            foreach ($categories as $category) {
                if (strtolower(trim($category['name'])) === strtolower(trim($categoryName))) {
                    $categoryExists = true;
                    break;
                }
            }
            
            if ($categoryExists) {
                $error = 'Eine Kategorie mit diesem Namen existiert bereits.';
            } else {
                $newCategory = [
                    'id' => uniqid('', true),
                    'name' => $categoryName,
                    'description' => $categoryDescription,
                    'created_by' => $_SESSION['user_id'],
                    'date_created' => date('Y-m-d H:i:s'),
                    'date_updated' => date('Y-m-d H:i:s')
                ];
                
                $categories[] = $newCategory;
                
                if (saveJsonData('training_categories.json', $categories)) {
                    $message = 'Schulungskategorie erfolgreich hinzugefügt.';
                    header("Location: trainings.php");
                    exit;
                } else {
                    $error = 'Fehler beim Hinzufügen der Kategorie.';
                }
            }
        }
    }
    
    // Kategorie bearbeiten
    elseif ($action === 'edit_category' && isset($_POST['category_id'])) {
        // Überprüfen, ob der Benutzer Berechtigung zum Bearbeiten von Kategorien hat
        if (!checkUserPermission($user_id, 'trainings', 'edit')) {
            header('Location: ../access_denied.php');
            exit;
        }
        
        $categoryId = sanitize($_POST['category_id']);
        error_log("Bearbeite Kategorie: " . $categoryId);
        
        if (empty($_POST['category_name'])) {
            $error = 'Der Kategoriename darf nicht leer sein.';
            error_log("Fehler: Kein Name angegeben");
        } else {
            $categories = loadJsonData('training_categories.json');
            $categoryExists = false;
            
            foreach ($categories as $category) {
                if (strtolower(trim($category['name'])) === strtolower(trim($_POST['category_name'])) && $category['id'] !== $categoryId) {
                    $categoryExists = true;
                    break;
                }
            }
            
            if ($categoryExists) {
                $error = 'Eine Kategorie mit diesem Namen existiert bereits.';
                error_log("Fehler: Name existiert bereits");
            } else {
                error_log("Suche Kategorie mit ID: " . $categoryId);
                $found = false;
                
                foreach ($categories as $index => $category) {
                    if ($category['id'] === $categoryId) {
                        error_log("Kategorie gefunden: " . $category['name']);
                        $found = true;
                        
                        $categories[$index]['name'] = sanitize($_POST['category_name']);
                        $categories[$index]['description'] = sanitize($_POST['category_description'] ?? '');
                        $categories[$index]['date_updated'] = date('Y-m-d H:i:s');
                        
                        if (saveJsonData('training_categories.json', $categories)) {
                            $message = 'Schulungskategorie erfolgreich aktualisiert.';
                            error_log("Kategorie erfolgreich aktualisiert");
                            header("Location: trainings.php");
                            exit;
                        } else {
                            $error = 'Fehler beim Aktualisieren der Kategorie.';
                            error_log("Fehler beim Speichern der Kategorie");
                        }
                        break;
                    }
                }
                
                if (!$found) {
                    $error = 'Schulungskategorie nicht gefunden.';
                    error_log("Kategorie nicht gefunden");
                }
            }
        }
    }
    
    // Kategorie löschen
    elseif ($action === 'delete_category' && isset($_POST['category_id'])) {
        // Überprüfen, ob der Benutzer Berechtigung zum Löschen von Kategorien hat
        if (!checkUserPermission($user_id, 'trainings', 'delete')) {
            header('Location: ../access_denied.php');
            exit;
        }
        
        $categoryId = sanitize($_POST['category_id']);
        error_log("Lösche Kategorie: " . $categoryId);
        
        // Prüfen, ob Kategorie in Verwendung ist
        $materials = loadJsonData('training_materials.json');
        $categoryInUse = false;
        
        foreach ($materials as $material) {
            if ($material['category_id'] === $categoryId) {
                $categoryInUse = true;
                error_log("Kategorie wird verwendet, kann nicht gelöscht werden");
                break;
            }
        }
        
        if ($categoryInUse) {
            $error = 'Die Kategorie kann nicht gelöscht werden, da sie von Schulungsmaterialien verwendet wird.';
        } else {
            $categories = loadJsonData('training_categories.json');
            $found = false;
            
            foreach ($categories as $index => $category) {
                if ($category['id'] === $categoryId) {
                    error_log("Kategorie gefunden und wird entfernt: " . $category['name']);
                    $found = true;
                    
                    unset($categories[$index]);
                    $categories = array_values($categories);
                    
                    if (saveJsonData('training_categories.json', $categories)) {
                        $message = 'Schulungskategorie erfolgreich gelöscht.';
                        error_log("Kategorie erfolgreich gelöscht");
                        header("Location: trainings.php");
                        exit;
                    } else {
                        $error = 'Fehler beim Löschen der Kategorie.';
                        error_log("Fehler beim Speichern nach dem Löschen");
                    }
                    break;
                }
            }
            
            if (!$found) {
                $error = 'Schulungskategorie nicht gefunden.';
                error_log("Zu löschende Kategorie nicht gefunden");
            }
        }
    }
    
    // Material hinzufügen
    elseif ($action === 'add_material') {
        // Überprüfen, ob der Benutzer Berechtigung zum Hinzufügen von Schulungsmaterialien hat
        if (!checkUserPermission($user_id, 'trainings', 'create')) {
            header('Location: ../access_denied.php');
            exit;
        }
        if (empty($_POST['material_title']) || empty($_POST['category_id'])) {
            $error = 'Titel und Kategorie sind erforderlich.';
        } else {
            $materialTitle = sanitize($_POST['material_title']);
            $categoryId = sanitize($_POST['category_id']);
            $materialDescription = sanitize($_POST['material_description'] ?? '');
            $materialType = sanitize($_POST['material_type'] ?? 'link');
            $materialLink = sanitize($_POST['material_link'] ?? '');
            $fileUploaded = false;
            $filePath = '';
            
            // Überprüfen, ob die Kategorie existiert
            $categories = loadJsonData('training_categories.json');
            $categoryExists = false;
            
            foreach ($categories as $category) {
                if ($category['id'] === $categoryId) {
                    $categoryExists = true;
                    break;
                }
            }
            
            if (!$categoryExists) {
                $error = 'Die ausgewählte Kategorie existiert nicht.';
            } else {
                // Datei hochladen, wenn Typ 'file' ist
                if ($materialType === 'file' && isset($_FILES['material_file']) && $_FILES['material_file']['error'] === UPLOAD_ERR_OK) {
                    $fileInfo = pathinfo($_FILES['material_file']['name']);
                    $fileExt = strtolower($fileInfo['extension']);
                    
                    // Erlaubte Dateitypen
                    $allowedExts = ['pdf', 'png'];
                    
                    if (!in_array($fileExt, $allowedExts)) {
                        $error = 'Nur PDF und PNG Dateien sind erlaubt.';
                    } else {
                        $fileName = uniqid('training_', true) . '.' . $fileExt;
                        $filePath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($_FILES['material_file']['tmp_name'], $filePath)) {
                            $fileUploaded = true;
                            $filePath = 'uploads/trainings/' . $fileName; // Relativer Pfad für die Datenbank
                        } else {
                            $error = 'Fehler beim Hochladen der Datei.';
                        }
                    }
                } elseif ($materialType === 'link' && empty($materialLink)) {
                    $error = 'Für den Typ "Link" muss eine URL angegeben werden.';
                }
                
                // Material speichern, wenn keine Fehler aufgetreten sind
                if (empty($error)) {
                    $materials = loadJsonData('training_materials.json');
                    
                    $newMaterial = [
                        'id' => uniqid('', true),
                        'title' => $materialTitle,
                        'description' => $materialDescription,
                        'category_id' => $categoryId,
                        'type' => $materialType,
                        'created_by' => $_SESSION['user_id'],
                        'date_created' => date('Y-m-d H:i:s'),
                        'date_updated' => date('Y-m-d H:i:s')
                    ];
                    
                    if ($materialType === 'file' && $fileUploaded) {
                        $newMaterial['file_path'] = $filePath;
                    } elseif ($materialType === 'link') {
                        $newMaterial['link_url'] = $materialLink;
                    }
                    
                    $materials[] = $newMaterial;
                    
                    if (saveJsonData('training_materials.json', $materials)) {
                        $message = 'Schulungsmaterial erfolgreich hinzugefügt.';
                        header("Location: trainings.php");
                        exit;
                    } else {
                        $error = 'Fehler beim Speichern des Schulungsmaterials.';
                    }
                }
            }
        }
    }
    
    // Material bearbeiten
    elseif ($action === 'edit_material' && isset($_POST['material_id'])) {
        // Überprüfen, ob der Benutzer Berechtigung zum Bearbeiten von Schulungsmaterialien hat
        if (!checkUserPermission($user_id, 'trainings', 'edit')) {
            header('Location: ../access_denied.php');
            exit;
        }
        
        $materialId = sanitize($_POST['material_id']);
        error_log("Bearbeite Material: " . $materialId);
        
        if (empty($_POST['material_title']) || empty($_POST['category_id'])) {
            $error = 'Titel und Kategorie sind erforderlich.';
            error_log("Fehler: Titel oder Kategorie fehlt");
        } else {
            $materials = loadJsonData('training_materials.json');
            $found = false;
            
            foreach ($materials as $index => $material) {
                if ($material['id'] === $materialId) {
                    error_log("Material gefunden: " . $material['title']);
                    $found = true;
                    
                    $materials[$index]['title'] = sanitize($_POST['material_title']);
                    $materials[$index]['description'] = sanitize($_POST['material_description'] ?? '');
                    $materials[$index]['category_id'] = sanitize($_POST['category_id']);
                    $materials[$index]['date_updated'] = date('Y-m-d H:i:s');
                    
                    // Material-Typ
                    $materialType = sanitize($_POST['material_type'] ?? $material['type']);
                    $materials[$index]['type'] = $materialType;
                    
                    // Wenn Typ 'link' ist und eine URL angegeben wurde
                    if ($materialType === 'link' && !empty($_POST['material_link'])) {
                        $materials[$index]['link_url'] = sanitize($_POST['material_link']);
                        // Altes Dateifeld entfernen, falls es existiert
                        if (isset($materials[$index]['file_path'])) {
                            unset($materials[$index]['file_path']);
                        }
                    }
                    
                    // Wenn Typ 'file' ist und eine neue Datei hochgeladen wurde
                    if ($materialType === 'file' && isset($_FILES['material_file']) && $_FILES['material_file']['error'] === UPLOAD_ERR_OK) {
                        $fileInfo = pathinfo($_FILES['material_file']['name']);
                        $fileExt = strtolower($fileInfo['extension']);
                        
                        // Erlaubte Dateitypen
                        $allowedExts = ['pdf', 'png'];
                        
                        if (!in_array($fileExt, $allowedExts)) {
                            $error = 'Nur PDF und PNG Dateien sind erlaubt.';
                            error_log("Fehler: Ungültiger Dateityp");
                            break;
                        } else {
                            $fileName = uniqid('training_', true) . '.' . $fileExt;
                            $filePath = $uploadDir . $fileName;
                            
                            if (move_uploaded_file($_FILES['material_file']['tmp_name'], $filePath)) {
                                // Alte Datei löschen, falls vorhanden
                                if (isset($material['file_path']) && file_exists('../' . $material['file_path'])) {
                                    unlink('../' . $material['file_path']);
                                }
                                
                                $materials[$index]['file_path'] = 'uploads/trainings/' . $fileName;
                                
                                // URL-Feld entfernen, falls es existiert
                                if (isset($materials[$index]['link_url'])) {
                                    unset($materials[$index]['link_url']);
                                }
                            } else {
                                $error = 'Fehler beim Hochladen der Datei.';
                                error_log("Fehler beim Hochladen der Datei");
                                break;
                            }
                        }
                    }
                    
                    if (empty($error)) {
                        if (saveJsonData('training_materials.json', $materials)) {
                            $message = 'Schulungsmaterial erfolgreich aktualisiert.';
                            error_log("Material erfolgreich aktualisiert");
                            header("Location: trainings.php");
                            exit;
                        } else {
                            $error = 'Fehler beim Aktualisieren des Materials.';
                            error_log("Fehler beim Speichern des Materials");
                        }
                    }
                    
                    break;
                }
            }
            
            if (!$found) {
                $error = 'Schulungsmaterial nicht gefunden.';
                error_log("Material nicht gefunden");
            }
        }
    }
    
    // Material löschen
    elseif ($action === 'delete_material' && isset($_POST['material_id'])) {
        // Überprüfen, ob der Benutzer Berechtigung zum Löschen von Schulungsmaterialien hat
        if (!checkUserPermission($user_id, 'trainings', 'delete')) {
            header('Location: ../access_denied.php');
            exit;
        }
        
        $materialId = sanitize($_POST['material_id']);
        error_log("Lösche Material: " . $materialId);
        
        // Prüfen, ob Schulungsmaterial in Nutzung ist (in Trainingsaufzeichnungen)
        $records = loadJsonData('training_records.json');
        $materialInUse = false;
        
        foreach ($records as $record) {
            if ($record['material_id'] === $materialId) {
                $materialInUse = true;
                error_log("Material wird in Trainingsaufzeichnungen verwendet");
                break;
            }
        }
        
        if ($materialInUse) {
            // Keine Löschung erlauben, wenn Material in Verwendung ist
            $error = 'Das Schulungsmaterial kann nicht gelöscht werden, da es in Trainingsaufzeichnungen verwendet wird.';
        } else {
            
            $materials = loadJsonData('training_materials.json');
            $found = false;
            
            foreach ($materials as $index => $material) {
                if ($material['id'] === $materialId) {
                    error_log("Material gefunden und wird entfernt: " . $material['title']);
                    $found = true;
                    
                    // Wenn es eine Datei gibt, diese löschen
                    if (isset($material['file_path']) && file_exists('../' . $material['file_path'])) {
                        unlink('../' . $material['file_path']);
                    }
                    
                    unset($materials[$index]);
                    $materials = array_values($materials);
                    
                    if (saveJsonData('training_materials.json', $materials)) {
                        $message = 'Schulungsmaterial erfolgreich gelöscht.';
                        error_log("Material erfolgreich gelöscht");
                        header("Location: trainings.php");
                        exit;
                    } else {
                        $error = 'Fehler beim Löschen des Materials.';
                        error_log("Fehler beim Speichern nach dem Löschen");
                    }
                    break;
                }
            }
            
            if (!$found) {
                $error = 'Schulungsmaterial nicht gefunden.';
                error_log("Zu löschendes Material nicht gefunden");
            }
        }
    }
}

// Daten laden
$categories = loadJsonData('training_categories.json');
$materials = loadJsonData('training_materials.json');
$staff = loadJsonData('staff.json');

// Nach Namen sortieren
usort($categories, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

usort($materials, function($a, $b) {
    return strcmp($a['title'], $b['title']);
});

usort($staff, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

// Ausgewählte Kategorie
$selectedCategory = $_GET['category'] ?? '';

// Materialien nach Kategorie filtern
$filteredMaterials = $materials;
if (!empty($selectedCategory)) {
    $filteredMaterials = array_filter($materials, function($material) use ($selectedCategory) {
        return $material['category_id'] === $selectedCategory;
    });
}

// Mitarbeiter-/Benutzerinformationen abrufen
$users = loadJsonData('users.json');
$userNames = [];

foreach ($users as $user) {
    $userNames[$user['id']] = $user['username'];
}
?>

<?php include '../includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Schulungsverwaltung</h1>
                <div>
                    <?php if (checkUserPermission($user_id, 'trainings', 'create')): ?>
                    <button type="button" class="btn btn-outline-secondary mr-2" data-toggle="modal" data-target="#addCategoryModal">
                        <span data-feather="folder-plus"></span> Kategorie hinzufügen
                    </button>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addMaterialModal">
                        <span data-feather="plus"></span> Schulungsmaterial hinzufügen
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-3">
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Kategorien</h5>
                            <?php if (checkUserPermission($user_id, 'trainings', 'create')): ?>
                            <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#addCategoryModal">
                                <span data-feather="plus"></span> Neu
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush training-categories">
                                <a href="trainings.php" class="list-group-item list-group-item-action <?php echo empty($selectedCategory) ? 'active' : ''; ?>">
                                    <strong>Alle Kategorien</strong>
                                    <span class="badge badge-pill badge-light float-right"><?php echo count($materials); ?></span>
                                </a>
                                <?php 
                                // Materialien pro Kategorie zählen
                                $categoryCount = [];
                                
                                foreach ($categories as $category) {
                                    $categoryCount[$category['id']] = 0;
                                }
                                
                                foreach ($materials as $material) {
                                    if (isset($categoryCount[$material['category_id']])) {
                                        $categoryCount[$material['category_id']]++;
                                    }
                                }
                                
                                // Alle Kategorien anzeigen
                                foreach ($categories as $category):
                                ?>
                                    <div class="list-group-item <?php echo $selectedCategory === $category['id'] ? 'active' : ''; ?>">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <a href="trainings.php?category=<?php echo urlencode($category['id']); ?>" 
                                               class="flex-grow-1 text-decoration-none <?php echo $selectedCategory === $category['id'] ? 'text-white' : 'text-dark'; ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                                <span class="badge badge-pill badge-light float-right"><?php echo $categoryCount[$category['id']] ?? 0; ?></span>
                                            </a>
                                            <div class="ml-2">
                                                <?php if (checkUserPermission($user_id, 'trainings', 'edit')): ?>
                                                <button type="button" class="btn btn-sm btn-link edit-category-btn p-0 mr-2" 
                                                        data-id="<?php echo $category['id']; ?>" 
                                                        data-name="<?php echo htmlspecialchars($category['name']); ?>"
                                                        data-description="<?php echo htmlspecialchars($category['description'] ?? ''); ?>">
                                                    <span data-feather="edit" class="<?php echo $selectedCategory === $category['id'] ? 'text-white' : 'text-dark'; ?>"></span>
                                                </button>
                                                <?php endif; ?>
                                                <?php if (checkUserPermission($user_id, 'trainings', 'delete')): ?>
                                                <button type="button" class="btn btn-sm btn-link delete-category-btn p-0" 
                                                        data-id="<?php echo $category['id']; ?>" 
                                                        data-name="<?php echo htmlspecialchars($category['name']); ?>">
                                                    <span data-feather="trash-2" class="<?php echo $selectedCategory === $category['id'] ? 'text-white' : 'text-dark'; ?>"></span>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-9">
                    <!-- Schulungsmaterialien anzeigen -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4>
                                <?php if (!empty($selectedCategory)): 
                                    $categoryName = '';
                                    foreach ($categories as $category) {
                                        if ($category['id'] === $selectedCategory) {
                                            $categoryName = $category['name'];
                                            break;
                                        }
                                    }
                                ?>
                                    Schulungen: <?php echo htmlspecialchars($categoryName); ?>
                                <?php else: ?>
                                    Alle Schulungsmaterialien
                                <?php endif; ?>
                            </h4>
                            <?php if (checkUserPermission($user_id, 'trainings', 'create')): ?>
                            <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#addMaterialModal">
                                <span data-feather="plus"></span> Neues Material
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if (count($filteredMaterials) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Titel</th>
                                                <th>Kategorie</th>
                                                <th>Typ</th>
                                                <th>Erstellt von</th>
                                                <th>Datum</th>
                                                <th class="text-center">Aktionen</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($filteredMaterials as $material): 
                                                $categoryName = 'Unbekannt';
                                                foreach ($categories as $category) {
                                                    if ($category['id'] === $material['category_id']) {
                                                        $categoryName = $category['name'];
                                                        break;
                                                    }
                                                }
                                                
                                                $createdBy = $userNames[$material['created_by']] ?? 'Unbekannt';
                                            ?>
                                            <tr>
                                                <td>
                                                    <span class="font-weight-bold view-material-btn" style="cursor: pointer;" 
                                                          data-id="<?php echo $material['id']; ?>"
                                                          data-title="<?php echo htmlspecialchars($material['title']); ?>">
                                                        <?php echo htmlspecialchars($material['title']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($categoryName); ?></td>
                                                <td>
                                                    <?php if ($material['type'] === 'file'): ?>
                                                        <span class="badge badge-info">Datei</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-success">Link</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($createdBy); ?></td>
                                                <td><?php echo date('d.m.Y', strtotime($material['date_created'])); ?></td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-outline-primary view-material-btn" 
                                                            data-id="<?php echo $material['id']; ?>"
                                                            data-title="<?php echo htmlspecialchars($material['title']); ?>">
                                                        <span data-feather="eye"></span> Anzeigen
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <p class="lead">Keine Schulungsmaterialien gefunden.</p>
                                    <p>Fügen Sie neue Schulungsmaterialien hinzu oder ändern Sie die Filterkriterien.</p>
                                    <?php if (checkUserPermission($user_id, 'trainings', 'create')): ?>
                                    <button type="button" class="btn btn-primary mt-3" data-toggle="modal" data-target="#addMaterialModal">
                                        <span data-feather="plus"></span> Schulungsmaterial hinzufügen
                                    </button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Kategorie hinzufügen Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCategoryModalLabel">Schulungskategorie hinzufügen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="trainings.php" method="post">
                <input type="hidden" name="action" value="add_category">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="category_name">Kategoriename <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="category_name" name="category_name" required>
                    </div>
                    <div class="form-group">
                        <label for="category_description">Beschreibung</label>
                        <textarea class="form-control" id="category_description" name="category_description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Hinzufügen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Kategorie bearbeiten Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCategoryModalLabel">Schulungskategorie bearbeiten</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="trainings.php" method="post">
                <input type="hidden" name="action" value="edit_category">
                <input type="hidden" name="category_id" id="edit_category_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_category_name">Kategoriename <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_category_name" name="category_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_category_description">Beschreibung</label>
                        <textarea class="form-control" id="edit_category_description" name="category_description" rows="3"></textarea>
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
<div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-labelledby="deleteCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteCategoryModalLabel">Schulungskategorie löschen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="trainings.php" method="post">
                <input type="hidden" name="action" value="delete_category">
                <input type="hidden" name="category_id" id="delete_category_id">
                <div class="modal-body">
                    <p>Sind Sie sicher, dass Sie die Kategorie <strong id="delete_category_name"></strong> löschen möchten?</p>
                    <p class="text-danger">Diese Aktion kann nicht rückgängig gemacht werden.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-danger">Löschen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Material hinzufügen Modal -->
<div class="modal fade" id="addMaterialModal" tabindex="-1" aria-labelledby="addMaterialModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addMaterialModalLabel">Schulungsmaterial hinzufügen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="trainings.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_material">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="material_title">Titel <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="material_title" name="material_title" required>
                    </div>
                    <div class="form-group">
                        <label for="category_id">Kategorie <span class="text-danger">*</span></label>
                        <select class="form-control" id="category_id" name="category_id" required>
                            <option value="">-- Kategorie auswählen --</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo ($selectedCategory === $category['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="material_description">Beschreibung</label>
                        <textarea class="form-control" id="material_description" name="material_description" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Material-Typ <span class="text-danger">*</span></label>
                        <div class="custom-control custom-radio">
                            <input type="radio" id="type_link" name="material_type" value="link" class="custom-control-input" checked>
                            <label class="custom-control-label" for="type_link">Link</label>
                        </div>
                        <div class="custom-control custom-radio">
                            <input type="radio" id="type_file" name="material_type" value="file" class="custom-control-input">
                            <label class="custom-control-label" for="type_file">Datei hochladen</label>
                        </div>
                    </div>
                    <div id="link_input" class="form-group">
                        <label for="material_link">Link URL <span class="text-danger">*</span></label>
                        <input type="url" class="form-control" id="material_link" name="material_link" placeholder="https://...">
                    </div>
                    <div id="file_input" class="form-group" style="display: none;">
                        <label for="material_file">Datei (PDF oder PNG) <span class="text-danger">*</span></label>
                        <input type="file" class="form-control-file" id="material_file" name="material_file" accept=".pdf,.png">
                        <small class="form-text text-muted">Maximale Dateigröße: 5 MB</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Hinzufügen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Material anzeigen Modal -->
<div class="modal fade" id="viewMaterialModal" tabindex="-1" aria-labelledby="viewMaterialModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewMaterialModalLabel">Schulungsmaterial anzeigen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="material-loading" class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Laden...</span>
                    </div>
                    <p class="mt-2">Lade Schulungsmaterial...</p>
                </div>
                <div id="material-details" style="display: none;">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <th>Titel</th>
                                    <td id="view-title">-</td>
                                </tr>
                                <tr>
                                    <th>Kategorie</th>
                                    <td id="view-category">-</td>
                                </tr>
                                <tr>
                                    <th>Beschreibung</th>
                                    <td id="view-description">-</td>
                                </tr>
                                <tr>
                                    <th>Typ</th>
                                    <td id="view-type">-</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <th>Erstellt am</th>
                                    <td id="view-date-created">-</td>
                                </tr>
                                <tr>
                                    <th>Erstellt von</th>
                                    <td id="view-created-by">-</td>
                                </tr>
                                <tr>
                                    <th>Zuletzt aktualisiert</th>
                                    <td id="view-date-updated">-</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div id="view-content-container" class="mt-4">
                        <h5>Schulungsinhalt</h5>
                        <div id="view-content" class="border p-3 bg-light">
                            <!-- Hier wird der Inhalt dynamisch eingefügt -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <div>
                    <?php if (checkUserPermission($user_id, 'trainings', 'delete')): ?>
                    <button type="button" class="btn btn-sm btn-danger" id="delete-material-btn">
                        <span data-feather="trash-2"></span> Löschen
                    </button>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if (checkUserPermission($user_id, 'trainings', 'edit')): ?>
                    <button type="button" class="btn btn-sm btn-primary" id="edit-material-btn">
                        <span data-feather="edit"></span> Bearbeiten
                    </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Schließen</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Material bearbeiten Modal -->
<div class="modal fade" id="editMaterialModal" tabindex="-1" aria-labelledby="editMaterialModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editMaterialModalLabel">Schulungsmaterial bearbeiten</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="trainings.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_material">
                <input type="hidden" name="material_id" id="edit_material_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_material_title">Titel <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_material_title" name="material_title" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_category_id">Kategorie <span class="text-danger">*</span></label>
                        <select class="form-control" id="edit_category_id" name="category_id" required>
                            <option value="">-- Kategorie auswählen --</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_material_description">Beschreibung</label>
                        <textarea class="form-control" id="edit_material_description" name="material_description" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Material-Typ <span class="text-danger">*</span></label>
                        <div class="custom-control custom-radio">
                            <input type="radio" id="edit_type_link" name="material_type" value="link" class="custom-control-input">
                            <label class="custom-control-label" for="edit_type_link">Link</label>
                        </div>
                        <div class="custom-control custom-radio">
                            <input type="radio" id="edit_type_file" name="material_type" value="file" class="custom-control-input">
                            <label class="custom-control-label" for="edit_type_file">Datei hochladen</label>
                        </div>
                    </div>
                    <div id="edit_link_input" class="form-group">
                        <label for="edit_material_link">Link URL <span class="text-danger">*</span></label>
                        <input type="url" class="form-control" id="edit_material_link" name="material_link" placeholder="https://...">
                    </div>
                    <div id="edit_file_input" class="form-group" style="display: none;">
                        <label for="edit_material_file">Datei (PDF oder PNG)</label>
                        <input type="file" class="form-control-file" id="edit_material_file" name="material_file" accept=".pdf,.png">
                        <small class="form-text text-muted">Lassen Sie dieses Feld leer, um die aktuelle Datei beizubehalten.</small>
                    </div>
                    <div id="edit_current_file" class="form-group">
                        <label>Aktueller Anhang</label>
                        <p id="edit_current_file_name">Keine Datei vorhanden</p>
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

<!-- Material löschen Modal -->
<div class="modal fade" id="deleteMaterialModal" tabindex="-1" aria-labelledby="deleteMaterialModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteMaterialModalLabel">Schulungsmaterial löschen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="trainings.php" method="post">
                <input type="hidden" name="action" value="delete_material">
                <input type="hidden" name="material_id" id="delete_material_id">
                <div class="modal-body">
                    <p>Sind Sie sicher, dass Sie das Schulungsmaterial <strong id="delete_material_title"></strong> löschen möchten?</p>
                    <p class="text-danger">Diese Aktion kann nicht rückgängig gemacht werden.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-danger">Löschen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
// Wechseln zwischen Link und Datei-Upload
document.addEventListener('DOMContentLoaded', function() {
    const typeRadios = document.querySelectorAll('input[name="material_type"]');
    const linkInput = document.getElementById('link_input');
    const fileInput = document.getElementById('file_input');
    
    typeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'link') {
                linkInput.style.display = 'block';
                fileInput.style.display = 'none';
            } else {
                linkInput.style.display = 'none';
                fileInput.style.display = 'block';
            }
        });
    });
    
    // Für Bearbeiten-Modal
    const editTypeRadios = document.querySelectorAll('input[name="material_type"]');
    const editLinkInput = document.getElementById('edit_link_input');
    const editFileInput = document.getElementById('edit_file_input');
    const editCurrentFile = document.getElementById('edit_current_file');
    
    editTypeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'link') {
                editLinkInput.style.display = 'block';
                editFileInput.style.display = 'none';
                editCurrentFile.style.display = 'none';
            } else {
                editLinkInput.style.display = 'none';
                editFileInput.style.display = 'block';
                editCurrentFile.style.display = 'block';
            }
        });
    });
    
    // Kategorien bearbeiten/löschen
    document.querySelectorAll('.edit-category-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const description = this.getAttribute('data-description') || '';
            
            document.getElementById('edit_category_id').value = id;
            document.getElementById('edit_category_name').value = name;
            document.getElementById('edit_category_description').value = description;
            
            $('#editCategoryModal').modal('show');
        });
    });
    
    document.querySelectorAll('.delete-category-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            
            document.getElementById('delete_category_id').value = id;
            document.getElementById('delete_category_name').textContent = name;
            
            $('#deleteCategoryModal').modal('show');
        });
    });
    
    // Material anzeigen
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('view-material-btn') || e.target.closest('.view-material-btn')) {
            const btn = e.target.classList.contains('view-material-btn') ? e.target : e.target.closest('.view-material-btn');
            const id = btn.getAttribute('data-id');
            
            document.getElementById('material-loading').style.display = 'block';
            document.getElementById('material-details').style.display = 'none';
            
            // AJAX-Anfrage für die Material-Details
            const xhr = new XMLHttpRequest();
            xhr.open('GET', `../includes/ajax_handlers.php?action=get_training_material&id=${id}`, true);
            xhr.onload = function() {
                if (this.status === 200) {
                    try {
                        const response = JSON.parse(this.responseText);
                        
                        if (response.success) {
                            const material = response.data;
                            
                            // Daten in die Modal-Felder einfügen
                            document.getElementById('view-title').textContent = material.title;
                            document.getElementById('view-category').textContent = material.category_name;
                            document.getElementById('view-description').textContent = material.description || '-';
                            document.getElementById('view-type').textContent = material.type === 'file' ? 'Datei' : 'Link';
                            document.getElementById('view-date-created').textContent = new Date(material.date_created).toLocaleDateString('de-DE');
                            document.getElementById('view-created-by').textContent = material.created_by_name;
                            document.getElementById('view-date-updated').textContent = new Date(material.date_updated).toLocaleDateString('de-DE');
                            
                            // Content-Container
                            const contentContainer = document.getElementById('view-content');
                            contentContainer.innerHTML = '';
                            
                            if (material.type === 'file') {
                                if (material.file_path.toLowerCase().endsWith('.pdf')) {
                                    contentContainer.innerHTML = `
                                        <div class="embed-responsive embed-responsive-16by9">
                                            <iframe class="embed-responsive-item" src="../${material.file_path}" allowfullscreen></iframe>
                                        </div>
                                        <a href="../${material.file_path}" target="_blank" class="btn btn-sm btn-primary mt-2">
                                            <span data-feather="download"></span> PDF öffnen
                                        </a>
                                    `;
                                } else if (material.file_path.toLowerCase().endsWith('.png')) {
                                    contentContainer.innerHTML = `
                                        <img src="../${material.file_path}" class="img-fluid" alt="Schulungsmaterial">
                                        <a href="../${material.file_path}" target="_blank" class="btn btn-sm btn-primary mt-2">
                                            <span data-feather="download"></span> Bild öffnen
                                        </a>
                                    `;
                                }
                            } else if (material.type === 'link') {
                                contentContainer.innerHTML = `
                                    <div class="alert alert-info">
                                        <strong>Link:</strong> <a href="${material.link_url}" target="_blank">${material.link_url}</a>
                                    </div>
                                    <a href="${material.link_url}" target="_blank" class="btn btn-sm btn-primary">
                                        <span data-feather="external-link"></span> Link öffnen
                                    </a>
                                `;
                            }
                            
                            // Bearbeiten- und Löschen-Button mit Daten verknüpfen
                            document.getElementById('edit-material-btn').onclick = function() {
                                $('#viewMaterialModal').modal('hide');
                                
                                document.getElementById('edit_material_id').value = material.id;
                                document.getElementById('edit_material_title').value = material.title;
                                document.getElementById('edit_category_id').value = material.category_id;
                                document.getElementById('edit_material_description').value = material.description || '';
                                
                                if (material.type === 'link') {
                                    document.getElementById('edit_type_link').checked = true;
                                    document.getElementById('edit_type_file').checked = false;
                                    document.getElementById('edit_material_link').value = material.link_url;
                                    document.getElementById('edit_link_input').style.display = 'block';
                                    document.getElementById('edit_file_input').style.display = 'none';
                                    document.getElementById('edit_current_file').style.display = 'none';
                                } else {
                                    document.getElementById('edit_type_file').checked = true;
                                    document.getElementById('edit_type_link').checked = false;
                                    document.getElementById('edit_link_input').style.display = 'none';
                                    document.getElementById('edit_file_input').style.display = 'block';
                                    document.getElementById('edit_current_file').style.display = 'block';
                                    document.getElementById('edit_current_file_name').textContent = material.file_path.split('/').pop();
                                }
                                
                                $('#editMaterialModal').modal('show');
                            };
                            
                            document.getElementById('delete-material-btn').onclick = function() {
                                $('#viewMaterialModal').modal('hide');
                                
                                document.getElementById('delete_material_id').value = material.id;
                                document.getElementById('delete_material_title').textContent = material.title;
                                
                                $('#deleteMaterialModal').modal('show');
                            };
                            
                            // Feather Icons neu laden
                            if (typeof feather !== 'undefined') {
                                feather.replace();
                            }
                            
                            document.getElementById('material-loading').style.display = 'none';
                            document.getElementById('material-details').style.display = 'block';
                        } else {
                            alert('Fehler: ' + response.message);
                        }
                    } catch (e) {
                        alert('Fehler beim Verarbeiten der Daten.');
                        console.error(e);
                    }
                } else {
                    alert('Fehler beim Laden der Daten.');
                }
            };
            xhr.send();
            
            $('#viewMaterialModal').modal('show');
        }
    });
});
</script>
