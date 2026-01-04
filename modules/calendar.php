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

// Handle event actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'delete' && isset($_POST['event_id'])) {
            $eventId = $_POST['event_id'];
            
            if (deleteRecord('calendar.json', $eventId)) {
                $message = 'Event deleted successfully.';
            } else {
                $error = 'Failed to delete event.';
            }
        }
    } else {
        // Handle event creation/edit
        $eventData = [
            'title' => sanitize($_POST['event_title'] ?? ''),
            'date' => sanitize($_POST['event_date'] ?? ''),
            'time' => sanitize($_POST['event_time'] ?? ''),
            'description' => sanitize($_POST['event_description'] ?? ''),
            'created_by' => $user_id,
            'created_by_name' => $username
        ];
        
        // Validate required fields
        if (empty($eventData['title']) || empty($eventData['date'])) {
            $error = 'Please fill in all required fields.';
        } else {
            if (isset($_POST['event_id']) && !empty($_POST['event_id'])) {
                // Update existing event
                $eventId = $_POST['event_id'];
                
                if (updateRecord('calendar.json', $eventId, $eventData)) {
                    $message = 'Event updated successfully.';
                } else {
                    $error = 'Failed to update event.';
                }
            } else {
                // Create new event
                $eventData['id'] = generateUniqueId();
                
                if (insertRecord('calendar.json', $eventData)) {
                    $message = 'Event created successfully.';
                } else {
                    $error = 'Failed to create event.';
                }
            }
        }
    }
}

// Deutsche Monatsnamen
$monatsnamen = [
    1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
    5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'
];

// Standard ist heute bei Tagesansicht
$day = isset($_GET['day']) ? (int)$_GET['day'] : (int)date('d');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Validate day, month and year
if ($day < 1 || $day > 31) {
    $day = (int)date('d');
}
if ($month < 1 || $month > 12) {
    $month = (int)date('m');
}
if ($year < 1800 || $year > 2100) {
    $year = (int)date('Y');
}

// Get first day of the month for navigation
$firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year);
$numberDays = date('t', $firstDayOfMonth);
if ($day > $numberDays) {
    $day = $numberDays;
}

// Aktuelles Datum korrigieren falls nötig
$currentDate = mktime(0, 0, 0, $month, $day, $year);
$dateComponents = getdate($currentDate);
$monthName = $monatsnamen[$month];
$dayOfWeek = $dateComponents['wday'];

// Get all events for this month (including court proceedings)
$events = loadJsonData('calendar.json');
$indictments = loadJsonData('indictments.json');
$monthEvents = [];

// Normale Kalenderereignisse verarbeiten
foreach ($events as $event) {
    $eventDate = explode('-', $event['date']);
    if ((int)$eventDate[0] === $year && (int)$eventDate[1] === $month) {
        $eventDay = (int)$eventDate[2];
        if (!isset($monthEvents[$eventDay])) {
            $monthEvents[$eventDay] = [];
        }
        $monthEvents[$eventDay][] = $event;
    }
}

// Gerichtstermine hinzufügen
foreach ($indictments as $indictment) {
    if (isset($indictment['trial_date']) && !empty($indictment['trial_date']) && $indictment['status'] === 'scheduled') {
        $trialDateTime = strtotime($indictment['trial_date']);
        if ($trialDateTime) {
            $trialYear = (int)date('Y', $trialDateTime);
            $trialMonth = (int)date('m', $trialDateTime);
            $trialDay = (int)date('d', $trialDateTime);
            $trialTime = date('H:i', $trialDateTime);
            
            // Prüfen, ob der Termin im angezeigten Monat liegt
            if ($trialYear === $year && $trialMonth === $month) {
                if (!isset($monthEvents[$trialDay])) {
                    $monthEvents[$trialDay] = [];
                }
                
                // Falldetails für den Titel ermitteln
                $caseId = $indictment['case_id'] ?? '';
                $caseData = findById('cases.json', $caseId);
                $defendant = $caseData['defendant'] ?? 'Unbekannt';
                $judge = $indictment['judge_name'] ?? ($caseData['judge_name'] ?? ($caseData['judge'] ?? 'Unbekannt'));
                
                // Gerichtstermin als Ereignis hinzufügen
                $courtEvent = [
                    'id' => 'court_' . $indictment['id'],
                    'title' => 'Gerichtsverhandlung: ' . $defendant,
                    'date' => sprintf('%04d-%02d-%02d', $trialYear, $trialMonth, $trialDay),
                    'time' => $trialTime,
                    'description' => 'Richter: ' . $judge . "\nFall: " . substr($caseId, 0, 8) . "\nAngeklagter: " . $defendant,
                    'is_court_date' => true,
                    'indictment_id' => $indictment['id'],
                    'case_id' => $caseId
                ];
                
                $monthEvents[$trialDay][] = $courtEvent;
            }
        }
    }
}

