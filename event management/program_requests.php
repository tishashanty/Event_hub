<?php
session_start();
// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$activePage = 'program_requests';
include __DIR__ . '/includes/admin_layout_start.php';

// Database connection
$conn = new mysqli('localhost', 'root', '', 'event_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Ensure event_requests table exists (in case not created by student page yet)
$conn->query("CREATE TABLE IF NOT EXISTS event_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    program_title VARCHAR(255) NOT NULL,
    program_description TEXT NOT NULL,
    program_date DATE NOT NULL,
    program_time TIME NOT NULL,
    program_venue VARCHAR(255) NOT NULL,
    requirements TEXT,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
// Ensure admin response columns exist
$col = $conn->query("SHOW COLUMNS FROM event_requests LIKE 'admin_comment'");
if ($col && $col->num_rows == 0) {
    $conn->query("ALTER TABLE event_requests ADD COLUMN admin_comment TEXT NULL");
}
$col = $conn->query("SHOW COLUMNS FROM event_requests LIKE 'responded_at'");
if ($col && $col->num_rows == 0) {
    $conn->query("ALTER TABLE event_requests ADD COLUMN responded_at DATETIME NULL");
}
// Track if student has viewed admin response
$col = $conn->query("SHOW COLUMNS FROM event_requests LIKE 'student_viewed'");
if ($col && $col->num_rows == 0) {
    $conn->query("ALTER TABLE event_requests ADD COLUMN student_viewed TINYINT(1) NOT NULL DEFAULT 1");
}

// Get count of pending requests for sidebar notification
$pendingCountResult = $conn->query("SELECT COUNT(*) as count FROM event_requests WHERE status = 'pending'");
$pendingCount = $pendingCountResult ? $pendingCountResult->fetch_assoc()['count'] : 0;

$action_msg = '';
// Handle Approve
if (isset($_POST['approve_request_id'])) {
    $requestId = intval($_POST['approve_request_id']);
    $adminComment = isset($_POST['admin_comment']) ? trim($_POST['admin_comment']) : '';
    
    // First, get the request details
    $requestQuery = $conn->prepare("SELECT * FROM event_requests WHERE request_id = ?");
    $requestQuery->bind_param('i', $requestId);
    $requestQuery->execute();
    $requestResult = $requestQuery->get_result();
    $requestData = $requestResult->fetch_assoc();
    $requestQuery->close();
    
    if ($requestData) {
        // Start transaction
        $conn->autocommit(false);
        
        try {
            // Update request status
            $stmt = $conn->prepare("UPDATE event_requests SET status = 'approved', admin_comment = ?, responded_at = NOW(), student_viewed = 0 WHERE request_id = ?");
            $stmt->bind_param('si', $adminComment, $requestId);
            $stmt->execute();
            $stmt->close();
            
            // Check for venue conflict before creating event
            $conflictCheck = $conn->prepare("SELECT COUNT(*) FROM events WHERE event_date = ? AND event_time = ? AND venue = ?");
            $conflictCheck->bind_param('sss', $requestData['program_date'], $requestData['program_time'], $requestData['program_venue']);
            $conflictCheck->execute();
            $conflictResult = $conflictCheck->get_result();
            $conflictCount = $conflictResult->fetch_row()[0];
            $conflictCheck->close();
            
            if ($conflictCount > 0) {
                throw new Exception("Venue conflict: Another event is already scheduled at the same date, time, and venue.");
            }
            
            // Ensure duration column exists
            $conn->query("ALTER TABLE events ADD COLUMN IF NOT EXISTS duration INT DEFAULT 60");
            
            // Create event from approved request
            $duration = isset($requestData['program_duration']) ? $requestData['program_duration'] : 60;
            $eventStmt = $conn->prepare("INSERT INTO events (title, description, event_date, event_time, venue, duration, created_by_user_type, created_by_user_id) VALUES (?, ?, ?, ?, ?, ?, 'student', ?)");
            $eventStmt->bind_param('sssssii', $requestData['program_title'], $requestData['program_description'], $requestData['program_date'], $requestData['program_time'], $requestData['program_venue'], $duration, $requestData['student_id']);
            $eventStmt->execute();
            $eventStmt->close();
            
            // Commit transaction
            $conn->commit();
            $conn->autocommit(true);
            
            $action_msg = '<div class=\'alert alert-success\'>Request approved successfully and event created for registration!</div>';
            // Update pending count after approval
            $pendingCount = max(0, $pendingCount - 1);
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $conn->autocommit(true);
            $action_msg = '<div class=\'alert alert-danger\'>Failed to approve request: ' . $e->getMessage() . '</div>';
        }
    } else {
        $action_msg = '<div class=\'alert alert-danger\'>Request not found.</div>';
    }
}
// Handle Reject
if (isset($_POST['reject_request_id'])) {
    $requestId = intval($_POST['reject_request_id']);
    $adminComment = isset($_POST['reject_reason']) ? trim($_POST['reject_reason']) : '';
    if ($adminComment === '') {
        $action_msg = '<div class=\'alert alert-warning\'>Please provide a reason for rejection.</div>';
    } else {
        $stmt = $conn->prepare("UPDATE event_requests SET status = 'rejected', admin_comment = ?, responded_at = NOW(), student_viewed = 0 WHERE request_id = ?");
        if ($stmt) {
            $stmt->bind_param('si', $adminComment, $requestId);
            if ($stmt->execute()) {
                $action_msg = '<div class=\'alert alert-success\'>Request rejected successfully.</div>';
                // Update pending count after rejection
                $pendingCount = max(0, $pendingCount - 1);
            } else {
                $action_msg = '<div class=\'alert alert-danger\'>Failed to reject request.</div>';
            }
            $stmt->close();
        } else {
            $action_msg = '<div class=\'alert alert-danger\'>Server error while rejecting.</div>';
        }
    }
}

// Filters
$allowed_status = ['all','pending','approved','rejected'];
$status = (isset($_GET['status']) && in_array($_GET['status'], $allowed_status)) ? $_GET['status'] : 'pending';
$where = '';
if ($status !== 'all') {
    $safeStatus = $conn->real_escape_string($status);
    $where = "WHERE er.status = '" . $safeStatus . "'";
}

// Fetch requests with student info
$sql = "SELECT er.*, s.first_name, s.last_name, s.email
        FROM event_requests er
        LEFT JOIN students s ON s.student_id = er.student_id
        $where
        ORDER BY er.created_at DESC";
$requests = $conn->query($sql);
?>

<!-- Store pending count in session for sidebar access -->
<script>
    sessionStorage.setItem('pendingRequestsCount', <?php echo $pendingCount; ?>);
</script>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Program Requests</h2>
    <div>
        <a href="?status=pending" class="btn btn-<?php echo $status==='pending'?'primary':'outline-primary'; ?> btn-sm position-relative">
            Pending
            <?php if ($pendingCount > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                    <?php echo $pendingCount; ?>
                    <span class="visually-hidden">pending requests</span>
                </span>
            <?php endif; ?>
        </a>
        <a href="?status=approved" class="btn btn-<?php echo $status==='approved'?'success':'outline-success'; ?> btn-sm">Approved</a>
        <a href="?status=rejected" class="btn btn-<?php echo $status==='rejected'?'danger':'outline-danger'; ?> btn-sm">Rejected</a>
        <a href="?status=all" class="btn btn-<?php echo $status==='all'?'secondary':'outline-secondary'; ?> btn-sm">All</a>
    </div>
</div>

<?php echo $action_msg; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Requests <?php echo $status !== 'all' ? '(' . ucfirst($status) . ')' : ''; ?></h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Title</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Venue</th>
                        <th>Requirements</th>
                        <th>Status</th>
                        <th>Admin Comment</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($requests && $requests->num_rows > 0): ?>
                        <?php while ($req = $requests->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php
                                    $studentName = trim(($req['first_name'] ?? '') . ' ' . ($req['last_name'] ?? ''));
                                    echo htmlspecialchars($studentName !== '' ? $studentName : ('Student #' . (int)$req['student_id']));
                                    if (!empty($req['email'])) {
                                        echo '<br><small class=\'text-muted\'>' . htmlspecialchars($req['email']) . '</small>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($req['program_title']); ?></strong>
                                    <br>
                                    <button type="button" class="btn btn-link p-0" data-bs-toggle="modal" data-bs-target="#descModal<?php echo $req['request_id']; ?>">View Description</button>
                                    <div class="modal" id="descModal<?php echo $req['request_id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Description: <?php echo htmlspecialchars($req['program_title']); ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p><?php echo nl2br(htmlspecialchars($req['program_description'])); ?></p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($req['program_date'])); ?></td>
                                <td><?php echo htmlspecialchars(substr($req['program_time'], 0, 5)); ?></td>
                                <td><?php echo htmlspecialchars($req['program_venue']); ?></td>
                                <td><?php echo $req['requirements'] ? htmlspecialchars($req['requirements']) : '<span class=\'text-muted\'>-</span>'; ?></td>
                                <td>
                                    <?php if ($req['status'] === 'approved'): ?>
                                        <span class="badge bg-success">Approved</span>
                                    <?php elseif ($req['status'] === 'rejected'): ?>
                                        <span class="badge bg-danger">Rejected</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo !empty($req['admin_comment']) ? htmlspecialchars($req['admin_comment']) : '<span class=\'text-muted\'>-</span>'; ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($req['created_at'])); ?></td>
                                <td>
                                    <?php if ($req['status'] === 'pending'): ?>
                                        <div class="d-flex gap-1">
                                            <form method="POST" action="" class="me-1">
                                                <input type="hidden" name="approve_request_id" value="<?php echo (int)$req['request_id']; ?>" />
                                                <input type="hidden" name="admin_comment" value="" />
                                                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check me-1"></i>Approve</button>
                                            </form>
                                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $req['request_id']; ?>">
                                                <i class="fas fa-times me-1"></i>Reject
                                            </button>
                                            <div class="modal" id="rejectModal<?php echo $req['request_id']; ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="POST" action="">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Reject Request</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="reject_request_id" value="<?php echo (int)$req['request_id']; ?>" />
                                                                <div class="mb-3">
                                                                    <label class="form-label">Reason for rejection</label>
                                                                    <textarea class="form-control" name="reject_reason" rows="3" required></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-danger">Reject</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">No actions</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted">No requests found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/admin_layout_end.php'; ?>