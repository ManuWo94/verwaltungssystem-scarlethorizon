<?php
session_start();
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/notifications.php';
require_once 'autoload_db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role']; // Hauptrolle
$roles = isset($_SESSION['roles']) ? $_SESSION['roles'] : [$role]; // Alle Rollen

// Get recent cases
$cases = getRecentCases(5);

// Get upcoming calendar events
$events = getUpcomingEvents(5);

// Get duty status
$dutyStatus = getUserDutyStatus($user_id);

// Get counts for dashboard
$caseCount = getCaseCount();
$defendantCount = getDefendantCount();
$openCaseCount = getOpenCaseCount();
$upcomingEventCount = getUpcomingEventCount();

// Get notifications
$notifications = getUserNotifications($user_id, true, 10); // Nur ungelesene, max 10
$unreadCount = countUnreadNotifications($user_id);

// AJAX Handler für Benachrichtigungen
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($action === 'mark_notification_read') {
        $notificationId = isset($_POST['notification_id']) ? sanitize($_POST['notification_id']) : '';
        
        if (markNotificationAsRead($notificationId, $user_id)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Fehler beim Markieren.']);
        }
        exit;
    }
    
    if ($action === 'mark_all_read') {
        if (markAllNotificationsAsRead($user_id)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Fehler beim Markieren.']);
        }
        exit;
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>

        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Übersicht</h1>
                <div class="duty-status-indicator">
                    <?php if ($dutyStatus): ?>
                        <span class="badge badge-success">Im Dienst</span>
                    <?php else: ?>
                        <span class="badge badge-secondary">Nicht im Dienst</span>
                    <?php endif; ?>
                    <form method="post" action="modules/duty_log.php" class="d-inline ml-2">
                        <input type="hidden" name="action" value="<?php echo $dutyStatus ? 'off_duty' : 'on_duty'; ?>">
                        <button type="submit" class="btn btn-sm <?php echo $dutyStatus ? 'btn-outline-danger' : 'btn-outline-success'; ?>">
                            <?php echo $dutyStatus ? 'Dienst beenden' : 'Dienst beginnen'; ?>
                        </button>
                    </form>
                </div>
            </div>

            <div class="welcome-message mb-4">
                <h3>Willkommen, <?php echo htmlspecialchars($username); ?> 
                    <small class="text-muted">
                        (<?php echo htmlspecialchars($role); ?>
                        <?php if (count($roles) > 1): ?>
                            <span class="badge bg-info" data-toggle="tooltip" data-placement="top" 
                                  title="<?php echo htmlspecialchars(implode(', ', $roles)); ?>">
                                +<?php echo count($roles)-1; ?> weitere
                            </span>
                        <?php endif; ?>)
                    </small>
                </h3>
                <p class="lead">Heute ist der <?php echo date('d. F Y'); ?></p>
            </div>

            <!-- Dashboard Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Alle Fälle</h5>
                            <p class="card-text display-4"><?php echo $caseCount; ?></p>
                            <a href="modules/cases.php" class="card-link">Alle Fälle anzeigen</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Offene Fälle</h5>
                            <p class="card-text display-4"><?php echo $openCaseCount; ?></p>
                            <a href="modules/cases.php?status=open" class="card-link">Offene Fälle anzeigen</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Angeklagte</h5>
                            <p class="card-text display-4"><?php echo $defendantCount; ?></p>
                            <a href="modules/defendants.php" class="card-link">Angeklagte anzeigen</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Anstehende Termine</h5>
                            <p class="card-text display-4"><?php echo $upcomingEventCount; ?></p>
                            <a href="modules/calendar.php" class="card-link">Kalender anzeigen</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Benachrichtigungen -->
                <div class="col-md-12 mb-4">
                    <div class="card">
                        <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">
                                <i class="fas fa-bell"></i> Benachrichtigungen
                                <?php if ($unreadCount > 0): ?>
                                    <span class="badge badge-light ml-2"><?php echo $unreadCount; ?></span>
                                <?php endif; ?>
                            </h4>
                            <?php if ($unreadCount > 0): ?>
                                <button type="button" class="btn btn-sm btn-light" id="markAllReadBtn">
                                    <i class="fas fa-check-double"></i> Alle als gelesen markieren
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($notifications) > 0): ?>
                                <div class="list-group list-group-flush" id="notificationsList">
                                    <?php foreach ($notifications as $notification): 
                                        $iconClass = 'fa-bell';
                                        $badgeClass = 'badge-secondary';
                                        
                                        switch ($notification['type']) {
                                            case 'task':
                                                $iconClass = 'fa-tasks';
                                                $badgeClass = 'badge-warning';
                                                break;
                                            case 'public_note_comment':
                                                $iconClass = 'fa-comment';
                                                $badgeClass = 'badge-info';
                                                break;
                                            case 'case':
                                                $iconClass = 'fa-folder';
                                                $badgeClass = 'badge-primary';
                                                break;
                                        }
                                    ?>
                                        <div class="list-group-item notification-item" data-notification-id="<?php echo htmlspecialchars($notification['id']); ?>">
                                            <div class="d-flex w-100 align-items-start">
                                                <div class="mr-3">
                                                    <span class="badge <?php echo $badgeClass; ?> badge-lg">
                                                        <i class="fas <?php echo $iconClass; ?>"></i>
                                                    </span>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="d-flex w-100 justify-content-between">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                                        <small class="text-muted"><?php echo timeAgo($notification['created_at']); ?></small>
                                                    </div>
                                                    <p class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                                    <?php if (!empty($notification['link'])): ?>
                                                        <a href="<?php echo htmlspecialchars($notification['link']); ?>" class="notification-link btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-external-link-alt"></i> Details anzeigen
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="p-4 text-center text-muted">
                                    <i class="fas fa-check-circle fa-3x mb-3"></i>
                                    <p>Keine neuen Benachrichtigungen</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Recent Cases -->
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h4>Neueste Fälle</h4>
                        </div>
                        <div class="card-body">
                            <?php if (count($cases) > 0): ?>
                                <div class="list-group">
                                    <?php foreach ($cases as $case): ?>
                                        <a href="modules/case_edit.php?id=<?php echo $case['id']; ?>" class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h5 class="mb-1"><?php echo htmlspecialchars($case['defendant']); ?></h5>
                                                <small><?php echo htmlspecialchars($case['status']); ?></small>
                                            </div>
                                            <p class="mb-1"><?php echo htmlspecialchars($case['charge']); ?></p>
                                            <small>Bezirk: <?php echo htmlspecialchars($case['district']); ?> | Staatsanwalt: <?php echo htmlspecialchars($case['prosecutor']); ?></small>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">Keine Fälle gefunden.</p>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <a href="modules/cases.php" class="btn btn-outline-primary btn-sm">Alle Fälle anzeigen</a>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Events -->
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h4>Anstehende Termine</h4>
                        </div>
                        <div class="card-body">
                            <?php if (count($events) > 0): ?>
                                <div class="list-group">
                                    <?php foreach ($events as $event): ?>
                                        <a href="modules/calendar.php?date=<?php echo $event['date']; ?>" class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h5 class="mb-1"><?php echo htmlspecialchars($event['title']); ?></h5>
                                                <small><?php echo htmlspecialchars(date('d.m.Y', strtotime($event['date']))); ?></small>
                                            </div>
                                            <p class="mb-1"><?php echo htmlspecialchars($event['description']); ?></p>
                                            <small>Uhrzeit: <?php echo htmlspecialchars($event['time']); ?></small>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">Keine anstehenden Termine.</p>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <a href="modules/calendar.php" class="btn btn-outline-primary btn-sm">Kalender anzeigen</a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<style>
