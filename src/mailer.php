<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once('connection.php');

class Mailer {
    
    public static function sendCustomEmail($fromEmail, $fromName, $toEmail, $toName, $subject, $htmlContent) {
        global $conn;

        $email = new \SendGrid\Mail\Mail();
        $email->setFrom($fromEmail, $fromName);
        $email->addTo($toEmail, $toName);
        $email->setSubject($subject);
        $email->addContent("text/html", $htmlContent);

        $sendgrid = new \SendGrid(getenv('SENDGRID_API_KEY'));

        $status = 'failed';

        try {
            $response = $sendgrid->send($email);
            $statusCode = $response->statusCode();
            if ($statusCode >= 200 && $statusCode < 300) {
                $status = 'sent';
            } else {
                $status = 'error_'.$statusCode;
            }
        } catch (Exception $e) {
            $status = 'exception';
            error_log('SendGrid exception: '. $e->getMessage());
        }

        $email_type = 'email_verification';

        // get user_id from email
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $toEmail);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_id = $result->fetch_assoc()['user_id'] ?? null;
        $stmt->close();

        // Log to database
        $stmt = $conn->prepare("INSERT INTO sendgrid_logs (user_id, email_type, recipient_email, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $email_type, $toEmail, $status);
        $stmt->execute();
        $stmt->close();
    }
}