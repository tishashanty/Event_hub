<?php
session_start();
// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$activePage = 'feedback';
include __DIR__ . '/includes/admin_layout_start.php';

// Database connection
$conn = new mysqli('localhost', 'root', '', 'event_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle export request
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    $feedback_id = isset($_GET['feedback_id']) ? (int)$_GET['feedback_id'] : 0;
    
    if ($export_type === 'single' && $feedback_id > 0) {
        exportSingleFeedback($feedback_id, $conn);
    } elseif ($export_type === 'all') {
        exportAllFeedback($conn, $_GET);
    }
    exit();
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Search and filtering
$search = isset($_GET['search']) ? $_GET['search'] : '';
$rating_filter = isset($_GET['rating']) ? $_GET['rating'] : '';
$user_type_filter = isset($_GET['user_type']) ? $_GET['user_type'] : '';
$event_filter = isset($_GET['event']) ? $_GET['event'] : '';

// Build query with filters
$where_conditions = [];
$params = [];
$param_types = '';

if ($search) {
    $where_conditions[] = "(e.title LIKE ? OR f.comments LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ss';
}

if ($rating_filter) {
    $where_conditions[] = "f.rating = ?";
    $params[] = $rating_filter;
    $param_types .= 's';
}

if ($user_type_filter) {
    $where_conditions[] = "f.user_type = ?";
    $params[] = $user_type_filter;
    $param_types .= 's';
}

if ($event_filter) {
    $where_conditions[] = "e.event_id = ?";
    $params[] = $event_filter;
    $param_types .= 's';
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM feedback f JOIN events e ON f.event_id = e.event_id $where_clause";
if ($params) {
    $stmt = $conn->prepare($count_query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $total_feedback = $stmt->get_result()->fetch_assoc()['total'];
} else {
    $total_feedback = $conn->query($count_query)->fetch_assoc()['total'];
}

$total_pages = ceil($total_feedback / $per_page);

// Get feedback data
$feedback_query = "SELECT f.*, e.title as event_title, e.event_date, e.description as event_description,
                   CASE 
                       WHEN f.user_type = 'student' THEN CONCAT(s.first_name, ' ', s.last_name)
                       WHEN f.user_type = 'teacher' THEN CONCAT(t.first_name, ' ', t.last_name)
                       WHEN f.user_type = 'admin' THEN CONCAT(a.first_name, ' ', a.last_name)
                       ELSE 'Unknown'
                   END as user_name,
                   CASE 
                       WHEN f.user_type = 'student' THEN s.email
                       WHEN f.user_type = 'teacher' THEN t.email
                       WHEN f.user_type = 'admin' THEN a.email
                       ELSE 'Unknown'
                   END as user_email
                   FROM feedback f 
                   JOIN events e ON f.event_id = e.event_id 
                   LEFT JOIN students s ON f.user_id = s.student_id AND f.user_type = 'student'
                   LEFT JOIN teachers t ON f.user_id = t.teacher_id AND f.user_type = 'teacher'
                   LEFT JOIN admin a ON f.user_id = a.admin_id AND f.user_type = 'admin'
                   $where_clause
                   ORDER BY f.feedback_date DESC 
                   LIMIT ? OFFSET ?";

$params[] = $per_page;
$params[] = $offset;
$param_types .= 'ii';

$stmt = $conn->prepare($feedback_query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$feedbacks = $stmt->get_result();

// Get events for filter dropdown
$events = $conn->query("SELECT event_id, title FROM events ORDER BY title");
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-comments me-2"></i>All User Feedback</h2>
        <div>
            <button class="btn btn-success me-2" onclick="exportAllFeedback()">
                <i class="fas fa-file-export me-1"></i>Export All
            </button>
            <a href="admin_dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Search and Filter Section -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search events or comments...">
                </div>
                <div class="col-md-2">
                    <label for="rating" class="form-label">Rating</label>
                    <select class="form-select" id="rating" name="rating">
                        <option value="">All Ratings</option>
                        <option value="5" <?php echo $rating_filter == '5' ? 'selected' : ''; ?>>5 Stars</option>
                        <option value="4" <?php echo $rating_filter == '4' ? 'selected' : ''; ?>>4 Stars</option>
                        <option value="3" <?php echo $rating_filter == '3' ? 'selected' : ''; ?>>3 Stars</option>
                        <option value="2" <?php echo $rating_filter == '2' ? 'selected' : ''; ?>>2 Stars</option>
                        <option value="1" <?php echo $rating_filter == '1' ? 'selected' : ''; ?>>1 Star</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="user_type" class="form-label">User Type</label>
                    <select class="form-select" id="user_type" name="user_type">
                        <option value="">All Users</option>
                        <option value="student" <?php echo $user_type_filter == 'student' ? 'selected' : ''; ?>>Student</option>
                        <option value="teacher" <?php echo $user_type_filter == 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                        <option value="admin" <?php echo $user_type_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="event" class="form-label">Event</label>
                    <select class="form-select" id="event" name="event">
                        <option value="">All Events</option>
                        <?php while ($event = $events->fetch_assoc()): ?>
                            <option value="<?php echo $event['event_id']; ?>" 
                                    <?php echo $event_filter == $event['event_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($event['title']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search me-1"></i>Filter
                    </button>
                    <a href="view_all_feedback.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Feedback Summary -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h4><?php echo $total_feedback; ?></h4>
                    <p class="mb-0">Total Feedback</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <?php
                    $positive_feedback = $conn->query("SELECT COUNT(*) as count FROM feedback WHERE rating >= 4")->fetch_assoc()['count'];
                    ?>
                    <h4><?php echo $positive_feedback; ?></h4>
                    <p class="mb-0">Positive (4-5 Stars)</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <?php
                    $neutral_feedback = $conn->query("SELECT COUNT(*) as count FROM feedback WHERE rating = 3")->fetch_assoc()['count'];
                    ?>
                    <h4><?php echo $neutral_feedback; ?></h4>
                    <p class="mb-0">Neutral (3 Stars)</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <?php
                    $negative_feedback = $conn->query("SELECT COUNT(*) as count FROM feedback WHERE rating <= 2")->fetch_assoc()['count'];
                    ?>
                    <h4><?php echo $negative_feedback; ?></h4>
                    <p class="mb-0">Negative (1-2 Stars)</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Feedback Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Feedback Details</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Event</th>
                            <th>User</th>
                            <th>User Type</th>
                            <th>Rating</th>
                            <th>Comments</th>
                            <th>Event Date</th>
                            <th>Feedback Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($feedbacks && $feedbacks->num_rows > 0): ?>
                            <?php while ($feedback = $feedbacks->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($feedback['event_title']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($feedback['user_name']); ?></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo ucfirst(htmlspecialchars($feedback['user_type'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $rating_color = 'secondary';
                                        if ($feedback['rating'] >= 4) $rating_color = 'success';
                                        elseif ($feedback['rating'] >= 3) $rating_color = 'warning';
                                        elseif ($feedback['rating'] >= 1) $rating_color = 'danger';
                                        ?>
                                        <span class="badge bg-<?php echo $rating_color; ?>">
                                            <?php echo htmlspecialchars($feedback['rating']); ?>/5
                                        </span>
                                    </td>
                                    <td>
                                        <div class="feedback-comments">
                                            <?php 
                                            $comments = htmlspecialchars($feedback['comments']);
                                            if (strlen($comments) > 100) {
                                                echo nl2br(substr($comments, 0, 100) . '...');
                                            } else {
                                                echo nl2br($comments);
                                            }
                                            ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($feedback['event_date'])); ?>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($feedback['feedback_date'])); ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="viewFeedbackDetails(<?php echo $feedback['feedback_id']; ?>)"
                                                title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-info" 
                                                onclick="exportSingleFeedback(<?php echo $feedback['feedback_id']; ?>)"
                                                title="Export">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">
                                    No feedback found matching your criteria.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Feedback pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&rating=<?php echo urlencode($rating_filter); ?>&user_type=<?php echo urlencode($user_type_filter); ?>&event=<?php echo urlencode($event_filter); ?>">
                                    Previous
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&rating=<?php echo urlencode($rating_filter); ?>&user_type=<?php echo urlencode($user_type_filter); ?>&event=<?php echo urlencode($event_filter); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&rating=<?php echo urlencode($rating_filter); ?>&user_type=<?php echo urlencode($user_type_filter); ?>&event=<?php echo urlencode($event_filter); ?>">
                                    Next
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Feedback Details Modal -->
<div class="modal fade" id="feedbackDetailsModal" tabindex="-1" aria-labelledby="feedbackDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="feedbackDetailsModalLabel">Feedback Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="feedbackDetailsModalBody">
                <!-- Content will be loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="exportModalFeedbackBtn">Export</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewFeedbackDetails(feedbackId) {
    // Show loading state
    document.getElementById('feedbackDetailsModalBody').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading feedback details...</p>
        </div>
    `;
    
    // Show the modal
    var feedbackModal = new bootstrap.Modal(document.getElementById('feedbackDetailsModal'));
    feedbackModal.show();
    
    // Set the export button to work with this feedback ID
    document.getElementById('exportModalFeedbackBtn').onclick = function() {
        exportSingleFeedback(feedbackId);
    };
    
    // Fetch feedback details via AJAX
    fetch('get_feedback_details.php?id=' + feedbackId)
        .then(response => response.text())
        .then(data => {
            document.getElementById('feedbackDetailsModalBody').innerHTML = data;
        })
        .catch(error => {
            document.getElementById('feedbackDetailsModalBody').innerHTML = `
                <div class="alert alert-danger">
                    Error loading feedback details. Please try again.
                </div>
            `;
            console.error('Error:', error);
        });
}

function exportSingleFeedback(feedbackId) {
    window.location.href = '?export=single&feedback_id=' + feedbackId;
}

function exportAllFeedback() {
    // Get current filter parameters
    const urlParams = new URLSearchParams(window.location.search);
    window.location.href = '?export=all&' + urlParams.toString();
}

// Function to print the current page as a report
function printReport() {
    window.print();
}
</script>

<style>
.feedback-comments {
    max-width: 300px;
    word-wrap: break-word;
}

.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.table th {
    background-color: #f8f9fa;
    border-top: none;
}

.star-rating {
    color: #ffc107;
    font-size: 1.2em;
}

@media print {
    body * {
        visibility: hidden;
    }
    .print-section, .print-section * {
        visibility: visible;
    }
    .print-section {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
}
</style>

<?php
// Function to export a single feedback as HTML (printable)
function exportSingleFeedback($feedback_id, $conn) {
    // Get feedback details
    $query = "SELECT f.*, e.title as event_title, e.event_date, e.description as event_description,
              CASE 
                  WHEN f.user_type = 'student' THEN CONCAT(s.first_name, ' ', s.last_name)
                  WHEN f.user_type = 'teacher' THEN CONCAT(t.first_name, ' ', t.last_name)
                  WHEN f.user_type = 'admin' THEN CONCAT(a.first_name, ' ', a.last_name)
                  ELSE 'Unknown'
              END as user_name,
              CASE 
                  WHEN f.user_type = 'student' THEN s.email
                  WHEN f.user_type = 'teacher' THEN t.email
                  WHEN f.user_type = 'admin' THEN a.email
                  ELSE 'Unknown'
              END as user_email
              FROM feedback f 
              JOIN events e ON f.event_id = e.event_id 
              LEFT JOIN students s ON f.user_id = s.student_id AND f.user_type = 'student'
              LEFT JOIN teachers t ON f.user_id = t.teacher_id AND f.user_type = 'teacher'
              LEFT JOIN admin a ON f.user_id = a.admin_id AND f.user_type = 'admin'
              WHERE f.feedback_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $feedback_id);
    $stmt->execute();
    $feedback = $stmt->get_result()->fetch_assoc();
    
    if (!$feedback) {
        die("Feedback not found");
    }
    
    // Generate HTML content for printing
    $html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Feedback Report - ' . $feedback['event_title'] . '</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body { font-family: Arial, sans-serif; background-color: #fff; }
            .print-container { max-width: 800px; margin: 0 auto; padding: 20px; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #337ab7; padding-bottom: 10px; }
            .section { margin-bottom: 20px; }
            .section-title { color: #337ab7; border-bottom: 1px solid #eee; padding-bottom: 5px; margin-bottom: 15px; }
            .star-rating { color: #ffc107; }
            .footer { margin-top: 30px; text-align: center; color: #777; font-size: 0.9em; }
        </style>
    </head>
    <body>
        <div class="print-container">
            <div class="header">
                <h1 style="color: #337ab7;">Feedback Report</h1>
                <p>Generated on: ' . date('F j, Y, g:i a') . '</p>
            </div>
            
            <div class="section">
                <h4 class="section-title">Event Information</h4>
                <table class="table table-bordered">
                    <tr>
                        <th width="30%">Event Title</th>
                        <td>' . htmlspecialchars($feedback['event_title']) . '</td>
                    </tr>
                    <tr>
                        <th>Event Date</th>
                        <td>' . date('F j, Y', strtotime($feedback['event_date'])) . '</td>
                    </tr>
                    <tr>
                        <th>Event Description</th>
                        <td>' . nl2br(htmlspecialchars($feedback['event_description'])) . '</td>
                    </tr>
                </table>
            </div>
            
            <div class="section">
                <h4 class="section-title">User Information</h4>
                <table class="table table-bordered">
                    <tr>
                        <th width="30%">User Name</th>
                        <td>' . htmlspecialchars($feedback['user_name']) . '</td>
                    </tr>
                    <tr>
                        <th>User Email</th>
                        <td>' . htmlspecialchars($feedback['user_email']) . '</td>
                    </tr>
                    <tr>
                        <th>User Type</th>
                        <td>' . ucfirst(htmlspecialchars($feedback['user_type'])) . '</td>
                    </tr>
                </table>
            </div>
            
            <div class="section">
                <h4 class="section-title">Feedback Details</h4>
                <table class="table table-bordered">
                    <tr>
                        <th width="30%">Rating</th>
                        <td>';
    
    // Add star rating
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $feedback['rating']) {
            $html .= '<i class="fas fa-star star-rating"></i>';
        } else {
            $html .= '<i class="far fa-star star-rating"></i>';
        }
    }
    
    $html .= ' (' . $feedback['rating'] . '/5)
                        </td>
                    </tr>
                    <tr>
                        <th>Submitted On</th>
                        <td>' . date('F j, Y, g:i a', strtotime($feedback['feedback_date'])) . '</td>
                    </tr>
                    <tr>
                        <th>Comments</th>
                        <td>' . nl2br(htmlspecialchars($feedback['comments'])) . '</td>
                    </tr>
                </table>
            </div>
            
            <div class="footer">
                <p>Event Management System - Feedback Report</p>
            </div>
        </div>
        
        <script>
            window.onload = function() {
                window.print();
                setTimeout(function() {
                    window.close();
                }, 500);
            }
        </script>
    </body>
    </html>';
    
    // Output HTML content
    header('Content-Type: text/html');
    echo $html;
    exit();
}

// Function to export all feedback as CSV
function exportAllFeedback($conn, $filters) {
    // Build query with filters
    $where_conditions = [];
    $params = [];
    $param_types = '';
    
    if (!empty($filters['search'])) {
        $where_conditions[] = "(e.title LIKE ? OR f.comments LIKE ?)";
        $search_param = "%" . $filters['search'] . "%";
        $params[] = $search_param;
        $params[] = $search_param;
        $param_types .= 'ss';
    }
    
    if (!empty($filters['rating'])) {
        $where_conditions[] = "f.rating = ?";
        $params[] = $filters['rating'];
        $param_types .= 's';
    }
    
    if (!empty($filters['user_type'])) {
        $where_conditions[] = "f.user_type = ?";
        $params[] = $filters['user_type'];
        $param_types .= 's';
    }
    
    if (!empty($filters['event'])) {
        $where_conditions[] = "e.event_id = ?";
        $params[] = $filters['event'];
        $param_types .= 's';
    }
    
    $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Get feedback data
    $feedback_query = "SELECT f.*, e.title as event_title, e.event_date,
                       CASE 
                           WHEN f.user_type = 'student' THEN CONCAT(s.first_name, ' ', s.last_name)
                           WHEN f.user_type = 'teacher' THEN CONCAT(t.first_name, ' ', t.last_name)
                           WHEN f.user_type = 'admin' THEN CONCAT(a.first_name, ' ', a.last_name)
                           ELSE 'Unknown'
                       END as user_name,
                       CASE 
                           WHEN f.user_type = 'student' THEN s.email
                           WHEN f.user_type = 'teacher' THEN t.email
                           WHEN f.user_type = 'admin' THEN a.email
                           ELSE 'Unknown'
                       END as user_email
                       FROM feedback f 
                       JOIN events e ON f.event_id = e.event_id 
                       LEFT JOIN students s ON f.user_id = s.student_id AND f.user_type = 'student'
                       LEFT JOIN teachers t ON f.user_id = t.teacher_id AND f.user_type = 'teacher'
                       LEFT JOIN admin a ON f.user_id = a.admin_id AND f.user_type = 'admin'
                       $where_clause
                       ORDER BY f.feedback_date DESC";
    
    $stmt = $conn->prepare($feedback_query);
    if ($params) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $feedbacks = $stmt->get_result();
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=all_feedback_' . date('Y-m-d') . '.csv');
    
    // Create a file pointer connected to the output stream
    $output = fopen('php://output', 'w');
    
    // Output the column headings
    fputcsv($output, array('Event Title', 'User Name', 'User Email', 'User Type', 'Rating', 'Comments', 'Event Date', 'Feedback Date'));
    
    // Output each row of the data
    while ($feedback = $feedbacks->fetch_assoc()) {
        fputcsv($output, array(
            $feedback['event_title'],
            $feedback['user_name'],
            $feedback['user_email'],
            ucfirst($feedback['user_type']),
            $feedback['rating'],
            $feedback['comments'],
            $feedback['event_date'],
            $feedback['feedback_date']
        ));
    }
    
    fclose($output);
    exit();
}

include __DIR__ . '/includes/admin_layout_end.php'; 
?>