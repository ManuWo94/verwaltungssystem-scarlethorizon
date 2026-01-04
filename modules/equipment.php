<?php
session_start();
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Berechtigungsprüfung für Ausrüstungsmodule
// Nur Leitungsebene, Director, Commander und Senior Deputy haben Zugriff
checkPermissionAndRedirect('equipment', 'view');

// Konstante für erlaubten Zugriff
define('ACCESS_ALLOWED', true);

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];
$message = '';
$error = '';

// Immer alle Ausrüstungsgegenstände zählen, nicht nur die gefilterten
$allEquipment = loadJsonData('equipment.json');

// Handle equipment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        // Log-Nachrichten für Debugging
        error_log("POST-Action: " . $action);
        if (isset($_POST['type_id'])) {
            error_log("Typ-ID: " . $_POST['type_id']);
        }
        
        if ($action === 'add_equipment') {
            // Überprüfen, ob der Benutzer Berechtigung zum Hinzufügen von Ausrüstung hat
            if (!checkUserPermission($_SESSION['user_id'], 'equipment', 'create')) {
                header('Location: ../access_denied.php');
                exit;
            }
            
            $equipmentData = [
                'id' => generateUniqueId(),
                'type_id' => sanitize($_POST['type_id'] ?? ''),
                'serial_number' => sanitize($_POST['serial_number'] ?? ''),
                'description' => sanitize($_POST['description'] ?? ''),
                'status' => sanitize($_POST['status'] ?? 'Available'),
                'notes' => sanitize($_POST['notes'] ?? ''),
                'date_added' => date('Y-m-d H:i:s'),
                'added_by' => $user_id,
                'assignment_history' => []
            ];
            
            // Validate required fields
            if (empty($equipmentData['type_id'])) {
                $error = 'Please select an equipment type.';
            } else {
                if (insertRecord('equipment.json', $equipmentData)) {
                    $message = 'Equipment added successfully.';
                } else {
                    $error = 'Failed to add equipment.';
                }
            }
        } elseif ($action === 'update_equipment' && isset($_POST['equipment_id'])) {
            // Überprüfen, ob der Benutzer Berechtigung zum Bearbeiten von Ausrüstung hat
            if (!checkUserPermission($_SESSION['user_id'], 'equipment', 'edit')) {
                header('Location: ../access_denied.php');
                exit;
            }
            
            $equipmentId = $_POST['equipment_id'];
            $equipment = findById('equipment.json', $equipmentId);
            
            if (!$equipment) {
                $error = 'Equipment not found.';
            } else {
                $equipmentData = [
                    'id' => $equipmentId,
                    'type_id' => sanitize($_POST['type_id'] ?? ''),
                    'serial_number' => sanitize($_POST['serial_number'] ?? ''),
                    'description' => sanitize($_POST['description'] ?? ''),
                    'status' => sanitize($_POST['status'] ?? 'Available'),
                    'notes' => sanitize($_POST['notes'] ?? ''),
                    'date_added' => $equipment['date_added'],
                    'added_by' => $equipment['added_by'],
                    'assignment_history' => $equipment['assignment_history'] ?? [],
                    'current_assignment' => $equipment['current_assignment'] ?? null
                ];
                
                // Validate required fields
                if (empty($equipmentData['type_id'])) {
                    $error = 'Please select an equipment type.';
                } else {
                    if (updateRecord('equipment.json', $equipmentId, $equipmentData)) {
                        $message = 'Equipment updated successfully.';
                    } else {
                        $error = 'Failed to update equipment.';
                    }
                }
            }
        } elseif ($action === 'delete_equipment' && isset($_POST['equipment_id'])) {
            // Überprüfen, ob der Benutzer Berechtigung zum Löschen von Ausrüstung hat
            if (!checkUserPermission($_SESSION['user_id'], 'equipment', 'delete')) {
                header('Location: ../access_denied.php');
                exit;
            }
            
            $equipmentId = $_POST['equipment_id'];
            
            if (deleteRecord('equipment.json', $equipmentId)) {
                $message = 'Equipment deleted successfully.';
            } else {
                $error = 'Failed to delete equipment.';
            }
        } elseif ($action === 'assign_equipment' && isset($_POST['equipment_id'])) {
            // Überprüfen, ob der Benutzer Berechtigung zum Zuweisen von Ausrüstung hat
            if (!checkUserPermission($_SESSION['user_id'], 'equipment', 'edit')) {
                header('Location: ../access_denied.php');
                exit;
            }
            
            $equipmentId = $_POST['equipment_id'];
            $equipment = findById('equipment.json', $equipmentId);
            
            if (!$equipment) {
                $error = 'Equipment not found.';
            } else {
                $assignmentData = [
                    'id' => generateUniqueId(),
                    'staff_id' => sanitize($_POST['staff_id'] ?? ''),
                    'assigned_by' => $user_id,
                    'assigned_date' => date('Y-m-d H:i:s'),
                    'notes' => sanitize($_POST['assignment_notes'] ?? '')
                ];
                
                // Validate required fields
                if (empty($assignmentData['staff_id'])) {
                    $error = 'Please select a staff member.';
                } else {
                    // Add assignment to history
                    if (!isset($equipment['assignment_history'])) {
                        $equipment['assignment_history'] = [];
                    }
                    
                    $equipment['assignment_history'][] = $assignmentData;
                    $equipment['status'] = 'Assigned';
                    $equipment['current_assignment'] = $assignmentData;
                    
                    if (updateRecord('equipment.json', $equipmentId, $equipment)) {
                        $message = 'Equipment assigned successfully.';
                    } else {
                        $error = 'Failed to assign equipment.';
                    }
                }
            }
        } elseif ($action === 'return_equipment' && isset($_POST['equipment_id'])) {
            // Überprüfen, ob der Benutzer Berechtigung zum Zurückgeben von Ausrüstung hat
            if (!checkUserPermission($_SESSION['user_id'], 'equipment', 'edit')) {
                header('Location: ../access_denied.php');
                exit;
            }
            
            $equipmentId = $_POST['equipment_id'];
            $equipment = findById('equipment.json', $equipmentId);
            
            if (!$equipment) {
                $error = 'Equipment not found.';
            } else {
                if (isset($equipment['current_assignment'])) {
                    // Add return information to current assignment
                    $returnInfo = [
                        'returned_by' => $user_id,
                        'return_date' => date('Y-m-d H:i:s'),
                        'return_notes' => sanitize($_POST['return_notes'] ?? '')
                    ];
                    
                    // Update last assignment in history
                    $lastIndex = count($equipment['assignment_history']) - 1;
                    if ($lastIndex >= 0) {
                        $equipment['assignment_history'][$lastIndex] = array_merge(
                            $equipment['assignment_history'][$lastIndex],
                            $returnInfo
                        );
                    }
                    
                    // Clear current assignment and update status
                    unset($equipment['current_assignment']);
                    $equipment['status'] = 'Available';
                    
                    if (updateRecord('equipment.json', $equipmentId, $equipment)) {
                        $message = 'Equipment returned successfully.';
                    } else {
                        $error = 'Failed to return equipment.';
                    }
                } else {
                    $error = 'This equipment is not currently assigned.';
                }
            }
        } elseif ($action === 'add_equipment_type') {
            // Überprüfen, ob der Benutzer Berechtigung zum Hinzufügen von Ausrüstungstypen hat
            if (!checkUserPermission($_SESSION['user_id'], 'equipment', 'create')) {
                header('Location: ../access_denied.php');
                exit;
            }
            
            $typeData = [
                'id' => generateUniqueId(),
                'name' => sanitize($_POST['type_name'] ?? ''),
                'description' => sanitize($_POST['type_description'] ?? ''),
                'date_created' => date('Y-m-d H:i:s')
            ];
            
            // Validate required fields
            if (empty($typeData['name'])) {
                $error = 'Please enter a type name.';
            } else {
                // Check if type already exists
                $equipmentTypes = loadJsonData('equipment_types.json');
                $typeExists = false;
                
                foreach ($equipmentTypes as $type) {
                    // Prüfe nur auf exakte Übereinstimmung
                    if (strtolower(trim($type['name'])) === strtolower(trim($typeData['name']))) {
                        $typeExists = true;
                        break;
                    }
                }
                
                if ($typeExists) {
                    $error = 'An equipment type with this name already exists.';
                } else {
                    if (insertRecord('equipment_types.json', $typeData)) {
                        $message = 'Equipment type added successfully.';
                    } else {
                        $error = 'Failed to add equipment type.';
                    }
                }
            }
        } elseif ($action === 'edit_equipment_type' && isset($_POST['type_id'])) {
            // Überprüfen, ob der Benutzer Berechtigung zum Bearbeiten von Ausrüstungstypen hat
            if (!checkUserPermission($_SESSION['user_id'], 'equipment', 'edit')) {
                header('Location: ../access_denied.php');
                exit;
            }
            
            $typeId = sanitize($_POST['type_id']);
            error_log("Bearbeite Kategorie: " . $typeId);
            error_log("POST-Daten: " . print_r($_POST, true));
            
            // Validate required fields
            if (empty($_POST['type_name'])) {
                $error = 'Please enter a type name.';
                error_log("Fehler: Kein Name angegeben");
            } else {
                // Check if type already exists with the same name (but different ID)
                $equipmentTypes = loadJsonData('equipment_types.json');
                error_log("Geladene Ausrüstungstypen: " . count($equipmentTypes));
                
                // Debug: Alle Kategorie-IDs ausgeben
                $allTypeIds = array_column($equipmentTypes, 'id');
                error_log("Alle Kategorie-IDs: " . implode(", ", $allTypeIds));
                
                $typeExists = false;
                
                foreach ($equipmentTypes as $type) {
                    // Prüfe nur auf exakte Übereinstimmung
                    if (strtolower(trim($type['name'])) === strtolower(trim($_POST['type_name'])) && $type['id'] !== $typeId) {
                        $typeExists = true;
                        break;
                    }
                }
                
                if ($typeExists) {
                    $error = 'An equipment type with this name already exists.';
                    error_log("Fehler: Name existiert bereits");
                } else {
                    error_log("Suche Kategorie mit ID: " . $typeId);
                    $found = false;
                    
                    // Typenprüfung hinzufügen
                    error_log("Typ der gesuchten ID: " . gettype($typeId));
                    
                    // Direkte Suche in der Liste
                    foreach ($equipmentTypes as $index => $type) {
                        error_log("Prüfe Kategorie ID: " . $type['id'] . " (Typ: " . gettype($type['id']) . ") gegen " . $typeId . " (Typ: " . gettype($typeId) . ")");
                        
                        // Stringvergleich erzwingen
                        if ((string)$type['id'] === (string)$typeId) {
                            error_log("Kategorie gefunden: " . $type['name']);
                            $found = true;
                            
                            // Aktualisiere Felder direkt
                            $equipmentTypes[$index]['name'] = sanitize($_POST['type_name']);
                            $equipmentTypes[$index]['description'] = sanitize($_POST['type_description'] ?? '');
                            $equipmentTypes[$index]['date_updated'] = date('Y-m-d H:i:s');
                            
                            if (saveJsonData('equipment_types.json', $equipmentTypes)) {
                                $message = 'Ausrüstungstyp erfolgreich aktualisiert.';
                                error_log("Kategorie erfolgreich aktualisiert");
                                header("Location: equipment.php");
                                exit;
                            } else {
                                $error = 'Failed to update equipment type.';
                                error_log("Fehler beim Speichern der Kategorie");
                            }
                            break;
                        }
                    }
                    
                    if (!$found) {
                        error_log("Kategorie mit ID " . $typeId . " wurde nicht gefunden, vorhandene IDs: " . implode(", ", array_column($equipmentTypes, 'id')));
                        $error = 'Equipment type not found.';
                    }
                }
            }
        } elseif ($action === 'delete_equipment_type' && isset($_POST['type_id'])) {
            // Überprüfen, ob der Benutzer Berechtigung zum Löschen von Ausrüstungstypen hat
            if (!checkUserPermission($_SESSION['user_id'], 'equipment', 'delete')) {
                header('Location: ../access_denied.php');
                exit;
            }
            
            $typeId = sanitize($_POST['type_id']);
            error_log("Lösche Kategorie: " . $typeId);
            
            // Check if type is in use
            $equipment = loadJsonData('equipment.json');
            $typeIsInUse = false;
            
            foreach ($equipment as $item) {
                if ($item['type_id'] === $typeId) {
                    $typeIsInUse = true;
                    error_log("Kategorie wird verwendet, kann nicht gelöscht werden");
                    break;
                }
            }
            
            if ($typeIsInUse) {
                $error = 'Cannot delete this equipment type because it is in use by one or more equipment items.';
            } else {
                // Direkte Löschung ohne deleteRecord zu verwenden
                $equipmentTypes = loadJsonData('equipment_types.json');
                error_log("Geladene Ausrüstungstypen: " . count($equipmentTypes));
                $found = false;
                
                foreach ($equipmentTypes as $index => $type) {
                    error_log("Prüfe Kategorie ID: " . $type['id'] . " (Typ: " . gettype($type['id']) . ") gegen " . $typeId . " (Typ: " . gettype($typeId) . ")");
                    
                    // Stringvergleich erzwingen
                    if ((string)$type['id'] === (string)$typeId) {
                        error_log("Kategorie gefunden und wird entfernt: " . $type['name']);
                        $found = true;
                        // Entferne die Kategorie
                        unset($equipmentTypes[$index]);
                        // Indizes neu ordnen
                        $equipmentTypes = array_values($equipmentTypes);
                        
                        if (saveJsonData('equipment_types.json', $equipmentTypes)) {
                            $message = 'Ausrüstungstyp erfolgreich gelöscht.';
                            error_log("Kategorie erfolgreich gelöscht");
                            header("Location: equipment.php");
                            exit;
                        } else {
                            $error = 'Failed to delete equipment type.';
                            error_log("Fehler beim Speichern nach dem Löschen");
                        }
                        break;
                    }
                }
                
                if (!$found) {
                    error_log("Zu löschende Kategorie mit ID " . $typeId . " wurde nicht gefunden, vorhandene IDs: " . implode(", ", array_column($equipmentTypes, 'id')));
                    $error = 'Equipment type not found.';
                }
            }
        }
    }
}

