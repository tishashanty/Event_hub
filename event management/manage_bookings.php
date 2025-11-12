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

// Handle delete booking
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $registration_id = intval($_GET['delete']);
    if ($conn->query("DELETE FROM registrations WHERE registration_id = $registration_id")) {
        $success = 'Booking deleted successfully!';
    } else {
        $error = 'Failed to delete booking.';
    }
}

// Fetch bookings grouped by event
$bookings = $conn->query("SELECT r.*, e.event_id, e.title, e.event_date, e.venue,
    CASE WHEN r.user_type = 'student' THEN s.first_name WHEN r.user_type = 'teacher' THEN t.first_name ELSE '' END as user_first_name,
    CASE WHEN r.user_type = 'student' THEN s.last_name WHEN r.user_type = 'teacher' THEN t.last_name ELSE '' END as user_last_name,
    CASE WHEN r.user_type = 'student' THEN s.email WHEN r.user_type = 'teacher' THEN t.email ELSE '' END as user_email
    FROM registrations r
    JOIN events e ON r.event_id = e.event_id
    LEFT JOIN students s ON r.user_type = 'student' AND r.user_id = s.student_id
    LEFT JOIN teachers t ON r.user_type = 'teacher' AND r.user_id = t.teacher_id
    ORDER BY e.event_date DESC, r.registration_date DESC");
?>

<?php
$activePage = 'bookings';
include __DIR__ . '/includes/admin_layout_start.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Manage Bookings</h2>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php elseif ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php
$currentEventId = null;
while ($row = $bookings->fetch_assoc()):
    if ($currentEventId !== $row['event_id']):
        // Close previous event’s table
        if ($currentEventId !== null) {
            echo "</tbody></table></div></div>";
        }

        // Start new event block
        echo '<div class="card mb-4"><div class="card-body">';
        echo '<h4 class="mb-3">' . htmlspecialchars($row['title']) . 
             ' <small>(' . date('M d, Y', strtotime($row['event_date'])) . 
             ' @ ' . htmlspecialchars($row['venue']) . ')</small></h4>';

        // Download button
        echo '<a href="download_participants.php?event_id=' . $row['event_id'] . '" 
                class="btn btn-primary btn-sm mb-3" target="_blank">Download Participants</a>';

        echo '<div class="table-responsive"><table class="table table-bordered">';
        echo '<thead>
                <tr>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>User Type</th>
                    <th>Email</th>
                    <th>Registration Date</th>
                    <th>Actions</th>
                </tr>
              </thead><tbody>';

        $currentEventId = $row['event_id'];
    endif;
    ?>
    <tr>
        <td><?php echo htmlspecialchars($row['user_first_name']); ?></td>
        <td><?php echo htmlspecialchars($row['user_last_name']); ?></td>
        <td><?php echo htmlspecialchars(ucfirst($row['user_type'])); ?></td>
        <td><?php echo htmlspecialchars($row['user_email']); ?></td>
        <td><?php echo date('M d, Y H:i', strtotime($row['registration_date'])); ?></td>
        <td>
            <a href="manage_bookings.php?delete=<?php echo $row['registration_id']; ?>" 
               class="btn btn-danger btn-sm" 
               onclick="return confirm('Delete this booking?');">Delete</a>
        </td>
    </tr>
<?php endwhile; 

// Close last event’s table if there was at least one
if ($currentEventId !== null) {
    echo "</tbody></table></div></div>";
}
?>

<?php include __DIR__ . '/includes/admin_layout_end.php'; ?>