// Calendar navigation
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

// Get vacation data to show on calendar
$vacations = loadJsonData('vacation.json');
$vacationData = [];

foreach ($vacations as $vacation) {
    if ($vacation['status'] === 'approved') {
        $startDate = strtotime($vacation['start_date']);
        $endDate = strtotime($vacation['end_date']);
        
        // Check if vacation period overlaps with current month
        $monthStart = mktime(0, 0, 0, $month, 1, $year);
        $monthEnd = mktime(23, 59, 59, $month, $numberDays, $year);
        
        if ($startDate <= $monthEnd && $endDate >= $monthStart) {
            // Calculate overlapping days
            $overlapStart = max($startDate, $monthStart);
            $overlapEnd = min($endDate, $monthEnd);
            
            // Add vacation data for each day
            for ($i = $overlapStart; $i <= $overlapEnd; $i += 86400) {
                $vacDay = date('j', $i);
                if (!isset($vacationData[$vacDay])) {
                    $vacationData[$vacDay] = [];
                }
                $vacationData[$vacDay][] = [
                    'user' => $vacation['user_name'],
                    'reason' => $vacation['reason']
                ];
            }
        }
    }
}
?>

<?php include '../includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4 calendar-page main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Kalender</h1>
                <div>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addEventModal">
                        <span data-feather="plus"></span> Neuer Termin
                    </button>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="calendar-container">
                <div class="calendar-header mb-4">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <h3><?php echo $monthName . ' ' . $year; ?></h3>
                        </div>
                        <div class="col-md-8">
                            <form method="get" action="calendar.php" class="form-inline justify-content-end calendar-nav-form">
                                <button type="button" class="btn btn-outline-secondary mr-2 calendar-prev-month">
                                    <span data-feather="chevron-left"></span> Vorheriger Monat
                                </button>
                                
                                <select name="month" id="month-select" class="form-control mr-2">
                                    <?php 
                                    $monate = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
                                    for($m = 1; $m <= 12; $m++): 
                                    ?>
                                        <option value="<?php echo $m; ?>" <?php echo $m === $month ? 'selected' : ''; ?>>
                                            <?php echo $monate[$m-1]; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                
                                <select name="year" id="year-select" class="form-control mr-2">
                                    <?php for($y = $year - 5; $y <= $year + 5; $y++): ?>
                                        <option value="<?php echo $y; ?>" <?php echo $y === $year ? 'selected' : ''; ?>>
                                            <?php echo $y; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                
                                <button type="button" class="btn btn-outline-secondary calendar-next-month">
                                    Nächster Monat <span data-feather="chevron-right"></span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="day-navigation d-flex justify-content-between mb-3">
                    <a href="?day=<?php echo $day-1 <= 0 ? $numberDays : $day-1; ?>&month=<?php echo $day-1 <= 0 ? $prevMonth : $month; ?>&year=<?php echo $day-1 <= 0 ? $prevYear : $year; ?>" class="btn btn-outline-secondary">
                        <span data-feather="chevron-left"></span> Vorheriger Tag
                    </a>
                    <div class="current-day-display">
                        <h4><?php 
                        $wochentage = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
                        echo $wochentage[$dayOfWeek] . ', ' . $day . '. ' . $monthName . ' ' . $year; 
                        ?></h4>
                    </div>
                    <a href="?day=<?php echo $day+1 > $numberDays ? 1 : $day+1; ?>&month=<?php echo $day+1 > $numberDays ? $nextMonth : $month; ?>&year=<?php echo $day+1 > $numberDays ? $nextYear : $year; ?>" class="btn btn-outline-secondary">
                        Nächster Tag <span data-feather="chevron-right"></span>
                    </a>
                </div>
                
                <div class="calendar-day-view">
                    <div class="day-schedule">
                        <?php
                        // Stunden anzeigen von 6 Uhr bis 22 Uhr
                        for ($hour = 6; $hour <= 22; $hour++) {
                            echo '<div class="hour-row">';
                            echo '<div class="hour-label">' . sprintf('%02d:00', $hour) . '</div>';
                            echo '<div class="hour-events">';
                            
                            // Ereignisse für diese Stunde anzeigen
                            if (isset($monthEvents[$day])) {
                                foreach ($monthEvents[$day] as $event) {
                                    $eventHour = !empty($event['time']) ? (int)substr($event['time'], 0, 2) : -1;
                                    
                                    if ($eventHour === $hour) {
                                        $eventClass = isset($event['is_court_date']) && $event['is_court_date'] ? 'event court-event' : 'event';
                                        echo '<div class="' . $eventClass . '">';
                                        echo '<div class="event-time">' . htmlspecialchars($event['time']) . '</div>';
                                        echo '<div class="event-title">' . htmlspecialchars($event['title']) . '</div>';
                                        if (!empty($event['description'])) {
                                            echo '<div class="event-description">' . htmlspecialchars($event['description']) . '</div>';
                                        }
                                        echo '<div class="event-actions">';
                                        // Nur bearbeitbar, wenn es kein Gerichtstermin ist
                                        if (!isset($event['is_court_date']) || !$event['is_court_date']) {
                                            echo '<button type="button" class="btn btn-sm btn-link edit-event" data-event-id="' . $event['id'] . '" data-title="' . htmlspecialchars($event['title']) . '" data-date="' . htmlspecialchars($event['date']) . '" data-time="' . htmlspecialchars($event['time']) . '" data-description="' . htmlspecialchars($event['description']) . '">Bearbeiten</button>';
                                        } else {
                                            // Für Gerichtstermine Link zur Klageschrift anzeigen
                                            echo '<a href="indictments.php?id=' . substr($event['id'], 6) . '&view=detail" class="btn btn-sm btn-link">Klageschrift</a>';
                                            
                                            // Link zum Fall anzeigen
                                            if (isset($event['case_id']) && !empty($event['case_id'])) {
                                                echo '<a href="case_view.php?id=' . $event['case_id'] . '" class="btn btn-sm btn-link">Fall</a>';
                                            }
                                        }
                                        echo '</div>';
                                        echo '</div>';
                                    }
                                }
                            }
                            
                            echo '</div>';
                            echo '</div>';
                        }
                        
                        // Ereignisse ohne Uhrzeit anzeigen
                        echo '<div class="no-time-events mt-4">';
                        echo '<h5>Ereignisse ohne Uhrzeit:</h5>';
                        
                        $hasEvents = false;
                        
                        // Ereignisse ohne Zeit
                        if (isset($monthEvents[$day])) {
                            foreach ($monthEvents[$day] as $event) {
                                if (empty($event['time'])) {
                                    $hasEvents = true;
                                    $eventClass = isset($event['is_court_date']) && $event['is_court_date'] ? 'event court-event' : 'event';
                                    echo '<div class="' . $eventClass . '">';
                                    echo '<div class="event-title">' . htmlspecialchars($event['title']) . '</div>';
                                    if (!empty($event['description'])) {
                                        echo '<div class="event-description">' . htmlspecialchars($event['description']) . '</div>';
                                    }
                                    echo '<div class="event-actions">';
                                    // Nur bearbeitbar, wenn es kein Gerichtstermin ist
                                    if (!isset($event['is_court_date']) || !$event['is_court_date']) {
                                        echo '<button type="button" class="btn btn-sm btn-link edit-event" data-event-id="' . $event['id'] . '" data-title="' . htmlspecialchars($event['title']) . '" data-date="' . htmlspecialchars($event['date']) . '" data-time="' . htmlspecialchars($event['time']) . '" data-description="' . htmlspecialchars($event['description']) . '">Bearbeiten</button>';
                                    } else {
                                        // Für Gerichtstermine Link zur Klageschrift anzeigen
                                        echo '<a href="indictments.php?id=' . substr($event['id'], 6) . '&view=detail" class="btn btn-sm btn-link">Klageschrift</a>';
                                        
                                        // Link zum Fall anzeigen
                                        if (isset($event['case_id']) && !empty($event['case_id'])) {
                                            echo '<a href="case_view.php?id=' . $event['case_id'] . '" class="btn btn-sm btn-link">Fall</a>';
                                        }
                                    }
                                    echo '</div>';
                                    echo '</div>';
                                }
                            }
                        }
                        
                        // Urlaubseinträge
                        if (isset($vacationData[$day])) {
                            foreach ($vacationData[$day] as $vacation) {
                                $hasEvents = true;
                                echo '<div class="event vacation-event">';
                                echo '<div class="event-title"><span data-feather="sun"></span> ' . htmlspecialchars($vacation['user']) . ' - Urlaub</div>';
                                echo '</div>';
                            }
                        }
                        
                        if (!$hasEvents) {
                            echo '<p>Keine Ereignisse für diesen Tag.</p>';
                        }
                        
                        echo '</div>';
                        ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add Event Modal -->
