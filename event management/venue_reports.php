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

// Get venue statistics
$venueStatsQuery = "SELECT 
    e.venue,
    COUNT(*) as total_programs,
    COUNT(CASE WHEN e.event_date >= CURDATE() THEN 1 END) as upcoming_programs,
    COUNT(CASE WHEN e.event_date < CURDATE() THEN 1 END) as past_programs,
    COUNT(CASE WHEN (e.status IS NULL OR e.status <> 'inactive') THEN 1 END) as active_programs,
    SUM(CASE WHEN r.registration_id IS NOT NULL THEN 1 ELSE 0 END) as total_participants,
    AVG(CASE WHEN r.registration_id IS NOT NULL THEN 1 ELSE 0 END) as avg_participants_per_event
    FROM events e
    LEFT JOIN registrations r ON e.event_id = r.event_id
    GROUP BY e.venue
    ORDER BY total_programs DESC";

$venueStats = $conn->query($venueStatsQuery);
if ($venueStats === false) {
    $venueStatsEmpty = true;
} else {
    $venueStatsEmpty = false;
}

// Get detailed venue usage
$venueDetailsQuery = "SELECT 
    venue,
    event_date,
    COUNT(*) as events_on_date,
    GROUP_CONCAT(title SEPARATOR ', ') as event_titles
    FROM events 
    GROUP BY venue, event_date
    HAVING COUNT(*) > 1
    ORDER BY venue, event_date";

$venueDetails = $conn->query($venueDetailsQuery);

// Get monthly venue usage
$monthlyUsageQuery = "SELECT 
    venue,
    DATE_FORMAT(event_date, '%Y-%m') as month,
    COUNT(*) as events_count
    FROM events 
    WHERE event_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY venue, DATE_FORMAT(event_date, '%Y-%m')
    ORDER BY venue, month";

