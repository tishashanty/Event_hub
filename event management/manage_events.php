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
// Handle delete event
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $event_id = intval($_GET['delete']);
    if ($conn->query("DELETE FROM events WHERE event_id = $event_id")) {
        $success = 'Event deleted successfully!';
    } else {
        $error = 'Failed to delete event.';
    }
}
$events = $conn->query("SELECT e.*, 
                              t.first_name as teacher_first_name, 
                              t.last_name as teacher_last_name, 
                              t.designation,
                              s.first_name as student_first_name,
                              s.last_name as student_last_name
                       FROM events e 
                       LEFT JOIN teachers t ON e.created_by_user_id = t.teacher_id AND e.created_by_user_type = 'teacher'
                       LEFT JOIN students s ON e.created_by_user_id = s.student_id AND e.created_by_user_type = 'student'
                       ORDER BY e.event_date DESC");
?>
<?php
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: admin_login.php');
    exit();
}
$activePage = 'events';
include __DIR__ . '/includes/admin_layout_start.php';
?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Manage Events</h2>
        </div>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Venue</th>
                                <th>Created By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($event = $events->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($event['title']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($event['event_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($event['event_time']); ?></td>
                                    <td><?php echo htmlspecialchars($event['venue']); ?></td>
                                    <td>
                                        <?php if ($event['created_by_user_type'] === 'teacher' && !empty($event['teacher_first_name'])): ?>
                                            <?php echo htmlspecialchars($event['teacher_first_name'] . ' ' . $event['teacher_last_name']); ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($event['designation']); ?></small>
                                        <?php elseif ($event['created_by_user_type'] === 'student' && !empty($event['student_first_name'])): ?>
                                            <?php echo htmlspecialchars($event['student_first_name'] . ' ' . $event['student_last_name']); ?>
                                            <br><small class="text-muted">Student</small>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($event['created_by_user_type']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="edit_event.php?event_id=<?php echo $event['event_id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                        <a href="manage_events.php?delete=<?php echo $event['event_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this event?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <a href="add_event.php" class="btn btn-success mt-3">Add New Event</a>
            </div>
        </div>
<?php include __DIR__ . '/includes/admin_layout_end.php'; ?>