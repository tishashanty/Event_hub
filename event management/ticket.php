<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit();
}
$conn = new mysqli('localhost', 'root', '', 'event_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$student_id = $_SESSION['user_id'];
$registration_id = isset($_GET['registration_id']) ? intval($_GET['registration_id']) : 0;
$ticket = $conn->query("SELECT r.*, e.title, e.event_date, e.event_time, e.venue, s.first_name, s.last_name FROM registrations r JOIN events e ON r.event_id = e.event_id JOIN students s ON r.user_id = s.student_id WHERE r.registration_id = $registration_id AND r.user_type = 'student' AND r.user_id = $student_id")->fetch_assoc();
if (!$ticket) {
    echo '<div class="alert alert-danger">Invalid ticket or access denied.</div>';
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Ticket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .ticket-card { 
            max-width: 500px; 
            margin: 40px auto; 
            border: none;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            position: relative;
        }
        
        .ticket-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="%23667eea" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="%23764ba2" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="%23667eea" opacity="0.05"/><circle cx="90" cy="40" r="0.5" fill="%23764ba2" opacity="0.05"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            pointer-events: none;
        }
        
        .ticket-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .ticket-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: shimmer 3s ease-in-out infinite;
        }
        
        @keyframes shimmer {
            0%, 100% { transform: translateX(-100%) translateY(-100%) rotate(30deg); }
            50% { transform: translateX(100%) translateY(100%) rotate(30deg); }
        }
        
        .ticket-header h3 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 700;
            position: relative;
            z-index: 1;
        }
        
        .ticket-body {
            padding: 30px;
            position: relative;
            z-index: 1;
        }
        
        .event-info {
            background: rgba(102, 126, 234, 0.05);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        
        .event-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 15px;
        }
        
        .event-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .detail-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.1rem;
        }
        
        .detail-text {
            flex: 1;
        }
        
        .detail-label {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 500;
            margin-bottom: 2px;
        }
        
        .detail-value {
            font-size: 0.95rem;
            color: #1e293b;
            font-weight: 600;
        }
        
        .student-info {
            background: rgba(118, 75, 162, 0.05);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #764ba2;
        }
        
        .student-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 10px;
        }
        
        .ticket-code {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            font-size: 1.2rem;
            font-weight: bold;
            letter-spacing: 3px;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }
        
        .ticket-code::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            animation: scan 2s ease-in-out infinite;
        }
        
        @keyframes scan {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        .ticket-footer {
            text-align: center;
            padding: 20px;
            background: rgba(248, 250, 252, 0.5);
            border-top: 1px solid rgba(226, 232, 240, 0.5);
        }
        
        .btn-print {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .qr-placeholder {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin: 0 auto 20px;
        }
        
        @media print {
            body {
                background: white !important;
                padding: 0 !important;
            }
            
            .ticket-card {
                box-shadow: none !important;
                margin: 0 !important;
                max-width: none !important;
            }
            
            .btn-print {
                display: none !important;
            }
            
            .ticket-header::before,
            .ticket-code::before {
                display: none !important;
            }
        }
        
        @media (max-width: 576px) {
            .event-details {
                grid-template-columns: 1fr;
            }
            
            .ticket-body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="ticket-card">
        <div class="ticket-header">
            <h3><i class="fas fa-ticket-alt me-2"></i>Event Ticket</h3>
        </div>
        <div class="ticket-body">
            <div class="event-info">
                <div class="event-title"><?php echo htmlspecialchars($ticket['title']); ?></div>
                <div class="event-details">
                    <div class="detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-calendar"></i>
                        </div>
                        <div class="detail-text">
                            <div class="detail-label">Date</div>
                            <div class="detail-value"><?php echo date('M d, Y', strtotime($ticket['event_date'])); ?></div>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="detail-text">
                            <div class="detail-label">Time</div>
                            <div class="detail-value"><?php echo htmlspecialchars($ticket['event_time']); ?></div>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="detail-text">
                            <div class="detail-label">Venue</div>
                            <div class="detail-value"><?php echo htmlspecialchars($ticket['venue']); ?></div>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="detail-text">
                            <div class="detail-label">Student</div>
                            <div class="detail-value"><?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="qr-placeholder">
                <i class="fas fa-qrcode"></i>
            </div>
            
            <div class="ticket-code">
                <?php echo str_pad($ticket['registration_id'], 6, '0', STR_PAD_LEFT); ?>
            </div>
        </div>
        <div class="ticket-footer">
            <button class="btn-print" onclick="window.print()">
                <i class="fas fa-print me-2"></i>Print Ticket
            </button>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 