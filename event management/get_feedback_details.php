<?php
session_start();
// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'event_management');
if ($conn->connect_error) {
    header('HTTP/1.1 500 Internal Server Error');
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_GET['id'])) {
    $feedback_id = (int)$_GET['id'];
    
    // Get feedback details
    $query = "SELECT f.*, e.title as event_title, e.event_date, e.description as event_description,
              CASE 
                  WHEN f.user_type = 'student' THEN CONCAT(s.first_name, ' ', s.last_name)
                  WHEN f.user_type = 'teacher' THEN CONCAT(t.first_name, ' ', t.last_name)
                  WHEN f.user_type = 'admin' THEN CONCAT(a.first_name, ' ', a.last_name)
                  ELSE 'Unknown'
              END as user_name,
              CASE 
                  WHEN f.user_type = 'student' THEN s.email
                  WHEN f.user_type = 'teacher' THEN t.email
                  WHEN f.user_type = 'admin' THEN a.email
                  ELSE 'Unknown'
              END as user_email
              FROM feedback f 
              JOIN events e ON f.event_id = e.event_id 
              LEFT JOIN students s ON f.user_id = s.student_id AND f.user_type = 'student'
              LEFT JOIN teachers t ON f.user_id = t.teacher_id AND f.user_type = 'teacher'
              LEFT JOIN admin a ON f.user_id = a.admin_id AND f.user_type = 'admin'
              WHERE f.feedback_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $feedback_id);
    $stmt->execute();
    $feedback = $stmt->get_result()->fetch_assoc();
    
    if ($feedback) {
        // Generate star rating HTML
        $stars = '';
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $feedback['rating']) {
                $stars .= '<i class="fas fa-star star-rating"></i>';
            } else {
                $stars .= '<i class="far fa-star star-rating"></i>';
            }
        }
        
        echo '
        <div class="row">
            <div class="col-md-6">
                <h6>Event Information</h6>
                <p><strong>Title:</strong> ' . htmlspecialchars($feedback['event_title']) . '</p>
                <p><strong>Date:</strong> ' . date('F j, Y', strtotime($feedback['event_date'])) . '</p>
                <p><strong>Description:</strong> ' . nl2br(htmlspecialchars($feedback['event_description'])) . '</p>
            </div>
            <div class="col-md-6">
                <h6>User Information</h6>
                <p><strong>Name:</strong> ' . htmlspecialchars($feedback['user_name']) . '</p>
                <p><strong>Email:</strong> ' . htmlspecialchars($feedback['user_email']) . '</p>
                <p><strong>Type:</strong> <span class="badge bg-info">' . ucfirst(htmlspecialchars($feedback['user_type'])) . '</span></p>
            </div>
        </div>
        
        <hr>
        
        <div class="row mt-3">
            <div class="col-12">
                <h6>Feedback Details</h6>
                <p><strong>Rating:</strong> ' . $stars . ' (' . $feedback['rating'] . '/5)</p>
                <p><strong>Submitted on:</strong> ' . date('F j, Y, g:i a', strtotime($feedback['feedback_date'])) . '</p>
                <p><strong>Comments:</strong></p>
                <div class="border p-3 bg-light rounded">
                    ' . nl2br(htmlspecialchars($feedback['comments'])) . '
                </div>
            </div>
        </div>
        ';
    } else {
        echo '<div class="alert alert-danger">Feedback not found.</div>';
    }
} else {
    echo '<div class="alert alert-danger">Invalid request.</div>';
}
?>