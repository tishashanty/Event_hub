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
if (isset($_POST['report_type'])) {
    $type = $_POST['report_type'];
    $filename = $type . '_report_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    if ($type === 'events') {
        fputcsv($output, ['Event ID', 'Title', 'Description', 'Date', 'Time', 'Venue', 'Created By Type', 'Created By ID']);
        $res = $conn->query("SELECT * FROM events");
        while ($row = $res->fetch_assoc()) {
            fputcsv($output, [$row['event_id'], $row['title'], $row['description'], $row['event_date'], $row['event_time'], $row['venue'], $row['created_by_user_type'], $row['created_by_user_id']]);
        }
    } elseif ($type === 'users') {
        fputcsv($output, ['User Type', 'ID', 'First Name', 'Last Name', 'Email', 'Phone/Designation', 'Department', 'Year/Roll No']);
        $students = $conn->query("SELECT * FROM students");
        while ($row = $students->fetch_assoc()) {
            $names = explode(' ', $row['full_name'], 2);
            $first_name = $names[0];
            $last_name = isset($names[1]) ? $names[1] : '';
            fputcsv($output, ['Student', $row['student_id'], $first_name, $last_name, $row['email'], $row['phone_number'], $row['department'], $row['year'] . '/' . $row['roll_no']]);
        }
        $teachers = $conn->query("SELECT * FROM teachers");
        while ($row = $teachers->fetch_assoc()) {
            $names = explode(' ', $row['full_name'], 2);
            $first_name = $names[0];
            $last_name = isset($names[1]) ? $names[1] : '';
            fputcsv($output, ['Teacher', $row['teacher_id'], $first_name, $last_name, $row['email'], $row['designation'], $row['department'], '']);
        }
        $admins = $conn->query("SELECT * FROM admin");
        while ($row = $admins->fetch_assoc()) {
            $names = explode(' ', $row['full_name'], 2);
            $first_name = $names[0];
            $last_name = isset($names[1]) ? $names[1] : '';
            fputcsv($output, ['Admin', $row['admin_id'], $first_name, $last_name, $row['email'], '', '', '']);
        }
    } elseif ($type === 'bookings') {
        fputcsv($output, ['Booking ID', 'Event', 'User Type', 'User ID', 'Registration Date']);
        $res = $conn->query("SELECT r.registration_id, e.title, r.user_type, r.user_id, r.registration_date FROM registrations r JOIN events e ON r.event_id = e.event_id");
        while ($row = $res->fetch_assoc()) {
            fputcsv($output, [$row['registration_id'], $row['title'], $row['user_type'], $row['user_id'], $row['registration_date']]);
        }
    } elseif ($type === 'combined') {
        fputcsv($output, ['Event ID', 'Event Title', 'Event Date', 'Event Venue', 'Participant Type', 'Participant Name', 'Participant Email', 'Participant Department', 'Registration Date', 'Feedback Rating', 'Feedback Comments', 'Feedback Date']);
        
        // Get all events with their participants and feedback
        $events = $conn->query("SELECT e.event_id, e.title, e.event_date, e.venue FROM events e ORDER BY e.event_date DESC");
        
        while ($event = $events->fetch_assoc()) {
            // Get participants for this event
            $participants = $conn->query("
                SELECT r.user_type, r.user_id, r.registration_date,
                       CASE 
                           WHEN r.user_type = 'student' THEN CONCAT(s.first_name, ' ', s.last_name)
                           WHEN r.user_type = 'teacher' THEN CONCAT(t.first_name, ' ', t.last_name)
                           ELSE 'Unknown'
                       END as participant_name,
                       CASE 
                           WHEN r.user_type = 'student' THEN s.email
                           WHEN r.user_type = 'teacher' THEN t.email
                           ELSE ''
                       END as participant_email,
                       CASE 
                           WHEN r.user_type = 'student' THEN s.department
                           WHEN r.user_type = 'teacher' THEN t.department
                           ELSE ''
                       END as participant_department
                FROM registrations r
                LEFT JOIN students s ON r.user_type = 'student' AND r.user_id = s.student_id
                LEFT JOIN teachers t ON r.user_type = 'teacher' AND r.user_id = t.teacher_id
                WHERE r.event_id = {$event['event_id']}
            ");
            
            if ($participants->num_rows > 0) {
                while ($participant = $participants->fetch_assoc()) {
                    // Get feedback for this participant and event
                    $feedback = $conn->query("SELECT rating, comments, feedback_date FROM feedback WHERE event_id = {$event['event_id']} AND user_type = '{$participant['user_type']}' AND user_id = {$participant['user_id']}");
                    $feedbackData = $feedback->fetch_assoc();
                    
                    fputcsv($output, [
                        $event['event_id'],
                        $event['title'],
                        $event['event_date'],
                        $event['venue'],
                        $participant['user_type'],
                        $participant['participant_name'],
                        $participant['participant_email'],
                        $participant['participant_department'],
                        $participant['registration_date'],
                        $feedbackData ? $feedbackData['rating'] : '',
                        $feedbackData ? $feedbackData['comments'] : '',
                        $feedbackData ? $feedbackData['feedback_date'] : ''
                    ]);
                }
            } else {
                // Event with no participants
                fputcsv($output, [
                    $event['event_id'],
                    $event['title'],
                    $event['event_date'],
                    $event['venue'],
                    '',
                    'No participants',
                    '',
                    '',
                    '',
                    '',
                    '',
                    ''
                ]);
            }
        }
    }
    fclose($output);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Report - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Generate Report</h2>
            <a href="admin_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
        <div class="card">
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="report_type" class="form-label">Select Report Type</label>
                        <select class="form-select" id="report_type" name="report_type" required>
                            <option value="events">Events</option>
                            <option value="users">Users</option>
                            <option value="bookings">Bookings</option>
                            <option value="combined">Combined Report (Participants + Feedback)</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Download CSV</button>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 