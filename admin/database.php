<?php
/**
 * Admin Database Management
 * Allows database operations and migrations
 */
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Ensure only admins can access this page
if (!isAdminSession()) {
    header('Location: ../dashboard.php');
    exit;
}

// Process database operations
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Synchronize JSON to Database
    if (isset($_POST['sync_to_db'])) {
        $tableName = $_POST['table_name'];
        $jsonFile = $tableName . '.json';
        
        // Get data from JSON file
        $records = loadJsonData($jsonFile);
        
        if (!empty($records)) {
            $successCount = 0;
            
            try {
                $pdo = getPDO();
                if ($pdo === null) {
                    throw new Exception('Keine Datenbankverbindung verfügbar.');
                }
                
                foreach ($records as $record) {
                    try {
                        // Build SQL query
                        $columns = array_keys($record);
                        $placeholders = array_map(function($col) { return ":$col"; }, $columns);
                        
                        $sql = "INSERT INTO $tableName (" . implode(", ", $columns) . ") 
                               VALUES (" . implode(", ", $placeholders) . ")
                               ON CONFLICT (id) DO UPDATE SET " . 
                               implode(", ", array_map(function($col) { return "$col = EXCLUDED.$col"; }, $columns));
                        
                        $stmt = $pdo->prepare($sql);
                        
                        // Bind parameters
                        foreach ($record as $key => $value) {
                            $stmt->bindValue(":$key", $value);
                        }
                        
                        $stmt->execute();
                        $successCount++;
                    } catch (Exception $e) {
                        // Log error and continue with next record
                        error_log("Failed to sync record from $jsonFile: " . $e->getMessage());
                    }
                }
                
                $message = "$successCount Einträge aus $jsonFile wurden erfolgreich in die Datenbank synchronisiert.";
            } catch (Exception $e) {
                $error = "Fehler bei der Synchronisierung: " . $e->getMessage();
            }
        } else {
            $error = "Keine Daten in $jsonFile gefunden.";
        }
    }
    
    // Test connection
    if (isset($_POST['test_connection'])) {
        if (isDatabaseConnected()) {
            $message = "Datenbankverbindung erfolgreich hergestellt.";
        } else {
            $error = "Keine Verbindung zur Datenbank möglich.";
        }
    }
}

// Get list of available JSON data files
$jsonFiles = [];
$dataDir = __DIR__ . '/../data/';
if (is_dir($dataDir)) {
    $files = scandir($dataDir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
            $jsonFiles[] = $file;
        }
    }
}

// Get list of existing database tables
$dbTables = [];
if (isDatabaseConnected()) {
    try {
        $pdo = getPDO();
        $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dbTables[] = $row['table_name'];
        }
    } catch (Exception $e) {
        $error = "Fehler beim Abrufen der Datenbanktabellen: " . $e->getMessage();
    }
}

// Page title
$pageTitle = "Datenbankverwaltung";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="col-md-9 ml-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Datenbankverwaltung</h1>
            </div>

            <?php if (isset($message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Database Connection Status -->
            <div class="row mb-4">
                <div class="col-lg-6">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Datenbankstatus</h5>
                            <?php if (isDatabaseConnected()): ?>
                                <div class="alert alert-success mb-3">
                                    <i class="bi bi-check-circle"></i> Datenbankverbindung aktiv. Daten werden in der PostgreSQL-Datenbank gespeichert.
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning mb-3">
                                    <i class="bi bi-exclamation-triangle"></i> Keine Datenbankverbindung. Daten werden in JSON-Dateien gespeichert.
                                </div>
                            <?php endif; ?>
                            
                            <form method="post" class="mt-3">
                                <button type="submit" name="test_connection" class="btn btn-primary">
                                    <i class="bi bi-database-check"></i> Verbindung testen
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Datenbank-Information</h5>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Host
                                    <span class="badge bg-primary rounded-pill"><?php echo getenv('PGHOST') ?: 'N/A'; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Datenbank
                                    <span class="badge bg-primary rounded-pill"><?php echo getenv('PGDATABASE') ?: 'N/A'; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Tabellen
                                    <span class="badge bg-primary rounded-pill"><?php echo count($dbTables); ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Database Operations -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Datensynchronisierung</h5>
                </div>
                <div class="card-body">
                    <p class="card-text">
                        Hier können Sie Daten aus den JSON-Dateien in die PostgreSQL-Datenbank synchronisieren. 
                        Wählen Sie die Tabelle aus, die Sie synchronisieren möchten.
                    </p>
                    
                    <?php if (isDatabaseConnected()): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>JSON-Datei</th>
                                        <th>Datensätze</th>
                                        <th>DB-Tabelle vorhanden</th>
                                        <th>Aktion</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($jsonFiles as $file): 
                                        $tableName = pathinfo($file, PATHINFO_FILENAME);
                                        $records = getJsonData($file);
                                        $tableExists = in_array($tableName, $dbTables);
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($file); ?></td>
                                            <td><?php echo count($records); ?></td>
                                            <td>
                                                <?php if ($tableExists): ?>
                                                    <span class="badge bg-success">Ja</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Nein</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($tableExists): ?>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="table_name" value="<?php echo $tableName; ?>">
                                                        <button type="submit" name="sync_to_db" class="btn btn-sm btn-primary">
                                                            <i class="bi bi-arrow-right"></i> In DB synchronisieren
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-secondary" disabled>
                                                        Tabelle fehlt
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i> Datenbankverbindung erforderlich für die Synchronisierung.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Wartungstools -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Wartungstools</h5>
                </div>
                <div class="card-body">
                    <p>Diese Tools helfen bei der Wartung und Korrektur der Datenbank.</p>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <a href="fix_status.php" class="btn btn-warning">
                            <i class="fas fa-wrench"></i> Status-Werte standardisieren
                        </a>
                        <a href="delete_cases_by_timeframe.php" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Akten nach Tatzeitraum löschen
                        </a>
                    </div>
                    <div class="small text-muted mt-3">
                        <p><strong>Status-Werte standardisieren:</strong> Dieses Tool korrigiert die Status-Werte in allen Datensätzen und konvertiert deutsche Werte in die standardisierten englischen Werte.</p>
                        <p><strong>Akten nach Tatzeitraum löschen:</strong> Mit diesem Tool können Sie Akten basierend auf dem Tatzeitraum suchen und gezielt löschen.</p>
                    </div>
                </div>
            </div>

            <!-- Database Tables -->
            <?php if (isDatabaseConnected() && !empty($dbTables)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Datenbanktabellen</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>Tabellenname</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dbTables as $table): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($table); ?></td>
                                            <td><span class="badge bg-success">Aktiv</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>