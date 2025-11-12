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
// Statistics
$total_events = $conn->query("SELECT COUNT(*) as count FROM events")->fetch_assoc()['count'];
$total_students = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
$total_teachers = $conn->query("SELECT COUNT(*) as count FROM teachers")->fetch_assoc()['count'];
$total_admins = $conn->query("SELECT COUNT(*) as count FROM admin")->fetch_assoc()['count'];
$total_users = $total_students + $total_teachers + $total_admins;
$total_bookings = $conn->query("SELECT COUNT(*) as count FROM registrations")->fetch_assoc()['count'];
$total_feedbacks = $conn->query("SELECT COUNT(*) as count FROM feedback")->fetch_assoc()['count'];
// Top 5 events by registrations
$top_events = $conn->query("SELECT e.title, COUNT(r.registration_id) as reg_count FROM events e LEFT JOIN registrations r ON e.event_id = r.event_id GROUP BY e.event_id ORDER BY reg_count DESC, e.event_date DESC LIMIT 5");
?>
<?php
$activePage = 'reports';
include __DIR__ . '/includes/admin_layout_start.php';
?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Reports & Analytics</h2>
        </div>
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Total Events</h5>
                        <h2><?php echo $total_events; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Total Users</h5>
                        <h2><?php echo $total_users; ?></h2>
                        <small>(Students: <?php echo $total_students; ?>, Teachers: <?php echo $total_teachers; ?>, Admins: <?php echo $total_admins; ?>)</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Total Bookings</h5>
                        <h2><?php echo $total_bookings; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Total Feedbacks</h5>
                        <h2><?php echo $total_feedbacks; ?></h2>
                    </div>
                </div>
            </div>
        </div>
        <div class="card mb-4">
            <div class="card-header"><h5>Top 5 Most Popular Events</h5></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>Registrations</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $top_events->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                                    <td><?php echo $row['reg_count']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
<?php include __DIR__ . '/includes/admin_layout_end.php'; ?>