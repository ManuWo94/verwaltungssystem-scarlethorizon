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

// Get case ID from URL
if (!isset($_GET['id'])) {
    header('Location: cases.php');
    exit;
}

$case_id = $_GET['id'];
$case = findById('cases.json', $case_id);

if (!$case) {
    $error = 'Fall nicht gefunden.';
}

// Überprüfe den Fall auf Verjährung
if ($case) {
    $case = checkCaseExpiration($case);
}

// Lade zugehörige Klageschriften zu diesem Fall
$indictments = loadJsonData('indictments.json');
$case_indictments = array_filter($indictments, function($indictment) use ($case_id) {
    return isset($indictment['case_id']) && $indictment['case_id'] === $case_id;
});

// Lade Benutzerinformationen für Anzeigenamen
$users = getAllUsers();
$userMap = [];
foreach ($users as $user) {
    $userMap[$user['username']] = $user;
}

// Wir verwenden die formatDateTime Funktion aus functions.php
// Diese lokale Version wird nicht mehr benötigt
/*
function formatDateTime($dateString) {
    if (empty($dateString)) {
        return '';
    }
    
    $date = new DateTime($dateString);
    return $date->format('d.m.Y H:i');
}
*/

// Holen Sie Richter und Staatsanwälte für die Anzeige
$judges = array_filter($users, function($user) {
    if ($user['role'] === 'Judge') {
        return true;
    }
    if (isset($user['roles']) && is_array($user['roles'])) {
        return in_array('Judge', $user['roles']);
    }
    return false;
});

