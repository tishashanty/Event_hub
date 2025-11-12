<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'event_management');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchType = isset($_GET['type']) ? $_GET['type'] : 'all';
$results = [];

if (!empty($search)) {
    $searchEscaped = $conn->real_escape_string($search);
    
    // Search Events
    if ($searchType === 'all' || $searchType === 'events') {
        $eventsQuery = "SELECT * FROM events WHERE (title LIKE '%$searchEscaped%' OR description LIKE '%$searchEscaped%' OR venue LIKE '%$searchEscaped%') ORDER BY event_date DESC";
        $eventsResult = $conn->query($eventsQuery);
        if ($eventsResult) {
            $results['events'] = $eventsResult->fetch_all(MYSQLI_ASSOC);
        }
    }
    
    // Search Participants (Students)
    if ($searchType === 'all' || $searchType === 'participants') {
        $studentsQuery = "SELECT s.*, 'student' as user_type FROM students s WHERE (s.first_name LIKE '%$searchEscaped%' OR s.last_name LIKE '%$searchEscaped%' OR s.email LIKE '%$searchEscaped%' OR s.department LIKE '%$searchEscaped%') ORDER BY s.first_name";
        $studentsResult = $conn->query($studentsQuery);
        if ($studentsResult) {
            $results['students'] = $studentsResult->fetch_all(MYSQLI_ASSOC);
        }
        
        $teachersQuery = "SELECT t.*, 'teacher' as user_type FROM teachers t WHERE (t.first_name LIKE '%$searchEscaped%' OR t.last_name LIKE '%$searchEscaped%' OR t.email LIKE '%$searchEscaped%' OR t.department LIKE '%$searchEscaped%') ORDER BY t.first_name";
        $teachersResult = $conn->query($teachersQuery);
        if ($teachersResult) {
            $results['teachers'] = $teachersResult->fetch_all(MYSQLI_ASSOC);
        }
    }
    
    // Search Programs (Events with participant counts)
    if ($searchType === 'all' || $searchType === 'programs') {
        $programsQuery = "SELECT e.*, COUNT(r.registration_id) as participant_count 
                         FROM events e 
                         LEFT JOIN registrations r ON e.event_id = r.event_id 
                         WHERE (e.title LIKE '%$searchEscaped%' OR e.description LIKE '%$searchEscaped%' OR e.venue LIKE '%$searchEscaped%')
                         GROUP BY e.event_id 
                         ORDER BY e.event_date DESC";
        $programsResult = $conn->query($programsQuery);
        if ($programsResult) {
            $results['programs'] = $programsResult->fetch_all(MYSQLI_ASSOC);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search - Event Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            letter-spacing: -0.025em;
        }

        .nav-link {
            font-weight: 500;
            transition: all 0.2s ease;
            border-radius: 6px;
            margin: 0 2px;
        }

        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            transform: translateY(-1px);
        }

        .container-narrow {
            max-width: 1200px;
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

        .search-form {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .form-control, .form-select {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 10px 12px;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .form-control:focus, .form-select:focus {
            border-color: #1e293b;
            box-shadow: 0 0 0 3px rgba(30, 41, 59, 0.1);
            outline: none;
        }

        .btn {
            font-weight: 500;
            border-radius: 6px;
            padding: 10px 20px;
            transition: all 0.2s ease;
            border: none;
        }

        .btn-primary {
            background-color: #1e293b;
            border-color: #1e293b;
        }

        .btn-primary:hover {
            background-color: #0f172a;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background-color: #64748b;
            border-color: #64748b;
        }

        .btn-secondary:hover {
            background-color: #475569;
            transform: translateY(-1px);
        }

        .result-section {
            margin-bottom: 30px;
        }

        .result-item {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.2s ease;
        }

        .result-item:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-1px);
        }

        .result-title {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .result-meta {
            color: #64748b;
            font-size: 0.875rem;
            margin-bottom: 10px;
        }

        .result-description {
            color: #374151;
            font-size: 0.9rem;
        }

        .badge {
            font-size: 0.75rem;
            padding: 4px 8px;
        }

        .no-results {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
        }

        .no-results i {
            font-size: 3rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?php echo $_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : ($_SESSION['role'] === 'student' ? 'student_dashboard.php' : 'teacher_dashboard.php'); ?>">
                <i class="fas fa-calendar-alt me-2"></i>
                EventHub
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : ($_SESSION['role'] === 'student' ? 'student_dashboard.php' : 'teacher_dashboard.php'); ?>">
                            <i class="fas fa-home me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="search.php">
                            <i class="fas fa-search me-1"></i>Search
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="all_events.php">
                            <i class="fas fa-calendar me-1"></i>All Events
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
        <!-- Search Form -->
        <div class="search-form">
            <h3 class="mb-4"><i class="fas fa-search me-2"></i>Advanced Search</h3>
            <form method="GET" class="row g-3">
                <div class="col-md-8">
                    <input type="text" class="form-control" name="search" placeholder="Search for events, participants, or programs..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="type">
                        <option value="all" <?php echo $searchType === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="events" <?php echo $searchType === 'events' ? 'selected' : ''; ?>>Events</option>
                        <option value="participants" <?php echo $searchType === 'participants' ? 'selected' : ''; ?>>Participants</option>
                        <option value="programs" <?php echo $searchType === 'programs' ? 'selected' : ''; ?>>Programs</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>

        <?php if (!empty($search)): ?>
            <!-- Search Results -->
            <div class="mb-3">
                <h4>Search Results for: "<strong><?php echo htmlspecialchars($search); ?></strong>"</h4>
                <p class="text-muted">Found results in <?php echo implode(', ', array_keys($results)); ?></p>
            </div>

            <!-- Events Results -->
            <?php if (isset($results['events']) && !empty($results['events'])): ?>
                <div class="result-section">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-calendar me-2"></i>Events (<?php echo count($results['events']); ?>)</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($results['events'] as $event): ?>
                                <div class="result-item">
                                    <div class="result-title"><?php echo htmlspecialchars($event['title']); ?></div>
                                    <div class="result-meta">
                                        <i class="fas fa-calendar me-1"></i><?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                                        <i class="fas fa-clock me-1 ms-3"></i><?php echo htmlspecialchars($event['event_time']); ?>
                                        <i class="fas fa-map-marker-alt me-1 ms-3"></i><?php echo htmlspecialchars($event['venue']); ?>
                                    </div>
                                    <?php if (!empty($event['description'])): ?>
                                        <div class="result-description"><?php echo htmlspecialchars($event['description']); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Participants Results -->
            <?php if (isset($results['students']) && !empty($results['students'])): ?>
                <div class="result-section">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-user-graduate me-2"></i>Students (<?php echo count($results['students']); ?>)</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($results['students'] as $student): ?>
                                <div class="result-item">
                                    <div class="result-title"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                    <div class="result-meta">
                                        <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($student['email']); ?>
                                        <i class="fas fa-building me-1 ms-3"></i><?php echo htmlspecialchars($student['department']); ?>
                                        <span class="badge bg-info ms-2">Year <?php echo $student['year']; ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($results['teachers']) && !empty($results['teachers'])): ?>
                <div class="result-section">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-chalkboard-teacher me-2"></i>Teachers (<?php echo count($results['teachers']); ?>)</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($results['teachers'] as $teacher): ?>
                                <div class="result-item">
                                    <div class="result-title"><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></div>
                                    <div class="result-meta">
                                        <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($teacher['email']); ?>
                                        <i class="fas fa-building me-1 ms-3"></i><?php echo htmlspecialchars($teacher['department']); ?>
                                        <span class="badge bg-success ms-2"><?php echo htmlspecialchars($teacher['designation']); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Programs Results -->
            <?php if (isset($results['programs']) && !empty($results['programs'])): ?>
                <div class="result-section">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-tasks me-2"></i>Programs (<?php echo count($results['programs']); ?>)</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($results['programs'] as $program): ?>
                                <div class="result-item">
                                    <div class="result-title"><?php echo htmlspecialchars($program['title']); ?></div>
                                    <div class="result-meta">
                                        <i class="fas fa-calendar me-1"></i><?php echo date('M d, Y', strtotime($program['event_date'])); ?>
                                        <i class="fas fa-clock me-1 ms-3"></i><?php echo htmlspecialchars($program['event_time']); ?>
                                        <i class="fas fa-map-marker-alt me-1 ms-3"></i><?php echo htmlspecialchars($program['venue']); ?>
                                        <span class="badge bg-primary ms-2"><?php echo $program['participant_count']; ?> participants</span>
                                    </div>
                                    <?php if (!empty($program['description'])): ?>
                                        <div class="result-description"><?php echo htmlspecialchars($program['description']); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- No Results -->
            <?php if (empty($results) || (empty($results['events']) && empty($results['students']) && empty($results['teachers']) && empty($results['programs']))): ?>
                <div class="card">
                    <div class="card-body">
                        <div class="no-results">
                            <i class="fas fa-search"></i>
                            <h4>No results found</h4>
                            <p>Try adjusting your search terms or search type.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Search Instructions -->
            <div class="card">
                <div class="card-body text-center">
                    <i class="fas fa-search" style="font-size: 4rem; color: #64748b; margin-bottom: 20px;"></i>
                    <h4>Search Everything</h4>
                    <p class="text-muted">Search across events, participants, and programs to find exactly what you're looking for.</p>
                    <div class="row mt-4">
                        <div class="col-md-4">
                            <div class="p-3">
                                <i class="fas fa-calendar text-primary" style="font-size: 2rem;"></i>
                                <h6 class="mt-2">Events</h6>
                                <small class="text-muted">Search by title, description, or venue</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3">
                                <i class="fas fa-users text-success" style="font-size: 2rem;"></i>
                                <h6 class="mt-2">Participants</h6>
                                <small class="text-muted">Find students and teachers by name or department</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3">
                                <i class="fas fa-tasks text-info" style="font-size: 2rem;"></i>
                                <h6 class="mt-2">Programs</h6>
                                <small class="text-muted">View programs with participant counts</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
