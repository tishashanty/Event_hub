<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'event_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create feedback table if it doesn't exist
$createTableQuery = "CREATE TABLE IF NOT EXISTS feedback (
    feedback_id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    user_type ENUM('student', 'teacher') NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    feedback_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('unseen', 'seen') DEFAULT 'unseen',
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE
)";

$conn->query($createTableQuery);

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];
$message = '';
$messageType = '';

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $eventId = intval($_POST['event_id']);
    $rating = intval($_POST['rating']);
    $comment = trim($_POST['comment']);
    
    // Validation
    $errors = [];
    
    if ($rating < 1 || $rating > 5) {
        $errors[] = 'Rating must be between 1 and 5.';
    }
    
    if (empty($comment)) {
        $errors[] = 'Comment is required.';
    }
    
    if (strlen($comment) < 10) {
        $errors[] = 'Comment must be at least 10 characters long.';
    }
    
    if (strlen($comment) > 500) {
        $errors[] = 'Comment must not exceed 500 characters.';
    }
    
    // Check if user is registered for this event
    $checkRegistration = $conn->query("SELECT 1 FROM registrations WHERE event_id = $eventId AND user_type = '".$conn->real_escape_string($userRole)."' AND user_id = $userId");
    if (!$checkRegistration || $checkRegistration->num_rows === 0) {
        $errors[] = 'You can only provide feedback for events you are registered for.';
    }
    
    // Check if feedback already exists
    $checkFeedback = $conn->query("SELECT 1 FROM feedback WHERE event_id = $eventId AND user_type = '".$conn->real_escape_string($userRole)."' AND user_id = $userId");
    if ($checkFeedback && $checkFeedback->num_rows > 0) {
        $errors[] = 'You have already provided feedback for this event.';
    }
    
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO feedback (event_id, user_id, user_type, rating, comment) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('iisis', $eventId, $userId, $userRole, $rating, $comment);
        
        if ($stmt->execute()) {
            $message = 'Feedback submitted successfully! Thank you for your input.';
            $messageType = 'success';
        } else {
            $message = 'Failed to submit feedback. Please try again.';
            $messageType = 'danger';
        }
        $stmt->close();
    } else {
        $message = implode('<br>', $errors);
        $messageType = 'danger';
    }
}

// Get user's registered events for feedback
$registeredEventsQuery = "SELECT DISTINCT e.* FROM events e 
                         JOIN registrations r ON e.event_id = r.event_id 
                         WHERE r.user_type = '".$conn->real_escape_string($userRole)."' AND r.user_id = $userId
                         AND e.event_date < CURDATE()
                         AND e.event_id NOT IN (
                             SELECT event_id FROM feedback 
                             WHERE user_type = '".$conn->real_escape_string($userRole)."' AND user_id = $userId
                         )
                         ORDER BY e.event_date DESC";

$registeredEvents = $conn->query($registeredEventsQuery);

// Get user's submitted feedback
$userFeedbackQuery = "SELECT f.*, e.title, e.event_date, e.venue 
                     FROM feedback f 
                     JOIN events e ON f.event_id = e.event_id 
                     WHERE f.user_type = '".$conn->real_escape_string($userRole)."' AND f.user_id = $userId
                     ORDER BY f.feedback_date DESC";

