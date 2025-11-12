<?php
// Email configuration for PHPMailer
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';
require_once 'PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailNotification {
    private $mail;
    
    public function __construct() {
        $this->mail = new PHPMailer(true);
        
        // Server settings
        $this->mail->isSMTP();
        $this->mail->Host = 'smtp.gmail.com'; // Change this to your SMTP server
        $this->mail->SMTPAuth = true;
        $this->mail->Username = 'your-email@gmail.com'; // Change to your email
        $this->mail->Password = 'your-app-password'; // Change to your app password
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port = 587;
        
        // Sender info
        $this->mail->setFrom('your-email@gmail.com', 'Event Management System');
        $this->mail->isHTML(true);
    }
    
    public function sendEventNotification($recipients, $eventTitle, $action, $eventDetails = []) {
        try {
            // Add recipients
            foreach ($recipients as $email => $name) {
                $this->mail->addAddress($email, $name);
            }
            
            // Set subject and body
            $subject = "Event $action: $eventTitle";
            $this->mail->Subject = $subject;
            
            $body = $this->generateEmailBody($eventTitle, $action, $eventDetails);
            $this->mail->Body = $body;
            
            // Send email
            $result = $this->mail->send();
            
            // Clear recipients for next email
            $this->mail->clearAddresses();
            
            return $result;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $this->mail->ErrorInfo);
            return false;
        }
    }
    
    private function generateEmailBody($eventTitle, $action, $eventDetails) {
        $actionText = $action === 'deleted' ? 'cancelled' : 'deactivated';
        $actionColor = $action === 'deleted' ? '#dc3545' : '#ffc107';
        
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .event-details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid $actionColor; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 14px; }
                .btn { display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Event $actionText</h1>
                    <p>Event Management System Notification</p>
                </div>
                <div class='content'>
                    <h2>Important Notice</h2>
                    <p>We regret to inform you that the following event has been <strong style='color: $actionColor;'>$actionText</strong>:</p>
                    
                    <div class='event-details'>
                        <h3 style='color: $actionColor; margin-top: 0;'>$eventTitle</h3>";
        
        if (!empty($eventDetails)) {
            $html .= "<ul style='list-style: none; padding: 0;'>";
            if (isset($eventDetails['date'])) {
                $html .= "<li><strong>Date:</strong> " . date('M d, Y', strtotime($eventDetails['date'])) . "</li>";
            }
            if (isset($eventDetails['time'])) {
                $html .= "<li><strong>Time:</strong> " . $eventDetails['time'] . "</li>";
            }
            if (isset($eventDetails['venue'])) {
                $html .= "<li><strong>Venue:</strong> " . $eventDetails['venue'] . "</li>";
            }
            if (isset($eventDetails['description'])) {
                $html .= "<li><strong>Description:</strong> " . $eventDetails['description'] . "</li>";
            }
            $html .= "</ul>";
        }
        
        $html .= "
                    </div>
                    
                    <p>If you have any questions or concerns, please contact the event organizers.</p>
                    
                    <div class='footer'>
                        <p>This is an automated message from the Event Management System.</p>
                        <p>Please do not reply to this email.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>";
        
        return $html;
    }
    
    public function sendCustomNotification($recipients, $subject, $message) {
        try {
            // Add recipients
            foreach ($recipients as $email => $name) {
                $this->mail->addAddress($email, $name);
            }
            
            $this->mail->Subject = $subject;
            $this->mail->Body = $message;
            
            $result = $this->mail->send();
            $this->mail->clearAddresses();
            
            return $result;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $this->mail->ErrorInfo);
            return false;
        }
    }
}
?>
