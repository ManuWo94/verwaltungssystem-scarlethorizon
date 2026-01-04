<?php
session_start();
require_once 'auth.php';
require_once 'functions.php';
require_once 'db.php';

// Überprüfen, ob der Benutzer angemeldet ist
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Nicht autorisiert']);
    exit;
}

$action = $_GET['action'] ?? '';
$response = ['success' => false, 'message' => 'Ungültige Anfrage'];

// Handler für verschiedene AJAX-Aktionen
switch ($action) {
    case 'get_training_material':
        if (isset($_GET['id'])) {
            $materialId = sanitize($_GET['id']);
            $materials = loadJsonData('training_materials.json');
            $found = false;
            
            foreach ($materials as $material) {
                if ($material['id'] === $materialId) {
                    $found = true;
                    
                    // Kategoriename abrufen
                    $categories = loadJsonData('training_categories.json');
                    $categoryName = 'Unbekannt';
                    
                    foreach ($categories as $category) {
                        if ($category['id'] === $material['category_id']) {
                            $categoryName = $category['name'];
                            break;
                        }
                    }
                    
                    // Benutzernamen abrufen
                    $users = loadJsonData('users.json');
                    $createdByName = 'Unbekannt';
                    
                    foreach ($users as $user) {
                        if ($user['id'] === $material['created_by']) {
                            $createdByName = $user['username'];
                            break;
                        }
                    }
                    
                    // Material-Daten mit zusätzlichen Informationen
                    $materialData = $material;
                    $materialData['category_name'] = $categoryName;
                    $materialData['created_by_name'] = $createdByName;
                    
                    $response = [
                        'success' => true,
                        'data' => $materialData
                    ];
                    break;
                }
            }
            
            if (!$found) {
                $response = [
                    'success' => false,
                    'message' => 'Schulungsmaterial nicht gefunden'
                ];
            }
        } else {
            $response = [
                'success' => false,
                'message' => 'Material-ID nicht angegeben'
            ];
        }
        break;
        
    case 'get_equipment_details':
        if (isset($_GET['id'])) {
            $equipmentId = sanitize($_GET['id']);
            $equipment = loadJsonData('equipment.json');
            $found = false;
            
            foreach ($equipment as $item) {
                if ($item['id'] === $equipmentId) {
                    $found = true;
                    
                    // Kategorienamen abrufen
                    $equipmentTypes = loadJsonData('equipment_types.json');
                    $typeName = 'Unbekannt';
                    
                    foreach ($equipmentTypes as $type) {
                        if ($type['id'] === $item['type_id']) {
                            $typeName = $type['name'];
                            break;
                        }
                    }
                    
                    // Aktuellen Benutzer (falls zugewiesen) abrufen
                    $staff = loadJsonData('staff.json');
                    $currentAssignment = null;
                    
                    if (isset($item['current_assignment'])) {
                        $staffId = $item['current_assignment']['staff_id'];
                        $staffName = 'Unbekannt';
                        
                        foreach ($staff as $staffMember) {
                            if ($staffMember['id'] === $staffId) {
                                $staffName = $staffMember['name'];
                                break;
                            }
                        }
                        
                        $currentAssignment = [
                            'staff_id' => $staffId,
                            'staff_name' => $staffName,
                            'date_assigned' => $item['current_assignment']['date_assigned'],
                            'assigned_by' => $item['current_assignment']['assigned_by'],
                            'notes' => $item['current_assignment']['notes'] ?? ''
                        ];
                    }
                    
                    // Benutzer abrufen (von hinzugefügt)
                    $users = loadJsonData('users.json');
                    $addedByName = 'Unbekannt';
                    
                    foreach ($users as $user) {
                        if ($user['id'] === $item['added_by']) {
                            $addedByName = $user['username'];
                            break;
                        }
                    }
                    
                    // Zuweisungsverlauf mit Benutzernamen anreichern
                    $assignmentHistory = [];
                    
                    if (isset($item['assignment_history']) && is_array($item['assignment_history'])) {
                        foreach ($item['assignment_history'] as $assignment) {
                            $staffName = 'Unbekannt';
                            $assignedByName = 'Unbekannt';
                            
                            foreach ($staff as $staffMember) {
                                if ($staffMember['id'] === $assignment['staff_id']) {
                                    $staffName = $staffMember['name'];
                                    break;
                                }
                            }
                            
                            foreach ($users as $user) {
                                if ($user['id'] === $assignment['assigned_by']) {
                                    $assignedByName = $user['username'];
                                    break;
                                }
                            }
                            
                            $assignmentHistory[] = [
                                'staff_id' => $assignment['staff_id'],
                                'staff_name' => $staffName,
                                'date_assigned' => $assignment['date_assigned'],
                                'date_returned' => $assignment['date_returned'] ?? null,
                                'assigned_by' => $assignment['assigned_by'],
                                'assigned_by_name' => $assignedByName,
                                'notes' => $assignment['notes'] ?? ''
                            ];
                        }
                    }
                    
                    // Ausrüstungsdaten mit zusätzlichen Informationen
                    $equipmentData = [
                        'id' => $item['id'],
                        'serial_number' => $item['serial_number'],
                        'type_id' => $item['type_id'],
                        'type_name' => $typeName,
                        'status' => $item['status'],
                        'description' => $item['description'] ?? '',
                        'notes' => $item['notes'] ?? '',
                        'date_added' => $item['date_added'],
                        'added_by' => $item['added_by'],
                        'added_by_name' => $addedByName,
                        'current_assignment' => $currentAssignment,
                        'assignment_history' => $assignmentHistory
                    ];
                    
                    $response = [
                        'success' => true,
                        'data' => $equipmentData
                    ];
                    break;
                }
            }
            
            if (!$found) {
                $response = [
                    'success' => false,
                    'message' => 'Ausrüstung nicht gefunden'
                ];
            }
        } else {
            $response = [
                'success' => false,
                'message' => 'Ausrüstungs-ID nicht angegeben'
            ];
        }
        break;
        
    // Ansehen eines Dokuments über AJAX
    case 'view_document':
        if (isset($_GET['id']) && isset($_GET['staff_id'])) {
            $documentId = sanitize($_GET['id']);
            $staffId = sanitize($_GET['staff_id']);
            $staff = loadJsonData('staff.json');
            $document = null;
            
            // Mitarbeiter und Dokument finden
            foreach ($staff as $staffMember) {
                if ($staffMember['id'] === $staffId && isset($staffMember['documents'])) {
                    foreach ($staffMember['documents'] as $doc) {
                        if ($doc['id'] === $documentId) {
                            $document = $doc;
                            break 2; // Aus beiden Schleifen ausbrechen
                        }
                    }
                }
            }
            
            if ($document) {
                // Je nach Dokumenttyp unterschiedliche Antwort zurückgeben
                $response = [
                    'success' => true,
                    'data' => [
                        'id' => $document['id'],
                        'title' => $document['title'],
                        'description' => $document['description'] ?? '',
                        'date_uploaded' => $document['date_uploaded'],
                        'document_type' => $document['document_type'] ?? 'text'
                    ]
                ];
                
                // Abhängig vom Dokumenttyp den entsprechenden Inhalt hinzufügen
                $documentType = $document['document_type'] ?? 'text';
                
                if ($documentType === 'text') {
                    $response['data']['content'] = $document['content'] ?? '';
                } elseif ($documentType === 'url') {
                    $response['data']['url'] = $document['url'] ?? '';
                } elseif ($documentType === 'file') {
                    $response['data']['file_path'] = $document['file_path'] ?? '';
                    $response['data']['file_type'] = $document['file_type'] ?? '';
                }
            } else {
                $response = [
                    'success' => false,
                    'message' => 'Dokument nicht gefunden'
                ];
            }
        } else {
            $response = [
                'success' => false,
                'message' => 'Dokument-ID oder Mitarbeiter-ID nicht angegeben'
            ];
        }
        break;
        
    // Weitere AJAX-Handler hier hinzufügen
        
    default:
        $response = [
            'success' => false,
            'message' => 'Unbekannte Aktion'
        ];
        break;
}

// Antwort als JSON zurückgeben
header('Content-Type: application/json');
echo json_encode($response);