<?php
// Expect $activePage to be set by the including page (e.g., 'dashboard', 'events', 'users', 'bookings', 'reports', 'settings', 'feedback')
if (!isset($activePage)) {
    $activePage = '';
}

// Database connection to get pending requests count
$conn = new mysqli('localhost', 'root', '', 'event_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get count of pending requests
$pendingCount = 0;
$pendingResult = $conn->query("SELECT COUNT(*) as count FROM event_requests WHERE status = 'pending'");
if ($pendingResult && $pendingResult->num_rows > 0) {
    $pendingCount = $pendingResult->fetch_assoc()['count'];
}
$conn->close();
?>
<div class="col-md-3 col-lg-2 sidebar">
    <h3 class="text-white text-center mb-4">Admin Panel</h3>
    <nav>
        <a href="admin_dashboard.php" class="<?php echo $activePage === 'dashboard' ? 'active' : ''; ?>"><i class="fas fa-home me-2"></i> Dashboard</a>
        <a href="manage_events.php" class="<?php echo $activePage === 'events' ? 'active' : ''; ?>"><i class="fas fa-calendar-alt me-2"></i> Manage Events</a>
        <a href="manage_users.php" class="<?php echo $activePage === 'users' ? 'active' : ''; ?>"><i class="fas fa-users me-2"></i> Manage Users</a>
        <a href="manage_bookings.php" class="<?php echo $activePage === 'bookings' ? 'active' : ''; ?>"><i class="fas fa-ticket-alt me-2"></i> Manage Bookings</a>
        <a href="program_requests.php" class="<?php echo $activePage === 'program_requests' ? 'active' : ''; ?>">
            <i class="fas fa-clipboard-list me-2"></i> Program Requests
            <?php if ($pendingCount > 0): ?>
                <span class="badge bg-danger float-end"><?php echo $pendingCount; ?></span>
            <?php endif; ?>
        </a>
        <a href="view_all_feedback.php" class="<?php echo $activePage === 'feedback' ? 'active' : ''; ?>"><i class="fas fa-comments me-2"></i> User Feedback</a>
        <a href="search.php"><i class="fas fa-search me-2"></i> Search</a>
        <a href="venue_reports.php"><i class="fas fa-building me-2"></i> Venue Reports</a>
        <a href="reports.php" class="<?php echo $activePage === 'reports' ? 'active' : ''; ?>"><i class="fas fa-chart-bar me-2"></i> Reports</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
    </nav>
</div>