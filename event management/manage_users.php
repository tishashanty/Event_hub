<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: admin_login.php');
    exit();
}

$activePage = 'users';
include __DIR__ . '/includes/admin_layout_start.php';

$conn = new mysqli('localhost', 'root', '', 'event_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$success = '';
$error = '';

// Handle delete user
if (isset($_GET['delete']) && isset($_GET['type'])) {
    $user_id = intval($_GET['delete']);
    $user_type = $_GET['type'];
    
    if (in_array($user_type, ['student', 'teacher', 'admin']) && $user_id > 0) {
        $table_name = $user_type . 's';
        $id_field = $user_type . '_id';
        
        // Prevent deleting the current admin user
        if ($user_type === 'admin' && $user_id == $_SESSION['user_id']) {
            $error = 'You cannot delete your own admin account.';
        } else {
            if ($conn->query("DELETE FROM $table_name WHERE $id_field = $user_id")) {
                $success = ucfirst($user_type) . ' deleted successfully!';
            } else {
                $error = 'Failed to delete ' . $user_type . '.';
            }
        }
    }
}

// Fetch all users with error handling
$students = $conn->query("SELECT student_id, first_name, last_name, email, department, year, roll_no FROM students ORDER BY first_name, last_name");
if ($students === false) {
    die("Error executing students query: " . $conn->error);
}

$teachers = $conn->query("SELECT teacher_id, first_name, last_name, email, department, designation FROM teachers ORDER BY first_name, last_name");
if ($teachers === false) {
    die("Error executing teachers query: " . $conn->error);
}

$admins = $conn->query("SELECT admin_id, first_name, last_name, email FROM admin ORDER BY first_name, last_name");
if ($admins === false) {
    die("Error executing admins query: " . $conn->error);
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Manage Users</h2>
    <div>
        <a href="add_admin.php" class="btn btn-success">
            <i class="fas fa-user-plus me-2"></i>Add New Admin
        </a>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php elseif ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Students Section -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-user-graduate me-2"></i>Students
            <span class="badge bg-primary ms-2"><?php echo $students->num_rows; ?></span>
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Year</th>
                        <th>Roll No</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($students->num_rows > 0): ?>
                        <?php while ($student = $students->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td><?php echo htmlspecialchars($student['department']); ?></td>
                                <td><?php echo $student['year']; ?></td>
                                <td><?php echo htmlspecialchars($student['roll_no']); ?></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="edit_user.php?type=student&id=<?php echo $student['student_id']; ?>" 
                                           class="btn btn-primary btn-sm">
                                            <i class="fas fa-edit me-1"></i>Edit
                                        </a>
                                        <a href="manage_users.php?delete=<?php echo $student['student_id']; ?>&type=student" 
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('Are you sure you want to delete this student?')">
                                            <i class="fas fa-trash me-1"></i>Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">No students found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Teachers Section -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-chalkboard-teacher me-2"></i>Teachers
            <span class="badge bg-warning ms-2"><?php echo $teachers->num_rows; ?></span>
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Designation</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($teachers->num_rows > 0): ?>
                        <?php while ($teacher = $teachers->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['department']); ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($teacher['designation']); ?></span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="edit_user.php?type=teacher&id=<?php echo $teacher['teacher_id']; ?>" 
                                           class="btn btn-primary btn-sm">
                                            <i class="fas fa-edit me-1"></i>Edit
                                        </a>
                                        <a href="manage_users.php?delete=<?php echo $teacher['teacher_id']; ?>&type=teacher" 
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('Are you sure you want to delete this teacher?')">
                                            <i class="fas fa-trash me-1"></i>Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">No teachers found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Admins Section -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-user-shield me-2"></i>Administrators
            <span class="badge bg-danger ms-2"><?php echo $admins->num_rows; ?></span>
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                      
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($admins->num_rows > 0): ?>
                        <?php while ($admin = $admins->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></strong>
                                    <?php if ($admin['admin_id'] == $_SESSION['user_id']): ?>
                                        <span class="badge bg-success ms-2">You</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($admin['email']); ?></td>
                               
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="edit_user.php?type=admin&id=<?php echo $admin['admin_id']; ?>" 
                                           class="btn btn-primary btn-sm">
                                            <i class="fas fa-edit me-1"></i>Edit
                                        </a>
                                        <?php if ($admin['admin_id'] != $_SESSION['user_id']): ?>
                                            <a href="manage_users.php?delete=<?php echo $admin['admin_id']; ?>&type=admin" 
                                               class="btn btn-danger btn-sm"
                                               onclick="return confirm('Are you sure you want to delete this admin?')">
                                                <i class="fas fa-trash me-1"></i>Delete
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-danger btn-sm" disabled title="Cannot delete your own account">
                                                <i class="fas fa-trash me-1"></i>Delete
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted">No administrators found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h3><?php echo $students->num_rows; ?></h3>
                <p class="mb-0">Total Students</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-warning text-white">
            <div class="card-body text-center">
                <h3><?php echo $teachers->num_rows; ?></h3>
                <p class="mb-0">Total Teachers</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-danger text-white">
            <div class="card-body text-center">
                <h3><?php echo $admins->num_rows; ?></h3>
                <p class="mb-0">Total Admins</p>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/admin_layout_end.php'; ?>