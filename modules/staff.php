<?php
session_start();
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Überprüfen, ob der Benutzer angemeldet ist
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role']; // Hauptrolle
$roles = isset($_SESSION['roles']) ? $_SESSION['roles'] : [$role]; // Alle Rollen
$message = '';
$error = '';

// Mitarbeiteraktionen verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'create_role') {
            $roleName = sanitize($_POST['role_name'] ?? '');
            $roleDescription = sanitize($_POST['role_description'] ?? '');
            
            // Validate required fields
            if (empty($roleName)) {
                $error = 'Bitte geben Sie einen Rollennamen ein.';
            } else {
                $roleData = [
                    'id' => generateUniqueId(),
                    'name' => $roleName,
                    'description' => $roleDescription,
                    'created_by' => $user_id,
                    'date_created' => date('Y-m-d H:i:s')
                ];
                
                // Lade bestehende Rollen
                $existingRoles = loadJsonData('roles.json');
                
                // Überprüfe, ob die Rolle bereits existiert
                $roleExists = false;
                foreach ($existingRoles as $existingRole) {
                    if (strtolower($existingRole['name']) === strtolower($roleName)) {
                        $roleExists = true;
                        break;
                    }
                }
                
                if ($roleExists) {
                    $error = 'Eine Rolle mit diesem Namen existiert bereits.';
                } else {
                    // Füge neue Rolle hinzu
                    $existingRoles[] = $roleData;
                    
                    if (saveJsonData('roles.json', $existingRoles)) {
                        $message = 'Rolle "' . htmlspecialchars($roleName) . '" erfolgreich erstellt.';
                        // Aktualisiere Rollen für das Dropdown
                        $roles = loadJsonData('roles.json');
                    } else {
                        $error = 'Fehler beim Erstellen der Rolle.';
                    }
                }
            }
        } elseif ($action === 'create') {
            $staffData = [
                'name' => sanitize($_POST['name'] ?? ''),
                'role' => sanitize($_POST['staff_role'] ?? ''),
                'tg_number' => sanitize($_POST['tg_number'] ?? ''),
                'contact' => sanitize($_POST['contact'] ?? ''),
                'position' => sanitize($_POST['position'] ?? ''),
                'trainings' => []
            ];
            
            // Validate required fields
            if (empty($staffData['name']) || empty($staffData['role'])) {
                $error = 'Bitte füllen Sie alle erforderlichen Felder aus.';
            } else {
                $staffData['id'] = generateUniqueId();
                $staffData['created_by'] = $user_id;
                $staffData['date_created'] = date('Y-m-d H:i:s');
                
                if (insertRecord('staff.json', $staffData)) {
                    $message = 'Mitarbeiter wurde erfolgreich hinzugefügt.';
                } else {
                    $error = 'Fehler beim Hinzufügen des Mitarbeiters.';
                }
            }
        } elseif ($action === 'update' && isset($_POST['staff_id'])) {
            $staffId = $_POST['staff_id'];
            $staff = findById('staff.json', $staffId);
            
            if (!$staff) {
                $error = 'Mitarbeiter nicht gefunden.';
            } else {
                $staffData = [
                    'name' => sanitize($_POST['name'] ?? ''),
                    'role' => sanitize($_POST['staff_role'] ?? ''),
                    'tg_number' => sanitize($_POST['tg_number'] ?? ''),
                    'contact' => sanitize($_POST['contact'] ?? ''),
                    'position' => sanitize($_POST['position'] ?? ''),
                    'trainings' => $staff['trainings'] ?? []
                ];
                
                // Validate required fields
                if (empty($staffData['name']) || empty($staffData['role'])) {
                    $error = 'Bitte füllen Sie alle erforderlichen Felder aus.';
                } else {
                    $staffData['created_by'] = $staff['created_by'];
                    $staffData['date_created'] = $staff['date_created'];
                    $staffData['last_updated_by'] = $user_id;
                    $staffData['date_updated'] = date('Y-m-d H:i:s');
                    
                    if (updateRecord('staff.json', $staffId, $staffData)) {
                        $message = 'Mitarbeiter wurde erfolgreich aktualisiert.';
                    } else {
                        $error = 'Fehler beim Aktualisieren des Mitarbeiters.';
                    }
                }
            }
        } elseif ($action === 'delete' && isset($_POST['staff_id'])) {
            $staffId = $_POST['staff_id'];
            
            if (deleteRecord('staff.json', $staffId)) {
                $message = 'Mitarbeiter wurde erfolgreich gelöscht.';
            } else {
                $error = 'Fehler beim Löschen des Mitarbeiters.';
            }
        } elseif (($action === 'add_training' || $action === 'assign_training') && isset($_POST['staff_id'])) {
            $staffId = $_POST['staff_id'];
            $staff = findById('staff.json', $staffId);
            
            // Der alte action-Typ ist 'add_training', der neue ist 'assign_training'
            if ($action === 'assign_training') {
                if (!$staff) {
                    $error = 'Mitarbeiter nicht gefunden.';
                } else {
                    $materialId = sanitize($_POST['training_material_id'] ?? '');
                    $status = sanitize($_POST['training_status'] ?? 'Ausstehend');
                    $completionDate = sanitize($_POST['training_completion_date'] ?? '');
                    $notes = sanitize($_POST['training_notes'] ?? '');
                    
                    // Validiere erforderliche Felder
                    if (empty($materialId)) {
                        $error = 'Bitte wählen Sie ein Schulungsmaterial aus.';
                    } else {
                        // Schulungsmaterial abrufen
                        $materials = loadJsonData('training_materials.json');
                        $material = null;
                        
                        foreach ($materials as $m) {
                            if ($m['id'] === $materialId) {
                                $material = $m;
                                break;
                            }
                        }
                        
                        if (!$material) {
                            $error = 'Das ausgewählte Schulungsmaterial existiert nicht.';
                        } else {
                            // Schulungsaufzeichnung erstellen
                            $trainingRecord = [
                                'id' => generateUniqueId(),
                                'staff_id' => $staffId,
                                'material_id' => $materialId,
                                'status' => $status,
                                'completion_date' => $completionDate,
                                'notes' => $notes,
                                'assigned_by' => $user_id,
                                'date_assigned' => date('Y-m-d H:i:s')
                            ];
                            
                            // Zur Schulungsaufzeichnungs-Datei hinzufügen
                            $records = loadJsonData('training_records.json');
                            $records[] = $trainingRecord;
                            
                            if (saveJsonData('training_records.json', $records)) {
                                // Zur Mitarbeiterliste der Schulungen hinzufügen (für die Abwärtskompatibilität)
                                $trainingData = [
                                    'id' => $trainingRecord['id'],
                                    'title' => $material['title'],
                                    'description' => $material['description'] ?? '',
                                    'date' => $completionDate,
                                    'status' => $status,
                                    'material_id' => $materialId,
                                    'added_by' => $user_id,
                                    'date_added' => date('Y-m-d H:i:s')
                                ];
                                
                                if (!isset($staff['trainings'])) {
                                    $staff['trainings'] = [];
                                }
                                
                                $staff['trainings'][] = $trainingData;
                                $staff['last_updated_by'] = $user_id;
                                $staff['date_updated'] = date('Y-m-d H:i:s');
                                
                                if (updateRecord('staff.json', $staffId, $staff)) {
                                    $message = 'Schulung erfolgreich zugewiesen.';
                                } else {
                                    $error = 'Fehler beim Aktualisieren des Mitarbeiterdatensatzes.';
                                }
                            } else {
                                $error = 'Fehler beim Speichern der Schulungsaufzeichnung.';
                            }
                        }
                    }
                }
            } else {
                // Alte Implementierung beibehalten für Abwärtskompatibilität
                if (!$staff) {
                    $error = 'Staff member not found.';
                } else {
                    $trainingData = [
                        'id' => generateUniqueId(),
                        'title' => sanitize($_POST['training_title'] ?? ''),
                        'description' => sanitize($_POST['training_description'] ?? ''),
                        'date' => sanitize($_POST['training_date'] ?? ''),
                        'status' => sanitize($_POST['training_status'] ?? 'Pending'),
                        'added_by' => $user_id,
                        'date_added' => date('Y-m-d H:i:s')
                    ];
                    
                    // Überprüfen der erforderlichen Felder
                    if (empty($trainingData['title'])) {
                        $error = 'Bitte geben Sie einen Schulungstitel an.';
                    } else {
                        if (!isset($staff['trainings'])) {
                            $staff['trainings'] = [];
                        }
                        
                        $staff['trainings'][] = $trainingData;
                        $staff['last_updated_by'] = $user_id;
                        $staff['date_updated'] = date('Y-m-d H:i:s');
                        
                        if (updateRecord('staff.json', $staffId, $staff)) {
                            $message = 'Training added successfully.';
                        } else {
                            $error = 'Failed to add training.';
                        }
                    }
                }
            }
        } elseif ($action === 'update_training' && isset($_POST['staff_id']) && isset($_POST['training_id'])) {
            $staffId = $_POST['staff_id'];
            $trainingId = $_POST['training_id'];
            $staff = findById('staff.json', $staffId);
            
            if (!$staff) {
                $error = 'Staff member not found.';
            } else {
                $found = false;
                
                if (isset($staff['trainings'])) {
                    foreach ($staff['trainings'] as $key => $training) {
                        if ($training['id'] === $trainingId) {
                            $staff['trainings'][$key]['title'] = sanitize($_POST['training_title'] ?? '');
                            $staff['trainings'][$key]['description'] = sanitize($_POST['training_description'] ?? '');
                            $staff['trainings'][$key]['date'] = sanitize($_POST['training_date'] ?? '');
                            $staff['trainings'][$key]['status'] = sanitize($_POST['training_status'] ?? 'Pending');
                            $staff['trainings'][$key]['updated_by'] = $user_id;
                            $staff['trainings'][$key]['date_updated'] = date('Y-m-d H:i:s');
                            $found = true;
                            break;
                        }
                    }
                }
                
                if ($found) {
                    $staff['last_updated_by'] = $user_id;
                    $staff['date_updated'] = date('Y-m-d H:i:s');
                    
                    if (updateRecord('staff.json', $staffId, $staff)) {
                        $message = 'Training updated successfully.';
                    } else {
                        $error = 'Failed to update training.';
                    }
                } else {
                    $error = 'Training not found.';
                }
            }
        } elseif ($action === 'delete_training' && isset($_POST['staff_id']) && isset($_POST['training_id'])) {
            $staffId = $_POST['staff_id'];
            $trainingId = $_POST['training_id'];
            $staff = findById('staff.json', $staffId);
            
            if (!$staff) {
                $error = 'Staff member not found.';
            } else {
                $found = false;
                
                if (isset($staff['trainings'])) {
                    foreach ($staff['trainings'] as $key => $training) {
                        if ($training['id'] === $trainingId) {
                            array_splice($staff['trainings'], $key, 1);
                            $found = true;
                            break;
                        }
                    }
                }
                
                if ($found) {
                    $staff['last_updated_by'] = $user_id;
                    $staff['date_updated'] = date('Y-m-d H:i:s');
                    
                    if (updateRecord('staff.json', $staffId, $staff)) {
                        $message = 'Schulung erfolgreich gelöscht.';
                    } else {
                        $error = 'Fehler beim Löschen der Schulung.';
                    }
                } else {
                    $error = 'Schulung nicht gefunden.';
                }
            }
        } elseif ($action === 'delete_document' && isset($_POST['staff_id']) && isset($_POST['document_id'])) {
            $staffId = $_POST['staff_id'];
            $documentId = $_POST['document_id'];
            $staff = findById('staff.json', $staffId);
            
            if (!$staff) {
                $error = 'Mitarbeiter nicht gefunden.';
            } else {
                $found = false;
                $filePath = null;
                
                if (isset($staff['documents'])) {
                    foreach ($staff['documents'] as $key => $document) {
                        if ($document['id'] === $documentId) {
                            // Speichere den Dateipfad, falls es eine Datei ist, die wir löschen müssen
                            if (isset($document['document_type']) && $document['document_type'] === 'file' && !empty($document['file_path'])) {
                                $filePath = $document['file_path'];
                            }
                            
                            unset($staff['documents'][$key]);
                            $staff['documents'] = array_values($staff['documents']); // Re-index the array
                            $found = true;
                            break;
                        }
                    }
                }
                
                if ($found) {
                    $staff['last_updated_by'] = $user_id;
                    $staff['date_updated'] = date('Y-m-d H:i:s');
                    
                    if (updateRecord('staff.json', $staffId, $staff)) {
                        // Wenn es eine Datei gibt, versuche diese zu löschen
                        if ($filePath) {
                            $fullPath = '../' . ltrim($filePath, '/');
                            if (file_exists($fullPath)) {
                                @unlink($fullPath);
                            }
                        }
                        
                        $message = 'Dokument erfolgreich gelöscht.';
                    } else {
                        $error = 'Fehler beim Löschen des Dokuments.';
                    }
                } else {
                    $error = 'Dokument nicht gefunden.';
                }
            }
        } elseif ($action === 'edit_document' && isset($_POST['staff_id']) && isset($_POST['document_id'])) {
            $staffId = sanitize($_POST['staff_id']);
            $documentId = sanitize($_POST['document_id']);
            $staff = findById('staff.json', $staffId);
            
            if (!$staff) {
                $error = 'Mitarbeiter nicht gefunden.';
            } else {
                $found = false;
                $documentIndex = -1;
                
                if (isset($staff['documents'])) {
                    foreach ($staff['documents'] as $index => $document) {
                        if ($document['id'] === $documentId) {
                            $found = true;
                            $documentIndex = $index;
                            break;
                        }
                    }
                }
                
                if ($found) {
                    $documentType = $staff['documents'][$documentIndex]['document_type'] ?? 'text';
                    
                    // Aktualisiere die allgemeinen Dokumentdaten
                    $staff['documents'][$documentIndex]['title'] = sanitize($_POST['document_title'] ?? '');
                    $staff['documents'][$documentIndex]['description'] = sanitize($_POST['document_description'] ?? '');
                    $staff['documents'][$documentIndex]['last_updated_by'] = $user_id;
                    $staff['documents'][$documentIndex]['date_updated'] = date('Y-m-d H:i:s');
                    
                    $validUpdate = true;
                    $errorMessage = '';
                    
                    // Dokumenttyp-spezifische Updates
                    if ($documentType === 'text') {
                        $content = isset($_POST['document_content']) ? $_POST['document_content'] : '';
                        if (empty($content)) {
                            $validUpdate = false;
                            $errorMessage = 'Bitte geben Sie einen Dokumentinhalt an.';
                        } else {
                            $staff['documents'][$documentIndex]['content'] = sanitize($content);
                        }
                    } elseif ($documentType === 'url') {
                        $url = isset($_POST['document_url']) ? $_POST['document_url'] : '';
                        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                            $validUpdate = false;
                            $errorMessage = 'Bitte geben Sie eine gültige URL an.';
                        } else {
                            $staff['documents'][$documentIndex]['url'] = $url;
                        }
                    }
                    // Bei Dateien machen wir nichts, da wir keine Dateien ersetzen, sondern nur Metadaten aktualisieren
                    
                    if ($validUpdate) {
                        $staff['last_updated_by'] = $user_id;
                        $staff['date_updated'] = date('Y-m-d H:i:s');
                        
                        if (updateRecord('staff.json', $staffId, $staff)) {
                            $message = 'Dokument erfolgreich aktualisiert.';
                        } else {
                            $error = 'Fehler beim Aktualisieren des Dokuments.';
                        }
                    } else {
                        $error = $errorMessage;
                    }
                } else {
                    $error = 'Dokument nicht gefunden.';
                }
            }
        } elseif ($action === 'upload_document' && isset($_POST['staff_id'])) {
            $staffId = $_POST['staff_id'];
            $staff = findById('staff.json', $staffId);
            
            if (!$staff) {
                $error = 'Mitarbeiter nicht gefunden.';
            } else {
                // Dokumenttyp überprüfen
                $documentType = sanitize($_POST['document_type'] ?? 'text');
                
                $documentData = [
                    'id' => generateUniqueId(),
                    'title' => sanitize($_POST['document_title'] ?? ''),
                    'description' => sanitize($_POST['document_description'] ?? ''),
                    'document_type' => $documentType,
                    'uploaded_by' => $user_id,
                    'date_uploaded' => date('Y-m-d H:i:s')
                ];
                
                $validDocument = true;
                $errorMessage = '';
                
                // Dokumenttyp-spezifische Validierung und Verarbeitung
                if ($documentType === 'text') {
                    // Textdokument
                    $content = $_POST['document_content'] ?? '';
                    if (empty($content)) {
                        $validDocument = false;
                        $errorMessage = 'Bitte geben Sie einen Dokumentinhalt an.';
                    } else {
                        $documentData['content'] = sanitize($content);
                    }
                } elseif ($documentType === 'url') {
                    // URL-Dokument
                    $url = $_POST['document_url'] ?? '';
                    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                        $validDocument = false;
                        $errorMessage = 'Bitte geben Sie eine gültige URL an.';
                    } else {
                        $documentData['url'] = $url; // URLs nicht sanitizen, damit sie funktionieren
                    }
                } elseif ($documentType === 'file') {
                    // Datei-Upload
                    if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
                        $validDocument = false;
                        $errorMessage = 'Fehler beim Hochladen der Datei.';
                        if (isset($_FILES['document_file'])) {
                            switch ($_FILES['document_file']['error']) {
                                case UPLOAD_ERR_INI_SIZE:
                                case UPLOAD_ERR_FORM_SIZE:
                                    $errorMessage = 'Die Datei ist zu groß.';
                                    break;
                                case UPLOAD_ERR_NO_FILE:
                                    $errorMessage = 'Keine Datei ausgewählt.';
                                    break;
                            }
                        }
                    } else {
                        $fileInfo = $_FILES['document_file'];
                        $fileName = $fileInfo['name'];
                        $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        
                        // Überprüfe, ob der Dateityp erlaubt ist
                        $allowedTypes = ['pdf', 'png'];
                        if (!in_array($fileType, $allowedTypes)) {
                            $validDocument = false;
                            $errorMessage = 'Nur PDF- und PNG-Dateien sind erlaubt.';
                        } else {
                            // Erstelle einen eindeutigen Dateinamen
                            $newFileName = $documentData['id'] . '_' . preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $fileName);
                            
                            // Stelle sicher, dass der Uploads-Ordner existiert
                            $documentsDir = '../uploads/documents/';
                            if (!is_dir($documentsDir)) {
                                mkdir($documentsDir, 0755, true);
                            }
                            
                            $uploadPath = $documentsDir . $newFileName;
                            
                            // Datei in den Uploads-Ordner verschieben
                            if (move_uploaded_file($fileInfo['tmp_name'], $uploadPath)) {
                                $documentData['file_path'] = '/uploads/documents/' . $newFileName;
                                $documentData['file_type'] = $fileType;
                            } else {
                                $validDocument = false;
                                $errorMessage = 'Fehler beim Speichern der Datei. Pfad: ' . $uploadPath;
                            }
                        }
                    }
                } else {
                    $validDocument = false;
                    $errorMessage = 'Ungültiger Dokumenttyp.';
                }
                
                // Validierung des Titels
                if (empty($documentData['title'])) {
                    $validDocument = false;
                    $errorMessage = 'Bitte geben Sie einen Dokumenttitel an.';
                }
                
                // Dokument speichern, wenn es gültig ist
                if ($validDocument) {
                    if (!isset($staff['documents'])) {
                        $staff['documents'] = [];
                    }
                    
                    $staff['documents'][] = $documentData;
                    $staff['last_updated_by'] = $user_id;
                    $staff['date_updated'] = date('Y-m-d H:i:s');
                    
                    if (updateRecord('staff.json', $staffId, $staff)) {
                        $message = 'Dokument erfolgreich hochgeladen.';
                    } else {
                        $error = 'Fehler beim Speichern des Dokuments.';
                    }
                } else {
                    $error = $errorMessage;
                }
            }
        }
    }
}

