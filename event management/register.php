<?php
// Database connection
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
    $password = $_POST['password'];
    $phone_number = trim($_POST['phone_number']);
    $department = trim($_POST['department']);

    // Validation for first name and last name
    if (!preg_match('/^[A-Z][a-zA-Z\s]*$/', $first_name)) {
        $error = 'First name must start with a capital letter and contain only letters and spaces.';
    } elseif (!preg_match('/^[A-Z][a-zA-Z\s]*$/', $last_name)) {
        $error = 'Last name must start with a capital letter and contain only letters and spaces.';
    } elseif (!preg_match('/^\d{10}$/', $phone_number)) {
        $error = 'Phone number must contain exactly 10 digits.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match('/\d/', $password)) {
        $error = 'Password must contain at least one number.';
    } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $error = 'Password must contain at least one special character.';
    } elseif (!preg_match('/^[a-z0-9._%+-]+@gmail\.com$/', $email)) {
        $error = 'Email must be in lowercase and end with @gmail.com';
    } else {
        // Role-specific validation
        if ($role === 'student') {
            if (!isset($_POST['roll_no']) || !preg_match('/^\d+$/', $_POST['roll_no'])) {
                $error = 'Roll No must contain only numbers.';
            }
        } else {
            if (!isset($_POST['designation']) || empty(trim($_POST['designation']))) {
                $error = 'Designation is required for teachers.';
            }
        }
        
        // If no errors, proceed with database insertion
        if (empty($error)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            if ($role === 'student') {
                $year = intval($_POST['year']);
                $roll_no = trim($_POST['roll_no']);
                $stmt = $conn->prepare("INSERT INTO students (first_name, last_name, email, password, phone_number, department, year, roll_no) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('ssssssis', $first_name, $last_name, $email, $hashed_password, $phone_number, $department, $year, $roll_no);
            } else {
                $designation = trim($_POST['designation']);
                $stmt = $conn->prepare("INSERT INTO teachers (first_name, last_name, email, password, phone_number, department, designation) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('sssssss', $first_name, $last_name, $email, $hashed_password, $phone_number, $department, $designation);
            }
            
            if ($stmt->execute()) {
                $success = 'Registration successful! You can now <a href="login.php">login</a>.';
            } else {
                if ($conn->errno === 1062) {
                    $error = 'Email already exists.';
                } else {
                    $error = 'Registration failed. Please try again. Error: ' . $conn->error;
                }
            }
            $stmt->close();
        }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Event Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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

        .register-container {
            max-width: 500px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .card-header {
            background-color: #1e293b;
            color: white;
            padding: 24px;
            text-align: center;
            border-bottom: 1px solid #e2e8f0;
        }

        .card-header h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
            letter-spacing: -0.025em;
        }

        .card-body {
            padding: 32px;
        }

        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 6px;
            font-size: 0.875rem;
        }

        .form-control, .form-select {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 10px 12px;
            font-size: 0.875rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            background-color: white;
        }

        .form-control:focus, .form-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }

        .form-control.is-valid {
            border-color: #10b981;
            background-image: none;
        }

        .form-control.is-invalid {
            border-color: #ef4444;
            background-image: none;
        }

        .mb-3 {
            margin-bottom: 1.25rem;
        }

        .error-message {
            color: #ef4444;
            font-size: 0.75rem;
            margin-top: 4px;
            font-weight: 500;
        }

        .btn-primary {
            background-color: #1e293b;
            border: 1px solid #1e293b;
            border-radius: 6px;
            padding: 12px 24px;
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: none;
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

        .form-text {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 4px;
        }

        a {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 500;
        }

        a:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }

        #student-fields, #teacher-fields {
            background-color: #f8fafc;
            border-radius: 6px;
            padding: 20px;
            margin-top: 16px;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease-in-out;
        }

        .text-center {
            text-align: center;
        }

        .w-100 {
            width: 100%;
        }

        .mt-3 {
            margin-top: 1rem;
        }

        /* Responsive design */
        @media (max-width: 576px) {
            .register-container {
                margin: 20px auto;
                padding: 0 15px;
            }
            
            .card-body {
                padding: 24px;
            }
        }

        /* Clean focus states */
        .form-control:focus-within + .form-label,
        .form-select:focus-within + .form-label {
            color: #3b82f6;
        }

        /* Professional spacing */
        .card-header {
            border-bottom: none;
        }

        .hidden {
            display: none !important;
        }
    </style>
    <script>
    function toggleRoleFields() {
        var role = document.getElementById('role').value;
        if (role === 'student') {
            document.getElementById('student-fields').classList.remove('hidden');
            document.getElementById('teacher-fields').classList.add('hidden');
            // Make roll_no required
            document.getElementById('roll_no').required = true;
            document.getElementById('designation').required = false;
        } else {
            document.getElementById('student-fields').classList.add('hidden');
            document.getElementById('teacher-fields').classList.remove('hidden');
            // Make designation required
            document.getElementById('roll_no').required = false;
            document.getElementById('designation').required = true;
        }
    }

    function capitalizeFirstLetter(input) {
        const value = input.value;
        if (value.length > 0) {
            // Capitalize first letter and make rest lowercase
            const capitalized = value.charAt(0).toUpperCase() + value.slice(1).toLowerCase();
            input.value = capitalized;
        }
    }

    function convertToLowercase(input) {
        const value = input.value;
        if (value.length > 0) {
            // Convert to lowercase
            input.value = value.toLowerCase();
        }
    }

    // Enhanced validation functions
    function validateName(input, fieldName) {
        const value = input.value.trim();
        const errorElement = document.getElementById(fieldName + '_error');
        
        // Check if it starts with capital letter and contains only letters and spaces
        const nameRegex = /^[A-Z][a-zA-Z\s]*$/;
        
        if (value === '') {
            errorElement.textContent = fieldName.charAt(0).toUpperCase() + fieldName.slice(1).replace('_', ' ') + ' is required.';
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            return false;
        } else if (!nameRegex.test(value)) {
            errorElement.textContent = fieldName.charAt(0).toUpperCase() + fieldName.slice(1).replace('_', ' ') + ' must start with a capital letter and contain only letters and spaces.';
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            return false;
        } else if (value.length < 2) {
            errorElement.textContent = fieldName.charAt(0).toUpperCase() + fieldName.slice(1).replace('_', ' ') + ' must be at least 2 characters long.';
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            return false;
        } else if (value.length > 50) {
            errorElement.textContent = fieldName.charAt(0).toUpperCase() + fieldName.slice(1).replace('_', ' ') + ' cannot exceed 50 characters.';
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            return false;
        } else {
            errorElement.textContent = '';
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
            return true;
        }
    }

    function validateEmail(input, fieldName) {
        const value = input.value.trim();
        const errorElement = document.getElementById(fieldName + '_error');
        
        // Email regex pattern - lowercase only, must end with @gmail.com
        const emailRegex = /^[a-z0-9._%+-]+@gmail\.com$/;
        
        if (value === '') {
            errorElement.textContent = 'Email is required.';
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            return false;
        } else if (!emailRegex.test(value)) {
            errorElement.textContent = 'Email must be in lowercase and end with @gmail.com (e.g., example@gmail.com)';
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            return false;
        } else if (value.length > 100) {
            errorElement.textContent = 'Email cannot exceed 100 characters.';
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            return false;
        } else {
            errorElement.textContent = '';
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
            return true;
        }
    }

    function validatePassword(input, fieldName) {
        const value = input.value;
        const errorElement = document.getElementById(fieldName + '_error');
        
        // Password criteria
        const hasLength = value.length >= 8;
        const hasUppercase = /[A-Z]/.test(value);
        const hasLowercase = /[a-z]/.test(value);
        const hasNumber = /\d/.test(value);
        const hasSpecial = /[^A-Za-z0-9]/.test(value);
        
        let errorMessage = '';
        let isValid = true;
        
        if (value === '') {
            errorMessage = 'Password is required.';
            isValid = false;
        } else if (!hasLength) {
            errorMessage = 'Password must be at least 8 characters long.';
            isValid = false;
        } else if (!hasUppercase) {
            errorMessage = 'Password must contain at least one uppercase letter.';
            isValid = false;
        } else if (!hasLowercase) {
            errorMessage = 'Password must contain at least one lowercase letter.';
            isValid = false;
        } else if (!hasNumber) {
            errorMessage = 'Password must contain at least one number.';
            isValid = false;
        } else if (!hasSpecial) {
            errorMessage = 'Password must contain at least one special character.';
            isValid = false;
        } else if (value.length > 128) {
            errorMessage = 'Password cannot exceed 128 characters.';
            isValid = false;
        }
        
        errorElement.textContent = errorMessage;
        
        if (isValid) {
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
        } else {
            input.classList.remove('is-valid');
            input.classList.add('is-invalid');
        }
        
        return isValid;
    }

    function validatePhone(input, fieldName) {
        const value = input.value.trim();
        const errorElement = document.getElementById(fieldName + '_error');
        
        // Remove any non-digit characters for validation
        const digitsOnly = value.replace(/\D/g, '');
        
        if (value === '') {
            errorElement.textContent = 'Phone number is required.';
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            return false;
        } else if (digitsOnly.length !== 10) {
            errorElement.textContent = 'Phone number must contain exactly 10 digits.';
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            return false;
        } else if (!/^\d+$/.test(value)) {
            errorElement.textContent = 'Phone number can only contain digits.';
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            return false;
        } else {
            errorElement.textContent = '';
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
            return true;
        }
    }

    function validateDepartment(input, fieldName) {
        const value = input.value;
        const errorElement = document.getElementById(fieldName + '_error');
        
        if (value === '') {
            errorElement.textContent = 'Department is required.';
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            return false;
        } else {
            errorElement.textContent = '';
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
            return true;
        }
    }

    function validateYear(input, fieldName) {
        const value = input.value;
        const errorElement = document.getElementById(fieldName + '_error');
        
        if (value === '') {
            errorElement.textContent = 'Year is required.';
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            return false;
        } else {
            errorElement.textContent = '';
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
            return true;
        }
    }

    function validateRollNo(input, fieldName) {
        const value = input.value.trim();
        const errorElement = document.getElementById(fieldName + '_error');
        
        if (value === '') {
            errorElement.textContent = 'Roll No is required.';
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            return false;
        } else if (!/^\d+$/.test(value)) {
            errorElement.textContent = 'Roll No must contain only numbers.';
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            return false;
        } else if (value.length > 20) {
            errorElement.textContent = 'Roll No cannot exceed 20 characters.';
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            return false;
        } else {
            errorElement.textContent = '';
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
            return true;
        }
    }

    function validateDesignation(input, fieldName) {
        const value = input.value.trim();
        const errorElement = document.getElementById(fieldName + '_error');
        
        if (value === '') {
            errorElement.textContent = 'Designation is required.';
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            return false;
        } else if (value.length > 50) {
            errorElement.textContent = 'Designation cannot exceed 50 characters.';
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            return false;
        } else {
            errorElement.textContent = '';
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
            return true;
        }
    }

    function validateRole(input, fieldName) {
        const value = input.value;
        const errorElement = document.getElementById(fieldName + '_error');
        
        if (value === '') {
            errorElement.textContent = 'Role is required.';
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            return false;
        } else {
            errorElement.textContent = '';
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
            return true;
        }
    }

    function validateForm() {
        const firstNameValid = validateName(document.getElementById('first_name'), 'first_name');
        const lastNameValid = validateName(document.getElementById('last_name'), 'last_name');
        const phoneValid = validatePhone(document.getElementById('phone_number'), 'phone_number');
        const passwordValid = validatePassword(document.getElementById('password'), 'password');
        const emailValid = validateEmail(document.getElementById('email'), 'email');
        const departmentValid = validateDepartment(document.getElementById('department'), 'department');
        const roleValid = validateRole(document.getElementById('role'), 'role');
        
        let roleSpecificValid = true;
        if (document.getElementById('role').value === 'student') {
            const yearValid = validateYear(document.getElementById('year'), 'year');
            roleSpecificValid = validateRollNo(document.getElementById('roll_no'), 'roll_no') && yearValid;
        } else {
            roleSpecificValid = validateDesignation(document.getElementById('designation'), 'designation');
        }
        
        return firstNameValid && lastNameValid && phoneValid && passwordValid && 
               emailValid && departmentValid && roleValid && roleSpecificValid;
    }

    // Real-time validation for all fields
    document.addEventListener('DOMContentLoaded', function() {
        // Add event listeners for real-time validation
        document.getElementById('first_name').addEventListener('input', function() {
            validateName(this, 'first_name');
        });
        
        document.getElementById('last_name').addEventListener('input', function() {
            validateName(this, 'last_name');
        });
        
        document.getElementById('email').addEventListener('input', function() {
            validateEmail(this, 'email');
        });
        
        document.getElementById('password').addEventListener('input', function() {
            validatePassword(this, 'password');
        });
        
        document.getElementById('phone_number').addEventListener('input', function() {
            validatePhone(this, 'phone_number');
        });
        
        document.getElementById('department').addEventListener('change', function() {
            validateDepartment(this, 'department');
        });
        
        document.getElementById('role').addEventListener('change', function() {
            validateRole(this, 'role');
            toggleRoleFields();
        });
        
        document.getElementById('year').addEventListener('change', function() {
            validateYear(this, 'year');
        });
        
        document.getElementById('roll_no').addEventListener('input', function() {
            validateRollNo(this, 'roll_no');
        });
        
        document.getElementById('designation').addEventListener('change', function() {
            validateDesignation(this, 'designation');
        });
    });
    </script>
