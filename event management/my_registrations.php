<?php
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['student', 'teacher'])) {
    header('Location: login.php');
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'event_management');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$userId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['role'];
$feedback_msg = '';

// Handle feedback submission (only for students)
if ($userRole === 'student' && isset($_POST['feedback_event_id'])) {
    $event_id = intval($_POST['feedback_event_id']);
    $comments = trim($_POST['comments']);
    $rating = intval($_POST['rating']);
    
    if (!empty($comments) && $rating >= 1 && $rating <= 5) {
        $check = $conn->query("SELECT * FROM feedback WHERE event_id = $event_id AND user_type = 'student' AND user_id = $userId");
        if ($check->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO feedback (event_id, user_type, user_id, comments, rating) VALUES (?, 'student', ?, ?, ?)");
            $stmt->bind_param('iisi', $event_id, $userId, $comments, $rating);
            if ($stmt->execute()) {
                $feedback_msg = '<div class="alert alert-success">Feedback submitted successfully!</div>';
            } else {
                $feedback_msg = '<div class="alert alert-danger">Failed to submit feedback.</div>';
            }
            $stmt->close();
        } else {
            $feedback_msg = '<div class="alert alert-warning">You have already submitted feedback for this event.</div>';
        }
    } else {
        $feedback_msg = '<div class="alert alert-danger">Please provide valid comments and rating.</div>';
    }
}

// Fetch user registrations
$registrationsSql = "SELECT r.*, e.title, e.description, e.event_date, e.event_time, e.venue
                     FROM registrations r
                     JOIN events e ON r.event_id = e.event_id
                     WHERE r.user_type = ? AND r.user_id = ?
                     ORDER BY e.event_date DESC";
$stmt = $conn->prepare($registrationsSql);
$stmt->bind_param('si', $userRole, $userId);
$stmt->execute();
$registrations = $stmt->get_result();
$stmt->close();

