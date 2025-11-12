<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'teacher'])) {
    header('Location: login.php');
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'event_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Include TCPDF library
require_once('tcpdf/tcpdf.php');

if (isset($_GET['event_id']) && is_numeric($_GET['event_id'])) {
    $event_id = intval($_GET['event_id']);

    // Get event details
    $eventQuery = "SELECT * FROM events WHERE event_id = $event_id";
    $eventResult = $conn->query($eventQuery);
    
    if ($eventResult && $eventResult->num_rows > 0) {
        $event = $eventResult->fetch_assoc();
        
        // Get participants with detailed information
        $participantsQuery = "SELECT 
            CASE WHEN r.user_type = 'student' THEN s.first_name 
                 WHEN r.user_type = 'teacher' THEN t.first_name 
                 ELSE '' END as first_name,
            CASE WHEN r.user_type = 'student' THEN s.last_name 
                 WHEN r.user_type = 'teacher' THEN t.last_name 
                 ELSE '' END as last_name,
            CASE WHEN r.user_type = 'student' THEN s.email 
                 WHEN r.user_type = 'teacher' THEN t.email 
                 ELSE '' END as email,
            CASE WHEN r.user_type = 'student' THEN s.phone_number 
                 WHEN r.user_type = 'teacher' THEN t.phone_number 
                 ELSE '' END as phone_number,
            CASE WHEN r.user_type = 'student' THEN s.department 
                 WHEN r.user_type = 'teacher' THEN t.department 
                 ELSE '' END as department,
            CASE WHEN r.user_type = 'student' THEN s.year 
                 WHEN r.user_type = 'teacher' THEN t.designation 
                 ELSE '' END as additional_info,
            r.user_type,
            r.registration_date
            FROM registrations r
            LEFT JOIN students s ON r.user_type = 'student' AND r.user_id = s.student_id
            LEFT JOIN teachers t ON r.user_type = 'teacher' AND r.user_id = t.teacher_id
            WHERE r.event_id = $event_id
            ORDER BY r.registration_date ASC";

        $participantsResult = $conn->query($participantsQuery);
        $participants = [];
        
        if ($participantsResult) {
            while ($row = $participantsResult->fetch_assoc()) {
                $participants[] = $row;
            }
        }

        // Create PDF
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('Event Management System');
        $pdf->SetAuthor('EventHub');
        $pdf->SetTitle('Participant List - ' . $event['title']);
        $pdf->SetSubject('Event Participants');
        
        // Set margins
        $pdf->SetMargins(15, 20, 15);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(10);
        
        // Add a page
        $pdf->AddPage();
        
        // Set font
        $pdf->SetFont('helvetica', 'B', 16);
        
        // Event title
        $pdf->Cell(0, 10, 'EVENT PARTICIPANT LIST', 0, 1, 'C');
        $pdf->Ln(5);
        
        // Event details
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, $event['title'], 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        
        $pdf->Cell(0, 6, 'Date: ' . date('M d, Y', strtotime($event['event_date'])), 0, 1, 'C');
        $pdf->Cell(0, 6, 'Time: ' . $event['event_time'], 0, 1, 'C');
        $pdf->Cell(0, 6, 'Venue: ' . $event['venue'], 0, 1, 'C');
        
        if (!empty($event['description'])) {
            $pdf->Ln(3);
            $pdf->Cell(0, 6, 'Description: ' . $event['description'], 0, 1, 'C');
        }
        
        $pdf->Ln(10);
        
        // Participant count
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 8, 'Total Participants: ' . count($participants), 0, 1, 'C');
        $pdf->Ln(5);
        
        // Table header
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(240, 240, 240);
        
        $pdf->Cell(8, 8, 'S.No', 1, 0, 'C', true);
        $pdf->Cell(35, 8, 'Name', 1, 0, 'C', true);
        $pdf->Cell(45, 8, 'Email', 1, 0, 'C', true);
        $pdf->Cell(25, 8, 'Phone', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'Department', 1, 0, 'C', true);
        $pdf->Cell(25, 8, 'Type/Year', 1, 0, 'C', true);
        $pdf->Cell(22, 8, 'Registered', 1, 1, 'C', true);
        
        // Table data
        $pdf->SetFont('helvetica', '', 8);
        $counter = 1;
        
        foreach ($participants as $participant) {
            $pdf->Cell(8, 6, $counter, 1, 0, 'C');
            $pdf->Cell(35, 6, $participant['first_name'] . ' ' . $participant['last_name'], 1, 0, 'L');
            $pdf->Cell(45, 6, $participant['email'], 1, 0, 'L');
            $pdf->Cell(25, 6, $participant['phone_number'], 1, 0, 'C');
            $pdf->Cell(30, 6, $participant['department'], 1, 0, 'C');
            
            // Additional info based on user type
            $additionalInfo = '';
            if ($participant['user_type'] === 'student') {
                $additionalInfo = 'Year ' . $participant['additional_info'];
            } else {
                $additionalInfo = $participant['additional_info'];
            }
            $pdf->Cell(25, 6, $additionalInfo, 1, 0, 'C');
            $pdf->Cell(22, 6, date('M d', strtotime($participant['registration_date'])), 1, 1, 'C');
            
            $counter++;
        }
        
        // Summary section
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 8, 'SUMMARY', 0, 1, 'C');
        
        // Count by type
        $studentCount = 0;
        $teacherCount = 0;
        foreach ($participants as $participant) {
            if ($participant['user_type'] === 'student') {
                $studentCount++;
            } else {
                $teacherCount++;
            }
        }
        
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 6, 'Students: ' . $studentCount, 0, 1, 'C');
        $pdf->Cell(0, 6, 'Teachers: ' . $teacherCount, 0, 1, 'C');
        $pdf->Cell(0, 6, 'Total: ' . count($participants), 0, 1, 'C');
        
        // Footer
        $pdf->SetY(-20);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->Cell(0, 6, 'Generated on ' . date('M d, Y H:i:s') . ' by Event Management System', 0, 1, 'C');
        
        // Output PDF
        $filename = 'participants_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $event['title']) . '_' . date('Y-m-d') . '.pdf';
        $pdf->Output($filename, 'D'); // 'D' for download
        
    } else {
        echo "Event not found.";
    }
} else {
    echo "Invalid Event ID.";
}

$conn->close();
?>
