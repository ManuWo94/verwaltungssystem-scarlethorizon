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

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];
$message = '';
$error = '';

// Create uploads directory if it doesn't exist
$uploadsDir = '../uploads/';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

// Define allowed file types and maximum file size (10MB)
$allowedFileTypes = ['image/png', 'application/pdf'];
$allowedFileExtensions = ['png', 'pdf'];
$maxFileSize = 10 * 1024 * 1024; // 10MB in bytes

/**
 * Format file size in a human-readable format
 * 
 * @param int $bytes File size in bytes
 * @return string Formatted file size with appropriate unit
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' Bytes';
    }
}

// Die findSubfolder- und updateNestedSubfolder-Funktionen sind bereits in includes/functions.php definiert

// Handle folder actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'create_folder') {
            $folderData = [
                'name' => sanitize($_POST['folder_name'] ?? ''),
                'description' => sanitize($_POST['folder_description'] ?? ''),
                'created_by' => $user_id,
                'created_by_name' => $username,
                'files' => [],
                'subfolders' => []
            ];
            
            // Check if this is a subfolder
            $parentFolderId = $_POST['parent_folder_id'] ?? null;
            
            if (empty($folderData['name'])) {
                $error = 'Bitte geben Sie einen Ordnernamen ein.';
            } else {
                $folderData['id'] = generateUniqueId();
                
                // If this is a subfolder, add it to the parent folder
                if ($parentFolderId) {
                    $parentFolder = findById('folders.json', $parentFolderId);
                    if ($parentFolder) {
                        if (!isset($parentFolder['subfolders'])) {
                            $parentFolder['subfolders'] = [];
                        }
                        $parentFolder['subfolders'][] = $folderData;
                        
                        if (updateRecord('folders.json', $parentFolderId, $parentFolder)) {
                            $message = 'Unterordner erfolgreich erstellt.';
                        } else {
                            $error = 'Fehler beim Erstellen des Unterordners.';
                        }
                    } else {
                        $error = 'Übergeordneter Ordner nicht gefunden.';
                    }
                } else {
                    // This is a top-level folder
                    if (insertRecord('folders.json', $folderData)) {
                        $message = 'Ordner erfolgreich erstellt.';
                    } else {
                        $error = 'Fehler beim Erstellen des Ordners.';
                    }
                }
            }
        } elseif ($action === 'delete_folder' && isset($_POST['folder_id'])) {
            $folderId = $_POST['folder_id'];
            
            // Nur Benutzer mit Leitungsfunktionen können Ordner löschen
            if (isLeadership($user_id) || checkUserPermission($user_id, 'files', 'delete')) {
                // Prüfen, ob es sich um einen Hauptordner oder Unterordner handelt
                $isSubfolder = false;
                $parentFolderId = null;
                $folders = loadJsonData('folders.json');
                
                // Zuerst prüfen, ob es ein Hauptordner ist
                $isMainFolder = false;
                foreach ($folders as $folder) {
                    if ($folder['id'] === $folderId) {
                        $isMainFolder = true;
                        break;
                    }
                }
                
                // Wenn kein Hauptordner, dann nach Unterordner suchen
                if (!$isMainFolder) {
                    foreach ($folders as $folder) {
                        if (isset($folder['subfolders']) && is_array($folder['subfolders'])) {
                            foreach ($folder['subfolders'] as $key => $subfolder) {
                                if ($subfolder['id'] === $folderId) {
                                    $isSubfolder = true;
                                    $parentFolderId = $folder['id'];
                                    break 2;
                                }
                            }
                        }
                    }
                }
                
                if ($isSubfolder && $parentFolderId) {
                    // Unterordner aus dem übergeordneten Ordner entfernen
                    $parentFolder = findById('folders.json', $parentFolderId);
                    if ($parentFolder) {
                        // Unterordner suchen und entfernen
                        $subfolderIndex = -1;
                        foreach ($parentFolder['subfolders'] as $key => $subfolder) {
                            if ($subfolder['id'] === $folderId) {
                                $subfolderIndex = $key;
                                break;
                            }
                        }
                        
                        if ($subfolderIndex >= 0) {
                            // Unterordner entfernen
                            array_splice($parentFolder['subfolders'], $subfolderIndex, 1);
                            
                            // Übergeordneten Ordner aktualisieren
                            if (updateRecord('folders.json', $parentFolderId, $parentFolder)) {
                                $message = 'Unterordner erfolgreich gelöscht.';
                            } else {
                                $error = 'Fehler beim Löschen des Unterordners.';
                            }
                        } else {
                            $error = 'Unterordner nicht gefunden.';
                        }
                    } else {
                        $error = 'Übergeordneter Ordner nicht gefunden.';
                    }
                } else {
                    // Hauptordner löschen
                    if (deleteRecord('folders.json', $folderId)) {
                        $message = 'Ordner erfolgreich gelöscht.';
                    } else {
                        $error = 'Fehler beim Löschen des Ordners.';
                    }
                }
            } else {
                $error = 'Nur Benutzer mit Leitungsfunktion können Ordner löschen.';
            }
        } elseif ($action === 'add_file') {
            $folderId = $_POST['folder_id'] ?? '';
            $documentType = $_POST['document_type'] ?? 'text';
            
            // Zuerst versuchen, Hauptordner zu finden
            $folder = findById('folders.json', $folderId);
            $isSubfolder = false;
            
            // Falls kein Hauptordner gefunden wurde, nach Unterordner suchen
            if (!$folder) {
                $folders = loadJsonData('folders.json');
                foreach ($folders as $parentFolder) {
                    $subfolder = findSubfolder($parentFolder, $folderId);
                    if ($subfolder) {
                        $folder = $subfolder;
                        $isSubfolder = true;
                        break;
                    }
                }
            }
            
            if (!$folder) {
                $error = 'Ungültiger Ordner. Der Ordner mit ID ' . $folderId . ' wurde nicht gefunden.';
            } else {
                $fileId = generateUniqueId();
                $fileData = [
                    'id' => $fileId,
                    'title' => sanitize($_POST['file_title'] ?? ''),
                    'description' => sanitize($_POST['file_description'] ?? ''),
                    'created_by' => $user_id,
                    'created_by_name' => $username,
                    'date_created' => date('Y-m-d H:i:s')
                ];
                
                // Handle different document types
                if ($documentType === 'text') {
                    // Text document
                    $fileData['content'] = sanitize($_POST['file_content'] ?? '');
                    
                    if (empty($fileData['title']) || empty($fileData['content'])) {
                        $error = 'Bitte geben Sie sowohl einen Titel als auch einen Inhalt für die Datei ein.';
                    } else {
                        // Add file to folder
                        $folder['files'][] = $fileData;
                        
                        // Abhängig davon, ob es ein Unterordner ist oder nicht, unterschiedliche Update-Methoden verwenden
                        if ($isSubfolder) {
                            if (updateNestedSubfolder($folderId, $folder)) {
                                $message = 'Datei erfolgreich zum Unterordner hinzugefügt.';
                            } else {
                                $error = 'Fehler beim Hinzufügen der Datei zum Unterordner.';
                            }
                        } else {
                            if (updateRecord('folders.json', $folderId, $folder)) {
                                $message = 'Datei erfolgreich hinzugefügt.';
                            } else {
                                $error = 'Fehler beim Hinzufügen der Datei.';
                            }
                        }
                    }
                } else {
                    // File upload (PNG or PDF)
                    if (empty($fileData['title'])) {
                        $error = 'Bitte geben Sie einen Titel für die Datei ein.';
                    } elseif (!isset($_FILES['file_upload']) || $_FILES['file_upload']['error'] !== UPLOAD_ERR_OK) {
                        $error = 'Fehler beim Hochladen der Datei. Bitte versuchen Sie es erneut.';
                    } else {
                        $uploadedFile = $_FILES['file_upload'];
                        $fileSize = $uploadedFile['size'];
                        $fileType = $uploadedFile['type'];
                        $tmpFilePath = $uploadedFile['tmp_name'];
                        $fileExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
                        
                        // Validate file
                        if ($fileSize > $maxFileSize) {
                            $error = 'Die Datei ist zu groß. Maximale Dateigröße: 10MB.';
                        } elseif (!in_array($fileType, $allowedFileTypes) || !in_array($fileExtension, $allowedFileExtensions)) {
                            $error = 'Ungültiger Dateityp. Nur PNG und PDF Dateien sind erlaubt.';
                        } else {
                            // Generate unique filename
                            $newFilename = $fileId . '.' . $fileExtension;
                            $targetFilePath = $uploadsDir . $newFilename;
                            
                            // Move uploaded file to target location
                            if (move_uploaded_file($tmpFilePath, $targetFilePath)) {
                                // Store file information
                                $fileData['file_type'] = $fileExtension;
                                $fileData['file_path'] = $newFilename;
                                $fileData['file_size'] = $fileSize;
                                
                                // Add file to folder
                                $folder['files'][] = $fileData;
                                
                                // Abhängig davon, ob es ein Unterordner ist oder nicht, unterschiedliche Update-Methoden verwenden
                                if ($isSubfolder) {
                                    if (updateNestedSubfolder($folderId, $folder)) {
                                        $message = 'Datei erfolgreich in Unterordner hochgeladen.';
                                    } else {
                                        // Delete uploaded file if record update failed
                                        @unlink($targetFilePath);
                                        $error = 'Fehler beim Speichern der Dateiinformationen im Unterordner.';
                                    }
                                } else {
                                    if (updateRecord('folders.json', $folderId, $folder)) {
                                        $message = 'Datei erfolgreich hochgeladen.';
                                    } else {
                                        // Delete uploaded file if record update failed
                                        @unlink($targetFilePath);
                                        $error = 'Fehler beim Speichern der Dateiinformationen.';
                                    }
                                }
                            } else {
                                $error = 'Fehler beim Speichern der hochgeladenen Datei.';
                            }
                        }
                    }
                }
            }
        } elseif ($action === 'edit_file') {
            $folderId = $_POST['folder_id'] ?? '';
            $fileId = $_POST['file_id'] ?? '';
            
            // Zuerst versuchen, Hauptordner zu finden
            $folder = findById('folders.json', $folderId);
            $isSubfolder = false;
            
            // Falls kein Hauptordner gefunden wurde, nach Unterordner suchen
            if (!$folder) {
                $folders = loadJsonData('folders.json');
                foreach ($folders as $parentFolder) {
                    $subfolder = findSubfolder($parentFolder, $folderId);
                    if ($subfolder) {
                        $folder = $subfolder;
                        $isSubfolder = true;
                        break;
                    }
                }
            }
            
            if (!$folder) {
                $error = 'Ungültiger Ordner. Der Ordner mit ID ' . $folderId . ' wurde nicht gefunden.';
            } else {
                $fileFound = false;
                $fileIndex = -1;
                
                foreach ($folder['files'] as $key => $file) {
                    if ($file['id'] === $fileId) {
                        $fileFound = true;
                        $fileIndex = $key;
                        break;
                    }
                }
                
                if ($fileFound) {
                    // Update basic information
                    $folder['files'][$fileIndex]['title'] = sanitize($_POST['file_title'] ?? '');
                    $folder['files'][$fileIndex]['description'] = sanitize($_POST['file_description'] ?? '');
                    $folder['files'][$fileIndex]['date_updated'] = date('Y-m-d H:i:s');
                    
                    // Check if it's a text file or uploaded file
                    $isFileType = isset($folder['files'][$fileIndex]['file_type']);
                    
                    if ($isFileType) {
                        // Uploaded file (PNG or PDF)
                        $currentFileType = $folder['files'][$fileIndex]['file_type'];
                        $currentFilePath = $folder['files'][$fileIndex]['file_path'];
                        
                        // Check if file replacement requested
                        if (isset($_POST['replace_file']) && $_POST['replace_file'] == 1 && isset($_FILES['file_upload']) && $_FILES['file_upload']['error'] === UPLOAD_ERR_OK) {
                            $uploadedFile = $_FILES['file_upload'];
                            $fileSize = $uploadedFile['size'];
                            $fileType = $uploadedFile['type'];
                            $tmpFilePath = $uploadedFile['tmp_name'];
                            $fileExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
                            
                            // Validate file is the same type as the original
                            if ($fileExtension !== $currentFileType) {
                                $error = 'Die neue Datei muss den gleichen Typ haben wie die ursprüngliche Datei (' . strtoupper($currentFileType) . ').';
                            } elseif ($fileSize > $maxFileSize) {
                                $error = 'Die Datei ist zu groß. Maximale Dateigröße: 10MB.';
                            } else {
                                // Update the file - keep same filename for consistency
                                $targetFilePath = $uploadsDir . $currentFilePath;
                                
                                if (move_uploaded_file($tmpFilePath, $targetFilePath)) {
                                    // Update file size
                                    $folder['files'][$fileIndex]['file_size'] = $fileSize;
                                } else {
                                    $error = 'Fehler beim Aktualisieren der Datei.';
                                }
                            }
                        }
                    } else {
                        // Text file
                        $folder['files'][$fileIndex]['content'] = sanitize($_POST['file_content'] ?? '');
                    }
                    
                    // Only update if no errors occurred
                    if (empty($error)) {
                        // Abhängig davon, ob es ein Unterordner ist oder nicht, unterschiedliche Update-Methoden verwenden
                        if ($isSubfolder) {
                            if (updateNestedSubfolder($folderId, $folder)) {
                                $message = 'Datei im Unterordner erfolgreich aktualisiert.';
                            } else {
                                $error = 'Fehler beim Aktualisieren der Datei im Unterordner.';
                            }
                        } else {
                            if (updateRecord('folders.json', $folderId, $folder)) {
                                $message = 'Datei erfolgreich aktualisiert.';
                            } else {
                                $error = 'Fehler beim Aktualisieren der Datei.';
                            }
                        }
                    }
                } else {
                    $error = 'Datei nicht gefunden.';
                }
            }
        } elseif ($action === 'delete_file') {
            $folderId = $_POST['folder_id'] ?? '';
            $fileId = $_POST['file_id'] ?? '';
            
            // Zuerst versuchen, Hauptordner zu finden
            $folder = findById('folders.json', $folderId);
            $isSubfolder = false;
            
            // Falls kein Hauptordner gefunden wurde, nach Unterordner suchen
            if (!$folder) {
                $folders = loadJsonData('folders.json');
                foreach ($folders as $parentFolder) {
                    $subfolder = findSubfolder($parentFolder, $folderId);
                    if ($subfolder) {
                        $folder = $subfolder;
                        $isSubfolder = true;
                        break;
                    }
                }
            }
            
            if (!$folder) {
                $error = 'Ungültiger Ordner. Der Ordner mit ID ' . $folderId . ' wurde nicht gefunden.';
            } else {
                // Nur Benutzer mit Leitungsfunktionen können Dateien löschen
                if (isLeadership($user_id) || checkUserPermission($user_id, 'files', 'delete')) {
                    $fileIndex = -1;
                    $fileToDelete = null;
                    
                    foreach ($folder['files'] as $key => $file) {
                        if ($file['id'] === $fileId) {
                            $fileIndex = $key;
                            $fileToDelete = $file;
                            break;
                        }
                    }
                    
                    if ($fileIndex >= 0) {
                        // Check if it's an uploaded file, delete the physical file first
                        if (isset($fileToDelete['file_path']) && !empty($fileToDelete['file_path'])) {
                            $filePath = $uploadsDir . $fileToDelete['file_path'];
                            if (file_exists($filePath)) {
                                @unlink($filePath);
                            }
                        }
                        
                        // Remove from the folder's files array
                        array_splice($folder['files'], $fileIndex, 1);
                        
                        // Abhängig davon, ob es ein Unterordner ist oder nicht, unterschiedliche Update-Methoden verwenden
                        if ($isSubfolder) {
                            if (updateNestedSubfolder($folderId, $folder)) {
                                $message = 'Datei im Unterordner erfolgreich gelöscht.';
                            } else {
                                $error = 'Fehler beim Löschen der Datei im Unterordner.';
                            }
                        } else {
                            if (updateRecord('folders.json', $folderId, $folder)) {
                                $message = 'Datei erfolgreich gelöscht.';
                            } else {
                                $error = 'Fehler beim Löschen der Datei.';
                            }
                        }
                    } else {
                        $error = 'Datei nicht gefunden.';
                    }
                } else {
                    $error = 'Nur Benutzer mit Leitungsfunktion können Dateien löschen.';
                }
            }
        }
    }
}

// Load folders
$folders = loadJsonData('folders.json');

// Sort folders by name
usort($folders, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

// Get selected folder and file if provided in URL
$selectedFolder = null;
$selectedFile = null;
$isSubfolder = false;
$parentFolder = null;

if (isset($_GET['folder'])) {
    $folderId = $_GET['folder'];
    $selectedFolder = findById('folders.json', $folderId);
    
    // Wenn kein Ordner gefunden wurde, könnte es ein Unterordner sein
    if (!$selectedFolder) {
        foreach ($folders as $folder) {
            $subfolder = findSubfolder($folder, $folderId);
            if ($subfolder) {
                $selectedFolder = $subfolder;
                $isSubfolder = true;
                $parentFolder = $folder;
                break;
            }
        }
    }
    
    if ($selectedFolder && isset($_GET['file'])) {
        $fileId = $_GET['file'];
        
        foreach ($selectedFolder['files'] as $file) {
            if ($file['id'] === $fileId) {
                $selectedFile = $file;
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

        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4 files-page main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Aktenschrank</h1>
                <div>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addFolderModal">
                        <span data-feather="folder-plus"></span> Neuer Ordner
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
                            <h4>Ordner</h4>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php if (count($folders) > 0): ?>
                                    <?php foreach ($folders as $folder): ?>
                                        <a href="files.php?folder=<?php echo $folder['id']; ?>" class="list-group-item list-group-item-action <?php echo (isset($_GET['folder']) && $_GET['folder'] === $folder['id']) ? 'active' : ''; ?>">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h5 class="mb-1"><span data-feather="folder"></span> <?php echo htmlspecialchars($folder['name']); ?></h5>
                                                <small>
                                                    <?php 
                                                    $fileCount = count($folder['files']);
                                                    $subfoldersCount = isset($folder['subfolders']) ? count($folder['subfolders']) : 0;
                                                    echo $fileCount . ' Dateien';
                                                    if ($subfoldersCount > 0) {
                                                        echo ', ' . $subfoldersCount . ' Unterordner';
                                                    }
                                                    ?>
                                                </small>
                                            </div>
                                            <?php if (!empty($folder['description'])): ?>
                                                <p class="mb-1"><?php echo htmlspecialchars($folder['description']); ?></p>
                                            <?php endif; ?>
                                            <small>Erstellt von: <?php echo htmlspecialchars($folder['created_by_name']); ?></small>
                                        </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="list-group-item">
                                        <p class="text-muted">Keine Ordner gefunden. Erstellen Sie einen neuen Ordner, um zu beginnen.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <?php if ($selectedFolder): ?>
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h4><span data-feather="folder"></span> <?php echo htmlspecialchars($selectedFolder['name']); ?></h4>
                                <div>
                                    <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#addFileModal">
                                        <span data-feather="file-plus"></span> Datei hinzufügen
                                    </button>
                                    <button type="button" class="btn btn-sm btn-success" data-toggle="modal" data-target="#addSubfolderModal">
                                        <span data-feather="folder-plus"></span> Unterordner erstellen
                                    </button>
                                    <?php if (isLeadership($user_id) || checkUserPermission($user_id, 'files', 'delete')): ?>
                                    <form method="post" action="files.php" class="d-inline">
                                        <input type="hidden" name="action" value="delete_folder">
                                        <input type="hidden" name="folder_id" value="<?php echo $selectedFolder['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger btn-delete">
                                            <span data-feather="trash-2"></span> Ordner löschen
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($selectedFolder['description'])): ?>
                                    <p class="mb-3"><?php echo htmlspecialchars($selectedFolder['description']); ?></p>
                                <?php endif; ?>
                                
                                <!-- Display subfolders if any -->
                                <?php if (isset($selectedFolder['subfolders']) && count($selectedFolder['subfolders']) > 0): ?>
                                    <h5 class="mt-3 mb-2">Unterordner</h5>
                                    <div class="list-group mb-4">
                                        <?php foreach ($selectedFolder['subfolders'] as $key => $subfolder): ?>
                                            <a href="files.php?folder=<?php echo $subfolder['id']; ?>" class="list-group-item list-group-item-action">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h5 class="mb-1"><span data-feather="folder"></span> <?php echo htmlspecialchars($subfolder['name']); ?></h5>
                                                    <small>
                                                        <?php
                                                        $subfolder_files = isset($subfolder['files']) ? count($subfolder['files']) : 0;
                                                        echo $subfolder_files . ' Dateien';
                                                        ?>
                                                    </small>
                                                </div>
                                                <?php if (!empty($subfolder['description'])): ?>
                                                    <p class="mb-1"><?php echo htmlspecialchars($subfolder['description']); ?></p>
                                                <?php endif; ?>
                                                <small>Erstellt von: <?php echo htmlspecialchars($subfolder['created_by_name']); ?></small>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Display files if any -->
                                <?php if (count($selectedFolder['files']) > 0): ?>
                                    <h5 class="mt-3 mb-2">Dateien</h5>
                                    <div class="list-group">
                                        <?php foreach ($selectedFolder['files'] as $file): ?>
                                            <a href="files.php?folder=<?php echo $selectedFolder['id']; ?>&file=<?php echo $file['id']; ?>" class="list-group-item list-group-item-action <?php echo (isset($_GET['file']) && $_GET['file'] === $file['id']) ? 'active' : ''; ?>">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h5 class="mb-1"><span data-feather="file-text"></span> <?php echo htmlspecialchars($file['title']); ?></h5>
                                                    <small><?php echo date('d.m.Y', strtotime($file['date_created'])); ?></small>
                                                </div>
                                                <small>Erstellt von: <?php echo htmlspecialchars($file['created_by_name']); ?></small>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php elseif (!isset($selectedFolder['subfolders']) || count($selectedFolder['subfolders']) === 0): ?>
                                    <p class="text-muted">Keine Dateien oder Unterordner in diesem Ordner. Fügen Sie eine Datei oder einen Unterordner hinzu, um zu beginnen.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($selectedFile): ?>
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h4><span data-feather="file-text"></span> <?php echo htmlspecialchars($selectedFile['title']); ?></h4>
                                    <div>
                                        <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#editFileModal">
                                            <span data-feather="edit"></span> Datei bearbeiten
                                        </button>
                                        <?php if (isLeadership($user_id) || checkUserPermission($user_id, 'files', 'delete')): ?>
                                        <form method="post" action="files.php" class="d-inline">
                                            <input type="hidden" name="action" value="delete_file">
                                            <input type="hidden" name="folder_id" value="<?php echo $selectedFolder['id']; ?>">
                                            <input type="hidden" name="file_id" value="<?php echo $selectedFile['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger btn-delete">
                                                <span data-feather="trash-2"></span> Datei löschen
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <small class="text-muted">
                                            Erstellt von: <?php echo htmlspecialchars($selectedFile['created_by_name']); ?> 
                                            am <?php echo date('d.m.Y', strtotime($selectedFile['date_created'])); ?>
                                            <?php if (isset($selectedFile['date_updated'])): ?>
                                                (Aktualisiert: <?php echo date('d.m.Y', strtotime($selectedFile['date_updated'])); ?>)
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    
                                    <?php if (isset($selectedFile['description']) && !empty($selectedFile['description'])): ?>
                                    <div class="mb-3">
                                        <h6>Beschreibung:</h6>
                                        <p><?php echo nl2br(htmlspecialchars($selectedFile['description'])); ?></p>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($selectedFile['file_type'])): ?>
                                        <!-- Uploaded file (PNG or PDF) -->
                                        <?php if ($selectedFile['file_type'] === 'png'): ?>
                                            <div class="text-center mb-3">
                                                <img src="../uploads/<?php echo $selectedFile['file_path']; ?>" class="img-fluid" alt="<?php echo htmlspecialchars($selectedFile['title']); ?>" style="max-height: 500px; border: 1px solid #ddd; padding: 5px; background: #f8f9fa;">
                                            </div>
                                            
                                            <div class="d-flex justify-content-center">
                                                <a href="../uploads/<?php echo $selectedFile['file_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-arrows-fullscreen"></i> In voller Größe anzeigen
                                                </a>
                                            </div>
                                        <?php elseif ($selectedFile['file_type'] === 'pdf'): ?>
                                            <div class="mb-3 text-center">
                                                <div class="alert alert-info">
                                                    <i class="bi bi-file-earmark-pdf"></i> PDF-Dokument
                                                </div>
                                                <div class="ratio ratio-16x9 mb-3" style="height: 500px;">
                                                    <embed src="../uploads/<?php echo $selectedFile['file_path']; ?>" type="application/pdf" width="100%" height="500px" />
                                                </div>
                                                <a href="../uploads/<?php echo $selectedFile['file_path']; ?>" target="_blank" class="btn btn-outline-primary">
                                                    <i class="bi bi-file-earmark-pdf"></i> PDF öffnen
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($selectedFile['file_size'])): ?>
                                            <div class="mt-3">
                                                <small class="text-muted">
                                                    Dateigröße: <?php echo formatFileSize($selectedFile['file_size']); ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <!-- Text file -->
                                        <div class="file-content p-3 border rounded bg-light">
                                            <?php echo nl2br(htmlspecialchars($selectedFile['content'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="card">
                            <div class="card-body">
                                <div class="text-center py-5">
                                    <h3 class="mb-3">Willkommen im Aktenschrank</h3>
                                    <p class="lead">Wählen Sie einen Ordner aus der linken Seitenleiste oder erstellen Sie einen neuen Ordner, um zu beginnen.</p>
                                    <button type="button" class="btn btn-lg btn-primary mt-3" data-toggle="modal" data-target="#addFolderModal">
                                        <span data-feather="folder-plus"></span> Neuen Ordner erstellen
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

<!-- Add Folder Modal -->
<div class="modal fade" id="addFolderModal" tabindex="-1" aria-labelledby="addFolderModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addFolderModalLabel">Neuen Ordner erstellen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Schließen">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="files.php" id="add-folder-form">
                <input type="hidden" name="action" value="create_folder">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="folder_name" class="form-label">Ordnername *</label>
                        <input type="text" class="form-control" id="folder_name" name="folder_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="folder_description" class="form-label">Beschreibung</label>
                        <textarea class="form-control" id="folder_description" name="folder_description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Ordner erstellen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add File Modal -->
<div class="modal fade" id="addFileModal" tabindex="-1" aria-labelledby="addFileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addFileModalLabel">Neue Datei hinzufügen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Schließen">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="files.php" id="add-file-form" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_file">
                <input type="hidden" name="folder_id" value="<?php echo $selectedFolder ? $selectedFolder['id'] : ''; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="file_title" class="form-label">Titel *</label>
                        <input type="text" class="form-control" id="file_title" name="file_title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Dokumenttyp *</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="document_type" id="document_type_text" value="text" checked>
                            <label class="form-check-label" for="document_type_text">
                                Textdokument
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="document_type" id="document_type_file" value="file">
                            <label class="form-check-label" for="document_type_file">
                                Datei (PNG oder PDF)
                            </label>
                        </div>
                    </div>
                    
                    <div id="text_content_container" class="mb-3">
                        <label for="file_content" class="form-label">Inhalt *</label>
                        <textarea class="form-control" id="file_content" name="file_content" rows="8"></textarea>
                    </div>
                    
                    <div id="file_upload_container" class="mb-3" style="display: none;">
                        <label for="file_upload" class="form-label">Datei hochladen * (nur PNG oder PDF)</label>
                        <input type="file" class="form-control" id="file_upload" name="file_upload" accept=".png,.pdf">
                        <div class="form-text">Maximale Dateigröße: 10MB</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="file_description" class="form-label">Beschreibung</label>
                        <textarea class="form-control" id="file_description" name="file_description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Datei speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle between text and file upload based on document type selection
    const documentTypeRadios = document.querySelectorAll('input[name="document_type"]');
    const textContentContainer = document.getElementById('text_content_container');
    const fileUploadContainer = document.getElementById('file_upload_container');
    const fileContentInput = document.getElementById('file_content');
    const fileUploadInput = document.getElementById('file_upload');
    
    documentTypeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'text') {
                textContentContainer.style.display = 'block';
                fileUploadContainer.style.display = 'none';
                fileContentInput.setAttribute('required', '');
                fileUploadInput.removeAttribute('required');
            } else {
                textContentContainer.style.display = 'none';
                fileUploadContainer.style.display = 'block';
                fileContentInput.removeAttribute('required');
                fileUploadInput.setAttribute('required', '');
            }
        });
    });
});
</script>

<!-- Edit File Modal -->
<div class="modal fade" id="editFileModal" tabindex="-1" aria-labelledby="editFileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editFileModalLabel">Datei bearbeiten</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Schließen">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="files.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_file">
                <input type="hidden" name="folder_id" value="<?php echo $selectedFolder ? $selectedFolder['id'] : ''; ?>">
                <input type="hidden" name="file_id" value="<?php echo $selectedFile ? $selectedFile['id'] : ''; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_file_title" class="form-label">Titel *</label>
                        <input type="text" class="form-control" id="edit_file_title" name="file_title" value="<?php echo $selectedFile ? htmlspecialchars($selectedFile['title']) : ''; ?>" required>
                    </div>
                    
                    <?php if ($selectedFile && isset($selectedFile['file_type']) && in_array($selectedFile['file_type'], ['png', 'pdf'])): ?>
                        <div class="mb-3">
                            <label class="form-label">Aktueller Dateityp: <?php echo strtoupper($selectedFile['file_type']); ?></label>
                            <div class="mb-3">
                                <?php if ($selectedFile['file_type'] == 'png'): ?>
                                    <img src="../uploads/<?php echo $selectedFile['file_path']; ?>" class="img-fluid mb-3" style="max-height: 300px; max-width: 100%;" alt="<?php echo htmlspecialchars($selectedFile['title']); ?>">
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="bi bi-file-earmark-pdf"></i> PDF-Dokument: <a href="../uploads/<?php echo $selectedFile['file_path']; ?>" target="_blank">Öffnen</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="replace_file" name="replace_file" value="1">
                                <label class="form-check-label" for="replace_file">
                                    Datei ersetzen
                                </label>
                            </div>
                            
                            <div id="edit_file_upload_container" class="mb-3" style="display: none;">
                                <label for="edit_file_upload" class="form-label">Neue Datei hochladen (nur <?php echo strtoupper($selectedFile['file_type']); ?>)</label>
                                <input type="file" class="form-control" id="edit_file_upload" name="file_upload" accept=".<?php echo $selectedFile['file_type']; ?>">
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="mb-3">
                            <label for="edit_file_content" class="form-label">Inhalt *</label>
                            <textarea class="form-control" id="edit_file_content" name="file_content" rows="12" required><?php echo $selectedFile ? htmlspecialchars($selectedFile['content']) : ''; ?></textarea>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="edit_file_description" class="form-label">Beschreibung</label>
                        <textarea class="form-control" id="edit_file_description" name="file_description" rows="3"><?php echo $selectedFile && isset($selectedFile['description']) ? htmlspecialchars($selectedFile['description']) : ''; ?></textarea>
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
// Toggle file upload visibility in edit form based on replace file checkbox
document.addEventListener('DOMContentLoaded', function() {
    const replaceFileCheckbox = document.getElementById('replace_file');
    const editFileUploadContainer = document.getElementById('edit_file_upload_container');
    const editFileUploadInput = document.getElementById('edit_file_upload');
    
    if (replaceFileCheckbox && editFileUploadContainer) {
        replaceFileCheckbox.addEventListener('change', function() {
            if (this.checked) {
                editFileUploadContainer.style.display = 'block';
                editFileUploadInput.setAttribute('required', '');
            } else {
                editFileUploadContainer.style.display = 'none';
                editFileUploadInput.removeAttribute('required');
            }
        });
    }
});
</script>

<!-- Add Subfolder Modal -->
<div class="modal fade" id="addSubfolderModal" tabindex="-1" aria-labelledby="addSubfolderModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addSubfolderModalLabel">Unterordner erstellen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Schließen">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="files.php" id="add-subfolder-form">
                <input type="hidden" name="action" value="create_folder">
                <input type="hidden" name="parent_folder_id" value="<?php echo isset($selectedFolder['id']) ? $selectedFolder['id'] : ''; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="subfolder_name" class="form-label">Unterordnername *</label>
                        <input type="text" class="form-control" id="subfolder_name" name="folder_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="subfolder_description" class="form-label">Beschreibung</label>
                        <textarea class="form-control" id="subfolder_description" name="folder_description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-success">Unterordner erstellen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