.notification-item {
    cursor: pointer;
    transition: background-color 0.2s;
}
.notification-item:hover {
    background-color: #f8f9fa;
}
.notification-link {
    margin-top: 0.5rem;
}
.badge-lg {
    padding: 0.5rem;
    font-size: 1rem;
}
</style>

<script>
$(document).ready(function() {
    // Benachrichtigung als gelesen markieren beim Klick auf den Link
    $(document).on('click', '.notification-link', function(e) {
        e.stopPropagation();
        const notificationItem = $(this).closest('.notification-item');
        const notificationId = notificationItem.data('notification-id');
        
        markNotificationAsRead(notificationId, function() {
            notificationItem.fadeOut(300, function() {
                $(this).remove();
                updateNotificationCount();
            });
        });
    });
    
    // Benachrichtigung als gelesen markieren beim Klick auf das Item
    $(document).on('click', '.notification-item', function() {
        const notificationId = $(this).data('notification-id');
        const notificationLink = $(this).find('.notification-link').attr('href');
        
        if (notificationLink) {
            markNotificationAsRead(notificationId, function() {
                window.location.href = notificationLink;
            });
        } else {
            markNotificationAsRead(notificationId, function() {
                $(this).fadeOut(300, function() {
                    $(this).remove();
                    updateNotificationCount();
                });
            }.bind(this));
        }
    });
    
    // Alle als gelesen markieren
    $('#markAllReadBtn').on('click', function() {
        $.ajax({
            url: 'dashboard.php',
            method: 'POST',
            data: {
                action: 'mark_all_read'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#notificationsList').fadeOut(300, function() {
                        $(this).replaceWith(
                            '<div class="p-4 text-center text-muted">' +
                            '<i class="fas fa-check-circle fa-3x mb-3"></i>' +
                            '<p>Keine neuen Benachrichtigungen</p>' +
                            '</div>'
                        );
                    });
                    $('#markAllReadBtn').fadeOut();
                    updateNotificationCount();
                    // Aktualisiere Sidebar-Badges
                    $('.notification-badge').remove();
                }
            }
        });
    });
    
    function markNotificationAsRead(notificationId, callback) {
        $.ajax({
            url: 'dashboard.php',
            method: 'POST',
            data: {
                action: 'mark_notification_read',
                notification_id: notificationId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && callback) {
                    callback();
                }
            }
        });
    }
    
    function updateNotificationCount() {
        const remainingCount = $('.notification-item').length;
        const badge = $('.card-header .badge-light');
        
        if (remainingCount === 0) {
            badge.fadeOut();
        } else {
            badge.text(remainingCount);
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>
