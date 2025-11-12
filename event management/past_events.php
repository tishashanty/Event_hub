<?php
session_start();

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['student', 'teacher'])) {
    header('Location: login.php');
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'event_management');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$userRole = $_SESSION['role'];

// Fetch past events
$past_events = $conn->query("SELECT * FROM events WHERE event_date < CURDATE() ORDER BY event_date DESC");

// Close database connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Past Events - EventHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
         * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
        }

        .container-narrow { 
            max-width: 1200px; 
            margin: 0 auto;
            padding: 2rem 1rem;
        }

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

        .main-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
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

        .card-header h5 {
            margin: 0;
            font-weight: 600;
            font-size: 1.25rem;
        }

        .card-body {
            padding: 24px;
        }

        .professional-table {
            margin: 0;
            background: white;
        }

        .professional-table thead th {
            background: #f8f9fa;
            color: #495057;
            border: none;
            padding: 1rem 1.5rem;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #dee2e6;
        }

        .professional-table tbody tr {
            border: none;
            transition: background-color 0.2s ease;
        }

        .professional-table tbody tr:hover {
            background: #f8f9fa;
        }

        .professional-table tbody tr:nth-child(even) {
            background: #fdfdfd;
        }

        .professional-table tbody tr:nth-child(even):hover {
            background: #f8f9fa;
        }

        .professional-table tbody td {
            border: none;
            padding: 1rem 1.5rem;
            color: #495057;
            border-bottom: 1px solid #f1f3f4;
            vertical-align: middle;
        }

        .event-title {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1rem;
        }

        .event-date {
            color: #6c757d;
            font-weight: 500;
        }

        .event-time {
            color: #6c757d;
        }

        .event-venue {
            color: #495057;
        }

        .status-completed {
            background: #28a745;
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6c757d;
        }

        .empty-state i {
            color: #dee2e6;
            margin-bottom: 1rem;
        }

        .empty-state h5 {
            color: #495057;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .table-icon {
            color: #6c757d;
            margin-right: 0.5rem;
            width: 14px;
        }

        /* Professional responsive design */
        @media (max-width: 768px) {
            .container-narrow {
                padding: 1rem 0.5rem;
            }
            
            .main-card {
                border-radius: 8px;
                margin: 0 0.5rem;
            }
            
            .card-header {
                padding: 1rem 1.5rem !important;
            }
            
            .professional-table thead th,
            .professional-table tbody td {
                padding: 0.75rem 1rem;
                font-size: 0.875rem;
            }
            
            .card-header h5 {
                font-size: 1.1rem;
            }

            .professional-table {
                font-size: 0.9rem;
            }
        }

        /* Clean focus states */
        .nav-link:focus,
        .dropdown-toggle:focus {
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
            outline: none;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?php echo $userRole === 'student' ? 'student_dashboard.php' : 'teacher_dashboard.php'; ?>">
                <i class="fas fa-calendar-alt me-2"></i>
                EventHub
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
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
                        <a class="nav-link active" href="past_events.php">
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
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" 
                           data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['first_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li>
                                <a class="dropdown-item" href="logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container-narrow">
        <div class="main-card">
            <div class="card-header">
                <h5>
                    <i class="fas fa-history me-2"></i>Past Events
                </h5>
            </div>
            <div class="card-body">
                <?php if ($past_events && $past_events->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="professional-table table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-tag table-icon"></i>Event Title</th>
                                <th><i class="fas fa-calendar-alt table-icon"></i>Date</th>
                                <th><i class="fas fa-clock table-icon"></i>Time</th>
                                <th><i class="fas fa-map-marker-alt table-icon"></i>Venue</th>
                                <th><i class="fas fa-check-circle table-icon"></i>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($event = $past_events->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="event-title"><?php echo htmlspecialchars($event['title']); ?></div>
                                    </td>
                                    <td class="event-date">
                                        <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                                    </td>
                                    <td class="event-time">
                                        <?php 
                                        if (!empty($event['event_time'])) {
                                            echo htmlspecialchars($event['event_time']); 
                                        } else {
                                            echo '<span style="color: #adb5bd;">Not specified</span>';
                                        }
                                        ?>
                                    </td>
                                    <td class="event-venue">
                                        <?php echo htmlspecialchars($event['venue']); ?>
                                    </td>
                                    <td>
                                        <span class="status-completed">
                                            <i class="fas fa-check me-1"></i>Completed
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times" style="font-size: 3rem;"></i>
                    <h5>No Past Events Found</h5>
                    <p>There are no completed events to display at this time.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>