<?php
session_start();

// Database connection
$conn = new mysqli('localhost', 'root', '', 'event_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if user is already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    header('Location: ' . $_SESSION['role'] . '_dashboard.php');
    exit();
}

$error = '';
$success = '';
$email = '';
$showResetForm = false;
$userFound = false;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['request_reset'])) {
        // Request password reset
        $email = trim($_POST['email']);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } else {
            // Check if email exists in any of the user tables
            $userType = '';
            $userId = '';
            
            // Check in admin table
            $stmt = $conn->prepare("SELECT admin_id FROM admin WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $userFound = true;
                $userType = 'admin';
                $userId = $result->fetch_assoc()['admin_id'];
            }
            
            // Check in students table
            if (!$userFound) {
                $stmt = $conn->prepare("SELECT student_id FROM students WHERE email = ?");
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows === 1) {
                    $userFound = true;
                    $userType = 'student';
                    $userId = $result->fetch_assoc()['student_id'];
                }
            }
            
            // Check in teachers table
            if (!$userFound) {
                $stmt = $conn->prepare("SELECT teacher_id FROM teachers WHERE email = ?");
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows === 1) {
                    $userFound = true;
                    $userType = 'teacher';
                    $userId = $result->fetch_assoc()['teacher_id'];
                }
            }
            
            if ($userFound) {
                $success = "Password reset email has been sent to your email address.";
                $showResetForm = true;
                // Store user info in session for the reset form
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_user_type'] = $userType;
                $_SESSION['reset_user_id'] = $userId;
            } else {
                $error = "No account found with that email address.";
            }
        }
    } elseif (isset($_POST['reset_password'])) {
        // Reset password
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $reset_email = $_SESSION['reset_email'] ?? '';
        $userType = $_SESSION['reset_user_type'] ?? '';
        $userId = $_SESSION['reset_user_id'] ?? '';
        
        // Validate passwords
        if ($password !== $confirm_password) {
            $error = "Passwords do not match.";
            $showResetForm = true;
            $email = $reset_email;
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters long.";
            $showResetForm = true;
            $email = $reset_email;
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $error = 'Password must contain at least one uppercase letter.';
            $showResetForm = true;
            $email = $reset_email;
        } elseif (!preg_match('/[a-z]/', $password)) {
            $error = 'Password must contain at least one lowercase letter.';
            $showResetForm = true;
            $email = $reset_email;
        } elseif (!preg_match('/\d/', $password)) {
            $error = 'Password must contain at least one number.';
            $showResetForm = true;
            $email = $reset_email;
        } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $error = 'Password must contain at least one special character.';
            $showResetForm = true;
            $email = $reset_email;
        } else {
            // Hash the new password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Update password in the appropriate table
            if ($userType === 'admin') {
                $stmt = $conn->prepare("UPDATE admin SET password = ? WHERE admin_id = ?");
            } elseif ($userType === 'student') {
                $stmt = $conn->prepare("UPDATE students SET password = ? WHERE student_id = ?");
            } elseif ($userType === 'teacher') {
                $stmt = $conn->prepare("UPDATE teachers SET password = ? WHERE teacher_id = ?");
            }
            
            $stmt->bind_param('si', $hashed_password, $userId);
            
            if ($stmt->execute()) {
                // Clear session data
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_user_type']);
                unset($_SESSION['reset_user_id']);
                
                $success = "Password has been reset successfully. <a href='login.php' class='text-decoration-none'>Login with your new password</a>";
                $showResetForm = false;
            } else {
                $error = "Error updating password. Please try again.";
                $showResetForm = true;
                $email = $reset_email;
            }
        }
    }
}

