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
$success = '';
$error = '';
// Ensure duration column exists
$conn->query("ALTER TABLE events ADD COLUMN IF NOT EXISTS duration INT DEFAULT 60");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $event_date = $_POST['event_date'];
    $event_time = $_POST['event_time'];
    $venue = trim($_POST['venue']);
    $duration = intval($_POST['duration']);
    $admin_id = $_SESSION['user_id'];
    if ($title && $event_date && $event_time) {
        // Validation A: prevent any other event at the exact same date and time (regardless of venue)
        $conflictTimeCount = 0;
        $checkTimeStmt = $conn->prepare("SELECT COUNT(*) FROM events WHERE event_date = ? AND event_time = ?");
        $checkTimeStmt->bind_param('ss', $event_date, $event_time);
        if ($checkTimeStmt->execute()) {
            $checkTimeStmt->bind_result($conflictTimeCount);
            $checkTimeStmt->fetch();
        }
        $checkTimeStmt->close();

        // Validation B: Check for venue conflicts considering duration
        $conflictVenueCount = 0;
        $checkVenueStmt = $conn->prepare("
            SELECT COUNT(*) FROM events 
            WHERE event_date = ? AND venue = ? AND (
                (TIME_TO_SEC(event_time) <= TIME_TO_SEC(?) AND TIME_TO_SEC(event_time) + (duration * 60) > TIME_TO_SEC(?)) OR
                (TIME_TO_SEC(?) < TIME_TO_SEC(event_time) + (duration * 60) AND TIME_TO_SEC(?) >= TIME_TO_SEC(event_time))
            )
        ");
        $checkVenueStmt->bind_param('ssssss', $event_date, $venue, $event_time, $event_time, $event_time, $event_time);
        if ($checkVenueStmt->execute()) {
            $checkVenueStmt->bind_result($conflictVenueCount);
            $checkVenueStmt->fetch();
        }
        $checkVenueStmt->close();

        if ($conflictTimeCount > 0) {
            $error = 'There is already another program at this date and time.';
        } elseif ($conflictVenueCount > 0) {
            $error = 'There is already another program at this venue that conflicts with the specified time and duration.';
        } else {
            $stmt = $conn->prepare("INSERT INTO events (title, description, event_date, event_time, venue, duration, created_by_user_type, created_by_user_id) VALUES (?, ?, ?, ?, ?, ?, 'admin', ?)");
            $stmt->bind_param('sssssii', $title, $description, $event_date, $event_time, $venue, $duration, $admin_id);
            if ($stmt->execute()) {
                $success = 'Event added successfully!';
            } else {
                $error = 'Failed to add event.';
            }
            $stmt->close();
        }
    } else {
        $error = 'Please fill in all required fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Event - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
      body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .add-event-container { max-width: 600px; margin: 40px auto; }
    </style>
</head>
<body>
    <div class="add-event-container">
        <div class="card">
            <div class="card-header text-center">
                <h3>Add New Event</h3>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php elseif ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="event_date" class="form-label">Date</label>
                        <input type="date" class="form-control" id="event_date" name="event_date" required>
                    </div>
                    <div class="mb-3">
                        <label for="event_time" class="form-label">Time</label>
                        <input type="time" class="form-control" id="event_time" name="event_time" required>
                    </div>
                    <div class="mb-3">
                        <label for="venue" class="form-label">Venue</label>
                        <select class="form-select" id="venue" name="venue" required>
                            <option value="">Select Venue</option>
                            <option value="Seminar Hall">Seminar Hall</option>
                            <option value="Yoga Hall">Yoga Hall</option>
                            <option value="Main Theatre">Main Theatre</option>
                            <option value="Mini Theatre">Mini Theatre</option>
                            <option value="Open Auditorium">Open Auditorium</option>
                            <option value="Room 1">Room 1</option>
                            <option value="Room 2">Room 2</option>
                            <option value="Room 3">Room 3</option>
                            <option value="Room 4">Room 4</option>
                            <option value="Room 5">Room 5</option>
                            <option value="Lab 1">Lab 1</option>
                            <option value="Lab 2">Lab 2</option>
                            <option value="Lab 3">Lab 3</option>
                            <option value="Lab 4">Lab 4</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="duration" class="form-label">Duration (minutes)</label>
                        <input type="number" class="form-control" id="duration" name="duration" min="15" max="480" value="60" required>
                        <small class="form-text text-muted">Minimum 15 minutes, Maximum 8 hours (480 minutes)</small>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Add Event</button>
                </form>
                <div class="mt-3 text-center">
                    <a href="manage_events.php">Back to Manage Events</a>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 