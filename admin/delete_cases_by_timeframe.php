<?php
/**
 * Löschen von Akten nach Tatzeitraum
 * Erlaubt Administratoren, Akten innerhalb eines bestimmten Tatzeitraums zu löschen
 */
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Sicherstellen, dass nur Administratoren auf diese Seite zugreifen können
if (!isAdminSession()) {
    header('Location: ../dashboard.php');
    exit;
}

// Verarbeitung des Formulars
$message = null;
$error = null;
$deletedCases = [];
$previewMode = true; // Standardmäßig werden Akten nur angezeigt, nicht gelöscht

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Formulardaten validieren
    $startDate = isset($_POST['start_date']) ? $_POST['start_date'] : '';
    $endDate = isset($_POST['end_date']) ? $_POST['end_date'] : '';
    $previewMode = isset($_POST['preview_mode']);
    
    if (empty($startDate) || empty($endDate)) {
        $error = "Bitte geben Sie sowohl ein Start- als auch ein Enddatum an.";
    } else {
        // Validierung der Datumsformate
        $startDateObj = DateTime::createFromFormat('Y-m-d', $startDate);
        $endDateObj = DateTime::createFromFormat('Y-m-d', $endDate);
        
        if (!$startDateObj || !$endDateObj) {
            $error = "Ungültiges Datumsformat. Bitte verwenden Sie das Format YYYY-MM-DD.";
        } elseif ($startDateObj > $endDateObj) {
            $error = "Das Startdatum kann nicht nach dem Enddatum liegen.";
        } else {
            // Akten laden
            $cases = getJsonData('cases.json');
            $casesToDelete = [];
            
            // Akten nach Tatzeitraum filtern
            foreach ($cases as $case) {
                // Tatzeitpunkt oder Datum prüfen
                $incidentDate = null;
                
                if (isset($case['incident_date']) && !empty($case['incident_date'])) {
                    $incidentDate = $case['incident_date'];
                } elseif (isset($case['date']) && !empty($case['date'])) {
                    $incidentDate = $case['date'];
                }
                
                if ($incidentDate) {
                    $caseDate = DateTime::createFromFormat('Y-m-d', $incidentDate);
                    if (!$caseDate) {
                        $caseDate = DateTime::createFromFormat('Y-m-d H:i:s', $incidentDate);
                    }
                    
                    if ($caseDate && $caseDate >= $startDateObj && $caseDate <= $endDateObj) {
                        $casesToDelete[] = $case;
                    }
                }
            }
            
            // Anzahl der gefundenen Akten
            $countCases = count($casesToDelete);
            
            if ($countCases === 0) {
                $message = "Es wurden keine Akten im angegebenen Zeitraum gefunden.";
            } else {
                if (!$previewMode && isset($_POST['confirm_delete'])) {
                    // Akten löschen - nur wenn 'confirm_delete' aktiviert ist
                    $deletedCount = 0;
                    foreach ($casesToDelete as $case) {
                        try {
                            // Akte aus der Liste entfernen
                            $cases = array_filter($cases, function($c) use ($case) {
                                return $c['id'] !== $case['id'];
                            });
                            
                            $deletedCases[] = $case;
                            $deletedCount++;
                        } catch (Exception $e) {
                            $error = "Fehler beim Löschen der Akten: " . $e->getMessage();
                            break;
                        }
                    }
                    
                    if ($deletedCount > 0) {
                        // Speichern der aktualisierten Daten
                        file_put_contents('../data/cases.json', json_encode(array_values($cases), JSON_PRETTY_PRINT));
                        
                        $message = "$deletedCount Akten wurden erfolgreich gelöscht.";
                    }
                } else {
                    // Nur Vorschau zeigen
                    $deletedCases = $casesToDelete;
                    $message = "Vorschau: $countCases Akten wurden im angegebenen Zeitraum gefunden. Deaktivieren Sie die Vorschau und bestätigen Sie den Löschvorgang, um die Akten zu löschen.";
                }
            }
        }
    }
}

