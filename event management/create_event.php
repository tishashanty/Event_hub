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

// Ensure duration column exists
$conn->query("ALTER TABLE events ADD COLUMN IF NOT EXISTS duration INT DEFAULT 60");

// Handle event creation
$create_msg = '';
if (isset($_POST['create_event'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $event_date = $_POST['event_date'];
    $event_time = $_POST['event_time'];
    $venue = trim($_POST['venue']);
    $duration = intval($_POST['duration']);
    
    // Enhanced validation
    $errors = [];
    
    if (empty($title)) {
        $errors[] = 'Event title is required.';
    } elseif (strlen($title) < 3) {
        $errors[] = 'Event title must be at least 3 characters long.';
    } elseif (strlen($title) > 100) {
        $errors[] = 'Event title must not exceed 100 characters.';
    }
    
    if (empty($event_date)) {
        $errors[] = 'Event date is required.';
    } elseif (strtotime($event_date) < strtotime('today')) {
        $errors[] = 'Event date cannot be in the past.';
    }
    
    if (empty($event_time)) {
        $errors[] = 'Event time is required.';
    }
    
    if (empty($venue)) {
        $errors[] = 'Venue is required.';
    }
    
    if ($duration < 15) {
        $errors[] = 'Duration must be at least 15 minutes.';
    } elseif ($duration > 480) {
        $errors[] = 'Duration cannot exceed 8 hours (480 minutes).';
    }
    
    if (!empty($description) && strlen($description) > 500) {
        $errors[] = 'Description must not exceed 500 characters.';
    }
    
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
            $create_msg = '<div class=\'alert alert-danger\'>Error: This venue is already booked for another program that conflicts with the specified time and duration. Please choose a different venue, date, or time.</div>';
        } else {
            $stmt = $conn->prepare("INSERT INTO events (title, description, event_date, event_time, venue, duration, created_by_user_type, created_by_user_id) VALUES (?, ?, ?, ?, ?, ?, 'teacher', ?)");
            $stmt->bind_param('sssssii', $title, $description, $event_date, $event_time, $venue, $duration, $teacher_id);
            if ($stmt->execute()) {
                $create_msg = '<div class=\'alert alert-success\'>Event created successfully!</div>';
                // Redirect to teacher events page after successful creation
                header('Location: teacher_events.php?success=1');
                exit();
            } else {
                $create_msg = '<div class=\'alert alert-danger\'>Failed to create event.</div>';
            }
            $stmt->close();
        }
    } else {
        $create_msg = '<div class=\'alert alert-danger\'>' . implode('<br>', $errors) . '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Event - Teacher</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #334155;
            line-height: 1.6;
            min-height: 100vh;
        }

        .create-event-container {
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

        .mb-3 {
            margin-bottom: 1.25rem;
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

        .alert-warning {
            background-color: #fffbeb;
            border-color: #fde68a;
            color: #d97706;
        }

        .form-text {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 4px;
        }

        .character-count {
            font-size: 0.8rem;
            color: #64748b;
            text-align: right;
            margin-top: 5px;
        }

        .character-count.text-danger {
            color: #dc2626;
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
            .create-event-container {
                margin: 20px auto;
                padding: 0 15px;
            }
            
            .card-body {
                padding: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="create-event-container">
        <div class="card">
            <div class="card-header">
                <h3>Create New Event</h3>
            </div>
            <div class="card-body">
                <?php echo $create_msg; ?>
                <form method="POST" action="" id="eventForm">
                    <input type="hidden" name="create_event" value="1">
                    <div class="mb-3">
                        <label for="title" class="form-label">Event Title</label>
                        <input type="text" class="form-control" id="title" name="title" required placeholder="Enter event title" minlength="3" maxlength="100">
                        <div class="invalid-feedback">Title must be between 3 and 100 characters.</div>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Enter event description" maxlength="500"></textarea>
                        <div class="character-count" id="descriptionCount">0/500 characters</div>
                        <div class="invalid-feedback">Description must not exceed 500 characters.</div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="event_date" class="form-label">Event Date</label>
                                <input type="date" class="form-control" id="event_date" name="event_date" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="event_time" class="form-label">Event Time</label>
                                <input type="time" class="form-control" id="event_time" name="event_time" required>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="venue" class="form-label">Venue</label>
                        <select class="form-select" id="venue" name="venue" required>
                            <option value="">Select Venue</option>
                            <option value="Seminar Hall">Seminar Hall</option>
                            <option value="Yoga Hall">Yoga Hall</option>
                            <option value="Main Theatre">Main Theatre</option>
                            <option value="Mini Theatre">Mini Theatre</option>
                            <option value="Open Auditorium">Open Auditorium</option>
                            <option value="Room 1">Room 1</option>
                            <option value="Room 2">Room 2</option>
                            <option value="Room 3">Room 3</option>
                            <option value="Room 4">Room 4</option>
                            <option value="Room 5">Room 5</option>
                            <option value="Lab 1">Lab 1</option>
                            <option value="Lab 2">Lab 2</option>
                            <option value="Lab 3">Lab 3</option>
                            <option value="Lab 4">Lab 4</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="duration" class="form-label">Duration (minutes)</label>
                        <input type="number" class="form-control" id="duration" name="duration" min="15" max="480" value="60" required>
                        <small class="form-text text-muted">Minimum 15 minutes, Maximum 8 hours (480 minutes)</small>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Create Event
                        </button>
                        <a href="teacher_events.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Events
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Character count for description
        const descriptionTextarea = document.getElementById('description');
        const descriptionCount = document.getElementById('descriptionCount');
        
        descriptionTextarea.addEventListener('input', function() {
            const length = this.value.length;
            descriptionCount.textContent = `${length}/500 characters`;
            
            if (length > 500) {
                descriptionCount.className = 'character-count text-danger';
                this.classList.add('is-invalid');
            } else {
                descriptionCount.className = 'character-count';
                this.classList.remove('is-invalid');
            }
        });
        
        // Form validation
        document.getElementById('eventForm').addEventListener('submit', function(e) {
            const title = document.getElementById('title').value.trim();
            const description = document.getElementById('description').value.trim();
            const eventDate = document.getElementById('event_date').value;
            const eventTime = document.getElementById('event_time').value;
            const venue = document.getElementById('venue').value;
            const duration = parseInt(document.getElementById('duration').value);
            
            let isValid = true;
            
            // Validate title
            if (title.length < 3 || title.length > 100) {
                document.getElementById('title').classList.add('is-invalid');
                isValid = false;
            } else {
                document.getElementById('title').classList.remove('is-invalid');
            }
            
            // Validate description
            if (description.length > 500) {
                document.getElementById('description').classList.add('is-invalid');
                isValid = false;
            } else {
                document.getElementById('description').classList.remove('is-invalid');
            }
            
            // Validate date
            if (!eventDate || new Date(eventDate) < new Date().setHours(0,0,0,0)) {
                document.getElementById('event_date').classList.add('is-invalid');
                isValid = false;
            } else {
                document.getElementById('event_date').classList.remove('is-invalid');
            }
            
            // Validate time
            if (!eventTime) {
                document.getElementById('event_time').classList.add('is-invalid');
                isValid = false;
            } else {
                document.getElementById('event_time').classList.remove('is-invalid');
            }
            
            // Validate venue
            if (!venue) {
                document.getElementById('venue').classList.add('is-invalid');
                isValid = false;
            } else {
                document.getElementById('venue').classList.remove('is-invalid');
            }
            
            // Validate duration
            if (duration < 15 || duration > 480) {
                document.getElementById('duration').classList.add('is-invalid');
                isValid = false;
            } else {
                document.getElementById('duration').classList.remove('is-invalid');
            }
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fix the validation errors before submitting.');
            }
        });
        
        // Real-time validation
        document.getElementById('title').addEventListener('input', function() {
            const length = this.value.length;
            if (length < 3 || length > 100) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });
        
        document.getElementById('event_date').addEventListener('change', function() {
            if (this.value && new Date(this.value) < new Date().setHours(0,0,0,0)) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });
        
        document.getElementById('duration').addEventListener('input', function() {
            const duration = parseInt(this.value);
            if (duration < 15 || duration > 480) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });
    </script>
</body>
</html>
