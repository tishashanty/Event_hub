<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Submitted - Event Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #334155;
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .confirmation-container {
            max-width: 500px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .card-body {
            padding: 40px;
            text-align: center;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            color: white;
            font-size: 2rem;
        }

        .card-title {
            color: #1e293b;
            font-weight: 700;
            margin-bottom: 16px;
            font-size: 1.5rem;
        }

        .card-text {
            color: #64748b;
            margin-bottom: 32px;
            font-size: 1rem;
        }

        .btn-primary {
            background-color: #1e293b;
            border: 1px solid #1e293b;
            border-radius: 8px;
            padding: 12px 24px;
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: none;
            transition: all 0.15s ease-in-out;
        }

        .btn-primary:hover {
            background-color: #0f172a;
            border-color: #0f172a;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background-color: #64748b;
            border: 1px solid #64748b;
            border-radius: 8px;
            padding: 12px 24px;
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: none;
            transition: all 0.15s ease-in-out;
        }

        .btn-secondary:hover {
            background-color: #475569;
            border-color: #475569;
            transform: translateY(-1px);
        }

        .d-flex {
            display: flex;
        }

        .gap-2 {
            gap: 0.5rem;
        }

        .w-100 {
            width: 100%;
        }

        /* Responsive design */
        @media (max-width: 576px) {
            .confirmation-container {
                padding: 0 15px;
            }
            
            .card-body {
                padding: 32px 24px;
            }

            .d-flex {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="confirmation-container">
        <div class="card">
            <div class="card-body">
                <div class="success-icon">
                    <i class="fas fa-check"></i>
                </div>
                <h3 class="card-title">Request Submitted Successfully!</h3>
                <p class="card-text">
                    Your program request has been submitted to the admin for review. 
                    You will be notified once the admin responds to your request.
                </p>
                <div class="d-flex gap-2">
                    <a href="student_dashboard.php?view=request_program" class="btn btn-primary">
                        <i class="fas fa-eye me-2"></i>View My Requests
                    </a>
                    <a href="student_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-home me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
