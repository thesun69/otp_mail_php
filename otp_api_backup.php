<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Content-Type: application/json');

// Database setup
$db = new PDO('sqlite:otps.db');
$db->exec("CREATE TABLE IF NOT EXISTS otps (email TEXT, otp TEXT, expiration DATETIME)");

$response = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    switch ($_GET['action']) {
        case 'send':
            $email = $_POST['email'];

            // Generate OTP
            $otp = rand(100000, 999999);

            // Store in database with expiration time (5 minutes)
            $expiration = new DateTime();
            $expiration->modify('+5 minutes');
            $stmt = $db->prepare("INSERT INTO otps (email, otp, expiration) VALUES (?, ?, ?)");
            $stmt->execute([$email, $otp, $expiration->format('Y-m-d H:i:s')]);

            // Send OTP via email with PHPMailer
            $mail = new PHPMailer(true);

            try {
                // Your PHPMailer settings here:
                $mail->SMTPDebug = 0;
                $mail->isSMTP();
                $mail->Host       = 'ecashxeccrypto.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'support@ecashxeccrypto.com';
                $mail->Password   = '?iYQKf_G5o64q6';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;

                // Recipients
                $mail->setFrom('support@ecashxeccrypto.com', 'EcashXecCrypto');
                $mail->addAddress($email);

                // Content
                $mail->isHTML(true);
                $emailContent = file_get_contents('email_template.html');
                $emailContent = str_replace('{OTP}', $otp, $emailContent);
                $mail->Subject = 'Your otp code.';
                $mail->Body    = $emailContent;

                $mail->send();
                $response = [
                    'status' => 'success',
                    'message' => 'OTP Sent!'
                ];
            } catch (Exception $e) {
                $response = [
                    'status' => 'error',
                    'message' => "Mailer Error: {$mail->ErrorInfo}"
                ];
            }
            break;

        case 'verify':
            $email = $_POST['email'];
            $otpProvided = $_POST['otp'];

            $stmt = $db->prepare("SELECT * FROM otps WHERE email = ?");
            $stmt->execute([$email]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $otpSaved = $row['otp'];
                $expiration = new DateTime($row['expiration']);
                $now = new DateTime();

                if ($otpSaved != $otpProvided) {
                    $response = [
                        'status' => 'error',
                        'message' => 'Invalid OTP! Provided: ' . $otpProvided . ' vs Expected: ' . $otpSaved
                    ];
                } elseif ($now >= $expiration) {
                    $response = [
                        'status' => 'error',
                        'message' => 'OTP expired! Now: ' . $now->format('Y-m-d H:i:s') . ' vs Expiration: ' . $expiration->format('Y-m-d H:i:s')
                    ];
                } else {
                    $response = [
                        'status' => 'success',
                        'message' => 'OTP Verified!'
                    ];
                }
            } else {
                $response = [
                    'status' => 'error',
                    'message' => 'Email not found!'
                ];
            }
            break;

        default:
            $response = [
                'status' => 'error',
                'message' => 'Invalid action'
            ];
            break;
    }
}

echo json_encode($response);
?>