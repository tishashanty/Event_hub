<?php
$conn = new mysqli('localhost', 'root', '', 'event_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$success = '';
$error = '';
// Check if any admin exists
$admin_count = $conn->query("SELECT COUNT(*) as count FROM admin")->fetch_assoc()['count'];
$registration_open = ($admin_count == 0);
if ($registration_open && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO admin (first_name, last_name, email, password) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('ssss', $first_name, $last_name, $email, $password);
    if ($stmt->execute()) {
        $success = 'Admin registered successfully! You can now <a href="admin_login.php">login</a>.';
    } else {
        if ($conn->errno === 1062) {
            $error = 'Email already exists.';
        } else {
            $error = 'Failed to register admin.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Admin - Event Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);}
        .register-admin-container { max-width: 500px; margin: 40px auto; }
    </style>
</head>
<body>
    <div class="register-admin-container">
        <div class="card">
            <div class="card-header text-center">
                <h3>Register Admin</h3>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php elseif ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($registration_open): ?>
                    <form method="POST" action="">
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
                        <button type="submit" class="btn btn-primary w-100">Register Admin</button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-info">Admin registration is closed. An admin already exists. Please contact the system administrator.</div>
                <?php endif; ?>
                <div class="mt-3 text-center">
                    <a href="admin_login.php">Back to Admin Login</a>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 