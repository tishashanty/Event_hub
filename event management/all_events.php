<?php
session_start();

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['student','teacher'])) {
    header('Location: login.php');
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'event_management');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$userId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['role'];

$registerMsg = '';
if (isset($_POST['register_event_id'])) {
    $eventId = (int)$_POST['register_event_id'];
    
    // Check if event is in the past
    $eventCheck = $conn->query("SELECT event_date FROM events WHERE event_id = $eventId");
    if ($eventCheck && $eventCheck->num_rows > 0) {
        $event = $eventCheck->fetch_assoc();
        $eventDate = new DateTime($event['event_date']);
        $today = new DateTime();
        
        if ($eventDate < $today) {
            $registerMsg = '<div class="alert alert-warning">Cannot register for past events.</div>';
        } else {
            // Check if already registered
            $check = $conn->query("SELECT 1 FROM registrations WHERE event_id = $eventId AND user_type = '".$conn->real_escape_string($userRole)."' AND user_id = $userId");
            if ($check && $check->num_rows === 0) {
                $conn->query("INSERT INTO registrations (event_id, user_type, user_id) VALUES ($eventId, '".$conn->real_escape_string($userRole)."', $userId)");
                $registerMsg = '<div class="alert alert-success">Registered successfully!</div>';
            } else {
                $registerMsg = '<div class="alert alert-warning">Already registered for this event.</div>';
            }
        }
    } else {
        $registerMsg = '<div class="alert alert-danger">Event not found.</div>';
    }
}

// Handle search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchCondition = '';

if (!empty($search)) {
    $searchEscaped = $conn->real_escape_string($search);
    $searchCondition = "WHERE (title LIKE '%$searchEscaped%' OR description LIKE '%$searchEscaped%' OR venue LIKE '%$searchEscaped%')";
}

// Get events with search filter
$eventsQuery = "SELECT * FROM events $searchCondition ORDER BY event_date DESC, event_time DESC";
$events = $conn->query($eventsQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Events</title>
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
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?php echo $userRole === 'student' ? 'student_dashboard.php' : 'teacher_dashboard.php'; ?>">
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
                        <a class="nav-link" href="my_registrations.php">
                            <i class="fas fa-ticket-alt me-1"></i>My Registrations
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="past_events.php">
                            <i class="fas fa-history me-1"></i>Past Events
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="all_events.php">
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
        <?php echo $registerMsg; ?>
        
        <!-- Search Bar -->
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" class="d-flex gap-2">
                    <div class="flex-grow-1">
                        <input type="text" class="form-control" name="search" placeholder="Search events by title, description, or venue..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>Search
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="all_events.php" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>Clear
                        </a>
                    <?php endif; ?>
                </form>
                <?php if (!empty($search)): ?>
                    <div class="mt-2">
                        <small class="text-muted">Search results for: "<strong><?php echo htmlspecialchars($search); ?></strong>"</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5>All Events
                    <?php if (!empty($search)): ?>
                        <span class="badge bg-info ms-2"><?php echo $events->num_rows; ?> result(s)</span>
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Venue</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($event = $events->fetch_assoc()):
                                $eventDate = new DateTime($event['event_date']);
                                $now = new DateTime();
                                $isPast = $eventDate < $now;
                                
                                // Check if user is already registered for this event
                                $checkRegistered = $conn->query("SELECT 1 FROM registrations WHERE event_id = ".$event['event_id']." AND user_type = '".$conn->real_escape_string($userRole)."' AND user_id = $userId");
                                $isRegistered = $checkRegistered && $checkRegistered->num_rows > 0;
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($event['title']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($event['event_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($event['event_time']); ?></td>
                                    <td><?php echo htmlspecialchars($event['venue']); ?></td>
                                    <td>
                                        <?php if ($isPast): ?>
                                            <span class="badge bg-secondary">Past Event</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Upcoming</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($isRegistered): ?>
                                            <span class="text-success">Registered</span>
                                        <?php elseif ($isPast): ?>
                                            <button class="btn btn-secondary btn-sm" disabled>Registration Closed</button>
                                        <?php else: ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="register_event_id" value="<?php echo (int)$event['event_id']; ?>">
                                                <button type="submit" class="btn btn-primary btn-sm">Register</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
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