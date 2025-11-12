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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $phone_number = trim($_POST['phone_number']);
    $department = trim($_POST['department']);
    if ($role === 'student') {
        $year = intval($_POST['year']);
        $roll_no = trim($_POST['roll_no']);
        $stmt = $conn->prepare("INSERT INTO students (first_name, last_name, email, password, phone_number, department, year, roll_no) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sssssis', $first_name, $last_name, $email, $password, $phone_number, $department, $year, $roll_no);
    } elseif ($role === 'teacher') {
        $designation = trim($_POST['designation']);
        $stmt = $conn->prepare("INSERT INTO teachers (first_name, last_name,email, password, phone_number, department, designation) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssss', $first_name, $last_name,$email, $password, $phone_number, $department, $designation);
    } else {
        $stmt = $conn->prepare("INSERT INTO admin (first_name, last_name,email, password) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $first_name, $last_name,$email, $password);
    }
    if ($stmt->execute()) {
        $success = ucfirst($role) . ' added successfully!';
    } else {
        if ($conn->errno === 1062) {
            $error = 'Email already exists.';
        } else {
            $error = 'Failed to add user.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .add-user-container { max-width: 600px; margin: 40px auto; }
    </style>
    <script>
    function toggleRoleFields() {
        var role = document.getElementById('role').value;
        document.getElementById('student-fields').style.display = (role === 'student') ? 'block' : 'none';
        document.getElementById('teacher-fields').style.display = (role === 'teacher') ? 'block' : 'none';
    }
    </script>
</head>
<body onload="toggleRoleFields()">
    <div class="add-user-container">
        <div class="card">
            <div class="card-header text-center">
                <h3>Add New User</h3>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php elseif ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="role" class="form-label">User Type</label>
                        <select class="form-select" id="role" name="role" onchange="toggleRoleFields()" required>
                            <option value="student">Student</option>
                            <option value="teacher">Teacher</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="first_name" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="first_name" name="first_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="last_name" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="last_name" name="last_name" required>
                </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="phone_number" class="form-label">Phone Number</label>
                        <input type="text" class="form-control" id="phone_number" name="phone_number">
                    </div>
                    <div class="mb-3">
                        <label for="department" class="form-label">Department</label>
                        <input type="text" class="form-control" id="department" name="department">
                    </div>
                    <div id="student-fields">
                        <div class="mb-3">
                            <label for="year" class="form-label">Year</label>
                            <input type="number" class="form-control" id="year" name="year">
                        </div>
                        <div class="mb-3">
                            <label for="roll_no" class="form-label">Roll No</label>
                            <input type="text" class="form-control" id="roll_no" name="roll_no">
                        </div>
                    </div>
                    <div id="teacher-fields">
                        <div class="mb-3">
                            <label for="designation" class="form-label">Designation</label>
                            <input type="text" class="form-control" id="designation" name="designation">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Add User</button>
                </form>
                <div class="mt-3 text-center">
                    <a href="manage_users.php">Back to Manage Users</a>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 