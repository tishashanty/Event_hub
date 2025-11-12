
 <?php
$conn = new mysqli('localhost', 'root', '', 'event_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_GET['event_id']) && is_numeric($_GET['event_id'])) {
    $event_id = intval($_GET['event_id']);

    // Fetch participants (names only)
    $result = $conn->query("SELECT 
        CASE WHEN r.user_type = 'student' THEN s.first_name 
             WHEN r.user_type = 'teacher' THEN t.first_name 
             ELSE '' END as first_name,
        CASE WHEN r.user_type = 'student' THEN s.last_name 
             WHEN r.user_type = 'teacher' THEN t.last_name 
             ELSE '' END as last_name
        FROM registrations r
        LEFT JOIN students s ON r.user_type = 'student' AND r.user_id = s.student_id
        LEFT JOIN teachers t ON r.user_type = 'teacher' AND r.user_id = t.teacher_id
        WHERE r.event_id = $event_id
        ORDER BY r.registration_date ASC");

    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="participants_event_'.$event_id.'.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['First Name', 'Last Name']); // Header row

    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [$row['first_name'], $row['last_name']]);
    }

    fclose($output);
    exit;
} else {
    echo "Invalid Event ID.";
}
