<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}
$conn = new mysqli('localhost', 'root', '', 'event_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
$event = $conn->query("SELECT * FROM events WHERE event_id = $event_id")->fetch_assoc();
if (!$event) {
    echo '<div class="alert alert-danger">Event not found.</div>';
    exit();
}
$tickets = $conn->query("SELECT r.registration_id, s.full_name, s.email FROM registrations r JOIN students s ON r.user_id = s.student_id WHERE r.event_id = $event_id AND r.user_type = 'student'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets for <?php echo htmlspecialchars($event['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Tickets for: <?php echo htmlspecialchars($event['title']); ?></h2>
            <a href="admin_events.php" class="btn btn-secondary">Back to Events</a>
        </div>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Ticket Number</th>
                                <th>First Name</th>
                                <th>Last Name</th>
                                <th>Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($tickets->num_rows > 0): ?>
                                <?php while ($row = $tickets->fetch_assoc()): ?>
                                    <?php 
                                        $nameParts = explode(' ', $row['full_name'], 2);
                                        $first_name = $nameParts[0];
                                        $last_name = isset($nameParts[1]) ? $nameParts[1] : '';
                                    ?>
                                    <tr>
                                        <td><?php echo $row['registration_id']; ?></td>
                                        <td><?php echo htmlspecialchars($first_name); ?></td>
                                        <td><?php echo htmlspecialchars($last_name); ?></td>
                                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center">No tickets issued for this event.</td></tr>
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