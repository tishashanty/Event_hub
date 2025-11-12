<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit();
}
$conn = new mysqli('localhost', 'root', '', 'event_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$teacher_id = $_SESSION['user_id'];

/// Handle event creation
$create_msg = '';
$register_msg = '';
// Ensure duration column exists
$conn->query("ALTER TABLE events ADD COLUMN IF NOT EXISTS duration INT DEFAULT 60");

// Fetch upcoming events and registrations (outside POST block)
$events = $conn->query("SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC");
$registrations = $conn->query("SELECT r.*, e.title, e.event_date, e.venue FROM registrations r JOIN events e ON r.event_id = e.event_id WHERE r.user_type = 'teacher' AND r.user_id = $teacher_id ORDER BY e.event_date DESC");

// Handle event registration
if (isset($_POST['register_event_id'])) {
    $event_id = intval($_POST['register_event_id']);
    $check = $conn->query("SELECT * FROM registrations WHERE event_id = $event_id AND user_type = 'teacher' AND user_id = $teacher_id");
    if ($check->num_rows == 0) {
        $conn->query("INSERT INTO registrations (event_id, user_type, user_id) VALUES ($event_id, 'teacher', $teacher_id)");
        $register_msg = '<div class="alert alert-success">Registered successfully!</div>';
    } else {
        $register_msg = '<div class="alert alert-warning">Already registered for this event.</div>';
    }
    // Refresh events and registrations after registration
    $events = $conn->query("SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC");
    $registrations = $conn->query("SELECT r.*, e.title, e.event_date, e.venue FROM registrations r JOIN events e ON r.event_id = e.event_id WHERE r.user_type = 'teacher' AND r.user_id = $teacher_id ORDER BY e.event_date DESC");
}

if (isset($_POST['create_event'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $event_date = $_POST['event_date'];
    $event_time = $_POST['event_time'];
    $venue = trim($_POST['venue']);
    $duration = intval($_POST['duration']);
    
    // Server-side validation
    $errors = [];
    
    // Title validation
    if (empty($title)) {
        $errors[] = 'Event title is required.';
    } elseif (strlen($title) < 3) {
        $errors[] = 'Event title must be at least 3 characters long.';
    } elseif (strlen($title) > 100) {
        $errors[] = 'Event title cannot exceed 100 characters.';
    } elseif (!preg_match('/^[a-zA-Z0-9\s\-_,.!:;()\']+$/', $title)) {
        $errors[] = 'Event title contains invalid characters.';
    }
    
    // Description validation
    if (!empty($description) && strlen($description) > 500) {
        $errors[] = 'Description cannot exceed 500 characters.';
    }
    
    // Date validation
    if (empty($event_date)) {
        $errors[] = 'Event date is required.';
    } else {
        $selected_date = strtotime($event_date);
        $today = strtotime(date('Y-m-d'));
        $max_date = strtotime('+1 year');
        
        if ($selected_date < $today) {
            $errors[] = 'Event date cannot be in the past.';
        } elseif ($selected_date > $max_date) {
            $errors[] = 'Event date cannot be more than 1 year in the future.';
        }
    }
    
    // Time validation
    if (empty($event_time)) {
        $errors[] = 'Event time is required.';
    } else {
        $time_parts = explode(':', $event_time);
        $hour = intval($time_parts[0]);
        if ($hour < 8 || $hour > 22) {
            $errors[] = 'Event time must be between 8:00 AM and 10:00 PM.';
        }
    }
    
    // Venue validation
    $valid_venues = ['Seminar Hall', 'Yoga Hall', 'Main Theatre', 'Mini Theatre', 'Open Auditorium', 'Room 1', 'Room 2', 'Room 3', 'Room 4', 'Room 5', 'Lab 1', 'Lab 2', 'Lab 3', 'Lab 4'];
    if (empty($venue)) {
        $errors[] = 'Venue is required.';
    } elseif (!in_array($venue, $valid_venues)) {
        $errors[] = 'Please select a valid venue.';
    }
    
    // Duration validation
    if (empty($duration)) {
        $errors[] = 'Duration is required.';
    } elseif ($duration < 15) {
        $errors[] = 'Duration must be at least 15 minutes.';
    } elseif ($duration > 480) {
        $errors[] = 'Duration cannot exceed 480 minutes (8 hours).';
    } elseif ($duration % 15 !== 0) {
        $errors[] = 'Duration must be in 15-minute increments.';
    }
    
    // If no validation errors, proceed with venue conflict check
    if (empty($errors)) {
        // Check for venue conflict considering duration
        $venueConflictCount = 0;
        $checkVenueStmt = $conn->prepare("
            SELECT COUNT(*) FROM events 
            WHERE event_date = ? AND venue = ? AND (
                (TIME_TO_SEC(event_time) <= TIME_TO_SEC(?) AND TIME_TO_SEC(event_time) + (duration * 60) > TIME_TO_SEC(?)) OR
                (TIME_TO_SEC(?) < TIME_TO_SEC(event_time) + (duration * 60) AND TIME_TO_SEC(?) >= TIME_TO_SEC(event_time))
            )
        ");
        $checkVenueStmt->bind_param('ssssss', $event_date, $venue, $event_time, $event_time, $event_time, $event_time);
        if ($checkVenueStmt->execute()) {
            $checkVenueStmt->bind_result($venueConflictCount);
            $checkVenueStmt->fetch();
        }
        $checkVenueStmt->close();

        if ($venueConflictCount > 0) {
            $errors[] = 'This venue is already booked for another program that conflicts with the specified time and duration. Please choose a different venue, date, or time.';
        }
    }
    
    // If no errors, insert into database
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO events (title, description, event_date, event_time, venue, duration, created_by_user_type, created_by_user_id) VALUES (?, ?, ?, ?, ?, ?, 'teacher', ?)");
        $stmt->bind_param('sssssii', $title, $description, $event_date, $event_time, $venue, $duration, $teacher_id);
        if ($stmt->execute()) {
            $create_msg = '<div class=\'alert alert-success\'>Event created successfully!</div>';
            // Clear form data on success
            unset($_POST);
        } else {
            $create_msg = '<div class=\'alert alert-danger\'>Failed to create event. Database error: ' . $conn->error . '</div>';
        }
        $stmt->close();
    } else {
        $create_msg = '<div class=\'alert alert-danger\'>' . implode('<br>', $errors) . '</div>';
    }

    // Refresh events and registrations after event creation
    $events = $conn->query("SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC");
    $registrations = $conn->query("SELECT r.*, e.title, e.event_date, e.venue FROM registrations r JOIN events e ON r.event_id = e.event_id WHERE r.user_type = 'teacher' AND r.user_id = $teacher_id ORDER BY e.event_date DESC");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Event Management</title>
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

        /* Navigation Styles */
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

        .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .dropdown-menu {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .dropdown-item {
            transition: all 0.2s ease;
        }

        .dropdown-item:hover {
            background-color: rgba(30, 41, 59, 0.1);
        }

        /* Main Content */
        .dashboard-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .welcome-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .welcome-header h2 {
            color: #1e293b;
            font-weight: 700;
            margin-bottom: 10px;
            font-size: 2rem;
        }

        .welcome-header p {
            color: #64748b;
            margin-bottom: 0;
            font-size: 1.1rem;
        }

        /* Cards */
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

        /* Buttons */
        .btn {
            font-weight: 500;
            border-radius: 8px;
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
            border-color: #0f172a;
            transform: translateY(-1px);
        }

        .btn-success {
            background-color: #16a34a;
            border-color: #16a34a;
        }

        .btn-success:hover {
            background-color: #15803d;
            border-color: #15803d;
            transform: translateY(-1px);
        }

        .btn-info {
            background-color: #0ea5e9;
            border-color: #0ea5e9;
        }

        .btn-info:hover {
            background-color: #0284c7;
            border-color: #0284c7;
            transform: translateY(-1px);
        }

        .btn-danger {
            background-color: #dc2626;
            border-color: #dc2626;
        }

        .btn-danger:hover {
            background-color: #b91c1c;
            border-color: #b91c1c;
            transform: translateY(-1px);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.875rem;
        }

        /* Form Elements */
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
            border-color: #1e293b;
            box-shadow: 0 0 0 3px rgba(30, 41, 59, 0.1);
            outline: none;
        }

        /* Alerts */
        .alert {
            border-radius: 8px;
            border: none;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-success {
            background-color: #f0fdf4;
            color: #166534;
            border-left: 4px solid #16a34a;
        }

        .alert-danger {
            background-color: #fef2f2;
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }

        .alert-warning {
            background-color: #fffbeb;
            color: #d97706;
            border-left: 4px solid #f59e0b;
        }

        /* Tables */
        .table {
            margin-bottom: 0;
        }

        .table th {
            background-color: rgba(248, 250, 252, 0.8);
            color: #374151;
            font-weight: 600;
            font-size: 0.875rem;
            padding: 12px;
            border-bottom: 2px solid #e5e7eb;
        }

        .table td {
            padding: 12px;
            vertical-align: middle;
            border-bottom: 1px solid #e5e7eb;
        }

        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }
/* Additional validation styles */
.form-control.is-invalid {
    border-color: #dc2626;
    background-image: none;
}

.form-control.is-valid {
    border-color: #16a34a;
    background-image: none;
}

.error-message {
    color: #dc2626;
    font-size: 0.75rem;
    margin-top: 4px;
    font-weight: 500;
}

.form-text {
    font-size: 0.75rem;
    color: #6b7280;
    margin-top: 4px;
}

.character-count {
    font-size: 0.75rem;
    color: #6b7280;
    text-align: right;
    margin-top: 4px;
}

.character-count.warning {
    color: #d97706;
}

.character-count.error {
    color: #dc2626;
}
        /* Action buttons container */
        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        /* Create event form */
        #create-event-form {
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .dashboard-container {
                margin: 20px auto;
                padding: 0 15px;
            }

            .welcome-header {
                padding: 20px;
            }

            .welcome-header h2 {
                font-size: 1.5rem;
            }

            .header-actions {
                flex-direction: column;
                gap: 8px;
                width: 100%;
            }

            .header-actions .btn {
                width: 100%;
            }

            .card-body {
                padding: 16px;
            }
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        /* Loading state */
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Hover effects for rows */
        .table tbody tr:hover {
            background-color: rgba(248, 250, 252, 0.5);
        }
    </style>
    <script>
    function toggleCreateEvent() {
        var form = document.getElementById('create-event-form');
        form.style.display = (form.style.display === 'none' || form.style.display === '') ? 'block' : 'none';
        // Reset form when closing
        if (form.style.display === 'none') {
            resetFormValidation();
        }
    }
    
    function resetFormValidation() {
        // Reset all form fields and validation states
        const form = document.querySelector('#create-event-form form');
        const inputs = form.querySelectorAll('.form-control, .form-select');
        inputs.forEach(input => {
            input.classList.remove('is-invalid', 'is-valid');
        });
        
        const errorMessages = form.querySelectorAll('.error-message');
        errorMessages.forEach(error => {
            error.textContent = '';
        });
        
        const characterCounts = form.querySelectorAll('.character-count');
        characterCounts.forEach(count => {
            count.textContent = '';
        });
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        var hasMessage = <?php echo json_encode(!empty($create_msg)); ?>;
        if (hasMessage) {
            var form = document.getElementById('create-event-form');
            if (form) form.style.display = 'block';
        }
        
        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('event_date').min = today;
        
        // Set maximum date to 1 year from today
        const maxDate = new Date();
        maxDate.setFullYear(maxDate.getFullYear() + 1);
        document.getElementById('event_date').max = maxDate.toISOString().split('T')[0];
        
        // Initialize real-time validation
        initializeValidation();
    });
    
    function initializeValidation() {
        // Title validation
        const titleInput = document.getElementById('title');
        if (titleInput) {
            titleInput.addEventListener('input', function() {
                validateTitle(this);
                updateCharacterCount(this, 'title-count', 100);
            });
            titleInput.addEventListener('blur', function() {
                validateTitle(this);
            });
        }
        
        // Description validation
        const descriptionInput = document.getElementById('description');
        if (descriptionInput) {
            descriptionInput.addEventListener('input', function() {
                validateDescription(this);
                updateCharacterCount(this, 'description-count', 500);
            });
            descriptionInput.addEventListener('blur', function() {
                validateDescription(this);
            });
        }
        
        // Date validation
        const dateInput = document.getElementById('event_date');
        if (dateInput) {
            dateInput.addEventListener('change', function() {
                validateEventDate(this);
            });
            dateInput.addEventListener('blur', function() {
                validateEventDate(this);
            });
        }
        
        // Time validation
        const timeInput = document.getElementById('event_time');
        if (timeInput) {
            timeInput.addEventListener('change', function() {
                validateEventTime(this);
            });
            timeInput.addEventListener('blur', function() {
                validateEventTime(this);
            });
        }
        
        // Venue validation
        const venueInput = document.getElementById('venue');
        if (venueInput) {
            venueInput.addEventListener('change', function() {
                validateVenue(this);
            });
        }
        
        // Duration validation
        const durationInput = document.getElementById('duration');
        if (durationInput) {
            durationInput.addEventListener('input', function() {
                validateDuration(this);
            });
            durationInput.addEventListener('blur', function() {
                validateDuration(this);
            });
        }
    }
    
    function validateTitle(input) {
        const value = input.value.trim();
        const errorElement = document.getElementById('title_error');
        
        if (value === '') {
            showError(input, errorElement, 'Event title is required.');
            return false;
        } else if (value.length < 3) {
            showError(input, errorElement, 'Event title must be at least 3 characters long.');
            return false;
        } else if (value.length > 100) {
            showError(input, errorElement, 'Event title cannot exceed 100 characters.');
            return false;
        } else if (!/^[a-zA-Z0-9\s\-_,.!:;()']+$/.test(value)) {
            showError(input, errorElement, 'Event title contains invalid characters. Only letters, numbers, spaces, and basic punctuation are allowed.');
            return false;
        } else {
            showSuccess(input, errorElement);
            return true;
        }
    }
    
    function validateDescription(input) {
        const value = input.value.trim();
        const errorElement = document.getElementById('description_error');
        
        if (value === '') {
            showSuccess(input, errorElement);
            return true; // Description is optional
        } else if (value.length > 500) {
            showError(input, errorElement, 'Description cannot exceed 500 characters.');
            return false;
        } else if (!/^[a-zA-Z0-9\s\-_,.!:;()'\n\r]+$/.test(value)) {
            showError(input, errorElement, 'Description contains invalid characters.');
            return false;
        } else {
            showSuccess(input, errorElement);
            return true;
        }
    }
    
    function validateEventDate(input) {
        const value = input.value;
        const errorElement = document.getElementById('event_date_error');
        
        if (value === '') {
            showError(input, errorElement, 'Event date is required.');
            return false;
        }
        
        const selectedDate = new Date(value);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        const maxDate = new Date();
        maxDate.setFullYear(maxDate.getFullYear() + 1);
        
        if (selectedDate < today) {
            showError(input, errorElement, 'Event date cannot be in the past.');
            return false;
        } else if (selectedDate > maxDate) {
            showError(input, errorElement, 'Event date cannot be more than 1 year in the future.');
            return false;
        } else {
            showSuccess(input, errorElement);
            return true;
        }
    }
    
    function validateEventTime(input) {
        const value = input.value;
        const errorElement = document.getElementById('event_time_error');
        
        if (value === '') {
            showError(input, errorElement, 'Event time is required.');
            return false;
        }
        
        const timeParts = value.split(':');
        const hour = parseInt(timeParts[0]);
        
        if (hour < 8 || hour > 22) {
            showError(input, errorElement, 'Event time must be between 8:00 AM and 10:00 PM.');
            return false;
        } else {
            showSuccess(input, errorElement);
            return true;
        }
    }
    
    function validateVenue(input) {
        const value = input.value;
        const errorElement = document.getElementById('venue_error');
        const validVenues = ['Seminar Hall', 'Yoga Hall', 'Main Theatre', 'Mini Theatre', 'Open Auditorium', 'Room 1', 'Room 2', 'Room 3', 'Room 4', 'Room 5', 'Lab 1', 'Lab 2', 'Lab 3', 'Lab 4'];
        
        if (value === '') {
            showError(input, errorElement, 'Venue is required.');
            return false;
        } else if (!validVenues.includes(value)) {
            showError(input, errorElement, 'Please select a valid venue from the list.');
            return false;
        } else {
            showSuccess(input, errorElement);
            return true;
        }
    }
    
    function validateDuration(input) {
        const value = parseInt(input.value);
        const errorElement = document.getElementById('duration_error');
        
        if (isNaN(value)) {
            showError(input, errorElement, 'Duration is required.');
            return false;
        } else if (value < 15) {
            showError(input, errorElement, 'Duration must be at least 15 minutes.');
            return false;
        } else if (value > 480) {
            showError(input, errorElement, 'Duration cannot exceed 480 minutes (8 hours).');
            return false;
        } else if (value % 15 !== 0) {
            showError(input, errorElement, 'Duration must be in 15-minute increments (15, 30, 45, 60, etc.).');
            return false;
        } else {
            showSuccess(input, errorElement);
            return true;
        }
    }
    
    function showError(input, errorElement, message) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
        errorElement.textContent = message;
    }
    
    function showSuccess(input, errorElement) {
        input.classList.remove('is-invalid');
        input.classList.add('is-valid');
        errorElement.textContent = '';
    }
    
    function updateCharacterCount(input, countElementId, maxLength) {
        const countElement = document.getElementById(countElementId);
        if (!countElement) return;
        
        const currentLength = input.value.length;
        countElement.textContent = `${currentLength}/${maxLength} characters`;
        
        countElement.classList.remove('warning', 'error');
        if (currentLength > maxLength * 0.8) {
            countElement.classList.add('warning');
        }
        if (currentLength > maxLength) {
            countElement.classList.add('error');
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
    
    function validateEventForm() {
        const titleValid = validateTitle(document.getElementById('title'));
        const descriptionValid = validateDescription(document.getElementById('description'));
        const dateValid = validateEventDate(document.getElementById('event_date'));
        const timeValid = validateEventTime(document.getElementById('event_time'));
        const venueValid = validateVenue(document.getElementById('venue'));
        const durationValid = validateDuration(document.getElementById('duration'));
        
        return titleValid && descriptionValid && dateValid && timeValid && venueValid && durationValid;
    }
    </script>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="teacher_dashboard.php">
                <i class="fas fa-calendar-alt me-2"></i>
                EventHub
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="teacher_dashboard.php">
                            <i class="fas fa-home me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my_registrations.php">
                            <i class="fas fa-ticket-alt me-1"></i>My Registrations
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="past_events.php">
                            <i class="fas fa-history me-1"></i>Past Events
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="all_events.php">
                            <i class="fas fa-calendar me-1"></i>All Events
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="teacher_events.php">
                            <i class="fas fa-user-tie me-1"></i>My Events
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="search.php">
                            <i class="fas fa-search me-1"></i>Search
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['first_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="dashboard-container">
        <!-- Welcome Header -->
        <div class="welcome-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h2>
                    <p>Manage your events and registrations from your dashboard</p>
                </div>
                <div class="header-actions">
                    <a href="teacher_events.php" class="btn btn-info">
                        <i class="fas fa-list me-2"></i>My Created Events
                    </a>
                    <a href="logout.php" class="btn btn-danger">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>

        <!-- Create Event Button -->
        <div class="mb-4">
            <button class="btn btn-success" onclick="toggleCreateEvent()">
                <i class="fas fa-plus me-2"></i>Create New Event
            </button>
        </div>

        <!-- Create Event Form -->
<div id="create-event-form" style="display:none;" class="mb-4">
    <div class="card">
        <div class="card-header">
            <i class="fas fa-plus-circle me-2"></i>Create New Event
        </div>
        <div class="card-body">
            <?php echo $create_msg; ?>
            <form method="POST" action="" onsubmit="return validateEventForm()">
                <input type="hidden" name="create_event" value="1">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="title" class="form-label">Event Title *</label>
                            <input type="text" class="form-control" id="title" name="title" 
                                   value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" 
                                   onblur="capitalizeFirstLetter(this); validateTitle(this)" 
                                   required placeholder="Enter event title">
                            <div class="error-message" id="title_error"></div>
                            <div class="character-count" id="title-count"></div>
                            <small class="form-text">3-100 characters. Only letters, numbers, spaces, and basic punctuation allowed.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="venue" class="form-label">Venue *</label>
                            <select class="form-select" id="venue" name="venue" required>
                                <option value="">Select Venue</option>
                                <option value="Seminar Hall" <?php echo (isset($_POST['venue']) && $_POST['venue'] === 'Seminar Hall') ? 'selected' : ''; ?>>Seminar Hall</option>
                                <option value="Yoga Hall" <?php echo (isset($_POST['venue']) && $_POST['venue'] === 'Yoga Hall') ? 'selected' : ''; ?>>Yoga Hall</option>
                                <option value="Main Theatre" <?php echo (isset($_POST['venue']) && $_POST['venue'] === 'Main Theatre') ? 'selected' : ''; ?>>Main Theatre</option>
                                <option value="Mini Theatre" <?php echo (isset($_POST['venue']) && $_POST['venue'] === 'Mini Theatre') ? 'selected' : ''; ?>>Mini Theatre</option>
                                <option value="Open Auditorium" <?php echo (isset($_POST['venue']) && $_POST['venue'] === 'Open Auditorium') ? 'selected' : ''; ?>>Open Auditorium</option>
                                <option value="Room 1" <?php echo (isset($_POST['venue']) && $_POST['venue'] === 'Room 1') ? 'selected' : ''; ?>>Room 1</option>
                                <option value="Room 2" <?php echo (isset($_POST['venue']) && $_POST['venue'] === 'Room 2') ? 'selected' : ''; ?>>Room 2</option>
                                <option value="Room 3" <?php echo (isset($_POST['venue']) && $_POST['venue'] === 'Room 3') ? 'selected' : ''; ?>>Room 3</option>
                                <option value="Room 4" <?php echo (isset($_POST['venue']) && $_POST['venue'] === 'Room 4') ? 'selected' : ''; ?>>Room 4</option>
                                <option value="Room 5" <?php echo (isset($_POST['venue']) && $_POST['venue'] === 'Room 5') ? 'selected' : ''; ?>>Room 5</option>
                                <option value="Lab 1" <?php echo (isset($_POST['venue']) && $_POST['venue'] === 'Lab 1') ? 'selected' : ''; ?>>Lab 1</option>
                                <option value="Lab 2" <?php echo (isset($_POST['venue']) && $_POST['venue'] === 'Lab 2') ? 'selected' : ''; ?>>Lab 2</option>
                                <option value="Lab 3" <?php echo (isset($_POST['venue']) && $_POST['venue'] === 'Lab 3') ? 'selected' : ''; ?>>Lab 3</option>
                                <option value="Lab 4" <?php echo (isset($_POST['venue']) && $_POST['venue'] === 'Lab 4') ? 'selected' : ''; ?>>Lab 4</option>
                            </select>
                            <div class="error-message" id="venue_error"></div>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3" 
                              onblur="capitalizeFirstLetter(this); validateDescription(this)"
                              placeholder="Enter event description (optional)"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    <div class="error-message" id="description_error"></div>
                    <div class="character-count" id="description-count"></div>
                    <small class="form-text">Maximum 500 characters. Optional field.</small>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="event_date" class="form-label">Event Date *</label>
                            <input type="date" class="form-control" id="event_date" name="event_date" 
                                   value="<?php echo isset($_POST['event_date']) ? htmlspecialchars($_POST['event_date']) : ''; ?>" required>
                            <div class="error-message" id="event_date_error"></div>
                            <small class="form-text">Must be between today and 1 year from now.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="event_time" class="form-label">Event Time *</label>
                            <input type="time" class="form-control" id="event_time" name="event_time" 
                                   value="<?php echo isset($_POST['event_time']) ? htmlspecialchars($_POST['event_time']) : ''; ?>" required>
                            <div class="error-message" id="event_time_error"></div>
                            <small class="form-text">Must be between 8:00 AM and 10:00 PM.</small>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="duration" class="form-label">Duration (minutes) *</label>
                    <input type="number" class="form-control" id="duration" name="duration" 
                           min="15" max="480" step="15" 
                           value="<?php echo isset($_POST['duration']) ? htmlspecialchars($_POST['duration']) : '60'; ?>" required>
                    <div class="error-message" id="duration_error"></div>
                    <small class="form-text">15-480 minutes (8 hours) in 15-minute increments.</small>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Create Event
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="toggleCreateEvent()">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

        <!-- Main Content Row -->
        <div class="row">
            <!-- Upcoming Events -->
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-calendar-plus me-2"></i>Upcoming Events
                    </div>
                    <div class="card-body">
                        <?php echo $register_msg; ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Event Title</th>
                                        <th>Date</th>
                                        <th>Venue</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($events->num_rows > 0): ?>
                                        <?php while ($event = $events->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($event['title']); ?></strong>
                                                    <?php if (!empty($event['description'])): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($event['description'], 0, 60)); ?><?php echo strlen($event['description']) > 60 ? '...' : ''; ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                                                    <?php if (!empty($event['event_time'])): ?>
                                                        <br><small class="text-muted"><?php echo date('h:i A', strtotime($event['event_time'])); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($event['venue'] ?: 'TBA'); ?></td>
                                                <td>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="register_event_id" value="<?php echo $event['event_id']; ?>">
                                                        <button type="submit" class="btn btn-primary btn-sm">
                                                            <i class="fas fa-user-plus me-1"></i>Register
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="empty-state">
                                                <i class="fas fa-calendar-times"></i>
                                                <p>No upcoming events found</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- My Registrations -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-ticket-alt me-2"></i>My Registrations
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Event</th>
                                        <th>Date</th>
                                        <th>Venue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($registrations->num_rows > 0): ?>
                                        <?php while ($reg = $registrations->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($reg['title']); ?></strong>
                                                </td>
                                                <td>
                                                    <small><?php echo date('M d, Y', strtotime($reg['event_date'])); ?></small>
                                                </td>
                                                <td>
                                                    <small><?php echo htmlspecialchars($reg['venue'] ?: 'TBA'); ?></small>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="empty-state">
                                                <i class="fas fa-inbox"></i>
                                                <p>No registrations yet</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>