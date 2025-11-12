<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'event_management');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$teacherId = (int)$_SESSION['user_id'];
$eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
if ($eventId <= 0) {
    die('Invalid event');
}

// Check ownership
$ownCheck = $conn->query("SELECT * FROM events WHERE event_id = $eventId AND created_by_user_type = 'teacher' AND created_by_user_id = $teacherId");
if (!$ownCheck || $ownCheck->num_rows === 0) {
    die('You do not have permission to view feedback for this event.');
}
$event = $ownCheck->fetch_assoc();

$feedbacks = $conn->query("SELECT * FROM feedback WHERE event_id = $eventId ORDER BY feedback_date DESC");
$feedbackQueryError = $feedbacks === false ? $conn->error : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback - <?php echo htmlspecialchars($event['title']); ?> | EventHub</title>
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

        .dropdown-item {
            transition: all 0.2s ease;
        }

        .dropdown-item:hover {
            background-color: rgba(30, 41, 59, 0.1);
        }

        /* Main Content */
        .dashboard-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .welcome-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .welcome-header h2 {
            color: #1e293b;
            font-weight: 700;
            margin-bottom: 10px;
            font-size: 2rem;
        }

        .welcome-header p {
            color: #64748b;
            margin-bottom: 0;
            font-size: 1.1rem;
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

        /* Buttons */
        .btn {
            font-weight: 500;
            border-radius: 8px;
            padding: 10px 20px;
            transition: all 0.2s ease;
            border: none;
        }

        .btn-primary {
            background-color: #1e293b;
            border-color: #1e293b;
        }

        .btn-primary:hover {
            background-color: #0f172a;
            border-color: #0f172a;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background-color: #64748b;
            border-color: #64748b;
        }

        .btn-secondary:hover {
            background-color: #475569;
            border-color: #475569;
            transform: translateY(-1px);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.875rem;
        }

        /* Form Elements */
        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 6px;
            font-size: 0.875rem;
        }

        .form-control, .form-select {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 10px 12px;
            font-size: 0.875rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            background-color: white;
        }

        .form-control:focus, .form-select:focus {
            border-color: #1e293b;
            box-shadow: 0 0 0 3px rgba(30, 41, 59, 0.1);
            outline: none;
        }

        /* Alerts */
        .alert {
            border-radius: 8px;
            border: none;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-info {
            background-color: #f0f9ff;
            color: #0c4a6e;
            border-left: 4px solid #0ea5e9;
        }

        .alert-warning {
            background-color: #fffbeb;
            color: #d97706;
            border-left: 4px solid #f59e0b;
        }

        /* Feedback items */
        .feedback-item {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .feedback-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .rating-stars {
            color: #f59e0b;
            margin-bottom: 8px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .dashboard-container {
                margin: 20px auto;
                padding: 0 15px;
            }

            .welcome-header {
                padding: 20px;
            }

            .welcome-header h2 {
                font-size: 1.5rem;
            }

            .header-actions {
                flex-direction: column;
                gap: 8px;
                width: 100%;
            }

            .header-actions .btn {
                width: 100%;
            }

            .card-body {
                padding: 16px;
            }
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="teacher_dashboard.php">
                <i class="fas fa-calendar-alt me-2"></i>
                EventHub
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="teacher_dashboard.php"><i class="fas fa-home me-1"></i>Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="my_registrations.php"><i class="fas fa-ticket-alt me-1"></i>My Registrations</a></li>
                    <li class="nav-item"><a class="nav-link" href="past_events.php"><i class="fas fa-history me-1"></i>Past Events</a></li>
                    <li class="nav-item"><a class="nav-link" href="all_events.php"><i class="fas fa-calendar me-1"></i>All Events</a></li>
                    <li class="nav-item"><a class="nav-link active" href="teacher_events.php"><i class="fas fa-user-tie me-1"></i>My Events</a></li>
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

    <div class="dashboard-container">
        <div class="welcome-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-comments me-2"></i>Feedback for <?php echo htmlspecialchars($event['title']); ?></h2>
                    <p>View all feedback submitted for your event</p>
                </div>
                <a class="btn btn-secondary" href="teacher_events.php"><i class="fas fa-arrow-left me-1"></i>Back to My Events</a>
            </div>
        </div>

        <?php if ($feedbacks === false): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Feedback table not available. Error: <?php echo htmlspecialchars($feedbackQueryError); ?>
            </div>
        <?php else: ?>
            <?php if ($feedbacks->num_rows === 0): ?>
                <div class="empty-state card">
                    <i class="fas fa-comment-slash"></i>
                    <h4>No feedback yet</h4>
                    <p>No one has submitted feedback for this event yet.</p>
                </div>
            <?php else: ?>
                <div class="mb-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-list me-2"></i>All Feedback (<?php echo $feedbacks->num_rows; ?>)</span>
                        </div>
                        <div class="card-body p-0">
                            <?php while ($fb = $feedbacks->fetch_assoc()): ?>
                                <div class="feedback-item">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="mb-1">
                                                <i class="fas fa-user me-1"></i>
                                                From: <?php echo htmlspecialchars($fb['user_type']); ?> #<?php echo (int)$fb['user_id']; ?>
                                            </h6>
                                            <?php if (isset($fb['rating']) && $fb['rating'] !== null): ?>
                                                <div class="rating-stars">
                                                    <?php 
                                                    $rating = (int)$fb['rating'];
                                                    for ($i = 1; $i <= 5; $i++): 
                                                        if ($i <= $rating): ?>
                                                            <i class="fas fa-star"></i>
                                                        <?php else: ?>
                                                            <i class="far fa-star"></i>
                                                        <?php endif;
                                                    endfor; ?>
                                                    <span class="ms-2 text-muted">(<?php echo $rating; ?>/5)</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo isset($fb['feedback_date']) ? htmlspecialchars($fb['feedback_date']) : ''; ?>
                                        </small>
                                    </div>
                                    <p class="mb-0 mt-3"><?php echo nl2br(htmlspecialchars($fb['comments'] ?? '')); ?></p>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>