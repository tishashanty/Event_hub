<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: admin_login.php');
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'event_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Include email functionality
require_once 'includes/email_config.php';
require_once 'includes/get_event_participants.php';

$message = '';
$messageType = '';

// Handle event actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['event_id'])) {
        $action = $_POST['action'];
        $eventId = intval($_POST['event_id']);
        
        // Get event details before any action
        $eventDetails = getEventDetails($conn, $eventId);
        
        if ($eventDetails) {
            if ($action === 'delete') {
                // Get participants before deleting
                $participants = getEventParticipants($conn, $eventId);
                
                // Delete registrations first
                $conn->query("DELETE FROM registrations WHERE event_id = $eventId");
                
                // Delete the event
                if ($conn->query("DELETE FROM events WHERE event_id = $eventId")) {
                    $message = 'Event deleted successfully!';
                    $messageType = 'success';
                    
                    // Send email notifications
                    if (!empty($participants)) {
                        $emailNotification = new EmailNotification();
                        $emailNotification->sendEventNotification(
                            $participants,
                            $eventDetails['title'],
                            'deleted',
                            $eventDetails
                        );
                    }
                } else {
                    $message = 'Failed to delete event.';
                    $messageType = 'danger';
                }
            } elseif ($action === 'deactivate') {
                // Add a status column if it doesn't exist
                $conn->query("ALTER TABLE events ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive') DEFAULT 'active'");
                
                // Deactivate the event
                if ($conn->query("UPDATE events SET status = 'inactive' WHERE event_id = $eventId")) {
                    $message = 'Event deactivated successfully!';
                    $messageType = 'warning';
                    
                    // Get participants and send email notifications
                    $participants = getEventParticipants($conn, $eventId);
                    if (!empty($participants)) {
                        $emailNotification = new EmailNotification();
                        $emailNotification->sendEventNotification(
                            $participants,
                            $eventDetails['title'],
                            'deactivated',
                            $eventDetails
                        );
                    }
                } else {
                    $message = 'Failed to deactivate event.';
                    $messageType = 'danger';
                }
            } elseif ($action === 'activate') {
                // Reactivate the event
                if ($conn->query("UPDATE events SET status = 'active' WHERE event_id = $eventId")) {
                    $message = 'Event activated successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to activate event.';
                    $messageType = 'danger';
                }
            }
        } else {
            $message = 'Event not found.';
            $messageType = 'danger';
        }
    }
}

// Get all events with participant counts
$eventsQuery = "SELECT e.*, COUNT(r.registration_id) as participant_count 
                FROM events e 
                LEFT JOIN registrations r ON e.event_id = r.event_id 
                GROUP BY e.event_id 
                ORDER BY e.event_date DESC, e.event_time DESC";
