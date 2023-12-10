Manual Configuration of Email from myHosting  

Protocol: Incoming server (IMAP)
Hostname: imap.hostinger.com
Port: 993
SSL/TLS: true

Protocol: Outgoing server (SMTP)
Hostname: smtp.hostinger.com
Port: 465
SSL/TLS: true

Protocol: Incoming server (POP)
Hostname: pop.hostinger.com
Port: 995
SSL/TLS: true

<?php
// config.php
return [
    'email' => [
        'host' => 'smtp.hostinger.com',
        'username' => 'otpsender@devsun.tech',
        'password' => '(i1I^:8,GPRu*c6alTVgy=BjKW34dS$U.Dh@k&mMOs+r)',
        'port' => 465,
        'from' => 'otpsender@devsun.tech',
        'from_name' => 'EcashXecCrypto',
    ],
];
?>


<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
$config = require 'config.php';  // Adjust the path as needed

header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');


// Database setup
$db = new PDO('sqlite:otps.db');
$db->exec("CREATE TABLE IF NOT EXISTS otps (email TEXT, otp TEXT, expiration DATETIME)");

$response = [];

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit();  // Just exit after returning the headers
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        switch ($_GET['action']) {
            case 'send':
                $email = $_POST['email'];
                sendOtp($email, $db, $config);
                break;

            case 'verify':
                $email = $_POST['email'];
                $otpProvided = $_POST['otp'];
                verifyOtp($email, $otpProvided, $db);
                break;

            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        $response = [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}



echo json_encode($response);

function sendOtp($email, $db, $config) {
    $stmt = $db->prepare("DELETE FROM otps WHERE email = ?");
    $stmt->execute([$email]);
    // Generate OTP
    $otp = rand(100000, 999999);

    // Store in database with expiration time (5 minutes)
    $expiration = new DateTime();
    $expiration->modify('+5 minutes');
    $stmt = $db->prepare("INSERT INTO otps (email, otp, expiration) VALUES (?, ?, ?)");
    $stmt->execute([$email, $otp, $expiration->format('Y-m-d H:i:s')]);

    // Send OTP via email with PHPMailer
    $mail = new PHPMailer(true);
    $mail->SMTPDebug = 2;
    $mail->isSMTP();
    $mail->Host       = $config['email']['host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $config['email']['username'];
    $mail->Password   = $config['email']['password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = $config['email']['port'];

    // Recipients
    $mail->setFrom($config['email']['from'], $config['email']['from_name']);
    $mail->addAddress($email);

    // Content
    $mail->isHTML(true);
    $emailContent = file_get_contents('email_template.html');
    $emailContent = str_replace('{OTP}', $otp, $emailContent);
    $mail->Subject = 'Your otp code.';
    $mail->Body    = $emailContent;

    $mail->send();

    // After successful send, clean up old OTPs
    cleanupOtps($db);
}

function verifyOtp($email, $otpProvided, $db) {
    $stmt = $db->prepare("SELECT * FROM otps WHERE email = ? ORDER BY expiration DESC LIMIT 1");
    $stmt->execute([$email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $otpSaved = $row['otp'];
        $expiration = new DateTime($row['expiration']);
        $now = new DateTime();

        if ($otpSaved != $otpProvided) {
            throw new Exception('Invalid OTP!');
        } elseif ($now >= $expiration) {
            throw new Exception('OTP expired!');
        } else {
            $response = [
                'status' => 'success',
                'message' => 'OTP Verified!'
            ];
        }
    } else {
        throw new Exception('Email not found!');
    }
}

function cleanupOtps($db) {
    $stmt = $db->prepare("DELETE FROM otps WHERE expiration <= ?");
    $stmt->execute([(new DateTime())->format('Y-m-d H:i:s')]);
}
?>

Now the otp email is not send when make action send  Help me fix please

https://ecashxeccrypto.com/email/otp_api.php?action=send

{
    "email": "kuayai66@gmail.com"
}