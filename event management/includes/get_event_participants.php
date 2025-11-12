<?php
// Function to get all participants for an event
function getEventParticipants($conn, $eventId) {
    $participants = [];
    
    // Get student participants
    $studentQuery = "SELECT s.email, CONCAT(s.first_name, ' ', s.last_name) as name 
                     FROM registrations r 
                     JOIN students s ON r.user_id = s.student_id 
                     WHERE r.event_id = ? AND r.user_type = 'student'";
    
    $stmt = $conn->prepare($studentQuery);
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $participants[$row['email']] = $row['name'];
    }
    $stmt->close();
    
    // Get teacher participants
    $teacherQuery = "SELECT t.email, CONCAT(t.first_name, ' ', t.last_name) as name 
                     FROM registrations r 
                     JOIN teachers t ON r.user_id = t.teacher_id 
                     WHERE r.event_id = ? AND r.user_type = 'teacher'";
    
    $stmt = $conn->prepare($teacherQuery);
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $participants[$row['email']] = $row['name'];
    }
    $stmt->close();
    
    return $participants;
}

// Function to get event details
function getEventDetails($conn, $eventId) {
    $query = "SELECT * FROM events WHERE event_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    $event = $result->fetch_assoc();
    $stmt->close();
    
    return $event;
}
?>
