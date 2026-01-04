<?php
/**
 * Adressbuch Modul
 * Department of Justice - Aktenverwaltungssystem
 */
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Handle form submission for adding/editing contacts
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
            // Get all submitted data
            $contact_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
            $contact_name = trim($_POST['name'] ?? '');
            $contact_tg_number = trim($_POST['tg_number'] ?? '');
            $contact_category = trim($_POST['category'] ?? '');
            $contact_position = trim($_POST['position'] ?? '');
            
            // Validation
            if (empty($contact_name) || empty($contact_tg_number) || empty($contact_category)) {
                $error_message = 'Bitte füllen Sie alle Pflichtfelder aus.';
            } else {
                // Load existing address book
                $contacts = loadJsonData('address_book.json');
                
                // For new entries, generate a new ID
                if ($_POST['action'] === 'add') {
                    $contact_id = getNextId($contacts);
                    $newContact = [
                        'id' => $contact_id,
                        'name' => $contact_name,
                        'tg_number' => $contact_tg_number,
                        'category' => $contact_category,
                        'position' => $contact_position
                    ];
                    $contacts[] = $newContact;
                    $success_message = 'Kontakt erfolgreich hinzugefügt.';
                } else {
                    // Update existing contact
                    foreach ($contacts as $key => $contact) {
                        if ($contact['id'] === $contact_id) {
                            $contacts[$key]['name'] = $contact_name;
                            $contacts[$key]['tg_number'] = $contact_tg_number;
                            $contacts[$key]['category'] = $contact_category;
                            $contacts[$key]['position'] = $contact_position;
                            break;
                        }
                    }
                    $success_message = 'Kontakt erfolgreich aktualisiert.';
                }
                
                // Save to JSON file
                saveJsonData('address_book.json', $contacts);
            }
        } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
            $contact_id = intval($_POST['id']);
            
            // Load existing address book
            $contacts = loadJsonData('address_book.json');
            
            // Find and remove the contact
            foreach ($contacts as $key => $contact) {
                if ($contact['id'] === $contact_id) {
                    unset($contacts[$key]);
                    break;
                }
            }
            
            // Reindex array
            $contacts = array_values($contacts);
            
            // Save to JSON file
            saveJsonData('address_book.json', $contacts);
            
            $success_message = 'Kontakt erfolgreich gelöscht.';
        }
    }
}

// Load address book data
$contacts = loadJsonData('address_book.json');

// Available categories
$categories = [
    'Department of Justice',
    'Marshal Service',
    'Sheriffs Department',
    'U.S. Army',
    'Doctors Office',
    'Präsidium',
    'Andere'
];

// Set page title
$pageTitle = "Adressbuch";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Adressbuch</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#addContactModal">
                        <span data-feather="plus"></span>
                        Neuen Kontakt hinzufügen
                    </button>
                </div>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <!-- Search and filter bar -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" class="form-control" id="contact-search" placeholder="Suche nach Name, TG-Nummer oder Position...">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" id="clear-search">
                                <span data-feather="x"></span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="btn-group btn-group-sm float-right" role="group">
                        <button type="button" class="btn btn-outline-secondary category-filter active" data-category="all">Alle</button>
                        <?php foreach ($categories as $category): ?>
                            <button type="button" class="btn btn-outline-secondary category-filter" data-category="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Address Book Table -->
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>TG-Nummer</th>
                            <th>Kategorie</th>
                            <th>Position</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($contacts)): ?>
                            <tr>
                                <td colspan="5" class="text-center">Keine Kontakte gefunden.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($contacts as $contact): ?>
                                <tr class="contact-row" data-category="<?php echo htmlspecialchars($contact['category']); ?>">
                                    <td><?php echo htmlspecialchars($contact['name']); ?></td>
                                    <td><?php echo htmlspecialchars($contact['tg_number']); ?></td>
                                    <td><?php echo htmlspecialchars($contact['category']); ?></td>
                                    <td><?php echo htmlspecialchars($contact['position'] ?? ''); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary btn-edit-contact" 
                                                    data-id="<?php echo $contact['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($contact['name']); ?>"
                                                    data-tg-number="<?php echo htmlspecialchars($contact['tg_number']); ?>"
                                                    data-category="<?php echo htmlspecialchars($contact['category']); ?>"
                                                    data-position="<?php echo htmlspecialchars($contact['position'] ?? ''); ?>">
                                                <span data-feather="edit"></span>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger btn-delete-contact" data-id="<?php echo $contact['id']; ?>" data-name="<?php echo htmlspecialchars($contact['name']); ?>">
                                                <span data-feather="trash-2"></span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>