$monthlyUsage = $conn->query($monthlyUsageQuery);
$monthlyData = [];
if ($monthlyUsage !== false) {
    while ($row = $monthlyUsage->fetch_assoc()) {
        $monthlyData[$row['venue']][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Venue Reports - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #334155;
            line-height: 1.6;
            min-height: 100vh;
        }

        .navbar {
            background: rgba(30, 41, 59, 0.95) !important;
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .container-narrow {
            max-width: 1400px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            margin-bottom: 30px;
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
        }

        .card-body {
            padding: 24px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 15px;
            opacity: 0.7;
        }

        .venue-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
            transition: all 0.2s ease;
        }

        .venue-card:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transform: translateX(5px);
        }

        .venue-name {
            font-weight: 600;
            font-size: 1.1rem;
            color: #1e293b;
            margin-bottom: 10px;
        }

        .venue-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
        }

        .venue-stat {
            text-align: center;
        }

        .venue-stat-number {
            font-size: 1.5rem;
            font-weight: 600;
            color: #667eea;
        }

        .venue-stat-label {
            font-size: 0.8rem;
            color: #64748b;
        }

        .table th {
            background-color: rgba(248, 250, 252, 0.8);
            color: #374151;
            font-weight: 600;
            font-size: 0.875rem;
            padding: 16px 12px;
            border-bottom: 2px solid #e5e7eb;
        }

        .table td {
            padding: 16px 12px;
            vertical-align: middle;
            border-bottom: 1px solid #e5e7eb;
        }

        .table tbody tr:hover {
            background-color: rgba(248, 250, 252, 0.5);
        }

        .chart-container {
            position: relative;
            height: 400px;
            margin: 20px 0;
        }

        .badge {
            font-size: 0.75rem;
            padding: 4px 8px;
        }

        .conflict-alert {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .conflict-alert .alert-icon {
            color: #dc2626;
            margin-right: 10px;
        }
    @media print {
        .navbar, .btn, .dropdown, .card-header .text-light { display: none !important; }
        body { background: white !important; }
        .container-narrow { margin: 0; max-width: 100%; }
        .card { box-shadow: none; border: 1px solid #ddd; }
        .card-header { display: none; }
    }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="admin_dashboard.php">
                <i class="fas fa-calendar-alt me-2"></i>
                EventHub Admin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="admin_dashboard.php">
                            <i class="fas fa-home me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_events_enhanced.php">
                            <i class="fas fa-calendar me-1"></i>Manage Events
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="venue_reports.php">
                            <i class="fas fa-chart-bar me-1"></i>Venue Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-file-alt me-1"></i>All Reports
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['first_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-narrow">
        <!-- Page Header -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-chart-bar me-2"></i>Venue Usage Reports</h5>
                <small class="text-light">Comprehensive analysis of venue utilization and program distribution</small>
            </div>
            <div class="card-body">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon text-primary">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="stat-number"><?php echo $venueStatsEmpty ? 0 : $venueStats->num_rows; ?></div>
                        <div class="stat-label">Total Venues</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon text-success">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-number"><?php 
                            $totalPrograms = 0;
                            if (!$venueStatsEmpty) {
                                $venueStats->data_seek(0);
                                while ($row = $venueStats->fetch_assoc()) {
                                    $totalPrograms += (int)$row['total_programs'];
                                }
                            }
                            echo $totalPrograms;
                        ?></div>
                        <div class="stat-label">Total Programs</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon text-info">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-number"><?php 
                            $totalParticipants = 0;
                            if (!$venueStatsEmpty) {
                                $venueStats->data_seek(0);
                                while ($row = $venueStats->fetch_assoc()) {
                                    $totalParticipants += (int)$row['total_participants'];
                                }
                            }
                            echo $totalParticipants;
                        ?></div>
                        <div class="stat-label">Total Participants</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Venue Statistics -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-list me-2"></i>Venue Statistics</h5>
            </div>
            <div class="card-body">
                <?php if (!$venueStatsEmpty): $venueStats->data_seek(0); ?>
                <?php while ($venue = $venueStats->fetch_assoc()): ?>
                    <div class="venue-card">
                        <div class="venue-name">
                            <i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars($venue['venue']); ?>
                        </div>
                        <div class="venue-stats">
                            <div class="venue-stat">
                                <div class="venue-stat-number"><?php echo $venue['total_programs']; ?></div>
                                <div class="venue-stat-label">Total Programs</div>
                            </div>
                            <div class="venue-stat">
                                <div class="venue-stat-number"><?php echo $venue['upcoming_programs']; ?></div>
                                <div class="venue-stat-label">Upcoming</div>
                            </div>
                            <div class="venue-stat">
                                <div class="venue-stat-number"><?php echo $venue['past_programs']; ?></div>
                                <div class="venue-stat-label">Past</div>
                            </div>
                            <div class="venue-stat">
                                <div class="venue-stat-number"><?php echo $venue['total_participants']; ?></div>
                                <div class="venue-stat-label">Participants</div>
                            </div>
                            <div class="venue-stat">
                                <div class="venue-stat-number"><?php echo number_format($venue['avg_participants_per_event'], 1); ?></div>
                                <div class="venue-stat-label">Avg/Event</div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-muted">No venue data available.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Venue Conflicts -->
        <?php if ($venueDetails && $venueDetails->num_rows > 0): ?>
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-exclamation-triangle me-2"></i>Venue Conflicts</h5>
            </div>
            <div class="card-body">
                <?php $venueDetails->data_seek(0); ?>
                <?php while ($conflict = $venueDetails->fetch_assoc()): ?>
                    <div class="conflict-alert">
                        <i class="fas fa-exclamation-triangle alert-icon"></i>
                        <strong><?php echo htmlspecialchars($conflict['venue']); ?></strong> has 
                        <strong><?php echo $conflict['events_on_date']; ?> events</strong> on 
                        <strong><?php echo date('M d, Y', strtotime($conflict['event_date'])); ?></strong>
                        <br>
                        <small class="text-muted">Events: <?php echo htmlspecialchars($conflict['event_titles']); ?></small>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Monthly Usage Chart -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-chart-line me-2"></i>Monthly Venue Usage (Last 12 Months)</h5>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="monthlyUsageChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Detailed Table -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-table me-2"></i>Detailed Venue Report</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Venue</th>
                                <th>Total Programs</th>
                                <th>Upcoming</th>
                                <th>Past</th>
                                <th>Total Participants</th>
                                <th>Avg Participants/Event</th>
                                <th>Utilization Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$venueStatsEmpty): $venueStats->data_seek(0); ?>
                            <?php while ($venue = $venueStats->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($venue['venue']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $venue['total_programs']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success"><?php echo $venue['upcoming_programs']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $venue['past_programs']; ?></span>
                                    </td>
                                    <td><?php echo $venue['total_participants']; ?></td>
                                    <td><?php echo number_format($venue['avg_participants_per_event'], 1); ?></td>
                                    <td>
                                        <?php 
                                        $utilizationRate = ($venue['total_programs'] / max($totalPrograms, 1)) * 100;
                                        $badgeClass = $utilizationRate > 20 ? 'bg-success' : ($utilizationRate > 10 ? 'bg-warning' : 'bg-danger');
                                        ?>
                                        <span class="badge <?php echo $badgeClass; ?>">
                                            <?php echo number_format($utilizationRate, 1); ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">No venue statistics available</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Monthly Usage Chart
        const monthlyData = <?php echo json_encode($monthlyData); ?>;
        const venues = Object.keys(monthlyData);
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        
        const ctx = document.getElementById('monthlyUsageChart').getContext('2d');
        const datasets = [];
        
        const colors = [
            '#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#00f2fe',
            '#43e97b', '#38f9d7', '#ffecd2', '#fcb69f', '#a8edea', '#fed6e3'
        ];
        
        venues.forEach((venue, index) => {
            const data = new Array(12).fill(0);
            monthlyData[venue].forEach(item => {
                const monthIndex = parseInt(item.month.split('-')[1]) - 1;
                data[monthIndex] = item.events_count;
            });
            
            datasets.push({
                label: venue,
                data: data,
                borderColor: colors[index % colors.length],
                backgroundColor: colors[index % colors.length] + '20',
                tension: 0.4,
                fill: false
            });
        });
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: months,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'Events per Month by Venue'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