// Seitentitel
$pageTitle = "Akten nach Tatzeitraum löschen";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="col-md-9 ml-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Akten nach Tatzeitraum löschen</h1>
            </div>

            <?php if (isset($message)): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button>
                </div>
            <?php endif; ?>

            <!-- Warnung -->
            <div class="alert alert-warning" role="alert">
                <h4 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Warnung!</h4>
                <p>Diese Funktion löscht unwiderruflich alle Akten, deren Tatzeitpunkt im angegebenen Zeitraum liegt. Bitte gehen Sie sorgfältig vor.</p>
                <hr>
                <p class="mb-0">Verwenden Sie die Vorschau-Funktion, um zu sehen, welche Akten gelöscht werden, bevor Sie den Löschvorgang durchführen.</p>
            </div>

            <!-- Formular zum Löschen von Akten nach Tatzeitraum -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Akten nach Tatzeitraum löschen</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="" id="deleteCasesForm">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="start_date" class="form-label">Tatzeitraum von:</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" 
                                       value="<?php echo isset($_POST['start_date']) ? htmlspecialchars($_POST['start_date']) : ''; ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="end_date" class="form-label">bis:</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" 
                                       value="<?php echo isset($_POST['end_date']) ? htmlspecialchars($_POST['end_date']) : ''; ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="preview_mode" name="preview_mode" 
                                  <?php echo isset($_POST['preview_mode']) || !isset($_POST['confirm_delete']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="preview_mode">
                                Vorschau-Modus (keine Akten löschen, nur anzeigen)
                            </label>
                        </div>
                        
                        <div class="form-check mb-3" id="confirmDeleteContainer" style="<?php echo isset($_POST['preview_mode']) ? 'display: none;' : ''; ?>">
                            <input class="form-check-input" type="checkbox" id="confirm_delete" name="confirm_delete" 
                                  <?php echo isset($_POST['confirm_delete']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="confirm_delete">
                                <strong>Ich bestätige, dass ich alle Akten im angegebenen Zeitraum unwiderruflich löschen möchte.</strong>
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Akten finden
                        </button>
                        
                        <a href="database.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Zurück zur Datenbankverwaltung
                        </a>
                    </form>
                </div>
            </div>

            <!-- Vorschau der zu löschenden Akten -->
            <?php if (!empty($deletedCases)): ?>
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <?php echo $previewMode ? 'Vorschau: Gefundene Akten' : 'Gelöschte Akten'; ?>
                        </h5>
                        <span class="badge bg-<?php echo $previewMode ? 'info' : 'danger'; ?>">
                            <?php echo count($deletedCases); ?> Akten
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Aktenzeichen</th>
                                        <th>Titel</th>
                                        <th>Angeklagter</th>
                                        <th>Tatzeitpunkt</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($deletedCases as $case): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($case['id']); ?></td>
                                            <td><?php echo htmlspecialchars($case['case_number'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($case['title'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($case['defendant'] ?? '-'); ?></td>
                                            <td>
                                                <?php 
                                                    if (isset($case['incident_date']) && !empty($case['incident_date'])) {
                                                        echo htmlspecialchars($case['incident_date']);
                                                    } elseif (isset($case['date']) && !empty($case['date'])) {
                                                        echo htmlspecialchars($case['date']);
                                                    } else {
                                                        echo '-';
                                                    }
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    if (isset($case['status'])) {
                                                        $status = $case['status'];
                                                        $statusClass = '';
                                                        
                                                        switch (strtolower($status)) {
                                                            case 'open':
                                                            case 'offen':
                                                                $statusClass = 'primary';
                                                                break;
                                                            case 'completed':
                                                            case 'abgeschlossen':
                                                                $statusClass = 'success';
                                                                break;
                                                            case 'pending':
                                                            case 'ausstehend':
                                                            case 'klageschrift eingereicht':
                                                                $statusClass = 'warning';
                                                                break;
                                                            case 'rejected':
                                                            case 'abgelehnt':
                                                                $statusClass = 'danger';
                                                                break;
                                                            default:
                                                                $statusClass = 'secondary';
                                                        }
                                                        
                                                        echo '<span class="badge bg-' . $statusClass . '">' . htmlspecialchars(mapStatusToGerman($status)) . '</span>';
                                                    } else {
                                                        echo '-';
                                                    }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if ($previewMode): ?>
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle"></i> Dies ist nur eine Vorschau. Deaktivieren Sie die Vorschau und bestätigen Sie den Löschvorgang, um die Akten zu löschen.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script>
    // JavaScript zur Anzeige/Ausblendung der Bestätigungscheckbox
    document.addEventListener('DOMContentLoaded', function() {
        const previewCheckbox = document.getElementById('preview_mode');
        const confirmContainer = document.getElementById('confirmDeleteContainer');
        const confirmCheckbox = document.getElementById('confirm_delete');
        
        function toggleConfirmation() {
            if (previewCheckbox.checked) {
                confirmContainer.style.display = 'none';
                confirmCheckbox.checked = false;
            } else {
                confirmContainer.style.display = 'block';
            }
        }
        
        previewCheckbox.addEventListener('change', toggleConfirmation);
        toggleConfirmation(); // Initial state
    });
</script>

<?php include '../includes/footer.php'; ?>