<div class="modal fade" id="addEventModal" tabindex="-1" aria-labelledby="addEventModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addEventModalLabel">Neuen Termin hinzufügen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="calendar.php" class="needs-validation" novalidate>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="event_title">Terminbezeichnung *</label>
                        <input type="text" class="form-control" id="event_title" name="event_title" required>
                        <div class="invalid-feedback">Bitte geben Sie einen Titel ein.</div>
                    </div>
                    <div class="form-group">
                        <label for="event_date">Datum *</label>
                        <input type="date" class="form-control" id="event_date" name="event_date" required>
                        <div class="invalid-feedback">Bitte wählen Sie ein Datum.</div>
                    </div>
                    <div class="form-group">
                        <label for="event_time">Uhrzeit</label>
                        <input type="time" class="form-control" id="event_time" name="event_time">
                    </div>
                    <div class="form-group">
                        <label for="event_description">Beschreibung</label>
                        <textarea class="form-control" id="event_description" name="event_description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Termin speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Event Modal -->
<div class="modal fade" id="editEventModal" tabindex="-1" aria-labelledby="editEventModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editEventModalLabel">Termin bearbeiten</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="calendar.php" class="needs-validation" novalidate>
                <input type="hidden" id="edit_event_id" name="event_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_event_title">Terminbezeichnung *</label>
                        <input type="text" class="form-control" id="edit_event_title" name="event_title" required>
                        <div class="invalid-feedback">Bitte geben Sie einen Titel ein.</div>
                    </div>
                    <div class="form-group">
                        <label for="edit_event_date">Datum *</label>
                        <input type="date" class="form-control" id="edit_event_date" name="event_date" required>
                        <div class="invalid-feedback">Bitte wählen Sie ein Datum.</div>
                    </div>
                    <div class="form-group">
                        <label for="edit_event_time">Uhrzeit</label>
                        <input type="time" class="form-control" id="edit_event_time" name="event_time">
                    </div>
                    <div class="form-group">
                        <label for="edit_event_description">Beschreibung</label>
                        <textarea class="form-control" id="edit_event_description" name="event_description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <form method="post" action="calendar.php" class="d-inline mr-auto">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="event_id" id="delete_event_id">
                        <button type="submit" class="btn btn-danger btn-delete">Termin löschen</button>
                    </form>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Änderungen speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Set delete_event_id when opening edit modal
        const editEventModal = document.getElementById('editEventModal');
        if (editEventModal) {
            editEventModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const eventId = button.dataset.eventId;
                document.getElementById('delete_event_id').value = eventId;
            });
        }
    });
</script>

<?php include '../includes/footer.php'; ?>
