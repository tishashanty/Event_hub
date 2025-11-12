<?php
    session_start();
    // Check if user is logged in and is admin
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header('Location: login.php');
        exit();
    }
    $activePage = 'dashboard';
    include __DIR__ . '/includes/admin_layout_start.php';

    // Database connection
    $conn = new mysqli('localhost', 'root', '', 'event_management');
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Get statistics
    $total_events = $conn->query("SELECT COUNT(*) as count FROM events")->fetch_assoc()['count'];
    $total_students = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
    $total_teachers = $conn->query("SELECT COUNT(*) as count FROM teachers")->fetch_assoc()['count'];
    $total_admins = $conn->query("SELECT COUNT(*) as count FROM admin")->fetch_assoc()['count'];
    $total_users = $total_students + $total_teachers + $total_admins;
    $upcoming_events = $conn->query("SELECT COUNT(*) as count FROM events WHERE event_date >= CURDATE()")->fetch_assoc()['count'];
    $total_registrations = $conn->query("SELECT COUNT(*) as count FROM registrations")->fetch_assoc()['count'];
    ?>

                
                <h2 class="mb-4">Dashboard Overview</h2>
                
                <!-- Statistics Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <h5 class="card-title">Total Events</h5>
                                <h2 class="card-text"><?php echo $total_events; ?></h2>
                                <i class="fas fa-calendar text-primary"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <h5 class="card-title">Total Users</h5>
                                <h2 class="card-text"><?php echo $total_users; ?></h2>
                                <i class="fas fa-users text-success"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <h5 class="card-title">Upcoming Events</h5>
                                <h2 class="card-text"><?php echo $upcoming_events; ?></h2>
                                <i class="fas fa-clock text-warning"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <h5 class="card-title">Total Registrations</h5>
                                <h2 class="card-text"><?php echo $total_registrations; ?></h2>
                                <i class="fas fa-ticket-alt text-danger"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Events -->
                <div class="row mt-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Recent Events</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Event Name</th>
                                                <th>Date</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $recent_events = $conn->query("SELECT * FROM events ORDER BY event_date DESC LIMIT 5");
                                            while ($event = $recent_events->fetch_assoc()) {
                                                echo "<tr>";
                                                echo "<td>" . htmlspecialchars($event['title']) . "</td>";
                                                echo "<td>" . date('M d, Y', strtotime($event['event_date'])) . "</td>";
                                                // Status: Upcoming or Past
                                                $status = (strtotime($event['event_date']) >= strtotime(date('Y-m-d'))) ? 'Upcoming' : 'Past';
                                                echo "<td><span class='badge bg-" . ($status == 'Upcoming' ? 'success' : 'secondary') . "'>" . $status . "</span></td>";
                                                echo "</tr>";
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="add_event.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Add New Event</a>
                                    <a href="manage_users.php" class="btn btn-success"><i class="fas fa-users me-2"></i>Manage Users</a>
                                    <a href="reports.php" class="btn btn-info text-white"><i class="fas fa-file-alt me-2"></i>Generate Report</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

<?php include __DIR__ . '/includes/admin_layout_end.php'; ?>
