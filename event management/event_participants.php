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
    header('Location: teacher_events.php?error=invalid_event');
    exit();
}

// Ensure the event belongs to this teacher using prepared statement
$stmt = $conn->prepare("SELECT * FROM events WHERE event_id = ? AND created_by_user_type = 'teacher' AND created_by_user_id = ?");
$stmt->bind_param("ii", $eventId, $teacherId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: teacher_events.php?error=no_permission');
    exit();
}
$event = $result->fetch_assoc();
$stmt->close();

// Get student participants with their names using prepared statement
$stmt = $conn->prepare("
    SELECT r.user_id, s.first_name, s.last_name, s.email, r.registration_date
    FROM registrations r 
    JOIN students s ON r.user_id = s.student_id 
    WHERE r.event_id = ? AND r.user_type = 'student'
    ORDER BY s.first_name, s.last_name
");
$stmt->bind_param("i", $eventId);
$stmt->execute();
$students = $stmt->get_result();
$stmt->close();

// Get teacher participants with their names using prepared statement
$stmt = $conn->prepare("
    SELECT r.user_id, t.first_name, t.last_name, t.email, r.registration_date
    FROM registrations r 
    JOIN teachers t ON r.user_id = t.teacher_id 
    WHERE r.event_id = ? AND r.user_type = 'teacher'
    ORDER BY t.first_name, t.last_name
");
$stmt->bind_param("i", $eventId);
$stmt->execute();
$teachers = $stmt->get_result();
$stmt->close();

$totalParticipants = $students->num_rows + $teachers->num_rows;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Participants - <?php echo htmlspecialchars($event['title']); ?></title>
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

        .page-header h3 {
            color: #1e293b;
            font-weight: 700;
            margin-bottom: 10px;
            font-size: 1.8rem;
        }

        .event-details {
            color: #64748b;
            font-size: 0.95rem;
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
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-body {
            padding: 0;
        }

        /* List Styles */
        .list-group-item {
            border: none;
            padding: 15px 24px;
            background: transparent;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
        }

        .list-group-item:hover {
            background-color: rgba(30, 41, 59, 0.05);
            transform: translateX(5px);
        }

        .list-group-item:last-child {
            border-bottom: none;
        }

        .participant-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 1rem;
        }

        .participant-email {
            color: #64748b;
            font-size: 0.9rem;
            margin-top: 2px;
        }

        .participant-date {
            color: #94a3b8;
            font-size: 0.85rem;
            font-style: italic;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #64748b;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Statistics */
        .stats-card {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            border: none;
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            line-height: 1;
        }

        .stats-label {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-top: 5px;
        }

        /* Action Buttons */
        .btn {
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-secondary {
            background-color: #64748b;
            border-color: #64748b;
        }

        .btn-primary {
            background-color: #3b82f6;
            border-color: #3b82f6;
        }

        /* Export Button */
        .export-btn {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border: none;
            color: white;
        }

        .export-btn:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="teacher_dashboard.php">
                <i class="fas fa-calendar-alt me-2"></i>
                EventHub
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="teacher_dashboard.php">
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
                        <a class="nav-link" href="all_events.php">
                            <i class="fas fa-calendar me-1"></i>All Events
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="teacher_events.php">
                            <i class="fas fa-user-tie me-1"></i>My Events
                        </a>
                    </li>
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
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h3>
                        <i class="fas fa-users me-2 text-primary"></i>
                        Event Participants
                    </h3>
                    <div class="event-details">
                        <strong><?php echo htmlspecialchars($event['title']); ?></strong><br>
                        <i class="fas fa-calendar me-1"></i>
                        <?php echo date('F d, Y', strtotime($event['event_date'])); ?>
                        <i class="fas fa-clock ms-3 me-1"></i>
                        <?php echo htmlspecialchars($event['event_time']); ?>
                        <i class="fas fa-map-marker-alt ms-3 me-1"></i>
                        <?php echo htmlspecialchars($event['venue']); ?>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn export-btn" onclick="exportParticipants()">
                        <i class="fas fa-download me-1"></i>Export List
                    </button>
                    <a class="btn btn-secondary" href="teacher_events.php">
                        <i class="fas fa-arrow-left me-1"></i>Back
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistics Row -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <div class="stats-number"><?php echo $totalParticipants; ?></div>
                        <div class="stats-label">Total Participants</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <div class="stats-number"><?php echo $students->num_rows; ?></div>
                        <div class="stats-label">Students</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <div class="stats-number"><?php echo $teachers->num_rows; ?></div>
                        <div class="stats-label">Teachers</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Participants Lists -->
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <span>
                            <i class="fas fa-user-graduate me-2"></i>
                            Students
                        </span>
                        <span class="badge bg-light text-dark"><?php echo $students->num_rows; ?></span>
                    </div>
                    <div class="card-body">
                        <?php if ($students->num_rows > 0): ?>
                            <ul class="list-group list-group-flush">
                                <?php while ($student = $students->fetch_assoc()): ?>
                                    <li class="list-group-item">
                                        <div class="participant-name">
                                            <i class="fas fa-user me-2 text-muted"></i>
                                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                        </div>
                                        <div class="participant-email">
                                            <i class="fas fa-envelope me-2 text-muted"></i>
                                            <?php echo htmlspecialchars($student['email']); ?>
                                        </div>
                                        <div class="participant-date">
                                            Registered: <?php echo date('M d, Y g:i A', strtotime($student['registration_date'])); ?>
                                        </div>
                                    </li>
                                <?php endwhile; ?>
                            </ul>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-user-graduate"></i>
                                <h5>No Students Yet</h5>
                                <p>No students have registered for this event.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <span>
                            <i class="fas fa-chalkboard-teacher me-2"></i>
                            Teachers
                        </span>
                        <span class="badge bg-light text-dark"><?php echo $teachers->num_rows; ?></span>
                    </div>
                    <div class="card-body">
                        <?php if ($teachers->num_rows > 0): ?>
                            <ul class="list-group list-group-flush">
                                <?php while ($teacher = $teachers->fetch_assoc()): ?>
                                    <li class="list-group-item">
                                        <div class="participant-name">
                                            <i class="fas fa-user-tie me-2 text-muted"></i>
                                            <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                        </div>
                                        <div class="participant-email">
                                            <i class="fas fa-envelope me-2 text-muted"></i>
                                            <?php echo htmlspecialchars($teacher['email']); ?>
                                        </div>
                                        <div class="participant-date">
                                            Registered: <?php echo date('M d, Y g:i A', strtotime($teacher['registration_date'])); ?>
                                        </div>
                                    </li>
                                <?php endwhile; ?>
                            </ul>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-chalkboard-teacher"></i>
                                <h5>No Other Teachers</h5>
                                <p>No other teachers have registered for this event.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportParticipants() {
            const eventTitle = "<?php echo addslashes($event['title']); ?>";
            const eventDate = "<?php echo date('F d, Y', strtotime($event['event_date'])); ?>";
            
            // Create CSV content
            let csvContent = "Event: " + eventTitle + "\n";
            csvContent += "Date: " + eventDate + "\n\n";
            csvContent += "Type,Name,Email,Registration Date\n";
            
            // Add students
            <?php
            $students->data_seek(0); // Reset result pointer
            while ($student = $students->fetch_assoc()): ?>
            csvContent += "Student,\"<?php echo addslashes($student['first_name'] . ' ' . $student['last_name']); ?>\",\"<?php echo addslashes($student['email']); ?>\",\"<?php echo date('M d, Y g:i A', strtotime($student['registration_date'])); ?>\"\n";
            <?php endwhile; ?>
            
            // Add teachers
            <?php
            $teachers->data_seek(0); // Reset result pointer
            while ($teacher = $teachers->fetch_assoc()): ?>
            csvContent += "Teacher,\"<?php echo addslashes($teacher['first_name'] . ' ' . $teacher['last_name']); ?>\",\"<?php echo addslashes($teacher['email']); ?>\",\"<?php echo date('M d, Y g:i A', strtotime($teacher['registration_date'])); ?>\"\n";
            <?php endwhile; ?>
            
            // Create and download file
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement("a");
            const url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", "participants_" + eventTitle.replace(/[^a-z0-9]/gi, '_').toLowerCase() + ".csv");
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>
<?php
$conn->close();
?>