<!-- Add Contact Modal -->
<div class="modal fade" id="addContactModal" tabindex="-1" role="dialog" aria-labelledby="addContactModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addContactModalLabel">Neuen Kontakt hinzufügen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="form-group mb-3">
                        <label for="contactName">Name *</label>
                        <input type="text" class="form-control" id="contactName" name="name" required>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="contactTgNumber">TG-Nummer *</label>
                        <input type="text" class="form-control" id="contactTgNumber" name="tg_number" required>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="contactCategory">Kategorie *</label>
                        <select class="form-control" id="contactCategory" name="category" required>
                            <option value="">Bitte wählen</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="contactPosition">Position</label>
                        <input type="text" class="form-control" id="contactPosition" name="position">
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

<!-- Edit Contact Modal -->
<div class="modal fade" id="editContactModal" tabindex="-1" role="dialog" aria-labelledby="editContactModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editContactModalLabel">Kontakt bearbeiten</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" id="editContactId" name="id">
                    
                    <div class="form-group mb-3">
                        <label for="editContactName">Name *</label>
                        <input type="text" class="form-control" id="editContactName" name="name" required>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="editContactTgNumber">TG-Nummer *</label>
                        <input type="text" class="form-control" id="editContactTgNumber" name="tg_number" required>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="editContactCategory">Kategorie *</label>
                        <select class="form-control" id="editContactCategory" name="category" required>
                            <option value="">Bitte wählen</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="editContactPosition">Position</label>
                        <input type="text" class="form-control" id="editContactPosition" name="position">
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

<!-- Delete Contact Modal -->
<div class="modal fade" id="deleteContactModal" tabindex="-1" role="dialog" aria-labelledby="deleteContactModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteContactModalLabel">Kontakt löschen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Sind Sie sicher, dass Sie den Kontakt <strong id="deleteContactName"></strong> löschen möchten?</p>
            </div>
            <div class="modal-footer">
                <form method="post">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" id="deleteContactId" name="id">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-danger">Löschen</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Search functionality
    const searchInput = document.getElementById('contact-search');
    const clearButton = document.getElementById('clear-search');
    let activeCategory = 'all';
    
    // Function to filter contacts based on search and category
    function filterContacts() {
        const searchTerm = searchInput.value.toLowerCase();
        
        document.querySelectorAll('.contact-row').forEach(function(row) {
            const rowCategory = row.getAttribute('data-category');
            const name = row.querySelector('td:nth-child(1)').textContent.toLowerCase();
            const tgNumber = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
            const category = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
            const position = row.querySelector('td:nth-child(4)').textContent.toLowerCase();
            
            const matchesSearch = searchTerm === '' || 
                                name.includes(searchTerm) || 
                                tgNumber.includes(searchTerm) || 
                                category.includes(searchTerm) || 
                                position.includes(searchTerm);
                                
            const matchesCategory = activeCategory === 'all' || rowCategory === activeCategory;
            
            if (matchesSearch && matchesCategory) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
    
    // Search input event
    searchInput.addEventListener('input', filterContacts);
    
    // Clear search button
    clearButton.addEventListener('click', function() {
        searchInput.value = '';
        filterContacts();
    });
    
    // Category filtering
    document.querySelectorAll('.category-filter').forEach(function(button) {
        button.addEventListener('click', function() {
            // Remove active class from all filter buttons
            document.querySelectorAll('.category-filter').forEach(function(btn) {
                btn.classList.remove('active');
            });
            
            // Add active class to clicked button
            this.classList.add('active');
            
            activeCategory = this.getAttribute('data-category');
            
            // Filter the contacts
            filterContacts();
        });
    });
    
    // Edit contact modal
    document.querySelectorAll('.btn-edit-contact').forEach(function(button) {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const tgNumber = this.getAttribute('data-tg-number');
            const category = this.getAttribute('data-category');
            const position = this.getAttribute('data-position');
            
            document.getElementById('editContactId').value = id;
            document.getElementById('editContactName').value = name;
            document.getElementById('editContactTgNumber').value = tgNumber;
            document.getElementById('editContactCategory').value = category;
            document.getElementById('editContactPosition').value = position;
            
            $('#editContactModal').modal('show');
        });
    });
    
    // Delete contact modal
    document.querySelectorAll('.btn-delete-contact').forEach(function(button) {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            
            document.getElementById('deleteContactId').value = id;
            document.getElementById('deleteContactName').textContent = name;
            
            $('#deleteContactModal').modal('show');
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>