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
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $first_name = ucwords(strtolower(preg_replace('/\s+/', ' ', $first_name)));
    $last_name = ucwords(strtolower(preg_replace('/\s+/', ' ', $last_name)));
    $email = trim($_POST['email']);
    $password_plain = $_POST['password'];

    $validationErrors = array();

    if ($first_name === '' || !preg_match('/^[A-Za-z][A-Za-z\s\-]{1,49}$/', $first_name)) {
        $validationErrors[] = 'First name must be 2-50 characters, using only letters, spaces, and hyphens.';
    }
    if ($last_name === '' || !preg_match('/^[A-Za-z][A-Za-z\s\-]{1,49}$/', $last_name)) {
        $validationErrors[] = 'Last name must be 2-50 characters, using only letters, spaces, and hyphens.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $validationErrors[] = 'Invalid email format.';
    }
    // Password complexity validation removed as requested

    if (empty($validationErrors)) {
        $password_hashed = password_hash($password_plain, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO admin (first_name, last_name, email, password) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('ssss', $first_name, $last_name, $email, $password_hashed);
        if ($stmt->execute()) {
            $success = 'Admin added successfully!';
            $first_name = '';
            $last_name = '';
            $email = '';
        } else {
            if ($conn->errno === 1062) {
                $error = 'Email already exists.';
            } else {
                $error = 'Failed to add admin.';
            }
        }
    } else {
        $error = implode('<br>', $validationErrors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Admin - Event Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); } 
        .add-admin-container { max-width: 500px; margin: 40px auto; }
    </style>
</head>
<body>
    <div class="add-admin-container">
        <div class="card">
            <div class="card-header text-center">
                <h3>Add New Admin</h3>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php elseif ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="first_name" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="first_name" name="first_name" required minlength="2" maxlength="50" pattern="^[A-Za-z][A-Za-z\s\-]{1,49}$" autocomplete="given-name" autocapitalize="words" value="<?php echo isset($first_name) ? htmlspecialchars($first_name, ENT_QUOTES) : ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label for="last_name" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="last_name" name="last_name" required minlength="2" maxlength="50" pattern="^[A-Za-z][A-Za-z\s\-]{1,49}$" autocomplete="family-name" autocapitalize="words" value="<?php echo isset($last_name) ? htmlspecialchars($last_name, ENT_QUOTES) : ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required autocomplete="email" inputmode="email" value="<?php echo isset($email) ? htmlspecialchars($email, ENT_QUOTES) : ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Add Admin</button>
                </form>
                <div class="mt-3 text-center">
                    <a href="admin_dashboard.php">Back to Dashboard</a>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            function toTitleCase(value) {
                value = value.toLowerCase().replace(/\s+/g, ' ').trim();
                if (value === '') return value;
                var parts = value.split(/([\-\s])/);
                for (var i = 0; i < parts.length; i++) {
                    if (parts[i].length > 0 && /[A-Za-z]/.test(parts[i].charAt(0))) {
                        parts[i] = parts[i].charAt(0).toUpperCase() + parts[i].slice(1);
                    }
                }
                return parts.join('');
            }
            ['first_name', 'last_name'].forEach(function (id) {
                var el = document.getElementById(id);
                if (!el) return;
                el.addEventListener('blur', function () {
                    el.value = toTitleCase(el.value);
                });
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>