<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit();
}
$conn = new mysqli('localhost', 'root', '', 'event_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$teacher_id = $_SESSION['user_id'];
// Get events created by this teacher
$events = $conn->query("SELECT * FROM events WHERE created_by_user_type = 'teacher' AND created_by_user_id = $teacher_id");
$event_ids = [];
while ($e = $events->fetch_assoc()) {
    $event_ids[] = $e['event_id'];
}
$feedbacks = [];
if ($event_ids) {
    $ids = implode(',', $event_ids);
    $sql = "SELECT f.*, e.title, s.first_name, s.last_name FROM feedback f JOIN events e ON f.event_id = e.event_id JOIN students s ON f.user_id = s.student_id WHERE f.user_type = 'student' AND f.event_id IN ($ids) ORDER BY f.event_id DESC";
    $feedbacks = $conn->query($sql);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Feedbacks - Teacher</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Feedback for Your Events</h2>
            <a href="teacher_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>First Name</th>
                                <th>Last Name</th>
                                <th>Comments</th>
                                <th>Rating</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($feedbacks && $feedbacks->num_rows > 0): ?>
                                <?php while ($fb = $feedbacks->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($fb['title']); ?></td>
                                        <td><?php echo htmlspecialchars($fb['first_name']); ?></td>
                                        <td><?php echo htmlspecialchars($fb['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($fb['comments']); ?></td>
                                        <td><?php echo htmlspecialchars($fb['rating']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($fb['feedback_date'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center">No feedbacks yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 