// Load staff records
$staffRecords = loadJsonData('staff.json');

// Sort staff by name
usort($staffRecords, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

// Load roles from roles.json for the dropdown
$roles = loadJsonData('roles.json');

// Get selected staff member if ID is provided
$selectedStaff = null;
if (isset($_GET['id'])) {
    $staffId = $_GET['id'];
    $selectedStaff = findById('staff.json', $staffId);
}

// Get training details if training ID is provided
$selectedTraining = null;
if ($selectedStaff && isset($_GET['training_id'])) {
    $trainingId = $_GET['training_id'];
    
    if (isset($selectedStaff['trainings'])) {
        foreach ($selectedStaff['trainings'] as $training) {
            if ($training['id'] === $trainingId) {
                $selectedTraining = $training;
                break;
            }
        }
    }
}
?>

<?php include '../includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Personalverwaltung</h1>
                <div>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addStaffModal">
                        <span data-feather="user-plus"></span> Mitarbeiter hinzufügen
                    </button>
                    <button type="button" class="btn btn-secondary ml-2" data-toggle="modal" data-target="#addRoleModal">
                        <span data-feather="tag"></span> Neue Rolle hinzufügen
                    </button>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h4>Mitarbeiterverzeichnis</h4>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php if (count($staffRecords) > 0): ?>
                                    <?php foreach ($staffRecords as $staff): ?>
                                        <a href="staff.php?id=<?php echo $staff['id']; ?>" class="list-group-item list-group-item-action <?php echo (isset($_GET['id']) && $_GET['id'] === $staff['id']) ? 'active' : ''; ?>">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h5 class="mb-1"><?php echo htmlspecialchars($staff['name']); ?></h5>
                                            </div>
                                            <p class="mb-1"><?php echo htmlspecialchars($staff['role']); ?></p>
                                            <?php if (!empty($staff['tg_number'])): ?>
                                                <small>TG-Nummer: <?php echo htmlspecialchars($staff['tg_number']); ?></small>
                                            <?php endif; ?>
                                        </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="list-group-item">
                                        <p class="text-muted">Keine Mitarbeiter gefunden.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <?php if ($selectedStaff): ?>
                        <!-- Staff Details Card -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h4><?php echo htmlspecialchars($selectedStaff['name']); ?></h4>
                                <div>
                                    <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#editStaffModal">
                                        <span data-feather="edit"></span> Bearbeiten
                                    </button>
                                    <form method="post" action="staff.php" class="d-inline">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="staff_id" value="<?php echo $selectedStaff['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger btn-delete">
                                            <span data-feather="trash-2"></span> Löschen
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Rolle:</strong> <?php echo htmlspecialchars($selectedStaff['role']); ?></p>
                                        <p><strong>TG-Nummer:</strong> <?php echo htmlspecialchars($selectedStaff['tg_number'] ?? 'N/A'); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Hinzugefügt von:</strong> <?php 
                                            $creator = getUserById($selectedStaff['created_by'] ?? '');
                                            echo $creator ? htmlspecialchars($creator['username']) : 'Unbekannt';
                                        ?></p>
                                    </div>
                                </div>
                                
                                <?php if (!empty($selectedStaff['position'])): ?>
                                    <div class="mt-3">
                                        <h5>Position</h5>
                                        <p><?php echo nl2br(htmlspecialchars($selectedStaff['position'])); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Trainings Card -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h4>Schulungsaufzeichnungen</h4>
                                <div>
                                    <a href="trainings.php" class="btn btn-sm btn-secondary mr-2">
                                        <span data-feather="book-open"></span> Schulungsverwaltung
                                    </a>
                                    <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#addTrainingRecordModal">
                                        <span data-feather="plus-circle"></span> Schulung zuweisen
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (isset($selectedStaff['trainings']) && count($selectedStaff['trainings']) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Titel</th>
                                                    <th>Datum</th>
                                                    <th>Status</th>
                                                    <th>Aktionen</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($selectedStaff['trainings'] as $training): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($training['title']); ?></td>
                                                        <td><?php echo !empty($training['date']) ? date('m/d/Y', strtotime($training['date'])) : 'N/A'; ?></td>
                                                        <td>
                                                            <?php 
                                                                $statusClass = 'secondary';
                                                                $status = strtolower($training['status']);
                                                                
                                                                if ($status === 'completed') {
                                                                    $statusClass = 'success';
                                                                } elseif ($status === 'in progress') {
                                                                    $statusClass = 'primary';
                                                                } elseif ($status === 'pending') {
                                                                    $statusClass = 'warning';
                                                                } elseif ($status === 'overdue') {
                                                                    $statusClass = 'danger';
                                                                }
                                                            ?>
                                                            <span class="badge badge-<?php echo $statusClass; ?>"><?php echo htmlspecialchars($training['status']); ?></span>
                                                        </td>
                                                        <td>
                                                            <a href="staff.php?id=<?php echo $selectedStaff['id']; ?>&training_id=<?php echo $training['id']; ?>" class="btn btn-sm btn-secondary">
                                                                <span data-feather="eye"></span> Ansehen
                                                            </a>
                                                            <form method="post" action="staff.php" class="d-inline">
                                                                <input type="hidden" name="action" value="delete_training">
                                                                <input type="hidden" name="staff_id" value="<?php echo $selectedStaff['id']; ?>">
                                                                <input type="hidden" name="training_id" value="<?php echo $training['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-danger btn-delete">
                                                                    <span data-feather="trash-2"></span>
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">Keine Schulungsaufzeichnungen für diesen Mitarbeiter gefunden.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Documents Card -->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h4>Dokumente</h4>
                                <div>
                                    <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#uploadDocumentModal">
                                        <span data-feather="file-plus"></span> Dokument hinzufügen
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (isset($selectedStaff['documents']) && count($selectedStaff['documents']) > 0): ?>
                                    <div class="list-group">
                                        <?php foreach ($selectedStaff['documents'] as $document): ?>
                                            <div class="list-group-item list-group-item-action flex-column align-items-start">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h5 class="mb-1"><?php echo htmlspecialchars($document['title']); ?></h5>
                                                    <small><?php echo date('d.m.Y', strtotime($document['date_uploaded'])); ?></small>
                                                </div>
                                                <?php if (!empty($document['description'])): ?>
                                                    <p class="mb-1"><?php echo htmlspecialchars($document['description']); ?></p>
                                                <?php endif; ?>
                                                <div class="mt-2">
                                                    <button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#viewDocModal_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $document['id']); ?>">
                                                        <span data-feather="file-text"></span> Dokument ansehen
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#editDocModal_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $document['id']); ?>">
                                                        <span data-feather="edit"></span> Bearbeiten
                                                    </button>
                                                    <form method="post" action="staff.php" class="d-inline">
                                                        <input type="hidden" name="action" value="delete_document">
                                                        <input type="hidden" name="staff_id" value="<?php echo $selectedStaff['id']; ?>">
                                                        <input type="hidden" name="document_id" value="<?php echo $document['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger">
                                                            <span data-feather="trash-2"></span> Löschen
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <!-- View Document Modals -->
                                    <?php foreach ($selectedStaff['documents'] as $document): ?>
                                        <div class="modal fade" id="viewDocModal_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $document['id']); ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title"><?php echo htmlspecialchars($document['title']); ?></h5>
                                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                            <span aria-hidden="true">&times;</span>
                                                        </button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <small class="text-muted">
                                                                Hochgeladen am <?php echo date('d.m.Y', strtotime($document['date_uploaded'])); ?>
                                                                <?php 
                                                                    $uploader = getUserById($document['uploaded_by'] ?? '');
                                                                    if ($uploader) {
                                                                        echo ' von ' . htmlspecialchars($uploader['username']);
                                                                    }
                                                                ?>
                                                            </small>
                                                        </div>
                                                        <?php if (!empty($document['description'])): ?>
                                                            <div class="mb-3">
                                                                <strong>Beschreibung:</strong>
                                                                <p><?php echo htmlspecialchars($document['description']); ?></p>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <?php
                                                        // Dokumenttyp bestimmen
                                                        $documentType = $document['document_type'] ?? 'text';
                                                        ?>
                                                        
                                                        <?php if ($documentType === 'text'): ?>
                                                            <div class="card">
                                                                <div class="card-body bg-light">
                                                                    <?php echo nl2br(htmlspecialchars($document['content'] ?? '')); ?>
                                                                </div>
                                                            </div>
                                                        <?php elseif ($documentType === 'url'): ?>
                                                            <div class="alert alert-info">
                                                                <strong>Externer Link:</strong> 
                                                                <a href="<?php echo htmlspecialchars($document['url'] ?? ''); ?>" target="_blank" rel="noopener noreferrer">
                                                                    <?php echo htmlspecialchars($document['url'] ?? ''); ?>
                                                                </a>
                                                            </div>
                                                            <p class="text-muted">
                                                                <small>Klicken Sie auf den Link oben, um das Dokument in einem neuen Tab zu öffnen.</small>
                                                            </p>
                                                        <?php elseif ($documentType === 'file'): ?>
                                                            <?php
                                                            $fileType = $document['file_type'] ?? '';
                                                            $filePath = $document['file_path'] ?? '';
                                                            
                                                            if (strtolower($fileType) === 'pdf'): ?>
                                                                <div class="embed-responsive embed-responsive-16by9">
                                                                    <iframe class="embed-responsive-item" src="<?php echo htmlspecialchars($filePath); ?>" allowfullscreen></iframe>
                                                                </div>
                                                            <?php elseif (strtolower($fileType) === 'png' || strtolower($fileType) === 'jpg' || strtolower($fileType) === 'jpeg'): ?>
                                                                <div class="text-center">
                                                                    <img src="<?php echo htmlspecialchars($filePath); ?>" alt="<?php echo htmlspecialchars($document['title']); ?>" class="img-fluid">
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="alert alert-warning">
                                                                    <strong>Hinweis:</strong> Der Dateityp kann nicht direkt angezeigt werden.
                                                                </div>
                                                            <?php endif; ?>
                                                            
                                                            <div class="mt-3">
                                                                <a href="<?php echo htmlspecialchars($filePath); ?>" class="btn btn-sm btn-primary" download>
                                                                    <span data-feather="download"></span> Datei herunterladen
                                                                </a>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <form method="post" action="staff.php" class="d-inline mr-2">
                                                            <input type="hidden" name="action" value="delete_document">
                                                            <input type="hidden" name="staff_id" value="<?php echo $selectedStaff['id']; ?>">
                                                            <input type="hidden" name="document_id" value="<?php echo $document['id']; ?>">
                                                            <button type="submit" class="btn btn-danger">
                                                                <span data-feather="trash-2"></span> Löschen
                                                            </button>
                                                        </form>
                                                        <button type="button" class="btn btn-primary mr-2" data-dismiss="modal" data-toggle="modal" data-target="#editDocModal_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $document['id']); ?>">
                                                            <span data-feather="edit"></span> Bearbeiten
                                                        </button>
                                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Schließen</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Edit Document Modal -->
                                        <div class="modal fade" id="editDocModal_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $document['id']); ?>" tabindex="-1" aria-labelledby="editDocModalLabel_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $document['id']); ?>" aria-hidden="true">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="editDocModalLabel_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $document['id']); ?>">Dokument bearbeiten</h5>
                                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                            <span aria-hidden="true">&times;</span>
                                                        </button>
                                                    </div>
                                                    <form method="post" action="staff.php">
                                                        <input type="hidden" name="action" value="edit_document">
                                                        <input type="hidden" name="staff_id" value="<?php echo $selectedStaff['id']; ?>">
                                                        <input type="hidden" name="document_id" value="<?php echo htmlspecialchars($document['id']); ?>">
                                                        
                                                        <div class="modal-body">
                                                            <div class="form-group">
                                                                <label for="document_title_<?php echo htmlspecialchars($document['id']); ?>">Dokumenttitel *</label>
                                                                <input type="text" class="form-control" id="document_title_<?php echo htmlspecialchars($document['id']); ?>" name="document_title" value="<?php echo htmlspecialchars($document['title']); ?>" required>
                                                            </div>
                                                            <div class="form-group">
                                                                <label for="document_description_<?php echo htmlspecialchars($document['id']); ?>">Beschreibung</label>
                                                                <textarea class="form-control" id="document_description_<?php echo htmlspecialchars($document['id']); ?>" name="document_description" rows="2"><?php echo htmlspecialchars($document['description'] ?? ''); ?></textarea>
                                                            </div>
                                                            
                                                            <div class="document-type-info alert alert-info">
                                                                <strong>Dokumenttyp:</strong> 
                                                                <?php 
                                                                $documentType = $document['document_type'] ?? 'text';
                                                                if ($documentType === 'text') {
                                                                    echo 'Textdokument';
                                                                } elseif ($documentType === 'url') {
                                                                    echo 'URL / Externer Link';
                                                                } elseif ($documentType === 'file') {
                                                                    echo 'Datei (' . strtoupper($document['file_type'] ?? '') . ')';
                                                                }
                                                                ?>
                                                                <br>
                                                                <small class="text-muted">Um den Dokumenttyp zu ändern, löschen Sie dieses Dokument und erstellen Sie ein neues.</small>
                                                            </div>
                                                            
                                                            <?php if ($documentType === 'text'): ?>
                                                                <div class="form-group">
                                                                    <label for="document_content_<?php echo htmlspecialchars($document['id']); ?>">Dokumentinhalt *</label>
                                                                    <textarea class="form-control" id="document_content_<?php echo htmlspecialchars($document['id']); ?>" name="document_content" rows="10"><?php echo htmlspecialchars($document['content'] ?? ''); ?></textarea>
                                                                </div>
                                                            <?php elseif ($documentType === 'url'): ?>
                                                                <div class="form-group">
                                                                    <label for="document_url_<?php echo htmlspecialchars($document['id']); ?>">URL *</label>
                                                                    <input type="url" class="form-control" id="document_url_<?php echo htmlspecialchars($document['id']); ?>" name="document_url" value="<?php echo htmlspecialchars($document['url'] ?? ''); ?>" placeholder="https://beispiel.de/dokument">
                                                                </div>
                                                            <?php elseif ($documentType === 'file'): ?>
                                                                <div class="mt-4">
                                                                    <div class="alert alert-warning">
                                                                        <strong>Hinweis:</strong> Um die Datei zu ersetzen, löschen Sie dieses Dokument und laden Sie ein neues hoch.
                                                                    </div>
                                                                    
                                                                    <p>
                                                                        <strong>Aktuelle Datei:</strong> 
                                                                        <?php if (isset($document['file_path'])): ?>
                                                                            <a href="<?php echo htmlspecialchars($document['file_path']); ?>" target="_blank">
                                                                                <?php echo htmlspecialchars(basename($document['file_path'])); ?>
                                                                            </a>
                                                                        <?php else: ?>
                                                                            <span class="text-muted">Keine Datei gefunden.</span>
                                                                        <?php endif; ?>
                                                                    </p>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                                                            <button type="submit" class="btn btn-primary">Änderungen speichern</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted">Keine Dokumente für diesen Mitarbeiter gefunden.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card">
                            <div class="card-body">
                                <div class="text-center py-5">
                                    <h3 class="mb-3">Personalverwaltung</h3>
                                    <p class="lead">Wählen Sie einen Mitarbeiter aus dem Verzeichnis aus oder fügen Sie einen neuen Mitarbeiter hinzu, um zu beginnen.</p>
                                    <button type="button" class="btn btn-lg btn-primary mt-3" data-toggle="modal" data-target="#addStaffModal">
                                        <span data-feather="user-plus"></span> Mitarbeiter hinzufügen
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add Staff Modal -->
<div class="modal fade" id="addStaffModal" tabindex="-1" aria-labelledby="addStaffModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addStaffModalLabel">Mitarbeiter hinzufügen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="staff.php">
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="name">Name *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="staff_role">Rolle *</label>
                        <select class="form-control" id="staff_role" name="staff_role" required>
                            <option value="">Rolle auswählen</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo htmlspecialchars($role['name']); ?>">
                                    <?php echo htmlspecialchars($role['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="badge_number">TG-Nummer</label>
                        <input type="text" class="form-control" id="badge_number" name="badge_number">
                    </div>
                    <div class="form-group">
                        <label for="notes">Position</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Mitarbeiter hinzufügen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Staff Modal -->
<?php if ($selectedStaff): ?>
<div class="modal fade" id="editStaffModal" tabindex="-1" aria-labelledby="editStaffModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editStaffModalLabel">Mitarbeiter bearbeiten</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="staff.php">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="staff_id" value="<?php echo $selectedStaff['id']; ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_name">Name *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" value="<?php echo htmlspecialchars($selectedStaff['name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_staff_role">Rolle *</label>
                        <select class="form-control" id="edit_staff_role" name="staff_role" required>
                            <option value="">Rolle auswählen</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo htmlspecialchars($role['name']); ?>" <?php echo ($selectedStaff['role'] === $role['name']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($role['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_badge_number">TG-Nummer</label>
                        <input type="text" class="form-control" id="edit_badge_number" name="badge_number" value="<?php echo htmlspecialchars($selectedStaff['badge_number'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="edit_notes">Position</label>
                        <textarea class="form-control" id="edit_notes" name="notes" rows="3"><?php echo htmlspecialchars($selectedStaff['notes'] ?? ''); ?></textarea>
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

<!-- Schulungszuweisungs-Modal -->
<div class="modal fade" id="addTrainingRecordModal" tabindex="-1" aria-labelledby="addTrainingRecordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addTrainingRecordModalLabel">Schulung zuweisen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="staff.php">
                <input type="hidden" name="action" value="assign_training">
                <input type="hidden" name="staff_id" value="<?php echo $selectedStaff['id']; ?>">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>Hinweis:</strong> Wählen Sie eine Schulung aus dem Schulungskatalog aus, die diesem Mitarbeiter zugewiesen werden soll.
                        <br>
                        <a href="trainings.php" target="_blank">Zum Schulungskatalog, um neue Schulungen zu erstellen</a>
                    </div>
                    
                    <div class="form-group">
                        <label for="training_material_id">Schulungsmaterial *</label>
                        <select class="form-control" id="training_material_id" name="training_material_id" required>
                            <option value="">-- Schulung auswählen --</option>
                            <?php 
                            // Lade alle Schulungsmaterialien
                            $materials = loadJsonData('training_materials.json');
                            $categories = loadJsonData('training_categories.json');
                            
                            // Kategorienamen-Mapping erstellen
                            $categoryNames = [];
                            foreach ($categories as $category) {
                                $categoryNames[$category['id']] = $category['name'];
                            }
                            
                            // Nach Kategorien gruppieren
                            $materialsByCategory = [];
                            foreach ($materials as $material) {
                                $categoryId = $material['category_id'];
                                if (!isset($materialsByCategory[$categoryId])) {
                                    $materialsByCategory[$categoryId] = [];
                                }
                                $materialsByCategory[$categoryId][] = $material;
                            }
                            
                            // Nach Kategorien sortierte Optionen ausgeben
                            foreach ($materialsByCategory as $categoryId => $categoryMaterials) {
                                $categoryName = $categoryNames[$categoryId] ?? 'Sonstige';
                                echo '<optgroup label="' . htmlspecialchars($categoryName) . '">';
                                
                                foreach ($categoryMaterials as $material) {
                                    echo '<option value="' . $material['id'] . '">' . 
                                         htmlspecialchars($material['title']) . '</option>';
                                }
                                
                                echo '</optgroup>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="training_completion_date">Abschlussdatum</label>
                        <input type="date" class="form-control" id="training_completion_date" name="training_completion_date">
                        <small class="form-text text-muted">Lassen Sie dieses Feld leer, wenn die Schulung noch nicht abgeschlossen ist.</small>
                    </div>
                    <div class="form-group">
                        <label for="training_status">Status *</label>
                        <select class="form-control" id="training_status" name="training_status" required>
                            <option value="Ausstehend">Ausstehend</option>
                            <option value="In Bearbeitung">In Bearbeitung</option>
                            <option value="Abgeschlossen">Abgeschlossen</option>
                            <option value="Überfällig">Überfällig</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="training_notes">Anmerkungen</label>
                        <textarea class="form-control" id="training_notes" name="training_notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Schulung zuweisen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Training Modal -->
<?php if ($selectedTraining): ?>
<div class="modal fade" id="editTrainingModal" tabindex="-1" aria-labelledby="editTrainingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editTrainingModalLabel">Schulungsaufzeichnung bearbeiten</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="staff.php">
                <input type="hidden" name="action" value="update_training">
                <input type="hidden" name="staff_id" value="<?php echo $selectedStaff['id']; ?>">
                <input type="hidden" name="training_id" value="<?php echo $selectedTraining['id']; ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_training_title">Schulungstitel *</label>
                        <input type="text" class="form-control" id="edit_training_title" name="training_title" value="<?php echo htmlspecialchars($selectedTraining['title']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_training_description">Beschreibung</label>
                        <textarea class="form-control" id="edit_training_description" name="training_description" rows="3"><?php echo htmlspecialchars($selectedTraining['description'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="edit_training_date">Datum</label>
                        <input type="date" class="form-control" id="edit_training_date" name="training_date" value="<?php echo htmlspecialchars($selectedTraining['date'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="edit_training_status">Status</label>
                        <select class="form-control" id="edit_training_status" name="training_status">
                            <option value="Ausstehend" <?php echo ($selectedTraining['status'] === 'Pending') ? 'selected' : ''; ?>>Ausstehend</option>
                            <option value="In Bearbeitung" <?php echo ($selectedTraining['status'] === 'In Progress') ? 'selected' : ''; ?>>In Bearbeitung</option>
                            <option value="Abgeschlossen" <?php echo ($selectedTraining['status'] === 'Completed') ? 'selected' : ''; ?>>Abgeschlossen</option>
                            <option value="Überfällig" <?php echo ($selectedTraining['status'] === 'Overdue') ? 'selected' : ''; ?>>Überfällig</option>
                        </select>
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
<?php endif; ?>

<!-- Upload Document Modal -->
<div class="modal fade" id="uploadDocumentModal" tabindex="-1" aria-labelledby="uploadDocumentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadDocumentModalLabel">Schulungsdokument hochladen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="staff.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_document">
                <input type="hidden" name="staff_id" value="<?php echo $selectedStaff['id']; ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="document_title">Dokumenttitel *</label>
                        <input type="text" class="form-control" id="document_title" name="document_title" required>
                    </div>
                    <div class="form-group">
                        <label for="document_description">Beschreibung</label>
                        <textarea class="form-control" id="document_description" name="document_description" rows="2"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Dokumenttyp *</label>
                        <div class="custom-control custom-radio">
                            <input type="radio" id="document_type_text" name="document_type" value="text" class="custom-control-input" checked>
                            <label class="custom-control-label" for="document_type_text">Textdokument</label>
                        </div>
                        <div class="custom-control custom-radio">
                            <input type="radio" id="document_type_file" name="document_type" value="file" class="custom-control-input">
                            <label class="custom-control-label" for="document_type_file">Datei (PDF, PNG)</label>
                        </div>
                        <div class="custom-control custom-radio">
                            <input type="radio" id="document_type_url" name="document_type" value="url" class="custom-control-input">
                            <label class="custom-control-label" for="document_type_url">URL / Link</label>
                        </div>
                    </div>
                    
                    <div id="document_content_container" class="form-group">
                        <label for="document_content">Dokumentinhalt *</label>
                        <textarea class="form-control" id="document_content" name="document_content" rows="10"></textarea>
                    </div>
                    
                    <div id="document_file_container" class="form-group d-none">
                        <label for="document_file">Dokument hochladen (PDF, PNG) *</label>
                        <input type="file" class="form-control-file" id="document_file" name="document_file" accept=".pdf,.png">
                        <small class="form-text text-muted">Erlaubte Dateitypen: PDF, PNG</small>
                    </div>
                    
                    <div id="document_url_container" class="form-group d-none">
                        <label for="document_url">Dokument-URL *</label>
                        <input type="url" class="form-control" id="document_url" name="document_url" placeholder="https://">
                        <small class="form-text text-muted">Geben Sie eine vollständige URL ein, die mit http:// oder https:// beginnt</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Dokument hochladen</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- View Training Details Modal -->
<?php if ($selectedTraining): ?>
<div class="modal fade" id="viewTrainingModal" tabindex="-1" aria-labelledby="viewTrainingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewTrainingModalLabel"><?php echo htmlspecialchars($selectedTraining['title']); ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <h6>Schulungsdetails</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Datum:</strong> <?php echo !empty($selectedTraining['date']) ? date('d.m.Y', strtotime($selectedTraining['date'])) : 'N/A'; ?></p>
                            <p><strong>Status:</strong> 
                                <?php 
                                    $statusClass = 'secondary';
                                    $status = strtolower($selectedTraining['status']);
                                    
                                    if ($status === 'completed') {
                                        $statusClass = 'success';
                                    } elseif ($status === 'in progress') {
                                        $statusClass = 'primary';
                                    } elseif ($status === 'pending') {
                                        $statusClass = 'warning';
                                    } elseif ($status === 'overdue') {
                                        $statusClass = 'danger';
                                    }
                                    
                                    // Status in deutsche Übersetzung umwandeln
                                    $statusText = $selectedTraining['status'];
                                    if ($status === 'completed') {
                                        $statusText = 'Abgeschlossen';
                                    } elseif ($status === 'in progress') {
                                        $statusText = 'In Bearbeitung';
                                    } elseif ($status === 'pending') {
                                        $statusText = 'Ausstehend';
                                    } elseif ($status === 'overdue') {
                                        $statusText = 'Überfällig';
                                    }
                                ?>
                                <span class="badge badge-<?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusText); ?></span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Hinzugefügt von:</strong> <?php 
                                $adder = getUserById($selectedTraining['added_by'] ?? '');
                                echo $adder ? htmlspecialchars($adder['username']) : 'Unbekannt';
                            ?></p>
                            <p><strong>Datum hinzugefügt:</strong> <?php echo date('d.m.Y', strtotime($selectedTraining['date_added'])); ?></p>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($selectedTraining['description'])): ?>
                    <div class="mb-3">
                        <h6>Beschreibung</h6>
                        <p><?php echo nl2br(htmlspecialchars($selectedTraining['description'])); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Schließen</button>
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#editTrainingModal">Schulung bearbeiten</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        $('#viewTrainingModal').modal('show');
    });
</script>
<?php endif; ?>

<!-- Add Role Modal -->
<div class="modal fade" id="addRoleModal" tabindex="-1" aria-labelledby="addRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addRoleModalLabel">Neue Rolle hinzufügen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="staff.php" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="create_role">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="role_name">Rollenname *</label>
                        <input type="text" class="form-control" id="role_name" name="role_name" required>
                        <div class="invalid-feedback">Bitte geben Sie einen Rollennamen ein.</div>
                    </div>
                    <div class="form-group">
                        <label for="role_description">Beschreibung</label>
                        <textarea class="form-control" id="role_description" name="role_description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Rolle speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<!-- Bearbeiten Dokument Modals -->
<?php if ($selectedStaff && isset($selectedStaff['documents'])): ?>
    <?php foreach ($selectedStaff['documents'] as $document): ?>
        <div class="modal fade" id="editDocumentModal<?php echo $document['id']; ?>" tabindex="-1" aria-labelledby="editDocumentModalLabel<?php echo $document['id']; ?>" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editDocumentModalLabel<?php echo $document['id']; ?>">Dokument bearbeiten</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form method="post" action="staff.php">
                        <input type="hidden" name="action" value="edit_document">
                        <input type="hidden" name="staff_id" value="<?php echo $selectedStaff['id']; ?>">
                        <input type="hidden" name="document_id" value="<?php echo $document['id']; ?>">
                        
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="document_title_<?php echo $document['id']; ?>">Dokumenttitel *</label>
                                <input type="text" class="form-control" id="document_title_<?php echo $document['id']; ?>" name="document_title" value="<?php echo htmlspecialchars($document['title']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="document_description_<?php echo $document['id']; ?>">Beschreibung</label>
                                <textarea class="form-control" id="document_description_<?php echo $document['id']; ?>" name="document_description" rows="2"><?php echo htmlspecialchars($document['description'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="document-type-info alert alert-info">
                                <strong>Dokumenttyp:</strong> 
                                <?php 
                                $documentType = $document['document_type'] ?? 'text';
                                if ($documentType === 'text') {
                                    echo 'Textdokument';
                                } elseif ($documentType === 'url') {
                                    echo 'URL / Externer Link';
                                } elseif ($documentType === 'file') {
                                    echo 'Datei (' . strtoupper($document['file_type'] ?? '') . ')';
                                }
                                ?>
                                <br>
                                <small class="text-muted">Um den Dokumenttyp zu ändern, löschen Sie dieses Dokument und erstellen Sie ein neues.</small>
                            </div>
                            
                            <?php if ($documentType === 'text'): ?>
                                <div class="form-group">
                                    <label for="document_content_<?php echo $document['id']; ?>">Dokumentinhalt *</label>
                                    <textarea class="form-control" id="document_content_<?php echo $document['id']; ?>" name="document_content" rows="10"><?php echo htmlspecialchars($document['content'] ?? ''); ?></textarea>
                                </div>
                            <?php elseif ($documentType === 'url'): ?>
                                <div class="form-group">
                                    <label for="document_url_<?php echo $document['id']; ?>">URL *</label>
                                    <input type="url" class="form-control" id="document_url_<?php echo $document['id']; ?>" name="document_url" value="<?php echo htmlspecialchars($document['url'] ?? ''); ?>" placeholder="https://beispiel.de/dokument">
                                </div>
                            <?php elseif ($documentType === 'file'): ?>
                                <div class="mt-4">
                                    <div class="alert alert-warning">
                                        <strong>Hinweis:</strong> Um die Datei zu ersetzen, löschen Sie dieses Dokument und laden Sie ein neues hoch.
                                    </div>
                                    
                                    <p>
                                        <strong>Aktuelle Datei:</strong> 
                                        <?php if (isset($document['file_path'])): ?>
                                            <a href="<?php echo htmlspecialchars($document['file_path']); ?>" target="_blank">
                                                <?php echo htmlspecialchars(basename($document['file_path'])); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">Keine Datei gefunden.</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                            <button type="submit" class="btn btn-primary">Änderungen speichern</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<script>
// Dokument-Upload-Formular Typ-Umschalter
document.addEventListener('DOMContentLoaded', function() {
    // Referenzen zu den Formular-Elementen
    const documentTypeRadios = document.querySelectorAll('input[name="document_type"]');
    const contentContainer = document.getElementById('document_content_container');
    const fileContainer = document.getElementById('document_file_container');
    const urlContainer = document.getElementById('document_url_container');
    const contentField = document.getElementById('document_content');
    const fileField = document.getElementById('document_file');
    const urlField = document.getElementById('document_url');
    
    // Event-Listener für Radio-Buttons
    documentTypeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            // Alle Felder ausblenden und required-Attribut entfernen
            contentContainer.classList.add('d-none');
            fileContainer.classList.add('d-none');
            urlContainer.classList.add('d-none');
            contentField.removeAttribute('required');
            fileField.removeAttribute('required');
            urlField.removeAttribute('required');
            
            // Das ausgewählte Feld anzeigen und required-Attribut setzen
            if (this.value === 'text') {
                contentContainer.classList.remove('d-none');
                contentField.setAttribute('required', '');
            } else if (this.value === 'file') {
                fileContainer.classList.remove('d-none');
                fileField.setAttribute('required', '');
            } else if (this.value === 'url') {
                urlContainer.classList.remove('d-none');
                urlField.setAttribute('required', '');
            }
        });
    });
});
</script>
