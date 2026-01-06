<?php
/**
 * Aktenverwaltungssystem - Department of Justice
 * Öffentliches Notizensystem
 * 
 * Dieses Modul ermöglicht die gemeinsame Verwaltung von öffentlichen Notizen.
 * Alle Benutzer können Notizen lesen und kommentieren.
 * Nur der Ersteller oder Admins können Notizen bearbeiten/löschen.
 */

session_start();
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';
require_once '../includes/notifications.php';

// Überprüfen, ob der Benutzer angemeldet ist
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Enforce view permission for public notes
checkPermissionOrDie('public_notes', 'view');

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
$message = '';
$error = '';

// JSON-Dateien laden/speichern
$publicNotesFile = '../data/public_notes.json';
$commentsFile = '../data/public_notes_comments.json';

// Initialisiere Dateien falls nicht existend
if (!file_exists($publicNotesFile)) {
    file_put_contents($publicNotesFile, json_encode([]), LOCK_EX);
}
if (!file_exists($commentsFile)) {
    file_put_contents($commentsFile, json_encode([]), LOCK_EX);
}

// Lade Notizen und Kommentare
$publicNotes = getJsonData($publicNotesFile) ?: [];
$comments = getJsonData($commentsFile) ?: [];

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Neue öffentliche Notiz erstellen
    if (isset($_POST['action']) && $_POST['action'] === 'add_note') {
        checkPermissionOrDie('public_notes', 'create');
        
        $title = sanitize($_POST['title'] ?? '');
        $content = sanitize($_POST['content'] ?? '');
        $category = sanitize($_POST['category'] ?? 'General');
        
        if (empty($title) || empty($content)) {
            $error = 'Titel und Inhalt sind erforderlich.';
        } else {
            $newNote = [
                'id' => generateUniqueId(),
                'title' => $title,
                'content' => $content,
                'category' => $category,
                'created_by' => $user_id,
                'created_by_name' => $username,
                'date_created' => date('Y-m-d H:i:s'),
                'date_updated' => date('Y-m-d H:i:s'),
                'updated_by' => $user_id,
                'is_pinned' => false
            ];
            
            array_unshift($publicNotes, $newNote);
            if (file_put_contents($publicNotesFile, json_encode($publicNotes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX)) {
                $message = 'Öffentliche Notiz erfolgreich erstellt.';
            } else {
                $error = 'Fehler beim Speichern der Notiz.';
            }
        }
    }
    
    // Notiz bearbeiten
    else if (isset($_POST['action']) && $_POST['action'] === 'edit_note') {
        checkPermissionOrDie('public_notes', 'edit');
        
        $note_id = $_POST['note_id'] ?? '';
        $title = sanitize($_POST['title'] ?? '');
        $content = sanitize($_POST['content'] ?? '');
        
        // Finde die Notiz
        $noteIndex = null;
        foreach ($publicNotes as $idx => $note) {
            if ($note['id'] === $note_id) {
                $noteIndex = $idx;
                break;
            }
        }
        
        if ($noteIndex === null) {
            $error = 'Notiz nicht gefunden.';
        } else if ($publicNotes[$noteIndex]['created_by'] !== $user_id && !$is_admin) {
            $error = 'Du darfst diese Notiz nicht bearbeiten.';
        } else if (empty($title) || empty($content)) {
            $error = 'Titel und Inhalt sind erforderlich.';
        } else {
            $publicNotes[$noteIndex]['title'] = $title;
            $publicNotes[$noteIndex]['content'] = $content;
            $publicNotes[$noteIndex]['date_updated'] = date('Y-m-d H:i:s');
            $publicNotes[$noteIndex]['updated_by'] = $user_id;
            
            if (file_put_contents($publicNotesFile, json_encode($publicNotes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX)) {
                $message = 'Notiz erfolgreich aktualisiert.';
            } else {
                $error = 'Fehler beim Speichern der Änderungen.';
            }
        }
    }
    
    // Notiz löschen
    else if (isset($_POST['action']) && $_POST['action'] === 'delete_note') {
        checkPermissionOrDie('public_notes', 'delete');
        
        $note_id = $_POST['note_id'] ?? '';
        
        // Finde die Notiz
        $noteIndex = null;
        foreach ($publicNotes as $idx => $note) {
            if ($note['id'] === $note_id) {
                $noteIndex = $idx;
                break;
            }
        }
        
        if ($noteIndex === null) {
            $error = 'Notiz nicht gefunden.';
        } else if ($publicNotes[$noteIndex]['created_by'] !== $user_id && !$is_admin) {
            $error = 'Du darfst diese Notiz nicht löschen.';
        } else {
            array_splice($publicNotes, $noteIndex, 1);
            
            if (file_put_contents($publicNotesFile, json_encode($publicNotes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX)) {
                $message = 'Notiz erfolgreich gelöscht.';
            } else {
                $error = 'Fehler beim Löschen der Notiz.';
            }
        }
    }
    
    // Kommentar hinzufügen
    else if (isset($_POST['action']) && $_POST['action'] === 'add_comment') {
        $note_id = $_POST['note_id'] ?? '';
        $comment_text = sanitize($_POST['comment_text'] ?? '');
        
        if (empty($comment_text)) {
            $error = 'Kommentar kann nicht leer sein.';
        } else {
            $comment = [
                'id' => generateUniqueId(),
                'note_id' => $note_id,
                'user_id' => $user_id,
                'username' => $username,
                'text' => $comment_text,
                'date_created' => date('Y-m-d H:i:s')
            ];
            
            $comments[] = $comment;
            if (file_put_contents($commentsFile, json_encode($comments, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX)) {
                // Finde die Notiz für Benachrichtigung
                $note = null;
                foreach ($publicNotes as $n) {
                    if ($n['id'] === $note_id) {
                        $note = $n;
                        break;
                    }
                }
                
                if ($note) {
                    // Lade alle Benutzer die bereits kommentiert haben + Ersteller
                    $usersFile = '../data/users.json';
                    $users = getJsonData($usersFile) ?: [];
                    
                    // Sammle alle Benutzer die benachrichtigt werden sollen
                    $notifyUsers = [];
                    
                    // Ersteller benachrichtigen (wenn nicht der Kommentierende)
                    if ($note['user_id'] !== $user_id) {
                        $notifyUsers[$note['user_id']] = true;
                    }
                    
                    // Alle die bereits kommentiert haben benachrichtigen
                    foreach ($comments as $c) {
                        if ($c['note_id'] === $note_id && $c['user_id'] !== $user_id) {
                            $notifyUsers[$c['user_id']] = true;
                        }
                    }
                    
                    // Erstelle Benachrichtigungen
                    $commenterName = $username;
                    foreach ($users as $u) {
                        if ($u['id'] === $user_id) {
                            $commenterName = isset($u['first_name']) && isset($u['last_name']) ?
                                $u['first_name'] . ' ' . $u['last_name'] : $u['username'];
                            break;
                        }
                    }
                    
                    foreach (array_keys($notifyUsers) as $notifyUserId) {
                        createNotification(
                            $notifyUserId,
                            'public_note_comment',
                            'Neuer Kommentar zu öffentlicher Notiz',
                            $commenterName . ' hat die Notiz "' . $note['title'] . '" kommentiert.',
                            'modules/public_notes.php',
                            $note_id
                        );
                    }
                }
                
                $message = 'Kommentar hinzugefügt.';
            } else {
                $error = 'Fehler beim Speichern des Kommentars.';
            }
        }
    }
    
    // Kommentar löschen (nur Ersteller oder Admin)
    else if (isset($_POST['action']) && $_POST['action'] === 'delete_comment') {
        $comment_id = $_POST['comment_id'] ?? '';
        
        $commentIndex = null;
        foreach ($comments as $idx => $comment) {
            if ($comment['id'] === $comment_id) {
                $commentIndex = $idx;
                break;
            }
        }
        
        if ($commentIndex === null) {
            $error = 'Kommentar nicht gefunden.';
        } else if ($comments[$commentIndex]['user_id'] !== $user_id && !$is_admin) {
            $error = 'Du darfst diesen Kommentar nicht löschen.';
        } else {
            array_splice($comments, $commentIndex, 1);
            if (file_put_contents($commentsFile, json_encode($comments, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX)) {
                $message = 'Kommentar gelöscht.';
            } else {
                $error = 'Fehler beim Löschen des Kommentars.';
            }
        }
    }
}

// Helper function to get comments for a note
function getCommentsForNote($noteId, $comments) {
    return array_filter($comments, function($comment) use ($noteId) {
        return $comment['note_id'] === $noteId;
    });
}

$pageTitle = "Öffentliche Notizen";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Öffentliche Notizen</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <?php if (currentUserCan('public_notes', 'create')): ?>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#addNoteModal">
                            <span data-feather="plus-circle"></span> Neue Notiz
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Öffentliche Notizen -->
            <div class="row">
                <?php foreach ($publicNotes as $note): 
                    $noteComments = getCommentsForNote($note['id'], $comments);
                    $isOwner = $note['created_by'] === $user_id;
                ?>
                    <div class="col-12 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-start">
                                <div>
                                    <h5 class="card-title mb-1">
                                        <?php echo htmlspecialchars($note['title']); ?>
                                        <?php if (!empty($note['category'])): ?>
                                            <span class="badge badge-secondary"><?php echo htmlspecialchars($note['category']); ?></span>
                                        <?php endif; ?>
                                    </h5>
                                    <small class="text-muted">
                                        Von <strong><?php echo htmlspecialchars($note['created_by_name']); ?></strong>
                                        am <?php echo date('d.m.Y H:i', strtotime($note['date_created'])); ?>
                                        <?php if ($note['date_updated'] !== $note['date_created']): ?>
                                            | Aktualisiert: <?php echo date('d.m.Y H:i', strtotime($note['date_updated'])); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div>
                                    <?php if ($isOwner || $is_admin): ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#editNoteModal<?php echo $note['id']; ?>">
                                            <span data-feather="edit-2"></span>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-toggle="modal" data-target="#deleteNoteModal<?php echo $note['id']; ?>">
                                            <span data-feather="trash-2"></span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <p class="card-text"><?php echo nl2br(htmlspecialchars($note['content'])); ?></p>
                            </div>
                            
                            <!-- Kommentare Section -->
                            <div class="card-footer bg-light">
                                <h6 class="mb-3">Kommentare (<?php echo count($noteComments); ?>)</h6>
                                
                                <div class="comments-list mb-3" style="max-height: 300px; overflow-y: auto;">
                                    <?php if (empty($noteComments)): ?>
                                        <p class="text-muted small">Keine Kommentare vorhanden.</p>
                                    <?php else: ?>
                                        <?php foreach ($noteComments as $comment): ?>
                                            <div class="comment mb-3 pb-3 border-bottom">
                                                <strong><?php echo htmlspecialchars($comment['username']); ?></strong>
                                                <small class="text-muted d-block"><?php echo date('d.m.Y H:i', strtotime($comment['date_created'])); ?></small>
                                                <p class="mb-0 mt-1"><?php echo nl2br(htmlspecialchars($comment['text'])); ?></p>
                                                
                                                <?php if ($comment['user_id'] === $user_id || $is_admin): ?>
                                                    <form method="post" style="display: inline;">
                                                        <input type="hidden" name="action" value="delete_comment">
                                                        <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-link text-danger p-0" onclick="return confirm('Kommentar wirklich löschen?');">
                                                            Löschen
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Kommentar eingeben -->
                                <form method="post" class="mt-3">
                                    <input type="hidden" name="action" value="add_comment">
                                    <input type="hidden" name="note_id" value="<?php echo $note['id']; ?>">
                                    <div class="input-group">
                                        <input type="text" class="form-control form-control-sm" name="comment_text" placeholder="Kommentar schreiben..." required>
                                        <div class="input-group-append">
                                            <button type="submit" class="btn btn-sm btn-outline-primary">Senden</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Edit Note Modal -->
                    <?php if ($isOwner || $is_admin): ?>
                        <div class="modal fade" id="editNoteModal<?php echo $note['id']; ?>" tabindex="-1" role="dialog" aria-hidden="true">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <form method="post">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Notiz bearbeiten</h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="action" value="edit_note">
                                            <input type="hidden" name="note_id" value="<?php echo $note['id']; ?>">
                                            
                                            <div class="form-group">
                                                <label for="title<?php echo $note['id']; ?>">Titel</label>
                                                <input type="text" class="form-control" id="title<?php echo $note['id']; ?>" name="title" value="<?php echo htmlspecialchars($note['title']); ?>" required>
                                            </div>
                                            <div class="form-group">
                                                <label for="content<?php echo $note['id']; ?>">Inhalt</label>
                                                <textarea class="form-control" id="content<?php echo $note['id']; ?>" name="content" rows="5" required><?php echo htmlspecialchars($note['content']); ?></textarea>
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
                        
                        <!-- Delete Note Modal -->
                        <div class="modal fade" id="deleteNoteModal<?php echo $note['id']; ?>" tabindex="-1" role="dialog" aria-hidden="true">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Notiz löschen</h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                        <p>Möchtest du diese Notiz wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.</p>
                                    </div>
                                    <div class="modal-footer">
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_note">
                                            <input type="hidden" name="note_id" value="<?php echo $note['id']; ?>">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                                            <button type="submit" class="btn btn-danger">Löschen</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                <?php endforeach; ?>
                
                <?php if (empty($publicNotes)): ?>
                    <div class="col-12">
                        <div class="alert alert-info text-center">
                            <p>Keine öffentlichen Notizen vorhanden.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<!-- Add Note Modal -->
<?php if (currentUserCan('public_notes', 'create')): ?>
    <div class="modal fade" id="addNoteModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">Neue öffentliche Notiz</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_note">
                        
                        <div class="form-group">
                            <label for="title">Titel</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="form-group">
                            <label for="category">Kategorie</label>
                            <input type="text" class="form-control" id="category" name="category" value="General">
                        </div>
                        <div class="form-group">
                            <label for="content">Inhalt</label>
                            <textarea class="form-control" id="content" name="content" rows="5" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn btn-primary">Erstellen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
