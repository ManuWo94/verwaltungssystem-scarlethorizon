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

// Handle defendant actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['defendant_id'])) {
        $defendantId = $_POST['defendant_id'];
        
        if (deleteRecord('defendants.json', $defendantId)) {
            $message = 'Angeklagter wurde erfolgreich gelöscht.';
        } else {
            $error = 'Fehler beim Löschen des Angeklagten.';
        }
    } else {
        // Handle defendant creation/edit
        $defendantData = [
            'name' => sanitize($_POST['name'] ?? ''),
            'tg_number' => sanitize($_POST['tg_number'] ?? '')
        ];
        
        // Validate required fields
        if (empty($defendantData['name'])) {
            $error = 'Bitte geben Sie einen Namen für den Angeklagten ein.';
        } else {
            if (isset($_POST['defendant_id']) && !empty($_POST['defendant_id'])) {
                // Update existing defendant
                $defendantId = $_POST['defendant_id'];
                
                if (updateRecord('defendants.json', $defendantId, $defendantData)) {
                    $message = 'Angeklagter wurde erfolgreich aktualisiert.';
                } else {
                    $error = 'Fehler beim Aktualisieren des Angeklagten.';
                }
            } else {
                // Create new defendant
                $defendantData['id'] = generateUniqueId();
                
                if (insertRecord('defendants.json', $defendantData)) {
                    $message = 'Angeklagter wurde erfolgreich erstellt.';
                } else {
                    $error = 'Fehler beim Erstellen des Angeklagten.';
                }
            }
        }
    }
}

// Load defendants
$defendants = loadJsonData('defendants.json');

// Sort defendants by name
usort($defendants, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

// Get cases for each defendant
$cases = loadJsonData('cases.json');
$defendantCases = [];

foreach ($defendants as $defendant) {
    $defendantCases[$defendant['id']] = array_filter($cases, function($case) use ($defendant) {
        return $case['defendant'] === $defendant['name'];
    });
}
?>

<?php include '../includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Angeklagtenverwaltung</h1>
                <div>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addDefendantModal">
                        <span data-feather="plus"></span> Neuen Angeklagten hinzufügen
                    </button>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Suchfeld -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" class="form-control" id="defendant-search" placeholder="Suche nach Name oder TG-Nummer...">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" id="clear-search">
                                <span data-feather="x"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>TG-Nummer</th>
                            <th>Fälle</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($defendants) > 0): ?>
                            <?php foreach ($defendants as $defendant): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($defendant['name']); ?></td>
                                    <td><?php echo htmlspecialchars($defendant['tg_number']); ?></td>
                                    <td>
                                        <?php 
                                            $caseCount = count($defendantCases[$defendant['id']]);
                                            echo $caseCount;
                                            
                                            if ($caseCount > 0) {
                                                echo ' <a href="cases.php?defendant=' . urlencode($defendant['name']) . '">Anzeigen</a>';
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary edit-defendant-btn" 
                                                data-id="<?php echo $defendant['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($defendant['name']); ?>"
                                                data-tg-number="<?php echo htmlspecialchars($defendant['tg_number']); ?>"
                                                data-toggle="modal" data-target="#editDefendantModal">
                                            <span data-feather="edit"></span> Bearbeiten
                                        </button>
                                        <?php if ($caseCount === 0): ?>
                                            <form method="post" action="defendants.php" class="d-inline">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="defendant_id" value="<?php echo $defendant['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger btn-delete">
                                                    <span data-feather="trash-2"></span> Löschen
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center">Keine Angeklagten gefunden.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>

<!-- Add Defendant Modal -->
<div class="modal fade" id="addDefendantModal" tabindex="-1" aria-labelledby="addDefendantModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addDefendantModalLabel">Neuen Angeklagten hinzufügen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="defendants.php" class="needs-validation" novalidate>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="name">Name des Angeklagten *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                        <div class="invalid-feedback">Bitte geben Sie einen Namen ein.</div>
                    </div>
                    <div class="form-group">
                        <label for="tg_number">TG-Nummer</label>
                        <input type="text" class="form-control" id="tg_number" name="tg_number">
                        <small class="form-text text-muted">Eindeutiger Identifikator für den Angeklagten.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Angeklagten speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Defendant Modal -->
<div class="modal fade" id="editDefendantModal" tabindex="-1" aria-labelledby="editDefendantModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editDefendantModalLabel">Angeklagten bearbeiten</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="defendants.php" class="needs-validation" novalidate>
                <input type="hidden" id="edit_defendant_id" name="defendant_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_name">Name des Angeklagten *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                        <div class="invalid-feedback">Bitte geben Sie einen Namen ein.</div>
                    </div>
                    <div class="form-group">
                        <label for="edit_tg_number">TG-Nummer</label>
                        <input type="text" class="form-control" id="edit_tg_number" name="tg_number">
                        <small class="form-text text-muted">Eindeutiger Identifikator für den Angeklagten.</small>
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
        // Search functionality
        const searchInput = document.getElementById('defendant-search');
        const clearButton = document.getElementById('clear-search');
        
        // Function to filter defendants based on search
        function filterDefendants() {
            const searchTerm = searchInput.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const name = row.querySelector('td:nth-child(1)').textContent.toLowerCase();
                const tgNumber = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                
                if (searchTerm === '' || name.includes(searchTerm) || tgNumber.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        // Search input event
        searchInput.addEventListener('input', filterDefendants);
        
        // Clear search button
        clearButton.addEventListener('click', function() {
            searchInput.value = '';
            filterDefendants();
        });
        
        // Handle edit defendant button clicks
        const editButtons = document.querySelectorAll('.edit-defendant-btn');
        
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.dataset.id;
                const name = this.dataset.name;
                const tgNumber = this.dataset.tgNumber;
                
                document.getElementById('edit_defendant_id').value = id;
                document.getElementById('edit_name').value = name;
                document.getElementById('edit_tg_number').value = tgNumber;
            });
        });
    });
</script>

<?php include '../includes/footer.php'; ?>