</head>
<body onload="toggleRoleFields()">
    <div class="register-container">
        <div class="card">
            <div class="card-header">
                <h3>Register</h3>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php elseif ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <form method="POST" action="" onsubmit="return validateForm()">
                    <div class="mb-3">
                        <label for="role" class="form-label">Register as</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="">Select Role</option>
                            <option value="student" <?php echo (isset($_POST['role']) && $_POST['role'] === 'student') ? 'selected' : ''; ?>>Student</option>
                            <option value="teacher" <?php echo (isset($_POST['role']) && $_POST['role'] === 'teacher') ? 'selected' : ''; ?>>Teacher</option>
                        </select>
                        <div class="error-message" id="role_error"></div>
                    </div>
                    <div class="mb-3">
                        <label for="first_name" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="first_name" name="first_name" 
                               value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>"
                               onblur="capitalizeFirstLetter(this); validateName(this, 'first_name')" required>
                        <div class="error-message" id="first_name_error"></div>
                    </div>
                    <div class="mb-3">
                        <label for="last_name" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="last_name" name="last_name" 
                               value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>"
                               onblur="capitalizeFirstLetter(this); validateName(this, 'last_name')" required>
                        <div class="error-message" id="last_name_error"></div>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                               onblur="convertToLowercase(this); validateEmail(this, 'email')" required>
                        <div class="error-message" id="email_error"></div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <div class="error-message" id="password_error"></div>
                        <small class="form-text">
                            Password must contain at least 8 characters, including uppercase, lowercase, number, and special character.
                        </small>
                    </div>
                    <div class="mb-3">
                        <label for="phone_number" class="form-label">Phone Number</label>
                        <input type="text" class="form-control" id="phone_number" name="phone_number" 
                               value="<?php echo isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : ''; ?>"
                               maxlength="10" required>
                        <div class="error-message" id="phone_number_error"></div>
                    </div>
                    <div class="mb-3">
                        <label for="department" class="form-label">Department</label>
                        <select class="form-select" id="department" name="department" required>
                            <option value="">Select Department</option>
                            <option value="BCA" <?php echo (isset($_POST['department']) && $_POST['department'] === 'BCA') ? 'selected' : ''; ?>>BCA</option>
                            <option value="B.COM" <?php echo (isset($_POST['department']) && $_POST['department'] === 'B.COM') ? 'selected' : ''; ?>>B.COM</option>
                            <option value="BBA" <?php echo (isset($_POST['department']) && $_POST['department'] === 'BBA') ? 'selected' : ''; ?>>BBA</option>
                            <option value="BA Visual Communication" <?php echo (isset($_POST['department']) && $_POST['department'] === 'BA Visual Communication') ? 'selected' : ''; ?>>BA Visual Communication</option>
                            <option value="BA Animation" <?php echo (isset($_POST['department']) && $_POST['department'] === 'BA Animation') ? 'selected' : ''; ?>>BA Animation</option>
                            <option value="BSW" <?php echo (isset($_POST['department']) && $_POST['department'] === 'BSW') ? 'selected' : ''; ?>>BSW</option>
                            <option value="MSW" <?php echo (isset($_POST['department']) && $_POST['department'] === 'MSW') ? 'selected' : ''; ?>>MSW</option>
                        </select>
                        <div class="error-message" id="department_error"></div>
                    </div>
                    <div id="student-fields">
                        <div class="mb-3">
                            <label for="year" class="form-label">Year</label>
                            <select class="form-select" id="year" name="year">
                                <option value="">Select Year</option>
                                <option value="1" <?php echo (isset($_POST['year']) && $_POST['year'] == '1') ? 'selected' : ''; ?>>1</option>
                                <option value="2" <?php echo (isset($_POST['year']) && $_POST['year'] == '2') ? 'selected' : ''; ?>>2</option>
                                <option value="3" <?php echo (isset($_POST['year']) && $_POST['year'] == '3') ? 'selected' : ''; ?>>3</option>
                                <option value="4" <?php echo (isset($_POST['year']) && $_POST['year'] == '4') ? 'selected' : ''; ?>>4</option>
                            </select>
                            <div class="error-message" id="year_error"></div>
                        </div>
                        <div class="mb-3">
                            <label for="roll_no" class="form-label">Roll No</label>
                            <input type="text" class="form-control" id="roll_no" name="roll_no" 
                                   value="<?php echo isset($_POST['roll_no']) ? htmlspecialchars($_POST['roll_no']) : ''; ?>">
                            <div class="error-message" id="roll_no_error"></div>
                        </div>
                    </div>
                    <div id="teacher-fields" class="hidden">
                        <div class="mb-3">
                            <label for="designation" class="form-label">Designation</label>
                            <select class="form-select" id="designation" name="designation">
                                <option value="">Select Designation</option>
                                <option value="Professor" <?php echo (isset($_POST['designation']) && $_POST['designation'] === 'Professor') ? 'selected' : ''; ?>>Professor</option>
                                <option value="Associate Professor" <?php echo (isset($_POST['designation']) && $_POST['designation'] === 'Associate Professor') ? 'selected' : ''; ?>>Associate Professor</option>
                                <option value="Assistant Professor" <?php echo (isset($_POST['designation']) && $_POST['designation'] === 'Assistant Professor') ? 'selected' : ''; ?>>Assistant Professor</option>
                                <option value="Lecturer" <?php echo (isset($_POST['designation']) && $_POST['designation'] === 'Lecturer') ? 'selected' : ''; ?>>Lecturer</option>
                                <option value="Lab Assistant" <?php echo (isset($_POST['designation']) && $_POST['designation'] === 'Lab Assistant') ? 'selected' : ''; ?>>Lab Assistant</option>
                            </select>
                            <div class="error-message" id="designation_error"></div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Register</button>
                </form>
                <div class="mt-3 text-center">
                    <a href="login.php">Already have an account? Login</a>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>