$userFeedback = $conn->query($userFeedbackQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback System</title>
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

        .navbar {
            background: rgba(30, 41, 59, 0.95) !important;
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .container-narrow {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            margin-bottom: 30px;
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

        .rating-stars {
            display: flex;
            gap: 5px;
            margin: 10px 0;
        }

        .star {
            font-size: 2rem;
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .star:hover,
        .star.active {
            color: #ffc107;
        }

        .star.selected {
            color: #ffc107;
        }

        .feedback-item {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
            transition: all 0.2s ease;
        }

        .feedback-item:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transform: translateX(5px);
        }

        .feedback-item.unseen {
            border-left-color: #dc3545;
            background: #fff5f5;
        }

        .feedback-item.seen {
            border-left-color: #28a745;
            background: #f8fff8;
        }

        .event-title {
            font-weight: 600;
            font-size: 1.1rem;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .feedback-meta {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        .feedback-comment {
            color: #374151;
            margin-bottom: 10px;
        }

        .rating-display {
            display: inline-flex;
            gap: 2px;
        }

        .rating-display .star {
            font-size: 1rem;
            cursor: default;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-unseen {
            background-color: #fef2f2;
            color: #dc2626;
        }

        .status-seen {
            background-color: #f0fdf4;
            color: #16a34a;
        }

        .form-control, .form-select {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 10px 12px;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .form-control:focus, .form-select:focus {
            border-color: #1e293b;
            box-shadow: 0 0 0 3px rgba(30, 41, 59, 0.1);
            outline: none;
        }

        .btn {
            font-weight: 500;
            border-radius: 6px;
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
            transform: translateY(-1px);
        }

        .btn-secondary {
            background-color: #64748b;
            border-color: #64748b;
        }

        .btn-secondary:hover {
            background-color: #475569;
            transform: translateY(-1px);
        }

        .btn-success {
            background-color: #198754;
            border-color: #198754;
        }

        .btn-success:hover {
            background-color: #157347;
            transform: translateY(-1px);
        }

        .btn-info {
            background-color: #0dcaf0;
            border-color: #0dcaf0;
        }

        .btn-info:hover {
            background-color: #0aa2c0;
            transform: translateY(-1px);
        }

        .character-count {
            font-size: 0.8rem;
            color: #64748b;
            text-align: right;
            margin-top: 5px;
        }

        .character-count.warning {
            color: #f59e0b;
        }

        .character-count.danger {
            color: #dc2626;
        }

        @media print {
            .no-print {
                display: none !important;
            }
            
            .card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
            
            .feedback-item {
                break-inside: avoid;
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm no-print">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?php echo $userRole === 'admin' ? 'admin_dashboard.php' : ($userRole === 'student' ? 'student_dashboard.php' : 'teacher_dashboard.php'); ?>">
                <i class="fas fa-calendar-alt me-2"></i>
                EventHub
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $userRole === 'admin' ? 'admin_dashboard.php' : ($userRole === 'student' ? 'student_dashboard.php' : 'teacher_dashboard.php'); ?>">
                            <i class="fas fa-home me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="feedback_system.php">
                            <i class="fas fa-comment-dots me-1"></i>Feedback
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="all_events.php">
                            <i class="fas fa-calendar me-1"></i>All Events
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['first_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-narrow">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'times-circle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Submit Feedback Section -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-plus-circle me-2"></i>Submit Feedback</h5>
            </div>
            <div class="card-body">
                <?php if ($registeredEvents->num_rows > 0): ?>
                    <form method="POST" id="feedbackForm">
                        <input type="hidden" name="submit_feedback" value="1">
                        
                        <div class="mb-3">
                            <label for="event_id" class="form-label">Select Event</label>
                            <select class="form-select" id="event_id" name="event_id" required>
                                <option value="">Choose an event...</option>
                                <?php $registeredEvents->data_seek(0); ?>
                                <?php while ($event = $registeredEvents->fetch_assoc()): ?>
                                    <option value="<?php echo $event['event_id']; ?>">
                                        <?php echo htmlspecialchars($event['title']); ?> - 
                                        <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Rating</label>
                            <div class="rating-stars" id="ratingStars">
                                <span class="star" data-rating="1">★</span>
                                <span class="star" data-rating="2">★</span>
                                <span class="star" data-rating="3">★</span>
                                <span class="star" data-rating="4">★</span>
                                <span class="star" data-rating="5">★</span>
                            </div>
                            <input type="hidden" name="rating" id="ratingInput" required>
                            <div class="invalid-feedback">Please select a rating.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="comment" class="form-label">Comment</label>
                            <textarea class="form-control" id="comment" name="comment" rows="4" 
                                      placeholder="Please share your experience and suggestions (10-500 characters)" 
                                      required minlength="10" maxlength="500"></textarea>
                            <div class="character-count" id="characterCount">0/500 characters</div>
                            <div class="invalid-feedback">Comment must be between 10 and 500 characters.</div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Submit Feedback
                        </button>
                    </form>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-info-circle text-info" style="font-size: 3rem; margin-bottom: 20px;"></i>
                        <h5>No Events Available for Feedback</h5>
                        <p class="text-muted">You can only provide feedback for past events you were registered for.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- My Feedback History -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-history me-2"></i>My Feedback History</h5>
                <button class="btn btn-info btn-sm no-print" onclick="window.print()">
                    <i class="fas fa-print me-1"></i>Print
                </button>
            </div>
            <div class="card-body">
                <?php if ($userFeedback->num_rows > 0): ?>
                    <?php $userFeedback->data_seek(0); ?>
                    <?php while ($feedback = $userFeedback->fetch_assoc()): ?>
                        <div class="feedback-item <?php echo $feedback['status']; ?>">
                            <div class="event-title">
                                <?php echo htmlspecialchars($feedback['title']); ?>
                                <span class="status-badge status-<?php echo $feedback['status']; ?>">
                                    <i class="fas fa-<?php echo $feedback['status'] === 'seen' ? 'eye' : 'eye-slash'; ?>"></i>
                                    <?php echo ucfirst($feedback['status']); ?>
                                </span>
                            </div>
                            <div class="feedback-meta">
                                <i class="fas fa-calendar me-1"></i><?php echo date('M d, Y', strtotime($feedback['event_date'])); ?>
                                <i class="fas fa-map-marker-alt me-1 ms-3"></i><?php echo htmlspecialchars($feedback['venue']); ?>
                                <i class="fas fa-clock me-1 ms-3"></i><?php echo date('M d, Y H:i', strtotime($feedback['feedback_date'])); ?>
                            </div>
                            <div class="rating-display mb-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <span class="star <?php echo $i <= $feedback['rating'] ? 'selected' : ''; ?>">★</span>
                                <?php endfor; ?>
                                <span class="ms-2 text-muted">(<?php echo $feedback['rating']; ?>/5)</span>
                            </div>
                            <div class="feedback-comment">
                                <?php echo nl2br(htmlspecialchars($feedback['comment'])); ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-comment-slash text-muted" style="font-size: 3rem; margin-bottom: 20px;"></i>
                        <h5>No Feedback Submitted Yet</h5>
                        <p class="text-muted">Your feedback history will appear here once you submit feedback for events.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Star rating functionality
        const stars = document.querySelectorAll('.star[data-rating]');
        const ratingInput = document.getElementById('ratingInput');
        
        stars.forEach(star => {
            star.addEventListener('click', function() {
                const rating = parseInt(this.dataset.rating);
                ratingInput.value = rating;
                
                stars.forEach((s, index) => {
                    if (index < rating) {
                        s.classList.add('selected');
                    } else {
                        s.classList.remove('selected');
                    }
                });
            });
            
            star.addEventListener('mouseenter', function() {
                const rating = parseInt(this.dataset.rating);
                stars.forEach((s, index) => {
                    if (index < rating) {
                        s.classList.add('active');
                    } else {
                        s.classList.remove('active');
                    }
                });
            });
        });
        
        document.getElementById('ratingStars').addEventListener('mouseleave', function() {
            stars.forEach(star => {
                star.classList.remove('active');
            });
        });
        
        // Character count for comment
        const commentTextarea = document.getElementById('comment');
        const characterCount = document.getElementById('characterCount');
        
        commentTextarea.addEventListener('input', function() {
            const length = this.value.length;
            characterCount.textContent = `${length}/500 characters`;
            
            if (length < 10) {
                characterCount.className = 'character-count danger';
            } else if (length > 450) {
                characterCount.className = 'character-count warning';
            } else {
                characterCount.className = 'character-count';
            }
        });
        
        // Form validation
        document.getElementById('feedbackForm').addEventListener('submit', function(e) {
            const rating = ratingInput.value;
            const comment = commentTextarea.value.trim();
            
            if (!rating) {
                e.preventDefault();
                alert('Please select a rating.');
                return;
            }
            
            if (comment.length < 10) {
                e.preventDefault();
                alert('Comment must be at least 10 characters long.');
                return;
            }
            
            if (comment.length > 500) {
                e.preventDefault();
                alert('Comment must not exceed 500 characters.');
                return;
            }
        });
    </script>
</body>
</html>