// Check if we should show reset form from session
if (isset($_SESSION['reset_email']) && !$showResetForm && empty($success)) {
    $showResetForm = true;
    $email = $_SESSION['reset_email'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset - Event Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .password-container {
            max-width: 450px;
            width: 100%;
            animation: fadeIn 0.5s ease-in-out;
        }

        .card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background-color: #1e293b;
            color: white;
            padding: 24px;
            text-align: center;
        }

        .card-header h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
            letter-spacing: -0.025em;
        }

        .card-header p {
            margin: 8px 0 0 0;
            font-size: 0.875rem;
            opacity: 0.9;
        }

        .card-body {
            padding: 32px;
        }

        .logo {
            font-size: 2rem;
            margin-bottom: 15px;
            color: white;
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            width: 60px;
            height: 60px;
            line-height: 60px;
            border-radius: 50%;
            text-align: center;
        }

        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 6px;
            font-size: 0.875rem;
        }

        .form-control {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 10px 12px;
            font-size: 0.875rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            background-color: white;
        }

        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }

        .btn-primary {
            background-color: #1e293b;
            border: 1px solid #1e293b;
            border-radius: 6px;
            padding: 12px 24px;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.15s ease-in-out;
        }

        .btn-primary:hover {
            background-color: #0f172a;
            border-color: #0f172a;
        }

        .btn-primary:focus {
            box-shadow: 0 0 0 3px rgba(30, 41, 59, 0.2);
        }

        .alert {
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            font-size: 0.875rem;
            animation: slideIn 0.3s ease-out;
        }

        .alert-success {
            background-color: #f0fdf4;
            border-color: #bbf7d0;
            color: #166534;
        }

        .alert-danger {
            background-color: #fef2f2;
            border-color: #fecaca;
            color: #dc2626;
        }

        .password-field {
            position: relative;
        }

        .password-toggle {
            cursor: pointer;
            position: absolute;
            right: 12px;
            top: 38px;
            color: #6b7280;
            z-index: 5;
        }

        .password-strength {
            height: 4px;
            margin-top: 6px;
            border-radius: 2px;
            background: #e5e7eb;
        }

        .password-strength-bar {
            height: 100%;
            border-radius: 2px;
            width: 0%;
            transition: width 0.3s ease;
        }

        .form-text {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 4px;
        }

        .login-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 0.875rem;
        }

        .login-footer a {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.15s ease-in-out;
        }

        .login-footer a:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }

        .mb-3 {
            margin-bottom: 1.25rem;
        }

        .mb-4 {
            margin-bottom: 1.5rem;
        }

        .w-100 {
            width: 100%;
        }

        .text-decoration-none {
            text-decoration: none !important;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        /* Reset form specific styles */
        .reset-option {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 16px;
            margin-top: 16px;
            text-align: center;
        }

        .reset-option p {
            margin-bottom: 12px;
            color: #64748b;
            font-size: 0.875rem;
        }

        .btn-outline-primary {
            color: #1e293b;
            border-color: #1e293b;
            background-color: transparent;
        }

        .btn-outline-primary:hover {
            color: white;
            background-color: #1e293b;
            border-color: #1e293b;
        }
    </style>
</head>
<body>
    <div class="password-container">
        <div class="card">
            <div class="card-header">
                <div class="logo">
                    <i class="fas fa-lock"></i>
                </div>
                <h3><?php echo $showResetForm ? 'Create New Password' : 'Reset Your Password'; ?></h3>
                <p><?php echo $showResetForm ? 'Enter your new password below' : 'Enter your email to get started'; ?></p>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if (!$showResetForm && empty($success)): ?>
                    <!-- Request reset form -->
                    <form method="POST" action="">
                        <input type="hidden" name="request_reset" value="1">
                        <div class="mb-4">
                            <p>Enter your email address and we'll send you instructions to reset your password.</p>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" required 
                                   placeholder="Enter your email address"
                                   value="<?php echo htmlspecialchars($email); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mb-3">Send Reset Instructions</button>
                    </form>
                <?php elseif ($showResetForm && empty($success)): ?>
                    <!-- Show email sent message and reset form -->
                    <?php if (isset($_SESSION['reset_email'])): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            Reset instructions have been sent to <strong><?php echo htmlspecialchars($_SESSION['reset_email']); ?></strong>
                        </div>
                        
                        <div class="reset-option">
                            <p><i class="fas fa-info-circle me-2"></i>You can also reset your password directly below:</p>
                            <button type="button" class="btn btn-outline-primary" onclick="showPasswordForm()">
                                <i class="fas fa-key me-2"></i>Reset Password Now
                            </button>
                        </div>
                        
                        <!-- Hidden password reset form -->
                        <div id="passwordResetForm" style="display: none; margin-top: 20px;">
                            <hr class="my-4">
                            <h5 class="mb-3">Set New Password</h5>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="resetForm" <?php echo !isset($_SESSION['reset_email']) ? '' : 'style="display: none;"'; ?>>
                        <input type="hidden" name="reset_password" value="1">
                        
                        <div class="mb-3 password-field">
                            <label for="password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="password" name="password" 
                                   required minlength="8"
                                   placeholder="Enter your new password"
                                   onkeyup="checkPasswordStrength(this.value)">
                            <span class="password-toggle" onclick="togglePassword('password')">
                                <i class="fas fa-eye" id="passwordToggleIcon"></i>
                            </span>
                            <div class="password-strength">
                                <div class="password-strength-bar" id="passwordStrengthBar"></div>
                            </div>
                            <div class="form-text">Must be at least 8 characters with uppercase, lowercase, number, and special character</div>
                        </div>
                        
                        <div class="mb-3 password-field">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                   required minlength="8"
                                   placeholder="Confirm your new password">
                            <span class="password-toggle" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye" id="confirmPasswordToggleIcon"></i>
                            </span>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 mb-3">Reset Password</button>
                    </form>
                    
                    <?php if (isset($_SESSION['reset_email'])): ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div class="login-footer">
                    <a href="login.php"><i class="fas fa-arrow-left me-1"></i>Back to Login</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Show password form when button is clicked
        function showPasswordForm() {
            document.getElementById('passwordResetForm').style.display = 'block';
            document.getElementById('resetForm').style.display = 'block';
        }
        
        // Function to toggle password visibility
        function togglePassword(fieldId) {
            const passwordInput = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + 'ToggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Function to check password strength
        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('passwordStrengthBar');
            let strength = 0;
            
            // Check password length
            if (password.length >= 8) strength += 25;
            
            // Check for uppercase
            if (password.match(/[A-Z]/)) strength += 25;
            
            // Check for lowercase
            if (password.match(/[a-z]/)) strength += 25;
            
            // Check for numbers and special characters
            if (password.match(/[0-9]/) && password.match(/[^A-Za-z0-9]/)) strength += 25;
            
            // Update strength bar
            strengthBar.style.width = strength + '%';
            
            // Update color based on strength
            if (strength <= 25) {
                strengthBar.style.backgroundColor = '#ef4444'; // Red
            } else if (strength <= 50) {
                strengthBar.style.backgroundColor = '#f97316'; // Orange
            } else if (strength <= 75) {
                strengthBar.style.backgroundColor = '#eab308'; // Yellow
            } else {
                strengthBar.style.backgroundColor = '#22c55e'; // Green
            }
        }
        
        // Validate password confirmation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('#resetForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const password = document.getElementById('password');
                    const confirmPassword = document.getElementById('confirm_password');
                    
                    if (password && confirmPassword && password.value !== confirmPassword.value) {
                        e.preventDefault();
                        alert('Passwords do not match. Please try again.');
                    }
                });
            }
        });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>