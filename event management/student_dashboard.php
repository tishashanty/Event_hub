<?php
session_start();

// Set cache control headers to prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Check if user is logged in and has student role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'event_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$student_id = $_SESSION['user_id'];

// Ensure event_requests table exists
$conn->query("CREATE TABLE IF NOT EXISTS event_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    program_title VARCHAR(255) NOT NULL,
    program_description TEXT NOT NULL,
    program_date DATE NOT NULL,
    program_time TIME NOT NULL,
    program_venue VARCHAR(255) NOT NULL,
    requirements TEXT,
    program_duration INT DEFAULT 60,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    admin_comment TEXT NULL,
    responded_at DATETIME NULL,
    student_viewed TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Ensure feedback table exists
$conn->query("CREATE TABLE IF NOT EXISTS feedback (
    feedback_id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_type ENUM('student','faculty') NOT NULL,
    user_id INT NOT NULL,
    comments TEXT NOT NULL,
    rating INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Fetch data
$events = $conn->query("SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC");
$past_events = $conn->query("SELECT * FROM events WHERE event_date < CURDATE() ORDER BY event_date DESC");
$registrations = $conn->query("SELECT r.*, e.title, e.event_date, e.venue FROM registrations r JOIN events e ON r.event_id = e.event_id WHERE r.user_type = 'student' AND r.user_id = $student_id ORDER BY e.event_date DESC");
$all_events = $conn->query("SELECT * FROM events ORDER BY event_date ASC");
$announcements = $conn->query("SELECT a.*, e.title as event_title FROM announcements a JOIN events e ON a.event_id = e.event_id ORDER BY a.created_at DESC");

// Handle event registration
$register_msg = '';
if (isset($_POST['register_event_id'])) {
    $event_id = intval($_POST['register_event_id']);
    $check = $conn->query("SELECT * FROM registrations WHERE event_id = $event_id AND user_type = 'student' AND user_id = $student_id");
    if ($check->num_rows == 0) {
        $conn->query("INSERT INTO registrations (event_id, user_type, user_id) VALUES ($event_id, 'student', $student_id)");
        $register_msg = '<div class="alert alert-success">Registered successfully!</div>';
    } else {
        $register_msg = '<div class="alert alert-warning">Already registered for this event.</div>';
    }
    $registrations = $conn->query("SELECT r.*, e.title, e.event_date, e.venue FROM registrations r JOIN events e ON r.event_id = e.event_id WHERE r.user_type = 'student' AND r.user_id = $student_id ORDER BY e.event_date DESC");
}

// Handle feedback submission
$feedback_msg = '';
if (isset($_POST['feedback_event_id'])) {
    $event_id = intval($_POST['feedback_event_id']);
    $comments = trim($_POST['comments']);
    $rating = intval($_POST['rating']);
    
    // Check if feedback already exists for this event and user
    $check = $conn->query("SELECT * FROM feedback WHERE event_id = $event_id AND user_type = 'student' AND user_id = $student_id");
    
    if ($check->num_rows == 0) {
        // No existing feedback, proceed with submission
        $stmt = $conn->prepare("INSERT INTO feedback (event_id, user_type, user_id, comments, rating) VALUES (?, 'student', ?, ?, ?)");
        $stmt->bind_param('iisi', $event_id, $student_id, $comments, $rating);
        if ($stmt->execute()) {
            $feedback_msg = '<div class="alert alert-success">Feedback submitted successfully!</div>';
        } else {
            $feedback_msg = '<div class="alert alert-danger">Failed to submit feedback.</div>';
        }
        $stmt->close();
    } else {
        // Feedback already exists
        $feedback_msg = '<div class="alert alert-warning">You have already submitted feedback for this event.</div>';
    }
    
    // Refresh registrations to update feedback status
    $registrations = $conn->query("SELECT r.*, e.title, e.event_date, e.venue FROM registrations r JOIN events e ON r.event_id = e.event_id WHERE r.user_type = 'student' AND r.user_id = $student_id ORDER BY e.event_date DESC");
}

// Handle student program request submission
$request_msg = '';
$override_view = null;
if (isset($_POST['request_program'])) {
    $program_title = trim($_POST['program_title'] ?? '');
    $program_description = trim($_POST['program_description'] ?? '');
    $program_date = trim($_POST['program_date'] ?? '');
    $program_time = trim($_POST['program_time'] ?? '');
    $program_venue = trim($_POST['program_venue'] ?? '');
    $program_duration = intval($_POST['program_duration'] ?? 60);
    $requirements = trim($_POST['requirements'] ?? '');

    if ($program_title === '' || $program_description === '' || $program_date === '' || $program_time === '' || $program_venue === '') {
        $request_msg = '<div class="alert alert-danger">Please fill in all required fields.</div>';
    } else {
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        
        if ($program_date <= $today) {
            $request_msg = '<div class="alert alert-danger">Program date must be at least 1 day from today. Please select a date from ' . date('M d, Y', strtotime($tomorrow)) . ' onwards.</div>';
        } else {
            $stmt = $conn->prepare("INSERT INTO event_requests (student_id, program_title, program_description, program_date, program_time, program_venue, program_duration, requirements) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param('isssssis', $student_id, $program_title, $program_description, $program_date, $program_time, $program_venue, $program_duration, $requirements);
                if ($stmt->execute()) {
                    $request_msg = '<div class="alert alert-success">Your request has been submitted to the admin for approval.</div>';
                } else {
                    $request_msg = '<div class="alert alert-danger">Failed to submit request. Please try again.</div>';
                }
                $stmt->close();
            } else {
                $request_msg = '<div class="alert alert-danger">Server error preparing your request.</div>';
            }
        }
    }
    $override_view = 'request_program';
}

// Compute unread admin response notifications
$unread_notifications = 0;
$res = $conn->query("SELECT COUNT(*) as c FROM event_requests WHERE student_id = $student_id AND responded_at IS NOT NULL AND student_viewed = 0");
if ($res) { 
    $row = $res->fetch_assoc(); 
    $unread_notifications = intval($row['c']); 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Event Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .page-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
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

        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }

        .btn {
            font-weight: 500;
            border-radius: 6px;
            padding: 8px 16px;
            transition: all 0.2s ease;
            border: none;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        @media (max-width: 768px) {
            .container-narrow {
                margin: 20px auto;
                padding: 0 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container-narrow">
        <div class="page-header">
            <h2>Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h2>
            <p class="mb-0">Student Dashboard - Manage your event registrations and program requests</p>
        </div>

        <?php
        $allowed_views = ['upcoming', 'my_registrations', 'past_events', 'all_events', 'request_program'];
        $view = isset($override_view) ? $override_view : ((isset($_GET['view']) && in_array($_GET['view'], $allowed_views)) ? $_GET['view'] : 'upcoming');
        ?>

        <nav class="navbar navbar-expand-lg navbar-dark rounded mb-4">
            <div class="container-fluid">
                <a class="navbar-brand" href="student_dashboard.php">Student Dashboard</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#studentNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="studentNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $view === 'upcoming' ? 'active' : ''; ?>" href="?view=upcoming">Upcoming Events</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $view === 'my_registrations' ? 'active' : ''; ?>" href="?view=my_registrations">My Registrations</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $view === 'past_events' ? 'active' : ''; ?>" href="?view=past_events">Past Events</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $view === 'all_events' ? 'active' : ''; ?>" href="?view=all_events">All Events</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $view === 'request_program' ? 'active' : ''; ?>" href="?view=request_program">
                                Request Program
                                <?php if ($unread_notifications > 0): ?>
                                    <span class="badge bg-danger ms-1"><?php echo $unread_notifications; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-danger" href="logout.php">Logout</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <?php if ($view === 'upcoming'): ?>
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">Upcoming Events</h5></div>
                <div class="card-body">
                    <?php echo $register_msg; ?>
                    <?php if ($events && $events->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Venue</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($event = $events->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <div class="event-title"><?php echo htmlspecialchars($event['title']); ?></div>
                                                <div class="event-description"><?php echo htmlspecialchars(substr($event['description'], 0, 100)) . '...'; ?></div>
                                            </td>
                                            <td>
                                                <div class="event-date"><?php echo date('M d, Y', strtotime($event['event_date'])); ?></div>
                                            </td>
                                            <td>
                                                <div class="event-time"><?php echo htmlspecialchars($event['event_time']); ?></div>
                                            </td>
                                            <td><?php echo htmlspecialchars($event['venue']); ?></td>
                                            <td>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="register_event_id" value="<?php echo $event['event_id']; ?>">
                                                    <button type="submit" class="btn btn-primary btn-sm">Register</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <p class="text-muted">No upcoming events found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($announcements && $announcements->num_rows > 0): ?>
                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0">Latest Announcements</h5></div>
                    <div class="card-body">
                        <?php while ($ann = $announcements->fetch_assoc()): ?>
                            <div class="alert alert-info mb-2">
                                <strong>Announcement for <?php echo htmlspecialchars($ann['event_title']); ?>:</strong>
                                <?php echo htmlspecialchars($ann['announcement_text']); ?>
                                <br><small class="text-muted">Announced on <?php echo date('M d, Y H:i', strtotime($ann['created_at'])); ?></small>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php elseif ($view === 'my_registrations'): ?>
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">My Registrations</h5></div>
                <div class="card-body">
                    <?php echo $feedback_msg; ?>
                    <?php if ($registrations && $registrations->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Event</th>
                                        <th>Date</th>
                                        <th>Venue</th>
                                        <th>Feedback Status</th>
                                        <th>Ticket</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    while ($reg = $registrations->fetch_assoc()): 
                                        // Check if feedback already submitted for this event
                                        $feedback_check = $conn->query("SELECT * FROM feedback WHERE event_id = {$reg['event_id']} AND user_type = 'student' AND user_id = $student_id");
                                        $feedback_submitted = $feedback_check->num_rows > 0;
                                        $is_past_event = strtotime($reg['event_date']) <= strtotime(date('Y-m-d'));
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($reg['title']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($reg['event_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($reg['venue']); ?></td>
                                            <td>
                                                <?php if ($is_past_event): ?>
                                                    <?php if ($feedback_submitted): ?>
                                                        <span class="badge bg-success">Feedback Submitted</span>
                                                    <?php else: ?>
                                                        <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#feedbackModal<?php echo $reg['event_id']; ?>">
                                                            Give Feedback
                                                        </button>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Available after event</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="ticket.php?registration_id=<?php echo $reg['registration_id']; ?>" class="btn btn-success btn-sm" target="_blank">
                                                    View Ticket
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <p class="text-muted">You haven't registered for any events yet.</p>
                            <a href="?view=upcoming" class="btn btn-primary">Browse Events</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($view === 'past_events'): ?>
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">Past Events</h5></div>
                <div class="card-body">
                    <?php if ($past_events && $past_events->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Date</th>
                                        <th>Venue</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($event = $past_events->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($event['title']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($event['event_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($event['venue']); ?></td>
                                            <td><span class="badge bg-secondary">Completed</span></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <p class="text-muted">No past events found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($view === 'all_events'): ?>
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">All Events</h5></div>
                <div class="card-body">
                    <?php echo $register_msg; ?>
                    <?php if ($all_events && $all_events->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Date</th>
                                        <th>Venue</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($event = $all_events->fetch_assoc()): ?>
                                        <?php
                                        $reg_check = $conn->query("SELECT * FROM registrations WHERE event_id = {$event['event_id']} AND user_type = 'student' AND user_id = $student_id");
                                        $is_registered = $reg_check->num_rows > 0;
                                        $is_past_event = strtotime($event['event_date']) < strtotime(date('Y-m-d'));
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($event['title']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($event['event_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($event['venue']); ?></td>
                                            <td>
                                                <?php if ($is_past_event): ?>
                                                    <span class="badge bg-secondary">Past Event</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Upcoming</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!$is_registered && !$is_past_event): ?>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="register_event_id" value="<?php echo $event['event_id']; ?>">
                                                        <button type="submit" class="btn btn-primary btn-sm">Register</button>
                                                    </form>
                                                <?php elseif ($is_registered): ?>
                                                    <span class="text-muted">Registered</span>
                                                <?php else: ?>
                                                    <span class="text-muted">Registration Closed</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <p class="text-muted">No events found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($view === 'request_program'): ?>
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header"><h5 class="mb-0">Request to Conduct a Program</h5></div>
                        <div class="card-body">
                            <?php echo $request_msg; ?>
                            <form method="POST" action="" id="request_program_form">
                                <input type="hidden" name="request_program" value="1">
                                <div class="mb-3">
                                    <label class="form-label">Program Title <span class="text-danger">*</span></label>
                                    <input type="text" name="program_title" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description <span class="text-danger">*</span></label>
                                    <textarea name="program_description" class="form-control" rows="4" placeholder="What program are you planning?" required></textarea>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Date <span class="text-danger">*</span></label>
                                        <input type="date" name="program_date" id="program_date" class="form-control" required>
                                        <div id="date_error" class="invalid-feedback" style="display: none;"></div>
                                        <small class="form-text text-muted">Program must be scheduled at least 1 day from today</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Time <span class="text-danger">*</span></label>
                                        <input type="time" name="program_time" class="form-control" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Venue <span class="text-danger">*</span></label>
                                    <select name="program_venue" class="form-control" required>
                                        <option value="">Select Venue</option>
                                        <option value="Seminar Hall">Seminar Hall</option>
                                        <option value="Yoga Hall">Yoga Hall</option>
                                        <option value="Main Theatre">Main Theatre</option>
                                        <option value="Mini Theatre">Mini Theatre</option>
                                        <option value="Open Auditorium">Open Auditorium</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Duration (minutes) <span class="text-danger">*</span></label>
                                    <input type="number" name="program_duration" class="form-control" min="15" max="480" value="60" required>
                                    <small class="form-text text-muted">Minimum 15 minutes, Maximum 8 hours (480 minutes)</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Requirements</label>
                                    <textarea name="requirements" class="form-control" rows="3" placeholder="Equipment, facilities, etc."></textarea>
                                </div>
                                <div class="text-end">
                                    <button type="submit" id="submit_request_btn" class="btn btn-primary">Submit Request</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header"><h5 class="mb-0">My Requests</h5></div>
                        <div class="card-body">
                            <?php
                            $my_requests = $conn->query("SELECT * FROM event_requests WHERE student_id = $student_id ORDER BY created_at DESC");
                            if ($my_requests && $my_requests->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Date</th>
                                                <th>Venue</th>
                                                <th>Status</th>
                                                <th>Admin Response</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($req = $my_requests->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($req['program_title']); ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($req['program_date'])); ?></td>
                                                    <td><?php echo htmlspecialchars($req['program_venue']); ?></td>
                                                    <td>
                                                        <?php if ($req['status'] === 'approved'): ?>
                                                            <span class="badge bg-success">Approved</span>
                                                        <?php elseif ($req['status'] === 'rejected'): ?>
                                                            <span class="badge bg-danger">Rejected</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning text-dark">Pending</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $admin_comment = isset($req['admin_comment']) ? trim($req['admin_comment']) : '';
                                                        $has_response = !empty($req['responded_at']);
                                                        $is_unread = $has_response && isset($req['student_viewed']) && intval($req['student_viewed']) === 0;
                                                        
                                                        if ($admin_comment): ?>
                                                            <div>
                                                                <?php echo htmlspecialchars($admin_comment); ?>
                                                                <?php if ($is_unread): ?>
                                                                    <span class="badge bg-info ms-1">New</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <small class="text-muted">
                                                                <?php echo date('M d, Y H:i', strtotime($req['responded_at'])); ?>
                                                            </small>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <p class="text-muted">No requests submitted yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Feedback Modals for My Registrations -->
    <?php
    // Generate modals for feedback only in My Registrations view
    if ($view === 'my_registrations' && $registrations) {
        $registrations->data_seek(0); // Reset pointer
        while ($reg = $registrations->fetch_assoc()): 
            $is_past_event = strtotime($reg['event_date']) <= strtotime(date('Y-m-d'));
            $feedback_check = $conn->query("SELECT * FROM feedback WHERE event_id = {$reg['event_id']} AND user_type = 'student' AND user_id = $student_id");
            $feedback_submitted = $feedback_check->num_rows > 0;
            
            // Only generate modal if event is past AND feedback not submitted
            if ($is_past_event && !$feedback_submitted): ?>
                <div class="modal fade" id="feedbackModal<?php echo $reg['event_id']; ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST">
                                <div class="modal-header">
                                    <h5 class="modal-title">Feedback for <?php echo htmlspecialchars($reg['title']); ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="feedback_event_id" value="<?php echo $reg['event_id']; ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Comments <span class="text-danger">*</span></label>
                                        <textarea class="form-control" name="comments" required placeholder="Share your experience about the event..."></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Rating <span class="text-danger">*</span></label>
                                        <select class="form-select" name="rating" required>
                                            <option value="">Select Rating</option>
                                            <option value="1">1 - Poor</option>
                                            <option value="2">2 - Fair</option>
                                            <option value="3">3 - Good</option>
                                            <option value="4">4 - Very Good</option>
                                            <option value="5">5 - Excellent</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-primary">Submit Feedback</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif;
        endwhile;
    }
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Date validation for program requests
        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.getElementById('program_date');
            const submitBtn = document.getElementById('submit_request_btn');
            
            if (dateInput && submitBtn) {
                const today = new Date();
                const tomorrow = new Date(today);
                tomorrow.setDate(today.getDate() + 1);
                
                const minDate = tomorrow.toISOString().split('T')[0];
                dateInput.setAttribute('min', minDate);
                
                dateInput.addEventListener('change', function() {
                    const selectedDate = new Date(this.value);
                    const errorDiv = document.getElementById('date_error');
                    
                    if (selectedDate <= today) {
                        this.classList.add('is-invalid');
                        if (errorDiv) {
                            errorDiv.textContent = 'Program date must be at least 1 day from today.';
                            errorDiv.style.display = 'block';
                        }
                        submitBtn.disabled = true;
                    } else {
                        this.classList.remove('is-invalid');
                        if (errorDiv) {
                            errorDiv.style.display = 'none';
                        }
                        submitBtn.disabled = false;
                    }
                });
            }
        });

        // Prevent back button after logout
        window.history.forward();
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        });
    </script>
</body>
</html>