// Load equipment types
$equipmentTypes = loadJsonData('equipment_types.json');

// Sort types by name
usort($equipmentTypes, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

// Load equipment
$equipment = loadJsonData('equipment.json');

// Sort equipment by type and serial number
usort($equipment, function($a, $b) use ($equipmentTypes) {
    // Get type names
    $typeA = '';
    $typeB = '';
    
    foreach ($equipmentTypes as $type) {
        if ($type['id'] === $a['type_id']) {
            $typeA = $type['name'];
        }
        if ($type['id'] === $b['type_id']) {
            $typeB = $type['name'];
        }
    }
    
    // Compare by type first
    $typeCompare = strcmp($typeA, $typeB);
    if ($typeCompare !== 0) {
        return $typeCompare;
    }
    
    // If same type, compare by serial number
    return strcmp($a['serial_number'], $b['serial_number']);
});

// Load staff for assignment dropdown
$staff = loadJsonData('staff.json');

// Sort staff by name
usort($staff, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

// Filter by type if provided
$selectedType = $_GET['type'] ?? '';
if (!empty($selectedType)) {
    $equipment = array_filter($equipment, function($item) use ($selectedType) {
        return $item['type_id'] === $selectedType;
    });
}

// Filter by status if provided
$selectedStatus = $_GET['status'] ?? '';
if (!empty($selectedStatus)) {
    $equipment = array_filter($equipment, function($item) use ($selectedStatus) {
        return strtolower($item['status']) === strtolower($selectedStatus);
    });
}
?>

<?php include '../includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Ausrüstungsverwaltung</h1>
                <div>
                    <button type="button" class="btn btn-outline-secondary mr-2" data-toggle="modal" data-target="#addEquipmentTypeModal">
                        <span data-feather="tag"></span> Ausrüstungstyp hinzufügen
                    </button>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addEquipmentModal">
                        <span data-feather="plus"></span> Ausrüstung hinzufügen
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
                <div class="col-md-3">
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Kategorien</h5>
                            <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#addEquipmentTypeModal">
                                <span data-feather="plus"></span> Neu
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush equipment-types">
                                <a href="equipment.php" class="list-group-item list-group-item-action <?php echo empty($selectedType) ? 'active' : ''; ?>">
                                    <strong>Alle Kategorien</strong>
                                    <span class="badge badge-pill badge-light float-right"><?php echo count($allEquipment); ?></span>
                                </a>
                                <?php 
                                // $allEquipment ist bereits am Anfang der Datei deklariert worden
                                
                                // Count equipment items for each type
                                $typeCount = [];
                                
                                foreach ($equipmentTypes as $type) {
                                    $typeCount[$type['id']] = 0;
                                }
                                
                                foreach ($allEquipment as $item) {
                                    if (isset($typeCount[$item['type_id']])) {
                                        $typeCount[$item['type_id']]++;
                                    }
                                }
                                
                                // Zeige alle Typen an, nicht nur diejenigen mit Ausrüstung
                                foreach ($equipmentTypes as $type):
                                ?>
                                    <div class="list-group-item <?php echo $selectedType === $type['id'] ? 'active' : ''; ?>">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <a href="equipment.php?type=<?php echo urlencode($type['id']); ?>" 
                                               class="flex-grow-1 text-decoration-none <?php echo $selectedType === $type['id'] ? 'text-white' : 'text-dark'; ?>">
                                                <?php echo htmlspecialchars($type['name']); ?>
                                                <span class="badge badge-pill badge-light float-right"><?php echo $typeCount[$type['id']] ?? 0; ?></span>
                                            </a>
                                            <div class="ml-2">
                                                <button type="button" class="btn btn-sm btn-link edit-type-btn p-0 mr-2" 
                                                        data-id="<?php echo $type['id']; ?>" 
                                                        data-name="<?php echo htmlspecialchars($type['name']); ?>"
                                                        data-description="<?php echo htmlspecialchars($type['description'] ?? ''); ?>">
                                                    <span data-feather="edit" class="<?php echo $selectedType === $type['id'] ? 'text-white' : 'text-dark'; ?>"></span>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-link delete-type-btn p-0" 
                                                        data-id="<?php echo $type['id']; ?>" 
                                                        data-name="<?php echo htmlspecialchars($type['name']); ?>">
                                                    <span data-feather="trash-2" class="<?php echo $selectedType === $type['id'] ? 'text-white' : 'text-dark'; ?>"></span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php 
                                endforeach; 
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Status Filter</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <a href="equipment.php<?php echo !empty($selectedType) ? '?type=' . urlencode($selectedType) : ''; ?>" 
                                   class="list-group-item list-group-item-action <?php echo empty($selectedStatus) ? 'active' : ''; ?>">
                                    <strong>Alle Status</strong>
                                </a>
                                <?php 
                                // Definieren der Status-Optionen
                                $statuses = ['Available', 'Assigned', 'Maintenance', 'Damaged', 'Retired'];
                                foreach ($statuses as $status):
                                    $statusCount = 0;
                                    foreach ($equipment as $item) {
                                        if (strtolower($item['status']) === strtolower($status)) {
                                            $statusCount++;
                                        }
                                    }
                                    
                                    if ($statusCount > 0):
                                ?>
                                    <a href="equipment.php?status=<?php echo urlencode($status); ?><?php echo !empty($selectedType) ? '&type=' . urlencode($selectedType) : ''; ?>" 
                                       class="list-group-item list-group-item-action <?php echo $selectedStatus === $status ? 'active' : ''; ?>">
                                        <?php 
                                            $statusClass = 'secondary';
                                            $status_lower = strtolower($status);
                                            
                                            if ($status_lower === 'available') {
                                                $statusClass = 'success';
                                            } elseif ($status_lower === 'assigned') {
                                                $statusClass = 'primary';
                                            } elseif ($status_lower === 'maintenance') {
                                                $statusClass = 'warning';
                                            } elseif ($status_lower === 'damaged') {
                                                $statusClass = 'danger';
                                            } elseif ($status_lower === 'retired') {
                                                $statusClass = 'dark';
                                            }
                                        ?>
                                        <span class="badge badge-<?php echo $statusClass; ?> mr-2"><?php echo htmlspecialchars(mapStatusToGerman($status)); ?></span>
                                        <span class="badge badge-pill badge-light float-right"><?php echo $statusCount; ?></span>
                                    </a>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-9">
                    <!-- Ausrüstungsliste anzeigen -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4>
                                <?php if (!empty($selectedType)): 
                                    $typeName = '';
                                    foreach ($equipmentTypes as $type) {
                                        if ($type['id'] === $selectedType) {
                                            $typeName = $type['name'];
                                            break;
                                        }
                                    }
                                ?>
                                    Ausrüstung: <?php echo htmlspecialchars($typeName); ?>
                                <?php elseif (!empty($selectedStatus)): ?>
                                    Ausrüstung: Status <?php echo htmlspecialchars(mapStatusToGerman($selectedStatus)); ?>
                                <?php else: ?>
                                    Alle Ausrüstungsgegenstände
                                <?php endif; ?>
                            </h4>
                            <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#addEquipmentModal">
                                <span data-feather="plus"></span> Neue Ausrüstung
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (count($equipment) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Typ</th>
                                                <th>Seriennummer</th>
                                                <th>Beschreibung</th>
                                                <th>Status</th>
                                                <th>Zuweisung</th>
                                                <th class="text-center">Aktionen</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($equipment as $item): 
                                                $typeName = 'Unbekannt';
                                                $typeDescription = '';
                                                foreach ($equipmentTypes as $type) {
                                                    if ($type['id'] === $item['type_id']) {
                                                        $typeName = $type['name'];
                                                        $typeDescription = $type['description'] ?? '';
                                                        break;
                                                    }
                                                }
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($typeName); ?></td>
                                                <td>
                                                    <span class="font-weight-bold view-equipment-btn" style="cursor: pointer;" 
                                                          data-id="<?php echo $item['id']; ?>"
                                                          data-type="<?php echo htmlspecialchars($typeName); ?>"
                                                          data-serial="<?php echo htmlspecialchars($item['serial_number'] ?? 'N/A'); ?>">
                                                        <?php echo htmlspecialchars($item['serial_number'] ?? 'N/A'); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($item['description'] ?? $typeDescription); ?></td>
                                                <td>
                                                    <?php 
                                                        $statusClass = 'secondary';
                                                        $status = strtolower($item['status']);
                                                        
                                                        if ($status === 'available') {
                                                            $statusClass = 'success';
                                                        } elseif ($status === 'assigned') {
                                                            $statusClass = 'primary';
                                                        } elseif ($status === 'maintenance') {
                                                            $statusClass = 'warning';
                                                        } elseif ($status === 'damaged') {
                                                            $statusClass = 'danger';
                                                        } elseif ($status === 'retired') {
                                                            $statusClass = 'dark';
                                                        }
                                                    ?>
                                                    <span class="badge badge-<?php echo $statusClass; ?>"><?php echo htmlspecialchars(mapStatusToGerman($item['status'])); ?></span>
                                                </td>
                                                <td>
                                                    <?php 
                                                        if (isset($item['current_assignment'])) {
                                                            $staffId = $item['current_assignment']['staff_id'];
                                                            $staffName = 'Unbekannt';
                                                            
                                                            foreach ($staff as $staffMember) {
                                                                if ($staffMember['id'] === $staffId) {
                                                                    $staffName = $staffMember['name'];
                                                                    break;
                                                                }
                                                            }
                                                            
                                                            echo htmlspecialchars($staffName);
                                                        } else {
                                                            echo '-';
                                                        }
                                                    ?>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-outline-primary view-equipment-btn" 
                                                            data-id="<?php echo $item['id']; ?>"
                                                            data-type="<?php echo htmlspecialchars($typeName); ?>"
                                                            data-serial="<?php echo htmlspecialchars($item['serial_number'] ?? 'N/A'); ?>">
                                                        <span data-feather="eye"></span> Details
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <p class="lead">Keine Ausrüstung gefunden.</p>
                                    <p>Fügen Sie neue Ausrüstung hinzu oder ändern Sie die Filterkriterien.</p>
                                    <button type="button" class="btn btn-primary mt-3" data-toggle="modal" data-target="#addEquipmentModal">
                                        <span data-feather="plus"></span> Ausrüstung hinzufügen
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- View Equipment Modal -->
<div class="modal fade" id="viewEquipmentModal" tabindex="-1" aria-labelledby="viewEquipmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewEquipmentModalLabel">Ausrüstungsdetails</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="equipment-loading" class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Laden...</span>
                    </div>
                    <p class="mt-2">Lade Ausrüstungsdaten...</p>
                </div>
                <div id="equipment-details" style="display: none;">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <th>Seriennummer</th>
                                    <td id="view-serial">-</td>
                                </tr>
                                <tr>
                                    <th>Typ</th>
                                    <td id="view-type">-</td>
                                </tr>
                                <tr>
                                    <th>Status</th>
                                    <td id="view-status">-</td>
                                </tr>
                                <tr>
                                    <th>Beschreibung</th>
                                    <td id="view-description">-</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <th>Hinzugefügt am</th>
                                    <td id="view-date-added">-</td>
                                </tr>
                                <tr>
                                    <th>Hinzugefügt von</th>
                                    <td id="view-added-by">-</td>
                                </tr>
                                <tr>
                                    <th>Aktuelle Zuweisung</th>
                                    <td id="view-current-assignment">-</td>
                                </tr>
                                <tr>
                                    <th>Notizen</th>
                                    <td id="view-notes">-</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div id="view-assignment-history-container" style="display: none;">
                        <h5 class="mt-4">Zuweisungsverlauf</h5>
                        <div class="table-responsive">
                            <table class="table table-striped" id="view-assignment-history">
                                <thead>
                                    <tr>
                                        <th>Datum</th>
                                        <th>Zugewiesen an</th>
                                        <th>Zugewiesen von</th>
                                        <th>Notizen</th>
                                        <th>Rückgabe</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Dynamisch gefüllt -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <div>
                    <button type="button" class="btn btn-sm btn-danger" id="delete-equipment-btn">
                        <span data-feather="trash-2"></span> Löschen
                    </button>
                </div>
                <div>
                    <button type="button" class="btn btn-sm btn-primary" id="edit-equipment-btn">
                        <span data-feather="edit"></span> Bearbeiten
                    </button>
                    <button type="button" class="btn btn-sm btn-success" id="assign-equipment-btn" style="display: none;">
                        <span data-feather="user-plus"></span> Zuweisen
                    </button>
                    <button type="button" class="btn btn-sm btn-warning" id="return-equipment-btn" style="display: none;">
                        <span data-feather="user-minus"></span> Zurückgeben
                    </button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Schließen</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Equipment Modal -->
<div class="modal fade" id="editEquipmentModal" tabindex="-1" aria-labelledby="editEquipmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editEquipmentModalLabel">Ausrüstung bearbeiten</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="equipment.php" id="edit-equipment-form">
                <input type="hidden" name="action" value="update_equipment">
                <input type="hidden" name="equipment_id" id="edit_equipment_id" value="">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_type_id">Ausrüstungstyp *</label>
                        <select class="form-control" id="edit_type_id" name="type_id" required>
                            <?php foreach ($equipmentTypes as $type): ?>
                                <option value="<?php echo $type['id']; ?>">
                                    <?php echo htmlspecialchars($type['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_serial_number">Seriennummer</label>
                        <input type="text" class="form-control" id="edit_serial_number" name="serial_number" value="">
                    </div>
                    <div class="form-group">
                        <label for="edit_description">Beschreibung</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="edit_status">Status</label>
                        <select class="form-control" id="edit_status" name="status">
                            <option value="Available">Verfügbar</option>
                            <option value="Assigned">Zugewiesen</option>
                            <option value="Maintenance">Wartung</option>
                            <option value="Damaged">Beschädigt</option>
                            <option value="Retired">Außer Dienst</option>
                        </select>
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

<!-- Assign Equipment Modal -->
<div class="modal fade" id="assignEquipmentModal" tabindex="-1" aria-labelledby="assignEquipmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assignEquipmentModalLabel">Ausrüstung zuweisen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="equipment.php" id="assign-equipment-form">
                <input type="hidden" name="action" value="assign_equipment">
                <input type="hidden" name="equipment_id" id="assign_equipment_id" value="">
                <div class="modal-body">
                    <p>
                        <strong>Ausrüstung:</strong> <span id="assign-equipment-type">-</span><br>
                        <strong>Seriennummer:</strong> <span id="assign-equipment-serial">-</span>
                    </p>
                    <div class="form-group">
                        <label for="staff_id">Mitarbeiter auswählen</label>
                        <select class="form-control" id="staff_id" name="staff_id" required>
                            <option value="">-- Bitte wählen --</option>
                            <?php foreach ($staff as $staffMember): ?>
                                <option value="<?php echo $staffMember['id']; ?>">
                                    <?php echo htmlspecialchars($staffMember['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="assignment_notes">Notizen</label>
                        <textarea class="form-control" id="assignment_notes" name="assignment_notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Zuweisen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Return Equipment Modal -->
<div class="modal fade" id="returnEquipmentModal" tabindex="-1" aria-labelledby="returnEquipmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="returnEquipmentModalLabel">Ausrüstung zurückgeben</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="equipment.php" id="return-equipment-form">
                <input type="hidden" name="action" value="return_equipment">
                <input type="hidden" name="equipment_id" id="return_equipment_id" value="">
                <div class="modal-body">
                    <p>
                        <strong>Ausrüstung:</strong> <span id="return-equipment-type">-</span><br>
                        <strong>Seriennummer:</strong> <span id="return-equipment-serial">-</span><br>
                        <strong>Aktuell zugewiesen an:</strong> <span id="return-equipment-assignment">-</span>
                    </p>
                    <div class="form-group">
                        <label for="return_notes">Notizen zur Rückgabe</label>
                        <textarea class="form-control" id="return_notes" name="return_notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-warning">Zurückgeben</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Equipment Type Modal -->
<div class="modal fade" id="addEquipmentTypeModal" tabindex="-1" aria-labelledby="addEquipmentTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addEquipmentTypeModalLabel">Neuen Ausrüstungstyp hinzufügen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="equipment.php">
                <input type="hidden" name="action" value="add_equipment_type">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="type_name">Name *</label>
                        <input type="text" class="form-control" id="type_name" name="type_name" required>
                    </div>
                    <div class="form-group">
                        <label for="type_description">Beschreibung</label>
                        <textarea class="form-control" id="type_description" name="type_description" rows="3"></textarea>
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

<!-- Add Equipment Modal -->
<div class="modal fade" id="addEquipmentModal" tabindex="-1" aria-labelledby="addEquipmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addEquipmentModalLabel">Neue Ausrüstung hinzufügen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="equipment.php">
                <input type="hidden" name="action" value="add_equipment">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="type_id">Ausrüstungstyp *</label>
                        <select class="form-control" id="type_id" name="type_id" required>
                            <option value="">-- Bitte wählen --</option>
                            <?php foreach ($equipmentTypes as $type): ?>
                                <option value="<?php echo $type['id']; ?>" <?php echo isset($_GET['type']) && $_GET['type'] === $type['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (count($equipmentTypes) === 0): ?>
                            <small class="form-text text-muted">
                                <a href="#" data-toggle="modal" data-target="#addEquipmentTypeModal">Erst Ausrüstungstyp erstellen</a>
                            </small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="serial_number">Seriennummer</label>
                        <input type="text" class="form-control" id="serial_number" name="serial_number">
                    </div>
                    <div class="form-group">
                        <label for="description">Beschreibung</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select class="form-control" id="status" name="status">
                            <option value="Available">Verfügbar</option>
                            <option value="Maintenance">Wartung</option>
                            <option value="Damaged">Beschädigt</option>
                            <option value="Retired">Außer Dienst</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="notes">Notizen</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
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

<!-- Delete Equipment Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteConfirmModalLabel">Ausrüstung löschen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Sind Sie sicher, dass Sie diese Ausrüstung löschen möchten?</p>
                <p class="font-weight-bold" id="delete-confirm-details"></p>
                <p class="text-danger">Diese Aktion kann nicht rückgängig gemacht werden.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                <form method="post" action="equipment.php" id="delete-equipment-form">
                    <input type="hidden" name="action" value="delete_equipment">
                    <input type="hidden" name="equipment_id" id="delete_equipment_id" value="">
                    <button type="submit" class="btn btn-danger">Löschen</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Equipment Type Modal -->
<div class="modal fade" id="editEquipmentTypeModal" tabindex="-1" aria-labelledby="editEquipmentTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editEquipmentTypeModalLabel">Ausrüstungstyp bearbeiten</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="equipment.php" id="edit-equipment-type-form">
                <input type="hidden" name="action" value="edit_equipment_type">
                <input type="hidden" name="type_id" id="edit_type_id" value="">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_type_name">Name *</label>
                        <input type="text" class="form-control" id="edit_type_name" name="type_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_type_description">Beschreibung</label>
                        <textarea class="form-control" id="edit_type_description" name="type_description" rows="3"></textarea>
                    </div>
                    <div class="text-muted small" id="debug-type-id"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Type Confirmation Modal -->
<div class="modal fade" id="deleteTypeConfirmModal" tabindex="-1" aria-labelledby="deleteTypeConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteTypeConfirmModalLabel">Ausrüstungstyp löschen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Sind Sie sicher, dass Sie diesen Ausrüstungstyp löschen möchten?</p>
                <p class="font-weight-bold" id="type-delete-confirm-details"></p>
                <p class="text-danger">Diese Aktion kann nicht rückgängig gemacht werden. Ausrüstungstypen können nur gelöscht werden, wenn keine Ausrüstung diesem Typ zugeordnet ist.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                <form method="post" action="equipment.php">
                    <input type="hidden" name="action" value="delete_equipment_type">
                    <input type="hidden" name="type_id" id="delete_type_id" value="">
                    <button type="submit" class="btn btn-danger">Löschen</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Variablen für aktuelles Equipment
    let currentEquipmentId = null;
    let currentEquipmentType = null;
    let currentEquipmentSerial = null;
    let currentEquipmentStatus = null;
    let currentAssignmentStaff = null;
    
    // Event-Handler für View-Button - delegiertes Event-Handling für bessere Kompatibilität
    document.addEventListener('click', function(event) {
        // Prüfen, ob das geklickte Element oder ein Elternelement die Klasse 'view-equipment-btn' hat
        const viewButton = event.target.closest('.view-equipment-btn');
        
        if (viewButton) {
            const equipmentId = viewButton.dataset.id;
            const equipmentType = viewButton.dataset.type;
            const equipmentSerial = viewButton.dataset.serial;
            
            if (!equipmentId) {
                console.error('Keine Equipment-ID gefunden', viewButton);
                return;
            }
            
            currentEquipmentId = equipmentId;
            currentEquipmentType = equipmentType;
            currentEquipmentSerial = equipmentSerial;
            
            // Modal-Titel setzen
            document.getElementById('viewEquipmentModalLabel').textContent = 
                equipmentType + ' - ' + equipmentSerial;
            
            // Lade-Animation anzeigen
            document.getElementById('equipment-loading').style.display = 'block';
            document.getElementById('equipment-details').style.display = 'none';
            
            // Ausrüstungsdaten laden
            loadEquipmentDetails(equipmentId);
            
            // Modal öffnen
            $('#viewEquipmentModal').modal('show');
        }
    });
    
    // Funktion zum Laden der Ausrüstungsdetails
    function loadEquipmentDetails(equipmentId) {
        // In einer echten Anwendung würde hier ein AJAX-Request stehen
        // Da wir keine AJAX verwenden, suchen wir die Daten direkt in der Seite
        const allEquipmentData = <?php echo json_encode($allEquipment); ?>;
        const staffData = <?php echo json_encode($staff); ?>;
        const typesData = <?php echo json_encode($equipmentTypes); ?>;
        const usersData = <?php echo json_encode(loadJsonData('users.json')); ?>;
        
        let equipment = null;
        // Wir durchsuchen alle Ausrüstungsgegenstände, nicht nur die gefilterten
        for (const item of allEquipmentData) {
            if (item.id === equipmentId) {
                equipment = item;
                break;
            }
        }
        
        if (!equipment) {
            alert('Ausrüstung nicht gefunden!');
            $('#viewEquipmentModal').modal('hide');
            return;
        }
        
        // Status merken für spätere Verwendung
        currentEquipmentStatus = equipment.status;
        
        // Ausrüstungsdaten anzeigen
        document.getElementById('view-serial').textContent = equipment.serial_number || 'N/A';
        
        // Typ-Name finden
        let typeName = 'Unbekannt';
        for (const type of typesData) {
            if (type.id === equipment.type_id) {
                typeName = type.name;
                break;
            }
        }
        document.getElementById('view-type').textContent = typeName;
        
        // Status mit Badge anzeigen
        let statusHTML = '';
        const status = equipment.status.toLowerCase();
        let statusClass = 'secondary';
        
        if (status === 'available') {
            statusClass = 'success';
        } else if (status === 'assigned') {
            statusClass = 'primary';
        } else if (status === 'maintenance') {
            statusClass = 'warning';
        } else if (status === 'damaged') {
            statusClass = 'danger';
        } else if (status === 'retired') {
            statusClass = 'dark';
        }
        
        statusHTML = `<span class="badge badge-${statusClass}">${mapStatusToGerman(equipment.status)}</span>`;
        document.getElementById('view-status').innerHTML = statusHTML;
        
        // Beschreibung
        document.getElementById('view-description').innerHTML = 
            equipment.description ? equipment.description.replace(/\n/g, '<br>') : '-';
        
        // Hinzugefügt am
        document.getElementById('view-date-added').textContent = 
            formatDateTime(equipment.date_added);
        
        // Hinzugefügt von
        let addedByName = 'Unbekannt';
        // Zuerst in Benutzern suchen
        for (const user of usersData) {
            if (user.id === equipment.added_by) {
                addedByName = user.username;
                break;
            }
        }
        // Falls nicht gefunden, in Mitarbeitern suchen
        if (addedByName === 'Unbekannt') {
            for (const member of staffData) {
                if (member.id === equipment.added_by) {
                    addedByName = member.name;
                    break;
                }
            }
        }
        document.getElementById('view-added-by').textContent = addedByName;
        
        // Aktuelle Zuweisung
        if (equipment.current_assignment) {
            let staffName = 'Unbekannt';
            for (const member of staffData) {
                if (member.id === equipment.current_assignment.staff_id) {
                    staffName = member.name;
                    break;
                }
            }
            currentAssignmentStaff = staffName;
            
            document.getElementById('view-current-assignment').innerHTML = 
                `${staffName}<br><small class="text-muted">Seit: ${formatDateTime(equipment.current_assignment.assigned_date)}</small>`;
        } else {
            document.getElementById('view-current-assignment').textContent = '-';
        }
        
        // Notizen
        document.getElementById('view-notes').innerHTML = 
            equipment.notes ? equipment.notes.replace(/\n/g, '<br>') : '-';
        
        // Zuweisungsverlauf
        if (equipment.assignment_history && equipment.assignment_history.length > 0) {
            document.getElementById('view-assignment-history-container').style.display = 'block';
            
            const historyTable = document.getElementById('view-assignment-history').querySelector('tbody');
            historyTable.innerHTML = '';
            
            // Sortiere nach Datum absteigend
            const sortedHistory = [...equipment.assignment_history].sort((a, b) => {
                return new Date(b.assigned_date) - new Date(a.assigned_date);
            });
            
            for (const record of sortedHistory) {
                const row = document.createElement('tr');
                
                // Datum
                const dateCell = document.createElement('td');
                dateCell.textContent = formatDateTime(record.assigned_date);
                row.appendChild(dateCell);
                
                // Zugewiesen an
                const assignedToCell = document.createElement('td');
                let assignedToName = 'Unbekannt';
                for (const member of staffData) {
                    if (member.id === record.staff_id) {
                        assignedToName = member.name;
                        break;
                    }
                }
                assignedToCell.textContent = assignedToName;
                row.appendChild(assignedToCell);
                
                // Zugewiesen von
                const assignedByCell = document.createElement('td');
                let assignedByName = 'Unbekannt';
                // Zuerst in Benutzern suchen
                for (const user of usersData) {
                    if (user.id === record.assigned_by) {
                        assignedByName = user.username;
                        break;
                    }
                }
                // Falls nicht gefunden, in Mitarbeitern suchen
                if (assignedByName === 'Unbekannt') {
                    for (const member of staffData) {
                        if (member.id === record.assigned_by) {
                            assignedByName = member.name;
                            break;
                        }
                    }
                }
                assignedByCell.textContent = assignedByName;
                row.appendChild(assignedByCell);
                
                // Notizen
                const notesCell = document.createElement('td');
                notesCell.innerHTML = record.notes ? record.notes.replace(/\n/g, '<br>') : '-';
                row.appendChild(notesCell);
                
                // Rückgabe
                const returnCell = document.createElement('td');
                if (record.return_date) {
                    returnCell.innerHTML = `${formatDateTime(record.return_date)}`;
                    if (record.return_notes) {
                        returnCell.innerHTML += `<br><small class="text-muted">${record.return_notes}</small>`;
                    }
                } else {
                    returnCell.textContent = '-';
                }
                row.appendChild(returnCell);
                
                historyTable.appendChild(row);
            }
        } else {
            document.getElementById('view-assignment-history-container').style.display = 'none';
        }
        
        // Aktionen-Buttons entsprechend dem Status ein-/ausblenden
        const assignButton = document.getElementById('assign-equipment-btn');
        const returnButton = document.getElementById('return-equipment-btn');
        
        if (equipment.status === 'Available') {
            assignButton.style.display = 'inline-block';
            returnButton.style.display = 'none';
        } else if (equipment.status === 'Assigned') {
            assignButton.style.display = 'none';
            returnButton.style.display = 'inline-block';
        } else {
            assignButton.style.display = 'none';
            returnButton.style.display = 'none';
        }
        
        // Lade-Animation ausblenden und Details anzeigen
        document.getElementById('equipment-loading').style.display = 'none';
        document.getElementById('equipment-details').style.display = 'block';
        
        // Icons neu laden
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    }
    
    // Event-Handler für Bearbeiten-Button im View-Modal
    document.getElementById('edit-equipment-btn').addEventListener('click', function() {
        if (!currentEquipmentId) return;
        
        // In einer echten Anwendung würde hier ein AJAX-Request stehen
        const equipmentData = <?php echo json_encode($equipment); ?>;
        
        let equipment = null;
        for (const item of equipmentData) {
            if (item.id === currentEquipmentId) {
                equipment = item;
                break;
            }
        }
        
        if (!equipment) {
            alert('Ausrüstung nicht gefunden!');
            return;
        }
        
        // Formular füllen
        document.getElementById('edit_equipment_id').value = equipment.id;
        document.getElementById('edit_type_id').value = equipment.type_id;
        document.getElementById('edit_serial_number').value = equipment.serial_number || '';
        document.getElementById('edit_description').value = equipment.description || '';
        document.getElementById('edit_status').value = equipment.status;
        document.getElementById('edit_notes').value = equipment.notes || '';
        
        // View-Modal schließen und Edit-Modal öffnen
        $('#viewEquipmentModal').modal('hide');
        $('#editEquipmentModal').modal('show');
    });
    
    // Event-Handler für Zuweisen-Button im View-Modal
    document.getElementById('assign-equipment-btn').addEventListener('click', function() {
        if (!currentEquipmentId || !currentEquipmentType || !currentEquipmentSerial) return;
        
        // Formular füllen
        document.getElementById('assign_equipment_id').value = currentEquipmentId;
        document.getElementById('assign-equipment-type').textContent = currentEquipmentType;
        document.getElementById('assign-equipment-serial').textContent = currentEquipmentSerial;
        
        // View-Modal schließen und Assign-Modal öffnen
        $('#viewEquipmentModal').modal('hide');
        $('#assignEquipmentModal').modal('show');
    });
    
    // Event-Handler für Zurückgeben-Button im View-Modal
    document.getElementById('return-equipment-btn').addEventListener('click', function() {
        if (!currentEquipmentId || !currentEquipmentType || !currentEquipmentSerial) return;
        
        // Formular füllen
        document.getElementById('return_equipment_id').value = currentEquipmentId;
        document.getElementById('return-equipment-type').textContent = currentEquipmentType;
        document.getElementById('return-equipment-serial').textContent = currentEquipmentSerial;
        document.getElementById('return-equipment-assignment').textContent = currentAssignmentStaff || 'Unbekannt';
        
        // View-Modal schließen und Return-Modal öffnen
        $('#viewEquipmentModal').modal('hide');
        $('#returnEquipmentModal').modal('show');
    });
    
    // Event-Handler für Löschen-Button im View-Modal
    document.getElementById('delete-equipment-btn').addEventListener('click', function() {
        if (!currentEquipmentId || !currentEquipmentType || !currentEquipmentSerial) return;
        
        // Formular füllen
        document.getElementById('delete_equipment_id').value = currentEquipmentId;
        document.getElementById('delete-confirm-details').textContent = 
            `${currentEquipmentType} - ${currentEquipmentSerial}`;
        
        // View-Modal schließen und Delete-Modal öffnen
        $('#viewEquipmentModal').modal('hide');
        $('#deleteConfirmModal').modal('show');
    });
    
    // Hilfsfunktion zum Formatieren eines Datums
    function formatDateTime(dateTimeStr) {
        if (!dateTimeStr) return '-';
        
        const date = new Date(dateTimeStr);
        if (isNaN(date.getTime())) return dateTimeStr;
        
        return date.toLocaleDateString('de-DE') + ' ' + 
               date.toLocaleTimeString('de-DE', {hour: '2-digit', minute:'2-digit'});
    }
    
    // Hilfsfunktion zur Übersetzung der Status-Werte
    function mapStatusToGerman(status) {
        const statusMap = {
            'Available': 'Verfügbar',
            'Assigned': 'Zugewiesen',
            'Maintenance': 'Wartung',
            'Damaged': 'Beschädigt',
            'Retired': 'Außer Dienst'
        };
        
        return statusMap[status] || status;
    }
    
    // Event-Handler für den Bearbeiten-Button der Ausrüstungstypen - delegiertes Event-Handling
    document.addEventListener('click', function(e) {
        const editTypeBtn = e.target.closest('.edit-type-btn');
        if (editTypeBtn) {
            e.preventDefault();
            e.stopPropagation();
            
            // Direkt vom Attribut abrufen, nicht über dataset
            const typeId = editTypeBtn.getAttribute('data-id');
            const typeName = editTypeBtn.getAttribute('data-name');
            const typeDescription = editTypeBtn.getAttribute('data-description') || '';
            
            console.log('Bearbeite Kategorie:', typeId, typeName, typeDescription);
            
            // Debug-Ausgaben für die Fehlerbehebung
            console.log('Button data-id:', editTypeBtn.getAttribute('data-id'));
            console.log('Button data-name:', editTypeBtn.getAttribute('data-name'));
            console.log('Button data-description:', editTypeBtn.getAttribute('data-description'));
            
            // Formular füllen
            const editTypeIdField = document.getElementById('edit_type_id');
            editTypeIdField.value = typeId;
            console.log('Formularfeld type_id gesetzt auf:', editTypeIdField.value);
            
            document.getElementById('edit_type_name').value = typeName;
            document.getElementById('edit_type_description').value = typeDescription;
            document.getElementById('debug-type-id').textContent = 'ID: ' + typeId;
            
            // Modal öffnen
            $('#editEquipmentTypeModal').modal('show');
            
            // Überprüfen nach dem Öffnen des Modals
            setTimeout(function() {
                console.log('Nach Timeout - Formularfeld type_id Wert:', document.getElementById('edit_type_id').value);
                
                // Sicherheitshalber nochmal setzen
                document.getElementById('edit_type_id').value = typeId;
                
                // Form-Submissions-Handler hinzufügen
                const form = document.getElementById('edit-equipment-type-form');
                form.onsubmit = function(event) {
                    // Prüfen ob die ID richtig gesetzt ist
                    if (!document.getElementById('edit_type_id').value) {
                        console.error('FEHLER: type_id fehlt beim Formular-Submit!');
                        document.getElementById('edit_type_id').value = typeId;
                    }
                };
            }, 500);
        }
    });
    
    // Event-Handler für den Löschen-Button der Ausrüstungstypen - delegiertes Event-Handling
    document.addEventListener('click', function(e) {
        const deleteTypeBtn = e.target.closest('.delete-type-btn');
        if (deleteTypeBtn) {
            e.preventDefault();
            e.stopPropagation();
            
            // Direkt vom Attribut abrufen, nicht über dataset
            const typeId = deleteTypeBtn.getAttribute('data-id');
            const typeName = deleteTypeBtn.getAttribute('data-name');
            
            console.log('Lösche Kategorie:', typeId, typeName);
            
            // Formular füllen
            document.getElementById('delete_type_id').value = typeId;
            document.getElementById('type-delete-confirm-details').textContent = typeName;
            
            // Modal öffnen
            $('#deleteTypeConfirmModal').modal('show');
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>