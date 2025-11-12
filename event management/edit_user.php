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

// Get user type and ID from URL parameters
$user_type = isset($_GET['type']) ? $_GET['type'] : '';
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!in_array($user_type, ['student', 'teacher', 'admin']) || $user_id <= 0) {
    $error = 'Invalid user parameters.';
} else {
    // Fetch user data based on type
    $table_name = $user_type . 's'; // students, teachers, admin
    $id_field = $user_type . '_id'; // student_id, teacher_id, admin_id
    
    $user_query = $conn->prepare("SELECT * FROM $table_name WHERE $id_field = ?");
    $user_query->bind_param('i', $user_id);
    $user_query->execute();
    $user_result = $user_query->get_result();
    $user_data = $user_result->fetch_assoc();
    $user_query->close();
    
    if (!$user_data) {
        $error = 'User not found.';
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone_number = trim($_POST['phone_number']);
    $department = trim($_POST['department']);
    
    // Validation
    if (!preg_match('/^[A-Z][a-zA-Z\s]*$/', $first_name)) {
        $error = 'First name must start with a capital letter and contain only letters and spaces.';
    } elseif (!preg_match('/^[A-Z][a-zA-Z\s]*$/', $last_name)) {
        $error = 'Last name must start with a capital letter and contain only letters and spaces.';
    } elseif (!preg_match('/^\d{10}$/', $phone_number)) {
        $error = 'Phone number must contain exactly 10 digits.';
    } elseif (!preg_match('/^[a-z0-9._%+-]+@gmail\.com$/', $email)) {
        $error = 'Email must be in lowercase and end with @gmail.com';
    } else {
        // Check if email is already taken by another user
        $email_check = $conn->prepare("SELECT $id_field FROM $table_name WHERE email = ? AND $id_field != ?");
        $email_check->bind_param('si', $email, $user_id);
        $email_check->execute();
        $email_result = $email_check->get_result();
        $email_check->close();
        
        if ($email_result->num_rows > 0) {
            $error = 'Email already exists for another user.';
        } else {
            // Prepare update query based on user type
            if ($user_type === 'student') {
                $year = intval($_POST['year']);
                $roll_no = trim($_POST['roll_no']);
                
                if (!preg_match('/^\d+$/', $roll_no)) {
                    $error = 'Roll No must contain only numbers.';
                } else {
                    $update_stmt = $conn->prepare("UPDATE students SET first_name=?, last_name=?, email=?, phone_number=?, department=?, year=?, roll_no=? WHERE student_id=?");
                    $update_stmt->bind_param('sssssisi', $first_name, $last_name, $email, $phone_number, $department, $year, $roll_no, $user_id);
                }
            } elseif ($user_type === 'teacher') {
                $designation = trim($_POST['designation']);
                $update_stmt = $conn->prepare("UPDATE teachers SET first_name=?, last_name=?, email=?, phone_number=?, department=?, designation=? WHERE teacher_id=?");
                $update_stmt->bind_param('ssssssi', $first_name, $last_name, $email, $phone_number, $department, $designation, $user_id);
            } else { // admin
                $update_stmt = $conn->prepare("UPDATE admin SET first_name=?, last_name=?, email=?, phone_number=?, department=? WHERE admin_id=?");
                $update_stmt->bind_param('sssssi', $first_name, $last_name, $email, $phone_number, $department, $user_id);
            }
            
            if (isset($update_stmt)) {
                if ($update_stmt->execute()) {
                    $success = 'User updated successfully!';
                    // Refresh user data
                    $user_query = $conn->prepare("SELECT * FROM $table_name WHERE $id_field = ?");
                    $user_query->bind_param('i', $user_id);
                    $user_query->execute();
                    $user_result = $user_query->get_result();
                    $user_data = $user_result->fetch_assoc();
                    $user_query->close();
                } else {
                    $error = 'Failed to update user.';
                }
                $update_stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Admin</title>
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

        .edit-user-container {
            max-width: 600px;
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

        .btn-secondary {
            background-color: #64748b;
            border: 1px solid #64748b;
            border-radius: 6px;
            padding: 12px 24px;
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: none;
            transition: all 0.15s ease-in-out;
        }

        .btn-secondary:hover {
            background-color: #475569;
            border-color: #475569;
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
            .edit-user-container {
                margin: 20px auto;
                padding: 0 15px;
            }
            
            .card-body {
                padding: 24px;
            }
        }

        .user-type-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .badge-student {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .badge-teacher {
            background-color: #fef3c7;
            color: #d97706;
        }

        .badge-admin {
            background-color: #fecaca;
            color: #dc2626;
        }
    </style>
    <script>
    function capitalizeFirstLetter(input) {
        const value = input.value;
        if (value.length > 0) {
            const capitalized = value.charAt(0).toUpperCase() + value.slice(1).toLowerCase();
            input.value = capitalized;
        }
    }

    function convertToLowercase(input) {
        const value = input.value;
        if (value.length > 0) {
            input.value = value.toLowerCase();
        }
    }

    function validateName(input, fieldName) {
        const value = input.value.trim();
        const errorElement = document.getElementById(fieldName + '_error');
        
        const nameRegex = /^[A-Z][a-zA-Z\s]*$/;
        
        if (value === '') {
            errorElement.textContent = fieldName.charAt(0).toUpperCase() + fieldName.slice(1) + ' is required.';
            input.classList.add('is-invalid');
            return false;
        } else if (!nameRegex.test(value)) {
            errorElement.textContent = fieldName.charAt(0).toUpperCase() + fieldName.slice(1) + ' must start with a capital letter and contain only letters and spaces.';
            input.classList.add('is-invalid');
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
        
        const emailRegex = /^[a-z0-9._%+-]+@gmail\.com$/;
        
        if (value === '') {
            errorElement.textContent = 'Email is required.';
            input.classList.add('is-invalid');
            return false;
        } else if (!emailRegex.test(value)) {
            errorElement.textContent = 'Email must be in lowercase and end with @gmail.com';
            input.classList.add('is-invalid');
            return false;
        } else {
            errorElement.textContent = '';
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
            return true;
        }
    }

    function validatePhone(input, fieldName) {
        const value = input.value.trim();
        const errorElement = document.getElementById(fieldName + '_error');
        
        const digitsOnly = value.replace(/\D/g, '');
        
        if (value === '') {
            errorElement.textContent = 'Phone number is required.';
            input.classList.add('is-invalid');
            return false;
        } else if (digitsOnly.length !== 10) {
            errorElement.textContent = 'Phone number must contain exactly 10 digits.';
            input.classList.add('is-invalid');
            return false;
        } else if (!/^\d+$/.test(value)) {
            errorElement.textContent = 'Phone number can only contain digits.';
            input.classList.add('is-invalid');
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
            return false;
        } else if (!/^\d+$/.test(value)) {
            errorElement.textContent = 'Roll No must contain only numbers.';
            input.classList.add('is-invalid');
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
        const emailValid = validateEmail(document.getElementById('email'), 'email');
        
        let rollNoValid = true;
        if (document.getElementById('roll_no')) {
            rollNoValid = validateRollNo(document.getElementById('roll_no'), 'roll_no');
        }
        
        return firstNameValid && lastNameValid && phoneValid && emailValid && rollNoValid;
    }
    </script>
</head>
<body>
    <div class="edit-user-container">
        <div class="card">
            <div class="card-header">
                <h3>
                    Edit <?php echo ucfirst($user_type); ?>
                    <span class="user-type-badge badge-<?php echo $user_type; ?>">
                        <?php echo $user_type; ?>
                    </span>
                </h3>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php elseif ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <?php if (!$error && $user_data): ?>
                    <form method="POST" action="" onsubmit="return validateForm()">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo htmlspecialchars($user_data['first_name']); ?>"
                                           onblur="capitalizeFirstLetter(this); validateName(this, 'first_name')" 
                                           oninput="capitalizeFirstLetter(this); validateName(this, 'first_name')" required>
                                    <div class="error-message" id="first_name_error"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo htmlspecialchars($user_data['last_name']); ?>"
                                           onblur="capitalizeFirstLetter(this); validateName(this, 'last_name')" 
                                           oninput="capitalizeFirstLetter(this); validateName(this, 'last_name')" required>
                                    <div class="error-message" id="last_name_error"></div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($user_data['email']); ?>"
                                           onblur="convertToLowercase(this); validateEmail(this, 'email')" 
                                           oninput="convertToLowercase(this); validateEmail(this, 'email')" required>
                                    <div class="error-message" id="email_error"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone_number" class="form-label">Phone Number</label>
                                    <input type="text" class="form-control" id="phone_number" name="phone_number" 
                                           value="<?php echo htmlspecialchars($user_data['phone_number']); ?>"
                                           onblur="validatePhone(this, 'phone_number')" 
                                           oninput="validatePhone(this, 'phone_number')" required>
                                    <div class="error-message" id="phone_number_error"></div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="department" class="form-label">Department</label>
                            <select class="form-select" id="department" name="department" required>
                                <option value="BCA" <?php echo ($user_data['department'] == 'BCA') ? 'selected' : ''; ?>>BCA</option>
                                <option value="B.COM" <?php echo ($user_data['department'] == 'B.COM') ? 'selected' : ''; ?>>B.COM</option>
                                <option value="BBA" <?php echo ($user_data['department'] == 'BBA') ? 'selected' : ''; ?>>BBA</option>
                                <option value="BA Visual Communication" <?php echo ($user_data['department'] == 'BA Visual Communication') ? 'selected' : ''; ?>>BA Visual Communication</option>
                                <option value="BA Animation" <?php echo ($user_data['department'] == 'BA Animation') ? 'selected' : ''; ?>>BA Animation</option>
                                <option value="BSW" <?php echo ($user_data['department'] == 'BSW') ? 'selected' : ''; ?>>BSW</option>
                                <option value="MSW" <?php echo ($user_data['department'] == 'MSW') ? 'selected' : ''; ?>>MSW</option>
                            </select>
                        </div>

                        <?php if ($user_type === 'student'): ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="year" class="form-label">Year</label>
                                        <select class="form-select" id="year" name="year" required>
                                            <option value="1" <?php echo ($user_data['year'] == 1) ? 'selected' : ''; ?>>1</option>
                                            <option value="2" <?php echo ($user_data['year'] == 2) ? 'selected' : ''; ?>>2</option>
                                            <option value="3" <?php echo ($user_data['year'] == 3) ? 'selected' : ''; ?>>3</option>
                                            <option value="4" <?php echo ($user_data['year'] == 4) ? 'selected' : ''; ?>>4</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="roll_no" class="form-label">Roll No</label>
                                        <input type="text" class="form-control" id="roll_no" name="roll_no" 
                                               value="<?php echo htmlspecialchars($user_data['roll_no']); ?>"
                                               onblur="validateRollNo(this, 'roll_no')" 
                                               oninput="validateRollNo(this, 'roll_no')" required>
                                        <div class="error-message" id="roll_no_error"></div>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($user_type === 'teacher'): ?>
                            <div class="mb-3">
                                <label for="designation" class="form-label">Designation</label>
                                <select class="form-select" id="designation" name="designation" required>
                                    <option value="">Select Designation</option>
                                    <option value="Professor" <?php echo ($user_data['designation'] == 'Professor') ? 'selected' : ''; ?>>Professor</option>
                                    <option value="Associate Professor" <?php echo ($user_data['designation'] == 'Associate Professor') ? 'selected' : ''; ?>>Associate Professor</option>
                                    <option value="Assistant Professor" <?php echo ($user_data['designation'] == 'Assistant Professor') ? 'selected' : ''; ?>>Assistant Professor</option>
                                    <option value="Lecturer" <?php echo ($user_data['designation'] == 'Lecturer') ? 'selected' : ''; ?>>Lecturer</option>
                                    <option value="Lab Assistant" <?php echo ($user_data['designation'] == 'Lab Assistant') ? 'selected' : ''; ?>>Lab Assistant</option>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Update User</button>
                            <a href="manage_users.php" class="btn btn-secondary">Back to Users</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