$prosecutors = array_filter($users, function($user) {
    if ($user['role'] === 'Prosecutor') {
        return true;
    }
    if (isset($user['roles']) && is_array($user['roles'])) {
        return in_array('Prosecutor', $user['roles']);
    }
    return false;
});

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Falldetails</h1>
                <div>
                    <a href="cases.php" class="btn btn-secondary">
                        <span data-feather="arrow-left"></span> Zurück zur Fallübersicht
                    </a>
                    <a href="case_edit.php?id=<?php echo $case_id; ?>" class="btn btn-primary">
                        <span data-feather="edit"></span> Fall bearbeiten
                    </a>

                    <!-- Aktionen für Staatsanwälte und Führungskräfte -->
                    <?php
                    // Debug-Ausgabe (temporär)
                    echo "<!-- DEBUG: Benutzerrolle: " . htmlspecialchars($_SESSION['role']) . " -->";
                    
                    // Verwende die checkUserHasRoleType Funktion für konsistente Rollenüberprüfung
                    $userRole = $_SESSION['role'];
                    $isProsecutor = checkUserHasRoleType($userRole, 'prosecutor');
                    $isLeadership = checkUserHasRoleType($userRole, 'leadership');
                    $isJudge = checkUserHasRoleType($userRole, 'judge');
                    $isMarshal = checkUserHasRoleType($userRole, 'marshal');
                    
                    // Debug-Output für Rollenprüfung
                    echo "<!-- DEBUG: isProsecutor: " . ($isProsecutor ? "true" : "false") . " -->";
                    echo "<!-- DEBUG: isLeadership: " . ($isLeadership ? "true" : "false") . " -->";
                    echo "<!-- DEBUG: isJudge: " . ($isJudge ? "true" : "false") . " -->";
                    echo "<!-- DEBUG: isMarshal: " . ($isMarshal ? "true" : "false") . " -->";
                    
                    // Wenn Benutzer Staatsanwalt oder Leitung ist - Klageschrift Button
                    if ($isProsecutor || $isLeadership):
                    ?>
                    <a href="case_edit.php?id=<?php echo $case_id; ?>#indictment" class="btn btn-success">
                        <span data-feather="file-plus"></span> Klageschrift einreichen
                    </a>
                    <?php endif; ?>

                    <!-- Revision beantragen Button für Staatsanwälte -->
                    <?php if (($isProsecutor || $isLeadership) && (isset($case['status']) && ($case['status'] === 'completed' || $case['status'] === 'rejected'))): ?>
                    <a href="case_edit.php?id=<?php echo $case_id; ?>#revision" class="btn btn-warning">
                        <span data-feather="refresh-cw"></span> Revision beantragen
                    </a>
                    <?php endif; ?>

                    <!-- Außergerichtlicher Deal Button für Staatsanwälte -->
                    <?php if (($isProsecutor || $isLeadership) && isset($case['status']) && 
                            ($case['status'] === 'open' || $case['status'] === 'in_progress' || $case['status'] === 'pending')): ?>
                    <a href="case_edit.php?id=<?php echo $case_id; ?>#settlement" class="btn btn-info">
                        <span data-feather="users"></span> Außergerichtlicher Deal
                    </a>
                    <?php endif; ?>
                    
                    <!-- Fall schließen Button, abhängig vom Status anzeigen -->
                    <?php 
                    $caseStatus = $case['status'] ?? '';
                    if (($isProsecutor || $isLeadership) && 
                            ($caseStatus !== 'closed' && $caseStatus !== 'abgeschlossen' && $caseStatus !== 'completed')): ?>
                    <a href="case_edit.php?id=<?php echo $case_id; ?>#close" class="btn btn-danger">
                        <span data-feather="archive"></span> Fall schließen
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($case): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Aktenzeichen: <?php echo htmlspecialchars($case['id']); ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Fallinformationen</h5>
                                <table class="table table-bordered">
                                    <tr>
                                        <th style="width: 30%">Angeklagter</th>
                                        <td><?php echo htmlspecialchars($case['defendant']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Anklage</th>
                                        <td><?php echo htmlspecialchars($case['charge']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Status</th>
                                        <td>
                                            <?php 
                                                $statusClass = 'secondary';
                                                $status = strtolower($case['status']);
                                                
                                                if ($status === 'open' || $status === 'offen') {
                                                    $statusClass = 'info';
                                                } elseif ($status === 'in progress' || $status === 'in bearbeitung') {
                                                    $statusClass = 'primary';
                                                } elseif ($status === 'pending trial' || $status === 'anhängiges verfahren') {
                                                    $statusClass = 'warning';
                                                } elseif ($status === 'closed' || $status === 'abgeschlossen') {
                                                    $statusClass = 'success';
                                                } elseif ($status === 'dismissed' || $status === 'eingestellt') {
                                                    $statusClass = 'danger';
                                                } elseif ($status === 'appealed' || $status === 'berufung eingelegt') {
                                                    $statusClass = 'dark';
                                                } elseif ($status === 'revision_requested' || $status === 'revision beantragt') {
                                                    $statusClass = 'info';
                                                } elseif ($status === 'revision_in_progress' || $status === 'revision in bearbeitung') {
                                                    $statusClass = 'primary';
                                                } elseif ($status === 'revision_completed' || $status === 'revision abgeschlossen') {
                                                    $statusClass = 'success';
                                                } elseif ($status === 'revision_rejected' || $status === 'revision abgelehnt') {
                                                    $statusClass = 'danger';
                                                } elseif ($status === 'revision_verdict' || $status === 'revisionsurteil') {
                                                    $statusClass = 'primary';
                                                } elseif ($status === 'pending' || $status === 'klageschrift eingereicht') {
                                                    $statusClass = 'info';
                                                } elseif ($status === 'accepted' || $status === 'klage angenommen') {
                                                    $statusClass = 'primary';
                                                } elseif ($status === 'scheduled' || $status === 'terminiert') {
                                                    $statusClass = 'warning';
                                                } elseif ($status === 'rejected' || $status === 'abgelehnt') {
                                                    $statusClass = 'danger';
                                                } elseif ($status === 'plea_deal_offered' || $status === 'außergerichtlicher deal angeboten') {
                                                    $statusClass = 'primary';
                                                } elseif ($status === 'plea_deal_accepted' || $status === 'außergerichtlicher deal angenommen') {
                                                    $statusClass = 'success';
                                                } elseif ($status === 'plea_deal_rejected' || $status === 'außergerichtlicher deal abgelehnt') {
                                                    $statusClass = 'danger';
                                                }
                                            ?>
                                            <?php 
                                                // Konvertiere englischen Status zu deutschen Anzeigetexten
                                                $displayStatus = mapStatusToGerman($case['status']);
                                            ?>
                                            <span class="badge badge-<?php echo $statusClass; ?>"><?php echo htmlspecialchars($displayStatus); ?></span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h5>Daten und Zuständigkeiten</h5>
                                <table class="table table-bordered">
                                    <tr>
                                        <th style="width: 30%">Vorfallsdatum</th>
                                        <td><?php echo formatDate($case['incident_date']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Verjährungsdatum</th>
                                        <td>
                                            <?php 
                                                echo formatDate($case['expiration_date']);
                                                $expirationDate = strtotime($case['expiration_date']);
                                                $today = time();
                                                $daysRemaining = floor(($expirationDate - $today) / (60 * 60 * 24));
                                                
                                                if ($daysRemaining < 0) {
                                                    echo ' <span class="badge badge-danger">Abgelaufen</span>';
                                                } elseif ($daysRemaining < 7) {
                                                    echo ' <span class="badge badge-warning">' . $daysRemaining . ' Tage übrig</span>';
                                                }
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Bezirk</th>
                                        <td><?php echo htmlspecialchars($case['district'] ?? ''); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Kaution</th>
                                        <td><?php echo htmlspecialchars($case['bail_amount'] ?? ''); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Staatsanwalt</th>
                                        <td><?php echo htmlspecialchars($case['prosecutor'] ?? ''); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Richter</th>
                                        <td><?php echo htmlspecialchars($case['judge'] ?? ''); ?></td>
                                    </tr>
                                    <?php if (!empty($case['witnesses'])): ?>
                                    <tr>
                                        <th>Zeugen</th>
                                        <td>
                                            <pre style="margin: 0; font-family: inherit; white-space: pre-wrap;"><?php echo htmlspecialchars($case['witnesses']); ?></pre>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if (!empty($case['victims'])): ?>
                                    <tr>
                                        <th>Geschädigte</th>
                                        <td>
                                            <pre style="margin: 0; font-family: inherit; white-space: pre-wrap;"><?php echo htmlspecialchars($case['victims']); ?></pre>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>

                        <!-- Revisionsurteil Anzeige, wenn ein Revisionsurteil vorliegt -->
                        <?php if ($status === 'revision_verdict' || $status === 'revisionsurteil'): ?>
                        <div class="mt-4">
                            <h5 class="text-primary">Revisionsurteil</h5>
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <strong>Revisionsurteil vom <?php echo formatDate($case['date_updated']); ?></strong>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($case['revision_notes'])): ?>
                                        <div class="revision-content border p-3 mb-3">
                                            <?php echo nl2br(htmlspecialchars($case['revision_notes'])); ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="revision-content border p-3 mb-3">
                                            <?php echo nl2br(htmlspecialchars($case['notes'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <p><strong>Richter:</strong> <?php echo htmlspecialchars($case['judge'] ?? 'Nicht angegeben'); ?></p>
                                        </div>
                                        <div class="col-md-6 text-right">
                                            <p><strong>Aktenzeichen:</strong> <?php echo htmlspecialchars($case['case_number']); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Klageschriften Abschnitt -->
                        <div class="mt-4">
                            <h5>Klageschriften</h5>
                            <?php if (count($case_indictments) > 0): ?>
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Aktennummer</th>
                                            <th>Titel</th>
                                            <th>Erstellt am</th>
                                            <th>Erstellt von</th>
                                            <th>Status</th>
                                            <th>Aktionen</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($case_indictments as $indictment): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars(substr($indictment['id'], 0, 8)); ?></td>
                                                <td><?php echo htmlspecialchars($indictment['case_id'] ?? 'N/A'); ?></td>
                                                <td><?php 
                                                    // Extrahiere einen Titel aus dem Content oder zeige Standardtext
                                                    $title = "Klageschrift";
                                                    if (!empty($indictment['content'])) {
                                                        // Versuche, die erste Zeile als Titel zu verwenden
                                                        $contentLines = explode("\n", $indictment['content']);
                                                        if (!empty($contentLines[0])) {
                                                            $title = trim($contentLines[0]);
                                                        }
                                                    }
                                                    echo htmlspecialchars($title);
                                                ?></td>
                                                <td><?php echo formatDateTime($indictment['date_created']); ?></td>
                                                <td>
                                                    <?php 
                                                        $creatorUsername = $indictment['created_by'] ?? '';
                                                        echo htmlspecialchars($creatorUsername);
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                        $indictmentStatus = $indictment['status'] ?? 'pending';
                                                        $indictmentStatusText = mapStatusToGerman($indictmentStatus);
                                                        $statusClass = 'secondary';
                                                        
                                                        if ($indictmentStatus === 'pending') {
                                                            $statusClass = 'info';
                                                        } elseif ($indictmentStatus === 'accepted') {
                                                            $statusClass = 'success';
                                                        } elseif ($indictmentStatus === 'rejected') {
                                                            $statusClass = 'danger';
                                                        } elseif ($indictmentStatus === 'scheduled') {
                                                            $statusClass = 'warning';
                                                        } elseif ($indictmentStatus === 'completed') {
                                                            $statusClass = 'dark';
                                                        }
                                                    ?>
                                                    <span class="badge badge-<?php echo $statusClass; ?>">
                                                        <?php echo htmlspecialchars($indictmentStatusText); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="indictments.php?id=<?php echo $indictment['id']; ?>&view=detail" class="btn btn-sm btn-info">
                                                        <span data-feather="eye"></span> Ansehen
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="alert alert-info">Keine Klageschriften für diesen Fall vorhanden.</div>
                            <?php endif; ?>
                        </div>

                        <!-- Urteilsbereich hinzufügen, wenn Status "Klage angenommen" oder "Terminiert" -->
                        <?php if ($status === 'accepted' || $status === 'klage angenommen' || $status === 'scheduled' || $status === 'terminiert'): ?>
                            <div class="mt-4">
                                <h5>Urteil hinzufügen</h5>
                                <form method="post" action="case_edit.php">
                                    <input type="hidden" name="action" value="add_verdict">
                                    <input type="hidden" name="case_id" value="<?php echo $case_id; ?>">
                                    
                                    <div class="form-group">
                                        <label for="verdict_text">Urteilsspruch</label>
                                        <textarea class="form-control" id="verdict_text" name="verdict_text" rows="4" required></textarea>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="verdict_date">Urteilsdatum</label>
                                        <input type="date" class="form-control" id="verdict_date" name="verdict_date" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="verdict_status">Fall-Status nach Urteil</label>
                                        <select class="form-control" id="verdict_status" name="verdict_status" required>
                                            <option value="completed">Abgeschlossen</option>
                                            <option value="appealed">Berufung eingelegt</option>
                                            <option value="dismissed">Eingestellt</option>
                                        </select>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">Urteil speichern</button>
                                </form>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Urteilsanzeige, wenn ein Urteil vorhanden ist -->
                        <?php if (isset($case['verdict']) && !empty($case['verdict'])): ?>
                            <div class="mt-4">
                                <h5>Urteil</h5>
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-2 text-muted">Datum: <?php echo formatDate($case['verdict_date'] ?? ''); ?></h6>
                                        <p class="card-text"><?php echo nl2br(htmlspecialchars($case['verdict'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Revisionsurteil Anzeige, wenn ein Revisionsurteil und ein Urteil vorhanden sind -->
                        <?php if (isset($case['verdict']) && !empty($case['verdict']) && 
                                 (isset($case['revision_verdict']) && !empty($case['revision_verdict']) || 
                                  (isset($case['status']) && ($case['status'] === 'revision_completed' || $case['status'] === 'revision abgeschlossen' || 
                                   $case['status'] === 'revision_verdict' || $case['status'] === 'revisionsurteil')))): ?>
                            <div class="mt-4">
                                <h5>Revisionsurteil</h5>
                                <div class="card border-primary">
                                    <div class="card-header bg-primary text-white">
                                        <strong>Revisionsurteil vom <?php echo formatDate($case['revision_date'] ?? $case['date_updated'] ?? ''); ?></strong>
                                    </div>
                                    <div class="card-body">
                                        <?php if (isset($case['revision_verdict']) && !empty($case['revision_verdict'])): ?>
                                            <p class="card-text"><?php echo nl2br(htmlspecialchars($case['revision_verdict'])); ?></p>
                                        <?php elseif (isset($case['revision_notes']) && !empty($case['revision_notes'])): ?>
                                            <p class="card-text"><?php echo nl2br(htmlspecialchars($case['revision_notes'])); ?></p>
                                        <?php else: ?>
                                            <p class="card-text"><?php echo nl2br(htmlspecialchars($case['notes'])); ?></p>
                                        <?php endif; ?>
                                        
                                        <div class="text-right mt-2">
                                            <small class="text-muted">Entscheidung durch: <?php echo htmlspecialchars($case['revision_judge'] ?? $case['judge'] ?? 'Nicht angegeben'); ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Anzeige für abgeschlossenen außergerichtlichen Deal -->
                        <?php if (isset($case['settlement_details']) && !empty($case['settlement_details'])): ?>
                            <div class="mt-4">
                                <h5>Außergerichtlicher Deal (Abgeschlossen)</h5>
                                <div class="card border-success">
                                    <div class="card-body">
                                        <p><strong>Abgeschlossen am:</strong> <?php echo formatDateTime($case['closed_date'] ?? ''); ?></p>
                                        <p><strong>Abgeschlossen von:</strong> <?php echo htmlspecialchars($case['closed_by_name'] ?? ''); ?></p>
                                        <div class="mt-3">
                                            <h6>Details des Deals:</h6>
                                            <div class="p-3 bg-light rounded">
                                                <pre style="margin: 0; font-family: inherit; white-space: pre-wrap;"><?php echo htmlspecialchars($case['settlement_details']); ?></pre>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Außergerichtlicher Deal Anzeige (noch nicht abgeschlossen) -->
                        <?php if (isset($case['plea_deal']) && !empty($case['plea_deal'])): ?>
                            <div class="mt-4">
                                <h5>Außergerichtlicher Deal</h5>
                                <div class="card">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p><strong>Status:</strong> 
                                                    <?php 
                                                    $dealStatus = $case['plea_deal']['status'] ?? 'pending';
                                                    $dealStatusText = '';
                                                    $dealStatusClass = 'secondary';
                                                    
                                                    if ($dealStatus === 'pending') {
                                                        $dealStatusText = 'Angeboten';
                                                        $dealStatusClass = 'primary';
                                                    } elseif ($dealStatus === 'accepted') {
                                                        $dealStatusText = 'Angenommen';
                                                        $dealStatusClass = 'success';
                                                    } elseif ($dealStatus === 'rejected') {
                                                        $dealStatusText = 'Abgelehnt';
                                                        $dealStatusClass = 'danger';
                                                    }
                                                    ?>
                                                    <span class="badge badge-<?php echo $dealStatusClass; ?>"><?php echo $dealStatusText; ?></span>
                                                </p>
                                                <p><strong>Angeboten von:</strong> <?php echo htmlspecialchars($case['plea_deal']['offered_by'] ?? 'Unbekannt'); ?></p>
                                                <p><strong>Angeboten am:</strong> <?php echo formatDateTime($case['plea_deal']['date_offered'] ?? ''); ?></p>
                                                <?php if (!empty($case['plea_deal']['reduced_charge'])): ?>
                                                    <p><strong>Reduzierte Anklage:</strong> <?php echo htmlspecialchars($case['plea_deal']['reduced_charge']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-6">
                                                <?php if (isset($case['plea_deal']['date_processed'])): ?>
                                                    <p><strong>Verarbeitet am:</strong> <?php echo formatDateTime($case['plea_deal']['date_processed']); ?></p>
                                                <?php endif; ?>
                                                
                                                <?php if (isset($case['plea_deal']['processed_by'])): ?>
                                                    <p><strong>Verarbeitet von:</strong> <?php echo htmlspecialchars($case['plea_deal']['processed_by']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-3">
                                            <h6>Bedingungen des Deals:</h6>
                                            <div class="p-3 bg-light rounded">
                                                <pre style="margin: 0; font-family: inherit; white-space: pre-wrap;"><?php echo htmlspecialchars($case['plea_deal']['terms'] ?? ''); ?></pre>
                                            </div>
                                        </div>
                                        
                                        <?php if ($dealStatus === 'pending' && ($isDefendant || $isJudge || $isLeadership)): ?>
                                            <div class="mt-3">
                                                <form method="post" action="case_edit.php">
                                                    <input type="hidden" name="action" value="process_plea_deal">
                                                    <input type="hidden" name="case_id" value="<?php echo $case_id; ?>">
                                                    
                                                    <div class="form-group">
                                                        <label for="plea_deal_response">Antwort:</label>
                                                        <select class="form-control" id="plea_deal_response" name="plea_deal_response" required>
                                                            <option value="">-- Bitte wählen --</option>
                                                            <option value="accepted">Annehmen</option>
                                                            <option value="rejected">Ablehnen</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <button type="submit" class="btn btn-primary">Antwort absenden</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>