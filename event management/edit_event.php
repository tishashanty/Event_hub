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
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
$event = $conn->query("SELECT * FROM events WHERE event_id = $event_id")->fetch_assoc();
if (!$event) {
    echo '<div class="alert alert-danger">Event not found.</div>';
    exit();
}
// Ensure duration column exists
$conn->query("ALTER TABLE events ADD COLUMN IF NOT EXISTS duration INT DEFAULT 60");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $event_date = $_POST['event_date'];
    $event_time = $_POST['event_time'];
    $venue = trim($_POST['venue']);
    $duration = intval($_POST['duration']);
    if ($title && $event_date && $event_time) {
        // Validation A: prevent any other event at the exact same date and time (regardless of venue, excluding this event)
        $conflictTimeCount = 0;
        $checkTimeStmt = $conn->prepare("SELECT COUNT(*) FROM events WHERE event_date = ? AND event_time = ? AND event_id <> ?");
        $checkTimeStmt->bind_param('ssi', $event_date, $event_time, $event_id);
        if ($checkTimeStmt->execute()) {
            $checkTimeStmt->bind_result($conflictTimeCount);
            $checkTimeStmt->fetch();
        }
        $checkTimeStmt->close();

        // Validation B: Check for venue conflicts considering duration
        $conflictVenueCount = 0;
        $checkVenueStmt = $conn->prepare("
            SELECT COUNT(*) FROM events 
            WHERE event_date = ? AND venue = ? AND event_id <> ? AND (
                (TIME_TO_SEC(event_time) <= TIME_TO_SEC(?) AND TIME_TO_SEC(event_time) + (duration * 60) > TIME_TO_SEC(?)) OR
                (TIME_TO_SEC(?) < TIME_TO_SEC(event_time) + (duration * 60) AND TIME_TO_SEC(?) >= TIME_TO_SEC(event_time))
            )
        ");
        $checkVenueStmt->bind_param('ssissss', $event_date, $venue, $event_id, $event_time, $event_time, $event_time, $event_time);
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
            $stmt = $conn->prepare("UPDATE events SET title=?, description=?, event_date=?, event_time=?, venue=?, duration=? WHERE event_id=?");
            $stmt->bind_param('sssssii', $title, $description, $event_date, $event_time, $venue, $duration, $event_id);
            if ($stmt->execute()) {
                $success = 'Event updated successfully!';
                // Refresh event data
                $event = $conn->query("SELECT * FROM events WHERE event_id = $event_id")->fetch_assoc();
            } else {
                $error = 'Failed to update event.';
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
    <title>Edit Event - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .edit-event-container { max-width: 600px; margin: 40px auto; }
    </style>
</head>
<body>
    <div class="edit-event-container">
        <div class="card">
            <div class="card-header text-center">
                <h3>Edit Event</h3>
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
                        <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($event['title']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description"><?php echo htmlspecialchars($event['description']); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="event_date" class="form-label">Date</label>
                        <input type="date" class="form-control" id="event_date" name="event_date" value="<?php echo $event['event_date']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="event_time" class="form-label">Time</label>
                        <input type="time" class="form-control" id="event_time" name="event_time" value="<?php echo $event['event_time']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="venue" class="form-label">Venue</label>
                        <select class="form-select" id="venue" name="venue" required>
                            <option value="">Select Venue</option>
                            <option value="Seminar Hall" <?php echo ($event['venue'] == 'Seminar Hall') ? 'selected' : ''; ?>>Seminar Hall</option>
                            <option value="Yoga Hall" <?php echo ($event['venue'] == 'Yoga Hall') ? 'selected' : ''; ?>>Yoga Hall</option>
                            <option value="Main Theatre" <?php echo ($event['venue'] == 'Main Theatre') ? 'selected' : ''; ?>>Main Theatre</option>
                            <option value="Mini Theatre" <?php echo ($event['venue'] == 'Mini Theatre') ? 'selected' : ''; ?>>Mini Theatre</option>
                            <option value="Open Auditorium" <?php echo ($event['venue'] == 'Open Auditorium') ? 'selected' : ''; ?>>Open Auditorium</option>
                            <option value="Room 1" <?php echo ($event['venue'] == 'Room 1') ? 'selected' : ''; ?>>Room 1</option>
                            <option value="Room 2" <?php echo ($event['venue'] == 'Room 2') ? 'selected' : ''; ?>>Room 2</option>
                            <option value="Room 3" <?php echo ($event['venue'] == 'Room 3') ? 'selected' : ''; ?>>Room 3</option>
                            <option value="Room 4" <?php echo ($event['venue'] == 'Room 4') ? 'selected' : ''; ?>>Room 4</option>
                            <option value="Room 5" <?php echo ($event['venue'] == 'Room 5') ? 'selected' : ''; ?>>Room 5</option>
                            <option value="Lab 1" <?php echo ($event['venue'] == 'Lab 1') ? 'selected' : ''; ?>>Lab 1</option>
                            <option value="Lab 2" <?php echo ($event['venue'] == 'Lab 2') ? 'selected' : ''; ?>>Lab 2</option>
                            <option value="Lab 3" <?php echo ($event['venue'] == 'Lab 3') ? 'selected' : ''; ?>>Lab 3</option>
                            <option value="Lab 4" <?php echo ($event['venue'] == 'Lab 4') ? 'selected' : ''; ?>>Lab 4</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="duration" class="form-label">Duration (minutes)</label>
                        <input type="number" class="form-control" id="duration" name="duration" min="15" max="480" value="<?php echo isset($event['duration']) ? $event['duration'] : 60; ?>" required>
                        <small class="form-text text-muted">Minimum 15 minutes, Maximum 8 hours (480 minutes)</small>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Update Event</button>
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