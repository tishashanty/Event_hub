<?php
session_start();
// Check if user is logged in and is student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'event_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $program_title = trim($_POST['program_title']);
    $program_description = trim($_POST['program_description']);
    $program_date = $_POST['program_date'];
    $program_time = $_POST['program_time'];
    $program_venue = trim($_POST['program_venue']);
    $requirements = trim($_POST['requirements']);
    $student_id = $_SESSION['user_id'];
    
    // Validate required fields
    if (empty($program_title) || empty($program_description) || empty($program_date) || 
        empty($program_time) || empty($program_venue)) {
        die("All required fields must be filled.");
    }
    
    // Validate date (must be today or future)
    $today = date('Y-m-d');
    if ($program_date < $today) {
        die("Unable to submit this request because the date given is in the past.");
    }
    
    // Insert into database
    $stmt = $conn->prepare("INSERT INTO event_requests (student_id, program_title, program_description, program_date, program_time, program_venue, requirements) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssss", $student_id, $program_title, $program_description, $program_date, $program_time, $program_venue, $requirements);
    
    if ($stmt->execute()) {
        echo "Request submitted successfully!";
        // Redirect to confirmation page or back to form
        header('Location: request_confirmation.php');
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
    
    $stmt->close();
}

$conn->close();
?>