// Check if user has already submitted feedback for each event (for students)
$feedbackGiven = [];
if ($userRole === 'student') {
    $feedbackSql = "SELECT event_id FROM feedback WHERE user_type = 'student' AND user_id = ?";
    $stmt = $conn->prepare($feedbackSql);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $feedbackResult = $stmt->get_result();
    while ($row = $feedbackResult->fetch_assoc()) {
        $feedbackGiven[$row['event_id']] = true;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Registrations - Event Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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

        /* Navigation Styles */
        .navbar {
            background: rgba(30, 41, 59, 0.95) !important;
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            letter-spacing: -0.025em;
        }

        .nav-link {
            font-weight: 500;
            transition: all 0.2s ease;
            border-radius: 6px;
            margin: 0 2px;
        }

        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            transform: translateY(-1px);
        }

        .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .dropdown-menu {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .dropdown-item:hover {
            background-color: rgba(30, 41, 59, 0.1);
        }

        /* Main Content */
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

        .page-header h2 {
            color: #1e293b;
            font-weight: 700;
            margin-bottom: 10px;
            font-size: 1.8rem;
        }

        .page-header p {
            color: #64748b;
            margin-bottom: 0;
        }

        /* Cards */
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

        .card-body {
            padding: 24px;
        }

        /* Tables */
        .table {
            margin-bottom: 0;
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

        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }

        .table tbody tr:hover {
            background-color: rgba(248, 250, 252, 0.5);
        }

        /* Event Details */
        .event-title {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .event-description {
            color: #64748b;
            font-size: 0.875rem;
            margin: 0;
        }

        .event-date {
            font-weight: 500;
            color: #374151;
        }

        .event-time {
            color: #64748b;
            font-size: 0.875rem;
        }

        /* Buttons */
        .btn {
            font-weight: 500;
            border-radius: 6px;
            padding: 8px 16px;
            transition: all 0.2s ease;
            border: none;
            font-size: 0.875rem;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        .btn-primary {
            background-color: #1e293b;
            border-color: #1e293b;
        }

        .btn-primary:hover {
            background-color: #0f172a;
            transform: translateY(-1px);
        }

        .btn-success {
            background-color: #16a34a;
            border-color: #16a34a;
        }

        .btn-success:hover {
            background-color: #15803d;
            transform: translateY(-1px);
        }

        .btn-info {
            background-color: #0ea5e9;
            border-color: #0ea5e9;
        }

        .btn-info:hover {
            background-color: #0284c7;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background-color: #64748b;
            border-color: #64748b;
        }

        .btn-secondary:hover {
            background-color: #475569;
            transform: translateY(-1px);
        }

        /* Alerts */
        .alert {
            border-radius: 8px;
            border: none;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-success {
            background-color: #f0fdf4;
            color: #166534;
            border-left: 4px solid #16a34a;
        }

        .alert-danger {
            background-color: #fef2f2;
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }

        .alert-warning {
            background-color: #fffbeb;
            color: #d97706;
            border-left: 4px solid #f59e0b;
        }

        /* Modal Styles */
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

        /* Form Elements */
        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 6px;
        }

        .form-control, .form-select {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 10px 12px;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .form-control:focus, .form-select:focus {
            border-color: #1e293b;
            box-shadow: 0 0 0 3px rgba(30, 41, 59, 0.1);
            outline: none;
        }

        /* Status Indicators */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-upcoming {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .status-past {
            background-color: #f3f4f6;
            color: #6b7280;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state h5 {
            margin-bottom: 8px;
            color: #374151;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container-narrow {
                margin: 20px auto;
                padding: 0 15px;
            }

            .page-header {
                padding: 20px;
            }

            .card-body {
                padding: 16px;
            }

            .table th, .table td {
                padding: 12px 8px;
            }
        }

        /* Rating Stars */
        .rating-stars {
            color: #fbbf24;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="<?php echo $userRole === 'student' ? 'student_dashboard.php' : 'teacher_dashboard.php'; ?>">
                <i class="fas fa-calendar-alt me-2"></i>
                EventHub
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $userRole === 'student' ? 'student_dashboard.php' : 'teacher_dashboard.php'; ?>">
                            <i class="fas fa-home me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="my_registrations.php">
                            <i class="fas fa-ticket-alt me-1"></i>My Registrations
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="past_events.php">
                            <i class="fas fa-history me-1"></i>Past Events
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="all_events.php">
                            <i class="fas fa-calendar me-1"></i>All Events
                        </a>
                    </li>
                    <?php if ($userRole === 'teacher'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="teacher_events.php">
                            <i class="fas fa-user-tie me-1"></i>My Events
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['first_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-narrow">
        <!-- Page Header -->
        <div class="page-header">
            <h2><i class="fas fa-ticket-alt me-3"></i>My Event Registrations</h2>
            <p>View and manage all your registered events</p>
        </div>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-list me-2"></i>Registered Events
            </div>
            <div class="card-body">
                <?php echo $feedback_msg; ?>
                
                <?php if ($registrations->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Event Details</th>
                                    <th>Date & Time</th>
                                    <th>Venue</th>
                                    <th>Status</th>
                                    <?php if ($userRole === 'student'): ?>
                                        <th>Feedback</th>
                                        <th>Ticket</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($reg = $registrations->fetch_assoc()): 
                                    $isEventPast = strtotime($reg['event_date']) < strtotime(date('Y-m-d'));
                                    $hasFeedback = isset($feedbackGiven[$reg['event_id']]);
                                ?>
                                    <tr>
                                        <td>
                                            <div class="event-title"><?php echo htmlspecialchars($reg['title']); ?></div>
                                            <?php if (!empty($reg['description'])): ?>
                                                <p class="event-description"><?php echo htmlspecialchars(substr($reg['description'], 0, 80)); ?><?php echo strlen($reg['description']) > 80 ? '...' : ''; ?></p>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="event-date"><?php echo date('M d, Y', strtotime($reg['event_date'])); ?></div>
                                            <?php if (!empty($reg['event_time'])): ?>
                                                <div class="event-time"><?php echo date('h:i A', strtotime($reg['event_time'])); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($reg['venue'] ?: 'TBA'); ?></td>
                                        <td>
                                            <?php if ($isEventPast): ?>
                                                <span class="status-badge status-past">
                                                    <i class="fas fa-check-circle"></i>
                                                    Completed
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge status-upcoming">
                                                    <i class="fas fa-clock"></i>
                                                    Upcoming
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <?php if ($userRole === 'student'): ?>
                                            <td>
                                                <?php if ($isEventPast): ?>
                                                    <?php if ($hasFeedback): ?>
                                                        <span class="text-success">
                                                            <i class="fas fa-check-circle me-1"></i>Submitted
                                                        </span>
                                                    <?php else: ?>
                                                        <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#feedbackModal<?php echo $reg['event_id']; ?>">
                                                            <i class="fas fa-star me-1"></i>Give Feedback
                                                        </button>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">
                                                        <i class="fas fa-hourglass-half me-1"></i>Available after event
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="ticket.php?registration_id=<?php echo $reg['registration_id']; ?>" class="btn btn-success btn-sm" target="_blank">
                                                    <i class="fas fa-download me-1"></i>View Ticket
                                                </a>
                                            </td>
                                        <?php endif; ?>
                                    </tr>

                                    <!-- Feedback Modal for Students -->
                                    <?php if ($userRole === 'student' && $isEventPast && !$hasFeedback): ?>
                                        <div class="modal fade" id="feedbackModal<?php echo $reg['event_id']; ?>" tabindex="-1" aria-labelledby="feedbackModalLabel<?php echo $reg['event_id']; ?>" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="POST" action="">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="feedbackModalLabel<?php echo $reg['event_id']; ?>">
                                                                <i class="fas fa-star me-2"></i>Feedback for "<?php echo htmlspecialchars($reg['title']); ?>"
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="feedback_event_id" value="<?php echo $reg['event_id']; ?>">
                                                            <div class="mb-3">
                                                                <label for="comments<?php echo $reg['event_id']; ?>" class="form-label">Your Comments</label>
                                                                <textarea class="form-control" id="comments<?php echo $reg['event_id']; ?>" name="comments" rows="4" required placeholder="Share your experience about this event..."></textarea>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="rating<?php echo $reg['event_id']; ?>" class="form-label">Overall Rating</label>
                                                                <select class="form-select" id="rating<?php echo $reg['event_id']; ?>" name="rating" required>
                                                                    <option value="">Select a rating</option>
                                                                    <option value="5">⭐⭐⭐⭐⭐ Excellent</option>
                                                                    <option value="4">⭐⭐⭐⭐ Very Good</option>
                                                                    <option value="3">⭐⭐⭐ Good</option>
                                                                    <option value="2">⭐⭐ Fair</option>
                                                                    <option value="1">⭐ Poor</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                                <i class="fas fa-times me-1"></i>Cancel
                                                            </button>
                                                            <button type="submit" class="btn btn-primary">
                                                                <i class="fas fa-paper-plane me-1"></i>Submit Feedback
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h5>No Registrations Yet</h5>
                        <p>You haven't registered for any events. Check out our <a href="all_events.php">upcoming events</a> to get started!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>