$events = $conn->query($eventsQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events - Enhanced</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #334155;
            line-height: 1.6;
            min-height: 100vh;
        }

        .navbar {
            background: rgba(30, 41, 59, 0.95) !important;
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .container-narrow {
            max-width: 1400px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            background: rgba(30, 41, 59, 0.95);
            color: white;
            border: none;
            border-radius: 12px 12px 0 0;
            padding: 20px 24px;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .table th {
            background-color: rgba(248, 250, 252, 0.8);
            color: #374151;
            font-weight: 600;
            font-size: 0.875rem;
            padding: 16px 12px;
            border-bottom: 2px solid #e5e7eb;
        }

        .table td {
            padding: 16px 12px;
            vertical-align: middle;
            border-bottom: 1px solid #e5e7eb;
        }

        .table tbody tr:hover {
            background-color: rgba(248, 250, 252, 0.5);
        }

        .btn {
            font-weight: 500;
            border-radius: 6px;
            padding: 6px 12px;
            transition: all 0.2s ease;
            border: none;
            font-size: 0.8rem;
        }

        .btn-sm {
            padding: 4px 8px;
            font-size: 0.75rem;
        }

        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }

        .btn-danger:hover {
            background-color: #c82333;
            transform: translateY(-1px);
        }

        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #000;
        }

        .btn-warning:hover {
            background-color: #e0a800;
            transform: translateY(-1px);
        }

        .btn-success {
            background-color: #198754;
            border-color: #198754;
        }

        .btn-success:hover {
            background-color: #157347;
            transform: translateY(-1px);
        }

        .btn-info {
            background-color: #0dcaf0;
            border-color: #0dcaf0;
        }

        .btn-info:hover {
            background-color: #0aa2c0;
            transform: translateY(-1px);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-active {
            background-color: #d1f2eb;
            color: #0e7b5e;
        }

        .status-inactive {
            background-color: #fef3cd;
            color: #856404;
        }

        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
        }

        .modal-header {
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            border-radius: 12px 12px 0 0;
            padding: 20px 24px;
        }

        .modal-title {
            color: #1e293b;
            font-weight: 600;
        }

        .modal-body {
            padding: 24px;
        }

        .modal-footer {
            border-top: 1px solid #e2e8f0;
            padding: 20px 24px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="admin_dashboard.php">
                <i class="fas fa-calendar-alt me-2"></i>
                EventHub Admin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="admin_dashboard.php">
                            <i class="fas fa-home me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="manage_events_enhanced.php">
                            <i class="fas fa-calendar me-1"></i>Manage Events
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_users.php">
                            <i class="fas fa-users me-1"></i>Manage Users
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['first_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-narrow">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'warning' ? 'exclamation-triangle' : 'times-circle'); ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-calendar me-2"></i>Enhanced Event Management</h5>
                <small class="text-light">Manage events with email notifications</small>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Event Title</th>
                                <th>Date & Time</th>
                                <th>Venue</th>
                                <th>Participants</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($event = $events->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($event['title']); ?></div>
                                        <?php if (!empty($event['description'])): ?>
                                            <small class="text-muted"><?php echo htmlspecialchars(substr($event['description'], 0, 50)) . (strlen($event['description']) > 50 ? '...' : ''); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><?php echo date('M d, Y', strtotime($event['event_date'])); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($event['event_time']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($event['venue']); ?></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $event['participant_count']; ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                        $status = isset($event['status']) ? $event['status'] : 'active';
                                        $statusClass = $status === 'active' ? 'status-active' : 'status-inactive';
                                        $statusIcon = $status === 'active' ? 'fa-check-circle' : 'fa-pause-circle';
                                        ?>
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <i class="fas <?php echo $statusIcon; ?>"></i>
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="edit_event.php?event_id=<?php echo $event['event_id']; ?>" class="btn btn-info btn-sm">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            
                                            <?php if ($status === 'active'): ?>
                                                <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#deactivateModal<?php echo $event['event_id']; ?>">
                                                    <i class="fas fa-pause"></i> Deactivate
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#activateModal<?php echo $event['event_id']; ?>">
                                                    <i class="fas fa-play"></i> Activate
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $event['event_id']; ?>">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Deactivate Modal -->
                                <div class="modal fade" id="deactivateModal<?php echo $event['event_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">
                                                    <i class="fas fa-pause text-warning me-2"></i>Deactivate Event
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to deactivate this event?</p>
                                                <div class="alert alert-warning">
                                                    <strong>Event:</strong> <?php echo htmlspecialchars($event['title']); ?><br>
                                                    <strong>Date:</strong> <?php echo date('M d, Y', strtotime($event['event_date'])); ?><br>
                                                    <strong>Participants:</strong> <?php echo $event['participant_count']; ?>
                                                </div>
                                                <p><strong>Note:</strong> All registered participants will be notified via email.</p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="deactivate">
                                                    <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                                                    <button type="submit" class="btn btn-warning">
                                                        <i class="fas fa-pause me-1"></i>Deactivate Event
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Activate Modal -->
                                <div class="modal fade" id="activateModal<?php echo $event['event_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">
                                                    <i class="fas fa-play text-success me-2"></i>Activate Event
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to activate this event?</p>
                                                <div class="alert alert-info">
                                                    <strong>Event:</strong> <?php echo htmlspecialchars($event['title']); ?><br>
                                                    <strong>Date:</strong> <?php echo date('M d, Y', strtotime($event['event_date'])); ?><br>
                                                    <strong>Participants:</strong> <?php echo $event['participant_count']; ?>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="activate">
                                                    <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                                                    <button type="submit" class="btn btn-success">
                                                        <i class="fas fa-play me-1"></i>Activate Event
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Delete Modal -->
                                <div class="modal fade" id="deleteModal<?php echo $event['event_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">
                                                    <i class="fas fa-trash text-danger me-2"></i>Delete Event
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="alert alert-danger">
                                                    <strong>Warning:</strong> This action cannot be undone!
                                                </div>
                                                <p>Are you sure you want to permanently delete this event?</p>
                                                <div class="alert alert-warning">
                                                    <strong>Event:</strong> <?php echo htmlspecialchars($event['title']); ?><br>
                                                    <strong>Date:</strong> <?php echo date('M d, Y', strtotime($event['event_date'])); ?><br>
                                                    <strong>Participants:</strong> <?php echo $event['participant_count']; ?>
                                                </div>
                                                <p><strong>Note:</strong> All registrations will be removed and participants will be notified via email.</p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                                                    <button type="submit" class="btn btn-danger">
                                                        <i class="fas fa-trash me-1"></i>Delete Permanently
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
